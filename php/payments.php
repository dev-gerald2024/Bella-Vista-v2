<?php
/**
 * BELLA VISTA OASIS SPA — PAYMENT & TRANSACTION MANAGEMENT SYSTEM
 *
 * Provides downpayment calculation, checkout, GCash/Maya/Bank Transfer/Cash at Spa
 * payments, receipt generation, refund & forfeiture management, and transaction auditing.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/rooms.php';

/* ============================================================================
   1. PAYMENT SETTINGS & CONFIGURATION
   ============================================================================ */

function get_downpayment_setting(): float {
  $row = q1("SELECT setting_value FROM payment_settings WHERE setting_key = 'DOWNPAYMENT_PERCENTAGE'");
  if ($row && is_numeric($row['setting_value'])) {
    return (float)$row['setting_value'];
  }
  return (float)(cfg('DOWNPAYMENT_PERCENTAGE') ?: 25.0);
}

/** Minimum downpayment in PHP: payment_settings first, then php/config.php, then 500. */
function get_min_downpayment(): float {
  $row = q1("SELECT setting_value FROM payment_settings WHERE setting_key = 'MIN_DOWNPAYMENT_AMOUNT'");
  if ($row && is_numeric($row['setting_value'])) {
    return (float)$row['setting_value'];
  }
  return (float)(cfg('MIN_DOWNPAYMENT_AMOUNT') ?: 500.0);
}

/**
 * Single authoritative reader for yes/no payment settings.
 * Uses the stored value when the row exists, otherwise $default. A missing row
 * (fresh install) therefore behaves the same everywhere: server, API and admin UI.
 */
function payment_setting_enabled(string $key, bool $default = true): bool {
  $row = q1('SELECT setting_value FROM payment_settings WHERE setting_key = ?', [$key]);
  if (!$row || $row['setting_value'] === null || trim((string)$row['setting_value']) === '') {
    return $default;
  }
  return !in_array(strtolower(trim((string)$row['setting_value'])), ['0', 'false', 'no', 'off'], true);
}

function full_payment_allowed(): bool {
  return payment_setting_enabled('ALLOW_FULL_PAYMENT', true);
}

function late_forfeiture_enabled(): bool {
  return payment_setting_enabled('FORFEIT_LATE_CANCELLATION', cfg('FORFEIT_LATE_CANCELLATION') !== false);
}

/* The bookings.payment_status column only stores UNPAID / PARTIALLY_PAID / PAID / REFUNDED / FORFEITED.
   A partly refunded booking is still partly paid there; the API summary reports the finer PARTIALLY_REFUNDED. */
function db_payment_status(string $s): string { return $s === 'PARTIALLY_REFUNDED' ? 'PARTIALLY_PAID' : $s; }

function get_all_payment_settings(): array {
  $rows = qa('SELECT setting_key, setting_value, description FROM payment_settings');
  $map = [];
  foreach ($rows as $r) {
    $map[$r['setting_key']] = $r['setting_value'];
  }
  return [
    'downpaymentPercentage' => isset($map['DOWNPAYMENT_PERCENTAGE']) ? (float)$map['DOWNPAYMENT_PERCENTAGE'] : (float)(cfg('DOWNPAYMENT_PERCENTAGE') ?: 25.0),
    'minDownpaymentAmount'  => get_min_downpayment(),
    'allowFullPayment'      => full_payment_allowed(),
    'forfeitLateCancel'     => late_forfeiture_enabled(),
    'disclaimer'            => cfg('DEMO_DISCLAIMER_TEXT') ?: ''
  ];
}

