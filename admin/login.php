<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /plant/admin/dashboard.php');
    exit();
}

$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare("SELECT admin_id, username, password FROM admin WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                reset_attempts($ip);
                regenerate_session();
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $admin['admin_id'];
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['last_activity']   = time();
                header('Location: /plant/admin/dashboard.php');
                exit();
            } else {
                record_failed_attempt($ip);
                $error = 'Invalid username or password.';
            }
        }
    }
}

$csrf_token = generate_csrf_token();
$timeout    = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Login</title>
      <link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
      <link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
   </head>
   <body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
      <div class="card border-0 shadow-sm" style="width:100%;max-width:380px;">
         <div class="card-body p-5">
            <h5 class="fw-bold text-dark mb-1">Sign in</h5>
            <p class="text-muted small mb-4">Enter your credentials to continue.</p>
            <?php if ($timeout): ?>
            <div class="alert alert-warning py-2 small"><i class="fas fa-clock me-1"></i> Session expired.</div>
            <?php elseif ($error): ?>
            <div class="alert alert-danger py-2 small"><i class="fas fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="" autocomplete="off" novalidate>
               <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
               <div class="mb-3">
                  <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                  <div class="input-group">
                     <span class="input-group-text bg-white border-end-0"><i class="fas fa-user text-secondary small"></i></span>
                     <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" autocomplete="username" maxlength="50" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                  </div>
               </div>
               <div class="mb-4">
                  <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                  <div class="input-group">
                     <span class="input-group-text bg-white border-end-0"><i class="fas fa-lock text-secondary small"></i></span>
                     <input type="password" class="form-control border-start-0 ps-0 border-end-0" id="password" name="password" autocomplete="current-password" maxlength="128" required>
                     <button type="button" class="input-group-text bg-white border-start-0" onclick="togglePw()" tabindex="-1"><i class="fas fa-eye text-secondary small" id="eye-icon"></i></button>
                  </div>
               </div>
               <button type="submit" class="btn btn-success w-100 fw-semibold">
               <i class="fas fa-right-to-bracket me-2"></i>Login
               </button>
            </form>
         </div>
      </div>
      <script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
      <script src="/plant/assets/js/admin/login.js"></script>
   </body>
</html>
