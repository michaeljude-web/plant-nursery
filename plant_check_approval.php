<?php
session_start();
require_once 'config/config.php';
header('Content-Type: application/json');

$device_hash = $_POST['device_hash'] ?? '';
$user_id     = (int)($_POST['user_id'] ?? 0);
$user_type   = $_POST['user_type']   ?? '';

if (!$device_hash || !$user_id || !in_array($user_type, ['admin'])) {
    echo json_encode(['status' => 'unknown']);
    exit();
}

$stmt = $pdo->prepare("SELECT status FROM device_requests WHERE user_id = ? AND user_type = ? AND device_hash = ?");
$stmt->execute([$user_id, $user_type, $device_hash]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['status' => 'unknown']);
    exit();
}

if ($row['status'] === 'approved') {
    $trusted = $pdo->prepare("SELECT id FROM trusted_devices WHERE user_id = ? AND user_type = ? AND device_hash = ? AND is_approved = 1");
    $trusted->execute([$user_id, $user_type, $device_hash]);

    if ($trusted->fetch()) {
        $stmt = $pdo->prepare("SELECT admin_id, username FROM admin WHERE admin_id = ?");
        $stmt->execute([$user_id]);
        $admin = $stmt->fetch();

        if ($admin) {
            require_once 'includes/admin/auth.php';
            regenerate_session();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $admin['admin_id'];
            $_SESSION['admin_username']  = $admin['username'];
            $_SESSION['last_activity']   = time();

            $pdo->prepare("INSERT INTO login_sessions (user_id, user_type, session_id)
                VALUES (?, 'admin', ?)
                ON DUPLICATE KEY UPDATE session_id = ?"
            )->execute([$admin['admin_id'], session_id(), session_id()]);

            echo json_encode(['status' => 'approved', 'redirect' => '/plant/admin/dashboard.php']);
            exit();
        }
    }
}

echo json_encode(['status' => $row['status']]);