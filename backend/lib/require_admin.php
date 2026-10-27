<?php
// backend/lib/require_admin.php
// Middleware กลาง: เริ่ม session, ตั้ง response header เป็น JSON,
// และเช็คว่า login เข้าหลังบ้านอยู่หรือไม่ (แอดมิน หรือ ผู้ดูอย่างเดียว) ถ้าไม่ใช่ให้ตอบ 403 แล้วหยุดทำงานทันที
//
// วิธีใช้: ไฟล์ endpoint ที่ต้องการสิทธิ์หลังบ้านแค่เพิ่มบรรทัดเดียวไว้บนสุด
//   require_once __DIR__ . '/lib/require_admin.php';
// action ที่ "เขียน/แก้/ลบ" ข้อมูลต้องเรียก require_full_admin() เพิ่ม (ผู้ดูอย่างเดียวจะได้ 403)

// โหลด .env อัตโนมัติถ้ารันบนเครื่อง local
require_once __DIR__ . '/load_env.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

/** 'admin' | 'viewer' | '' (session เก่าที่มีแค่ is_admin ถือเป็นแอดมิน) */
function admin_role(): string
{
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin' || $role === 'viewer') return $role;
    return !empty($_SESSION['is_admin']) ? 'admin' : '';
}

/** หยุดทันทีถ้าไม่ใช่แอดมินเต็มสิทธิ์ */
function require_full_admin(): void
{
    if (admin_role() !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'บัญชีนี้มีสิทธิ์ดูข้อมูลอย่างเดียว'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (admin_role() === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
