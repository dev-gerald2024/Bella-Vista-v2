<?php
/* Bella Vista Oasis Spa - settings.
   Phase 4.1: Operating hours (1:00 PM - 9:00 PM), 30-min cleaning buffer,
   service duration management, guest count validation, 24-hour policy. */
$config = [
  /* ---- database (the same one you import database/schema.sql into) ---- */
  'DB_HOST' => '127.0.0.1',
  'DB_PORT' => 3306,
  'DB_NAME' => 'bellavista',
  'DB_USER' => 'root',
  'DB_PASS' => '',
  'TEST_PASSWORDS' => [],

  /* ---- site ---- */
  'PRODUCTION'    => false,   // true on a live HTTPS server: Secure + __Host- cookies, hides debug mail log
  'BASE_URL'      => '',      // leave empty to auto-detect (e.g. http://localhost/Bella-Vista-Oasis-Spa). Set it on a live server.
  'TRUST_PROXY'   => false,   // true only when HTTPS is terminated by a reverse proxy in front of PHP
  'COOKIE_SECURE' => false,   // force Secure cookies even when PRODUCTION is false
  'TIMEZONE'      => 'Asia/Manila',   // official timezone for Bella Vista Oasis Spa

  /* ---- official operating hours (Part 2 & Part 3) ----
     Monday–Sunday: 1:00 PM to 9:00 PM (13:00 to 21:00).
     Both treatment AND 30-minute cleaning buffer must finish by CLOSE_TIME (21:00). */
  'OPEN_TIME'       => '13:00',
  'CLOSE_TIME'      => '21:00',
  'CLOSED_WEEKDAYS' => [],            // e.g. [7] = closed every Sunday; empty = open Mon-Sun
  'CLOSED_DATES'    => [],            // e.g. ['2026-12-25'] (YYYY-MM-DD)
  'SLOT_STEP'       => 30,            // start times offered every 30 minutes
  'BUFFER_MINUTES'  => 30,            // mandatory 30-minute cleaning and sanitization buffer after every treatment

  /* ---- service duration rules & ranges (Part 4) ----
     Documented duration ranges:
     - Body Massage: 60 to 90 minutes (options: 60, 90)
     - Foot Massage: 30 to 45 minutes (options: 30, 45)
     - Body Scrub: 45 to 60 minutes (options: 45, 60)
     - Facial Treatment: 45 to 60 minutes (options: 45, 60)
     - Hair and Scalp Treatment: 30 to 45 minutes (options: 30, 45)
     Services not configured here (e.g. 'Other Spa Treatments', 'Not sure yet') require
     management configuration before booking is permitted. */
  'SERVICE_DURATIONS' => [
    'Body Massage' => [
      'min'     => 60,
      'max'     => 90,
      'options' => [60, 90],
      'prices'  => [60 => 800.0, 90 => 1100.0],
      'default' => 60,
    ],
    'Foot Massage' => [
      'min'     => 30,
      'max'     => 45,
      'options' => [30, 45],
      'prices'  => [30 => 450.0, 45 => 600.0],
      'default' => 30,
    ],
    'Body Scrub' => [
      'min'     => 45,
      'max'     => 60,
      'options' => [45, 60],
      'prices'  => [45 => 800.0, 60 => 1000.0],
      'default' => 45,
    ],
    'Facial Treatment' => [
      'min'     => 45,
      'max'     => 60,
      'options' => [45, 60],
      'prices'  => [45 => 700.0, 60 => 900.0],
      'default' => 45,
    ],
    'Hair and Scalp Treatment' => [
      'min'     => 30,
      'max'     => 45,
      'options' => [30, 45],
      'prices'  => [30 => 500.0, 45 => 700.0],
      'default' => 30,
    ],
  ],

  /* ---- guest count limits (Part 6) ----
     Capacity reference: Single Room = 1 guest; Couples Suite = 2 guests;
     Family Spa Lounge = up to 4 base, maximum 6 guests.
     Enforced on booking requests until full Phase 5 room/package assignment. */
  'MIN_GUESTS' => 1,
  'MAX_GUESTS' => 6,

  /* ---- scheduling capacity & rules (Part 5) ---- */
  'SERVICE_CAPACITY'    => 1,         // how many bookings of the SAME service can run at the same time
  'TOTAL_CAPACITY'      => 3,         // how many bookings of ALL services can run at the same time
  'MIN_NOTICE_MINUTES'  => 60,        // online requests for today must start at least this many minutes from now
  'BOOKING_WINDOW_DAYS' => 90,        // online requests are accepted up to this many days ahead
  'MAX_OPEN_BOOKINGS_PER_CUSTOMER' => 5, // Audit F3: prevent single account calendar hoarding

  /* ---- customer cancellation and rescheduling policy (Part 7) ----
     Customer cancellation and rescheduling must be at least 24 hours prior to appointment. */
  'CANCELLATION_NOTICE_HOURS' => 24,
  'MAX_RESCHEDULE_REQUESTS'   => 3,

  /* ---- Phase 5.0 Room & Package Pricing Relationship ----
     The documentation provides room/package rates (Single: ₱800, Couples: ₱1,800, Family: ₱4,000 + ₱750/addl),
     but does not conclusively define whether room/package rate replaces, is included in, or is added to
     treatment pricing.
     'INDEPENDENT' (default): Room/package rates are tracked separately and not automatically appended
     to existing booking amounts without explicit management decision.
     'INCLUSIVE': Treatment price covers room/package rate.
     'ADDITIVE': Booking amount = Treatment price + Package price. */
  'PACKAGE_PRICING_RELATIONSHIP' => 'INDEPENDENT',

  /* ---- Phase 6.0 Demo Payment & Transaction System ----
     SIMULATION ONLY — NO REAL MONEY TRANSFERRED.
     Configurable downpayment percentage, minimum downpayment amount,
     and demo mode settings. */
  'DEMO_PAYMENT_MODE'          => true,
  'DOWNPAYMENT_PERCENTAGE'     => 25, // default demo downpayment percentage (25%)
  'MIN_DOWNPAYMENT_AMOUNT'     => 500.0, // minimum demo downpayment in PHP
  'FORFEIT_LATE_CANCELLATION'  => true, // late cancellations (< 24h) forfeit demo downpayment
  'DEMO_DISCLAIMER_TEXT'       => 'DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED.',

  /* ---- email (verification + password reset). Leave SMTP_URL empty to run without email. ---- */
  'SMTP_URL'   => '',
  'MAIL_FROM'  => 'Bella Vista Oasis Spa <no-reply@localhost>',
  'NOTIFY_EMAIL' => '',

  /* Local development only: with no SMTP, write emails to data/mail.log so you can copy the
     verify / reset link out of it. Ignored when PRODUCTION is true. */
  'DEV_MAIL_TO_LOG' => true,
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
  $localConfig = require $localConfigFile;
  if (!is_array($localConfig)) {
    throw new RuntimeException('php/config.local.php must return an array.');
  }
  $config = array_replace($config, $localConfig);
}

return $config;
