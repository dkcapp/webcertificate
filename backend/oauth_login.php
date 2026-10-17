<?php
// backend/oauth_login.php
// เริ่มล็อกอิน DKC OAuth: สร้าง state สุ่ม เก็บใน session แล้ว redirect ไปหน้า authorize

require_once __DIR__ . '/lib/load_env.php';
require_once __DIR__ . '/lib/user_session.php';
require_once __DIR__ . '/lib/DkcOAuth.php';

user_session_start();

try {
    $oauth = DkcOAuth::fromEnv();
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    header('Location: ' . $oauth->authorizeUrl($state), true, 302);
    exit;
} catch (Throwable $e) {
    error_log('[oauth_login] ' . $e->getMessage());
    dkc_redirect_thankyou(['e' => 'login']);
}
