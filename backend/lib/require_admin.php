<?php
// backend/lib/require_admin.php
// Middleware กลาง: เริ่ม session, ตั้ง response header เป็น JSON,
// และเช็คว่า login เป็น admin อยู่หรือไม่ ถ้าไม่ใช่ให้ตอบ 403 แล้วหยุดทำงานทันที
//
// วิธีใช้: ไฟล์ endpoint ที่ต้องการสิทธิ์ admin แค่เพิ่มบรรทัดเดียวไว้บนสุด
//   require_once __DIR__ . '/lib/require_admin.php';
// แล้วไม่ต้องเขียนโค้ดเช็ค login ซ้ำเองอีก

session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}