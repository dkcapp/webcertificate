<?php
// backend/oauth_callback.php
// DKC OAuth redirect กลับมาที่นี่ (ต้องตรงกับ redirect_uri ที่ลงทะเบียนไว้กับ IT Dev ทุกตัวอักษร):
//   https://comtrain-webcertificate.onrender.com/backend/oauth_callback.php
// ตรวจ state → แลก code เป็น token → ดึงโปรไฟล์ → เก็บ username ใน session → ไปส่งคำขอต่อ

require_once __DIR__ . '/lib/load_env.php';
require_once __DIR__ . '/lib/user_session.php';
require_once __DIR__ . '/lib/DkcOAuth.php';

user_session_start();

$code  = (string) ($_GET['code']  ?? '');
$state = (string) ($_GET['state'] ?? '');
$saved = (string) ($_SESSION['oauth_state'] ?? '');
unset($_SESSION['oauth_state']); // ใช้ได้ครั้งเดียว

// ผู้ใช้กดไม่อนุญาต / state ไม่ตรง (กัน CSRF) / ไม่มี code
if (isset($_GET['error']) || $code === '' || $state === '' || $saved === '' || !hash_equals($saved, $state)) {
    error_log('[oauth_callback] invalid callback (error=' . ($_GET['error'] ?? '-') . ')');
    dkc_redirect_thankyou(['e' => 'login']);
}

try {
    $oauth = DkcOAuth::fromEnv();
    $token = $oauth->exchangeCode($code);
    $user  = $oauth->fetchUser($token);
} catch (Throwable $e) {
    error_log('[oauth_callback] ' . $e->getMessage());
    dkc_redirect_thankyou(['e' => 'login']);
}

// ป้องกัน session fixation แล้วเก็บเฉพาะข้อมูลที่ต้องใช้ (ไม่เก็บ access token)
session_regenerate_id(true);
$_SESSION['user'] = [
    'username'     => (string) $user['username'],
    'display_name' => dkc_clip($user['display_name'] ?? ($user['name'] ?? ''), 200),
];

dkc_redirect(empty($_SESSION['pending']) ? '/thankyou.html' : '/backend/approval_submit.php');
