<?php
/* Bella Vista Oasis Spa - Login Endpoint (Compatible with login.php) */
if (file_exists(__DIR__ . '/database/db_connection.php')) {
    include __DIR__ . '/database/db_connection.php';
} elseif (file_exists(__DIR__ . '/../database/db_connection.php')) {
    include __DIR__ . '/../database/db_connection.php';
} else {
    $conn = new mysqli("127.0.0.1", "root", "", "bellavista");
}

$message = "";
$toastClass = "";
$loggedIn = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, full_name, password_hash, role, active FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($db_id, $db_name, $db_password_hash, $db_role, $db_active);
        $stmt->fetch();

        if (password_verify($password, $db_password_hash)) {
            if (!$db_active) {
                $message = "This account is inactive. Please contact the spa.";
                $toastClass = "bg-danger";
            } else {
                $message = "Login successful";
                $toastClass = "bg-success";
                $loggedIn = true;

                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['email'] = $email;
                $_SESSION['user_id'] = $db_id;
                $_SESSION['full_name'] = $db_name;
                $_SESSION['role'] = $db_role;

                $target = ($db_role === 'ADMIN') ? 'admin.html' : 'account.html';

                $isJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
                if ($isJson || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'message' => 'Login successful',
                        'redirect' => $target,
                        'user' => ['fullName' => $db_name, 'email' => $email, 'role' => $db_role]
                    ]);
                    exit();
                }

                header("Location: " . $target);
                exit();
            }
        } else {
            $message = "Incorrect password";
            $toastClass = "bg-danger";
        }
    } else {
        $message = "Email not found";
        $toastClass = "bg-warning";
    }
    $stmt->close();

    $isJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    if ($isJson || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'message' => $message,
            'status' => 'error'
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href="assets/logo/logo.png">
    <title>Login Page | Bella Vista Oasis Spa</title>
</head>
<body class="bg-light">
<div class="container p-5 d-flex flex-column align-items-center">
    <?php if ($message): ?>
        <div class="toast align-items-center text-white <?php echo $toastClass; ?> border-0"
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo htmlspecialchars($message); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <form action="" method="post" class="form-control mt-5 p-4"
          style="height:auto; width:380px; box-shadow: rgba(60,64,67,0.3) 0px 1px 2px 0px,
                 rgba(60,64,67,0.15) 0px 2px 6px 2px;">
        <div class="row">
            <i class="fa fa-user-circle-o fa-3x mt-1 mb-2"
               style="text-align: center; color: green;"></i>
            <h5 class="text-center p-4" style="font-weight: 700;">Login Into Your Account</h5>
        </div>
        <div class="col mb-3">
            <label for="email"><i class="fa fa-envelope"></i> Email</label>
            <input type="text" name="email" id="email" class="form-control" required>
        </div>
        <div class="col mb-3 mt-3">
            <label for="password"><i class="fa fa-lock"></i> Password</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <div class="col mb-3 mt-3">
            <button type="submit" class="btn btn-success bg-success w-100"
                    style="font-weight: 600;">Login</button>
        </div>
        <div class="col mb-2 mt-4">
            <p class="text-center" style="font-weight: 600; color: navy;">
                <a href="./register.php" style="text-decoration: none;">Create Account</a>
                OR
                <a href="./resetpassword.php" style="text-decoration: none;">Forgot Password</a>
            </p>
            <p class="text-center" style="font-size: 0.85rem;">
                <a href="./login.html" style="text-decoration: underline; color: #555;">View in Bella Vista Theme &rarr;</a>
            </p>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let toastElList = [].slice.call(document.querySelectorAll('.toast'));
    let toastList = toastElList.map(function (toastEl) {
        return new bootstrap.Toast(toastEl, { delay: 3000 });
    });
    toastList.forEach(toast => toast.show());
</script>
</body>
</html>
