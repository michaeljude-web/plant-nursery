<?php
function check_session_valid($pdo, $user_id, $user_type) {
    $stmt = $pdo->prepare("SELECT session_id FROM login_sessions WHERE user_id = ? AND user_type = ?");
    $stmt->execute([$user_id, $user_type]);
    $row = $stmt->fetch();
    if (!$row || $row['session_id'] !== session_id()) {
        session_unset();
        session_destroy();
        header('Location: /plant/login.php?timeout=1');
        exit();
    }
}