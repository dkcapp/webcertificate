<?php
session_start();

// ====== อ่าน username/password จาก Environment Variables (ตั้งค่าจริงไว้ที่ Render Dashboard) ======
define('ADMIN_USER', getenv('ADMIN_USER') ?: '');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: '');
// ======================================================================================

header('Content-Type: application/json; charset=utf-8');

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'login') {
    $user = isset($_POST['username']) ? $_POST['username'] : '';
    $pass = isset($_POST['password']) ? $_POST['password'] : '';

    if (!ADMIN_USER || !ADMIN_PASS) {
        echo json_encode(array('ok' => false, 'error' => 'ระบบยังไม่ได้ตั้งค่า admin credentials (ADMIN_USER, ADMIN_PASS)'));
        exit;
    }

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['is_admin'] = true;
        echo json_encode(array('ok' => true));
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
    echo json_encode(array('is_admin' => !empty($_SESSION['is_admin'])));
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'invalid action'));