function update_payment_setting(string $key, string $value, string $adminName = 'Admin'): void {
  run('INSERT INTO payment_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
  admin_log(admin_actor(), 'Payment setting updated', $key, "Set $key = $value", $adminName);
}

/* ============================================================================
   2. DOWNPAYMENT & REMAINING BALANCE CALCULATION
   ============================================================================ */

/** The ONE downpayment formula (summary, booking quote and the confirmation all use it):
 *  min(total, max(minimum, total * percentage)). */
function required_downpayment(float $total): float {
  $dp = round(max(get_min_downpayment(), $total * (get_downpayment_setting() / 100.0)), 2);
  return min($dp, round($total, 2));
}

/**
 * Accurately calculates booking payment status, required downpayment, total paid, and remaining balance.
 * Formula:
 * Downpayment = min(Total, max(Minimum Downpayment, Total * Configured Percentage))
 * Remaining Balance = Total Amount - Confirmed Payments
 */
function calculate_booking_payment_summary(int $bookingId): array {
  $bk = q1('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
  if (!$bk) fail('Booking not found.', 404);

  $totalAmount = (float)$bk['amount'];
  if ($totalAmount <= 0) {
    // If booking has no manual amount, check package or service default
    if (!empty($bk['package_id'])) {
      $pkg = get_package((int)$bk['package_id']);
      if ($pkg) $totalAmount = calculate_package_price($pkg, (int)($bk['guests'] ?? 1));
    }
    if ($totalAmount <= 0) {
      $dur = (int)($bk['duration'] ?: 60);
      $totalAmount = get_service_price((string)$bk['service'], $dur, (int)($bk['guests'] ?? 1));
    }
    if ($totalAmount <= 0) {
      $totalAmount = 800.00; // baseline service price
    }
  }

  $pct = get_downpayment_setting();
  $dpRequired = required_downpayment($totalAmount);

  // Query transactions for this booking
  $txns = qa('SELECT * FROM transactions WHERE booking_id = ? ORDER BY id ASC', [$bookingId]);

  $confirmedPayments = 0.0;
  $refundedPayments = 0.0;
  $pendingPayments = 0.0;
  $isForfeited = false;

  foreach ($txns as $tx) {
    $amt = (float)$tx['amount'];
    if ($tx['status'] === 'PAID') {
      if (in_array($tx['transaction_type'], ['DOWNPAYMENT', 'BALANCE_PAYMENT', 'FULL_PAYMENT', 'ADJUSTMENT'], true)) {
        $confirmedPayments += $amt;
      } elseif ($tx['transaction_type'] === 'REFUND') {
        $refundedPayments += $amt;
      }
    } elseif ($tx['status'] === 'PENDING') {
      $pendingPayments += $amt;
    } elseif ($tx['status'] === 'FORFEITED') {
      $isForfeited = true;
    }
  }

  $netPaid = max(0.0, round($confirmedPayments - $refundedPayments, 2));
  $remainingBalance = max(0.0, round($totalAmount - $netPaid, 2));

  // Determine booking payment status
  if ($isForfeited) {
    $payStatus = 'FORFEITED';
  } elseif ($refundedPayments > 0 && $netPaid > 0) {
    $payStatus = 'PARTIALLY_REFUNDED';
  } elseif ($refundedPayments > 0 && $netPaid <= 0) {
    $payStatus = 'REFUNDED';
  } elseif ($netPaid >= $totalAmount && $totalAmount > 0) {
    $payStatus = 'PAID';
  } elseif ($netPaid > 0) {
    $payStatus = 'PARTIALLY_PAID';
  } else {
    $payStatus = 'UNPAID';
  }

  // Update booking record caches
  run('UPDATE bookings SET amount = ?, payment_status = ?, paid_amount = ?, downpayment_required = ? WHERE id = ?',
    [$totalAmount, db_payment_status($payStatus), $netPaid, $dpRequired, $bookingId]);

  return [
    'bookingId'            => $bookingId,
    'bookingRef'           => bk_ref($bk),
    'serviceName'          => $bk['service'],
    'customerName'         => $bk['name'],
    'customerEmail'        => $bk['email'],
    'appointmentDate'      => $bk['preferred_date'],
    'appointmentTime'      => $bk['time'],
    'duration'             => (int)($bk['duration'] ?: 60),
    'guests'               => (int)($bk['guests'] ?: 1),
    'totalAmount'          => $totalAmount,
    'downpaymentRate'      => $pct,
    'downpaymentPercentage'=> $pct,
    'downpaymentRequired'  => $dpRequired,
    'downpaymentRemaining' => max(0.0, round($dpRequired - $netPaid, 2)),
    'totalConfirmedPaid'   => $confirmedPayments,
    'totalRefunded'        => $refundedPayments,
    'netPaid'              => $netPaid,
    'paidAmount'           => $netPaid,
    'pendingAmount'        => $pendingPayments,
    'remainingBalance'     => $remainingBalance,
    'paymentStatus'        => $payStatus,
    'allowFullPayment'     => full_payment_allowed(),
    'isFullyPaid'          => ($remainingBalance <= 0.0 && $totalAmount > 0),
    'disclaimer'           => '',
    'transactions'         => array_map('shape_txn', $txns)
  ];
}

/* ============================================================================
   3. CHECKOUT & PAYMENT PROCESSING ENGINE
   ============================================================================ */

/**
 * Processes a payment.
 * Supported methods: GCASH_DEMO, MAYA_DEMO, BANK_TRANSFER_DEMO, CASH_AT_SPA.
 * Supported types: DOWNPAYMENT, BALANCE_PAYMENT, FULL_PAYMENT.
 */
function process_demo_payment(
  int $bookingId,
  $methodOrPayload,
  $type = 'DOWNPAYMENT',
  ?float $reqAmount = null,
  ?int $userId = null,
  string $simulateResult = 'SUCCESS',
  string $notes = ''
): array {
  if (is_array($methodOrPayload)) {
    $method = strtoupper(trim((string)($methodOrPayload['paymentMethod'] ?? $methodOrPayload['method'] ?? '')));
    $type = strtoupper(trim((string)($methodOrPayload['paymentType'] ?? $methodOrPayload['type'] ?? (is_string($type) ? $type : 'DOWNPAYMENT'))));
    $reqAmount = isset($methodOrPayload['amount']) ? (float)$methodOrPayload['amount'] : $reqAmount;
    $userId = isset($methodOrPayload['userId']) ? (int)$methodOrPayload['userId'] : $userId;
    if (!empty($methodOrPayload['simulateFailure'])) $simulateResult = 'FAILURE';
    elseif (isset($methodOrPayload['simulateResult'])) $simulateResult = $methodOrPayload['simulateResult'];
    $notes = trim((string)($methodOrPayload['notes'] ?? $notes));
  } else {
    $method = strtoupper(trim((string)$methodOrPayload));
    $type = strtoupper(trim((string)$type));
  }

  $bk = q1('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
  if (!$bk) fail('Booking not found.', 404);
  $lock = booking_lock($bookingId);                       // one payment at a time per booking (double-click / two tabs)
  $bk = q1('SELECT * FROM bookings WHERE id = ?', [$bookingId]);   // re-read now that we hold the lock
  if (in_array($bk['status'], ['CANCELLED', 'REJECTED', 'NO-SHOW'], true)) {
    fail('This reservation is ' . strtolower($bk['status']) . ' and can no longer be paid.', 409);
  }

  // Payment opens only after the spa confirms the appointment: a pending request can still be declined or clash with another
  // booking, and the customer should not pay for a slot that may not be theirs.
  if ($bk['status'] === 'PENDING') {
    fail('Payment opens once the spa confirms your appointment. You will be notified.', 409, ['code' => 'awaiting_confirmation']);
  }

  // Security: Customer ownership check
  if ($userId !== null) {
    $u = q1('SELECT id, role FROM users WHERE id = ?', [$userId]);
    if ($u && $u['role'] !== 'ADMIN' && (int)$bk['user_id'] !== $userId) {
      fail('You do not have permission to pay for this reservation.', 403);
    }
  }
  if (!in_array($method, ['GCASH_DEMO', 'MAYA_DEMO', 'BANK_TRANSFER_DEMO', 'CASH_AT_SPA'], true)) {
    fail('Invalid payment method selected.', 400);
  }

  $type = strtoupper(trim($type));
  if (!in_array($type, ['DOWNPAYMENT', 'BALANCE_PAYMENT', 'FULL_PAYMENT'], true)) {
    fail('Invalid payment type.', 400);
  }

  // Calculate authoritative server-side summary
  $summary = calculate_booking_payment_summary($bookingId);

  // Compute exact payment amount on the server (do not trust client input)
  if ($type === 'DOWNPAYMENT') {
    $payAmount = $summary['downpaymentRemaining'];
    if ($payAmount <= 0) {
      fail('The required downpayment for this appointment has already been satisfied.', 400);
    }
  } elseif ($type === 'BALANCE_PAYMENT') {
    $payAmount = $summary['remainingBalance'];
    if ($payAmount <= 0) {
      fail('This appointment has already been paid in full. No remaining balance is due.', 400);
    }
  } else { // FULL_PAYMENT
    if (!full_payment_allowed()) {
      fail('Full payment is not available right now. Please pay the required downpayment.', 403);
    }
    $payAmount = $summary['remainingBalance'];
    if ($payAmount <= 0) {
      fail('This appointment has already been paid in full.', 400);
    }
  }

  // Prevent duplicate concurrent payments
  $recent = q1("SELECT id FROM transactions WHERE booking_id = ? AND status = 'PAID' AND amount = ? AND created_at >= (UTC_TIMESTAMP() - INTERVAL 10 SECOND)",
    [$bookingId, $payAmount]);
  if ($recent) {
    fail('A payment transaction was just processed for this reservation. Please check your transaction history.', 409);
  }

  // Generate unique references
  $refSuffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
  $txnRef = 'TXN-' . date('Ymd') . '-' . $refSuffix;
  $recNum = 'REC-' . date('Ymd') . '-' . $refSuffix;

  // Determine transaction status based on method and simulation scenario
  $simResult = strtoupper(trim($simulateResult));
  $status = 'PAID';
  $processedAt = now();
  $txNotes = clean_text($notes, 300);

  if ($simResult === 'FAIL' || $simResult === 'FAILURE') {
    $status = 'FAILED';
    $recNum = null;
    $processedAt = null;
    $txNotes = 'Payment failed. No money deducted.';
  } elseif ($simResult === 'CANCEL' || $simResult === 'CANCELLED') {
    $status = 'CANCELLED';
    $recNum = null;
    $processedAt = null;
    $txNotes = 'Checkout was cancelled by the customer.';
  } elseif ($method === 'CASH_AT_SPA') {
    // Cash at spa creates a PENDING bookkeeping record; money is NOT received until admin confirms
    $status = 'PENDING';
    $recNum = null;
    $processedAt = null;
    $txNotes = 'Cash-at-Spa payment request. Pending front desk confirmation.';
  }

  // Insert + summary + activity + audit in ONE transaction: any failure rolls everything back (the booking lock stays held until return)
  [$txnId, $updatedSummary] = tx(function () use ($txnRef, $bk, $bookingId, $recNum, $method, $payAmount, $type, $txNotes, $processedAt, $status, $userId) {
    run('INSERT INTO transactions (transaction_reference, user_id, booking_id, description, payment_method, amount, transaction_type, receipt_number, demo_mode, notes, processed_at, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())',
      [$txnRef, $bk['user_id'], $bookingId, $bk['service'], $method, $payAmount, $type, $recNum, $txNotes, $processedAt, $status]);

    $txnId = last_id();

    // If status is PAID, update booking summaries, notifications, and activity
    if ($status === 'PAID') {
      $updatedSummary = calculate_booking_payment_summary($bookingId);
      if (!empty($bk['user_id'])) {
        act((int)$bk['user_id'], "Payment: ₱" . number_format($payAmount, 2) . " via " . format_payment_method($method));
        notify((int)$bk['user_id'], "Your payment of ₱" . number_format($payAmount, 2) . " for " . $bk['service'] . " was successful.");
      }
    } else {
      $updatedSummary = calculate_booking_payment_summary($bookingId);
    }

    $actor = admin_actor() ?: ($userId ?: null);   // never a made-up user id: it must exist in `users` (clean installs have no user 1)
    admin_log($actor, 'Payment processed', $txnRef, "Method: $method, Amount: ₱$payAmount, Status: $status, Booking: " . bk_ref($bk));
    return [$txnId, $updatedSummary];
  });

  $txnRow = q1('SELECT * FROM transactions WHERE id = ?', [$txnId]);

  return [
    'ok'                 => ($status === 'PAID' || $status === 'PENDING'),
    'status'             => $status,
    'transaction'        => shape_txn($txnRow),
    'paymentSummary'     => $updatedSummary,
    'remainingBalance'   => $updatedSummary['remainingBalance'],
    'receipt'            => ($recNum !== null) ? generate_digital_receipt($txnId) : null,
    'demoNotice'         => ''
  ];
}

/**
 * Formats enum payment method to friendly label.
 */
function format_payment_method(string $method): string {
  return [
    'GCASH_DEMO'         => 'GCash',
    'MAYA_DEMO'          => 'Maya',
    'BANK_TRANSFER_DEMO' => 'Bank Transfer',
    'CASH_AT_SPA'        => 'Cash at Spa'
  ][$method] ?? $method;
}

/* ============================================================================
   4. DIGITAL RECEIPT GENERATION
   ============================================================================ */

function resolve_txn_id($ref): ?int {
  if (is_int($ref) || (is_string($ref) && ctype_digit($ref))) return (int)$ref;
  $p = parse_ref((string)$ref, 'TXN');
  if ($p) return $p;
  $r = q1("SELECT id FROM transactions WHERE transaction_reference = ?", [(string)$ref]);
  if ($r) return (int)$r['id'];
  $r = q1("SELECT id FROM transactions WHERE receipt_number = ?", [(string)$ref]);
  return $r ? (int)$r['id'] : null;
}

/**
 * Builds a standardized digital payment receipt.
 */
function generate_digital_receipt(int $transactionId): ?array {
  $tx = q1('SELECT t.*, b.name AS customer_name, b.email AS customer_email, b.service AS service_name, 
                   b.preferred_date, b.time, b.duration, b.guests, b.amount AS total_amount, b.created_at AS b_created, pk.name AS package_name
            FROM transactions t
            JOIN bookings b ON b.id = t.booking_id
            LEFT JOIN packages pk ON pk.id = b.package_id
            WHERE t.id = ?', [$transactionId]);

  if (!$tx || $tx['status'] !== 'PAID') return null;

  $summary = calculate_booking_payment_summary((int)$tx['booking_id']);

  // T&C 14: Unique digital locker assignment and combination passcode (changes every booking)
  $rawBId = (int)$tx['booking_id'];
  $rawTId = (int)$tx['id'];
  $lockerNum = 'L-' . str_pad((string)((($rawBId * 7 + $rawTId) % 24) + 1), 2, '0', STR_PAD_LEFT);
  $passcodeHash = crc32(($tx['receipt_number'] ?: ('TXN-' . $rawTId)) . '-' . $rawBId);
  $lockerPasscode = sprintf('%04d', (abs($passcodeHash) % 9000) + 1000);

  return [
    'receiptNumber'       => clean_ref($tx['receipt_number']) ?: ('REC-' . pad($tx['id'])),
    'transactionReference'=> clean_ref($tx['transaction_reference']) ?: ('TXN-' . pad($tx['id'])),
    'customerName'        => $tx['customer_name'],
    'customerEmail'       => $tx['customer_email'],
    'bookingReference'    => 'BK-' . substr((string)$tx['b_created'], 0, 4) . '-' . pad($tx['booking_id']),
    'serviceName'         => $tx['service_name'],
    'packageName'         => $tx['package_name'] ?? null,
    'guestCount'          => (int)$tx['guests'],
    'appointmentDate'     => $tx['preferred_date'],
    'appointmentTime'     => $tx['time'],
    'paymentMethod'       => format_payment_method($tx['payment_method']),
    'transactionType'     => $tx['transaction_type'],
    'amountPaid'          => (float)$tx['amount'],
    'totalBookingAmount'  => (float)$tx['total_amount'],
    'remainingBalance'    => $summary['remainingBalance'],
    'paymentStatus'       => $summary['paymentStatus'],
    'transactionDate'     => !empty($tx['processed_at']) ? iso($tx['processed_at']) : iso($tx['created_at']),
    'lockerNumber'        => $lockerNum,
    'lockerPasscode'      => $lockerPasscode,
    'lockerPolicy'        => 'Per T&C 14, unique passcode changes combination after each booking.',
    'disclaimer'          => ''
  ];
}

/* ============================================================================
   5. REFUND & FORFEITURE MANAGEMENT
   ============================================================================ */

/**
 * Admin processes a refund.
 * Refund amount cannot exceed confirmed net paid payments.
 */
function process_demo_refund(int $bookingId, float $amount, string $reason, string $adminName = 'Admin', ?string $refundMethod = null): array {
  $bk = q1('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
  if (!$bk) fail('Booking not found.', 404);

  $amount = round($amount, 2);
  if ($amount <= 0) fail('Please enter a valid refund amount greater than zero.', 400);
  $lock = booking_lock($bookingId);                       // two refunds at once must not exceed what was paid

  $summary = calculate_booking_payment_summary($bookingId);
  if ($amount > $summary['netPaid']) {
    fail("Refund amount (₱" . number_format($amount, 2) . ") cannot exceed confirmed net payments (₱" . number_format($summary['netPaid'], 2) . ").", 400);
  }

  // Determine refund payment method from original payment if not specified
  if (!$refundMethod) {
    $origTxn = q1("SELECT payment_method FROM transactions WHERE booking_id = ? AND status = 'PAID' AND transaction_type IN ('DOWNPAYMENT', 'BALANCE_PAYMENT', 'FULL_PAYMENT') ORDER BY id DESC LIMIT 1", [$bookingId]);
    $refundMethod = $origTxn['payment_method'] ?? 'GCASH_DEMO';
  }

  $methodLabel = format_payment_method($refundMethod);
  $refSuffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
  $txnRef = 'REF-' . date('Ymd') . '-' . $refSuffix;
  $recNum = 'REC-REFUND-' . date('Ymd') . '-' . $refSuffix;
  $reasonText = clean_text($reason, 400) ?: 'Customer requested refund';

  [$refundTxnId, $updatedSummary] = tx(function () use ($txnRef, $recNum, $bk, $bookingId, $methodLabel, $refundMethod, $amount, $reasonText, $adminName) {
    run('INSERT INTO transactions (transaction_reference, receipt_number, user_id, booking_id, description, payment_method, amount, transaction_type, demo_mode, refund_reason, processed_at, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW())',
      [$txnRef, $recNum, $bk['user_id'], $bookingId, 'Refund via ' . $methodLabel . ': ' . $bk['service'], $refundMethod, $amount, 'REFUND', $reasonText, 'PAID']);

    $refundTxnId = last_id();
    $updatedSummary = calculate_booking_payment_summary($bookingId);

    admin_log(admin_actor(), 'Refund processed', $txnRef, "Refunded ₱$amount via $methodLabel for booking " . bk_ref($bk) . " (Reason: $reasonText)", $adminName);

    if (!empty($bk['user_id'])) {
      act((int)$bk['user_id'], "Refund processed: ₱" . number_format($amount, 2) . " via " . $methodLabel);
      notify((int)$bk['user_id'], "A refund of ₱" . number_format($amount, 2) . " has been credited immediately to your " . $methodLabel . " account.");
    }
    return [$refundTxnId, $updatedSummary];
  });

  return [
    'ok'                 => true,
    'refundId'           => $refundTxnId,
    'transactionRef'     => $txnRef,
    'receiptNumber'      => $recNum,
    'amount'             => $amount,
    'formattedAmount'    => '₱' . number_format($amount, 2),
    'paymentMethod'      => $refundMethod,
    'paymentMethodLabel' => $methodLabel,
    'estimatedTime'      => 'Instant',
    'paymentSummary'     => $updatedSummary
  ];
}

/**
 * Enforces the 24-hour cancellation policy: late cancellations forfeit downpayment.
 */
function forfeit_demo_downpayment(int $bookingId, string $reason = 'Late cancellation (< 24h notice)', string $adminName = 'Admin'): bool {
  $bk = q1('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
  if (!$bk) return false;
  $lock = booking_lock($bookingId);

  // Only money that was really paid and not refunded can be forfeited. An unpaid booking, a refunded one and one
  // that was already forfeited are left exactly as they are (this also makes a repeated call harmless).
  if (calculate_booking_payment_summary($bookingId)['netPaid'] <= 0) return false;
  return tx(function () use ($bookingId, $reason, $bk, $adminName) {
    $n = run("UPDATE transactions SET status = 'FORFEITED', notes = CONCAT(COALESCE(notes,''), ' [FORFEITED: ', ?, ']')
              WHERE booking_id = ? AND transaction_type = 'DOWNPAYMENT' AND status = 'PAID'", [$reason, $bookingId])->rowCount();
    if ($n < 1) return false;
    run("UPDATE bookings SET payment_status = 'FORFEITED' WHERE id = ?", [$bookingId]);
    admin_log(admin_actor(), 'Downpayment forfeited', bk_ref($bk), "Forfeited downpayment due to policy: $reason", $adminName);
    return true;
  });
}

function admin_confirm_cash_payment(int $transactionId, string $adminName = 'Admin'): array {
  $tx = q1("SELECT * FROM transactions WHERE id = ? AND payment_method = 'CASH_AT_SPA' AND status = 'PENDING'", [$transactionId]);
  if (!$tx) fail('Pending cash transaction not found.', 404);
  $lock = booking_lock((int)$tx['booking_id']);
  $tx = q1("SELECT * FROM transactions WHERE id = ? AND payment_method = 'CASH_AT_SPA' AND status = 'PENDING'", [$transactionId]);
  if (!$tx) fail('This cash payment was already confirmed or cancelled.', 409);

  $refSuffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
  $recNum = 'REC-' . date('Ymd') . '-' . $refSuffix;

  $summary = tx(function () use ($recNum, $adminName, $transactionId, $tx) {
    run("UPDATE transactions SET status = 'PAID', receipt_number = ?, processed_at = NOW(), notes = CONCAT(COALESCE(notes,''), ' [Confirmed by ', ?, ']') WHERE id = ?",
      [$recNum, $adminName, $transactionId]);

    $summary = calculate_booking_payment_summary((int)$tx['booking_id']);
    admin_log(admin_actor(), 'Cash payment confirmed', $tx['transaction_reference'] ?: ('TXN-' . $tx['id']), "Confirmed cash payment ₱{$tx['amount']} for booking #{$tx['booking_id']}", $adminName);
    return $summary;
  });

  return [
    'ok'             => true,
    'paymentSummary' => $summary,
    'receipt'        => generate_digital_receipt($transactionId)
  ];
}

/* ============================================================================
   6. ADMIN PAYMENT ANALYTICS
   ============================================================================ */

/**
 * Compiles real payment metrics from database records for the Admin Dashboard.
 */
function get_payment_analytics(): array {
  $totPaid = (float)(q1("SELECT SUM(amount) AS s FROM transactions WHERE status = 'PAID' AND transaction_type IN ('DOWNPAYMENT','BALANCE_PAYMENT','FULL_PAYMENT')")['s'] ?? 0.0);
  $totRefund = (float)(q1("SELECT SUM(amount) AS s FROM transactions WHERE status = 'PAID' AND transaction_type = 'REFUND'")['s'] ?? 0.0);
  $totPending = (float)(q1("SELECT SUM(amount) AS s FROM transactions WHERE status = 'PENDING'")['s'] ?? 0.0);
  $countPending = (int)(q1("SELECT COUNT(*) AS c FROM transactions WHERE status = 'PENDING'")['c'] ?? 0);
  $countFailed = (int)(q1("SELECT COUNT(*) AS c FROM transactions WHERE status = 'FAILED'")['c'] ?? 0);
  $countPaid = (int)(q1("SELECT COUNT(*) AS c FROM transactions WHERE status = 'PAID'")['c'] ?? 0);
  $countForfeited = (int)(q1("SELECT COUNT(*) AS c FROM transactions WHERE status = 'FORFEITED'")['c'] ?? 0);

  $fullyPaidBks = (int)(q1("SELECT COUNT(*) AS c FROM bookings WHERE payment_status = 'PAID'")['c'] ?? 0);
  $partPaidBks = (int)(q1("SELECT COUNT(*) AS c FROM bookings WHERE payment_status = 'PARTIALLY_PAID'")['c'] ?? 0);
  $unpaidBks = (int)(q1("SELECT COUNT(*) AS c FROM bookings WHERE payment_status = 'UNPAID'")['c'] ?? 0);

  return [
    'totalSimulatedPayments' => $totPaid,
    'totalSimulatedRefunds'  => $totRefund,
    'netSimulatedIncome'     => max(0.0, round($totPaid - $totRefund, 2)),
    'pendingSimulatedAmount' => $totPending,
    'pendingCount'           => $countPending,
    'failedCount'            => $countFailed,
    'paidCount'              => $countPaid,
    'forfeitedCount'         => $countForfeited,
    'fullyPaidBookings'      => $fullyPaidBks,
    'partiallyPaidBookings'  => $partPaidBks,
    'unpaidBookings'         => $unpaidBks,
    'disclaimer'             => '',
    'settings'               => get_all_payment_settings()
  ];
}
