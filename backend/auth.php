<?php
session_start();

// โหลด .env อัตโนมัติถ้ารันบนเครื่อง local
require_once __DIR__ . '/lib/load_env.php';

// ====== อ่าน username/password จาก Environment Variables (ตั้งค่าจริงไว้ที่ Render Dashboard) ======
// ADMIN_*  = แอดมินเต็มสิทธิ์ (เพิ่ม/แก้/ลบ/นำเข้า)
// VIEWER_* = ผู้ดูอย่างเดียว (ไม่บังคับ — ถ้าไม่ตั้งจะไม่มีบัญชีนี้)
define('ADMIN_USER', getenv('ADMIN_USER') ?: '');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: '');
define('VIEWER_USER', getenv('VIEWER_USER') ?: '');
define('VIEWER_PASS', getenv('VIEWER_PASS') ?: '');
// ======================================================================================

header('Content-Type: application/json; charset=utf-8');

$action = isset($_POST['action']) ? $_POST['action'] : '';

/** เทียบ user/pass แบบเวลาคงที่ (ค่าที่ตั้งไว้ว่าง = ไม่มีบัญชีนี้) */
function credentials_match(string $user, string $pass, string $wantUser, string $wantPass): bool
{
    if ($wantUser === '' || $wantPass === '') return false;
    return (bool) (hash_equals($wantUser, $user) & hash_equals($wantPass, $pass)); // & ไม่ลัดวงจร: เช็คครบทั้งคู่เสมอ
}

/** role ของ session ปัจจุบัน (session เก่าที่มีแค่ is_admin ถือเป็นแอดมิน) */
function current_role(): string
{
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin' || $role === 'viewer') return $role;
    return !empty($_SESSION['is_admin']) ? 'admin' : '';
}

if ($action === 'login') {
    $user = isset($_POST['username']) ? (string) $_POST['username'] : '';
    $pass = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if (!ADMIN_USER || !ADMIN_PASS) {
        echo json_encode(array('ok' => false, 'error' => 'ระบบยังไม่ได้ตั้งค่า admin credentials (ADMIN_USER, ADMIN_PASS)'));
        exit;
    }

    $role = '';
    if (credentials_match($user, $pass, ADMIN_USER, ADMIN_PASS)) {
        $role = 'admin';
    } elseif (credentials_match($user, $pass, VIEWER_USER, VIEWER_PASS)) {
        $role = 'viewer';
    }

    if ($role !== '') {
        session_regenerate_id(true); // กัน session fixation
        $_SESSION['role']     = $role;
        $_SESSION['is_admin'] = $role === 'admin';
        echo json_encode(array('ok' => true, 'role' => $role));
    } else {
        // จงใจ delay 1 วินาที ป้องกัน brute force
        sleep(1);
        echo json_encode(array('ok' => false, 'error' => 'username หรือ password ไม่ถูกต้อง'));
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(array('ok' => true));
    exit;
}

if ($action === 'check') {
    $role = current_role();
    // is_admin = เข้าหลังบ้านได้ (ทั้งแอดมินและผู้ดู) ส่วน role บอกว่าแก้ไขได้หรือไม่
    echo json_encode(array('is_admin' => $role !== '', 'role' => $role));
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'invalid action'));
