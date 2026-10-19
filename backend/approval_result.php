<?php
// backend/approval_result.php
// endpoint สาธารณะสำหรับหน้า thankyou.html: ถามผลคำขออนุมัติตามรหัสอ้างอิง (อ่านจาก Neon)
// ตอบเฉพาะ สถานะ + ชื่อหัวหน้ากอง รหัสอ้างอิงสุ่มเดาไม่ได้ (JF-<เวลา>-<hex8>) และไม่ส่งข้อมูลผู้สมัครกลับ

require_once __DIR__ . '/lib/load_env.php';
require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/lib/ApprovalRepository.php';
require_once __DIR__ . '/../database/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ref = isset($_GET['ref']) ? (string) $_GET['ref'] : '';

if (!preg_match('/^JF-\d{10,14}-[a-f0-9]{8}$/', $ref)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'รหัสอ้างอิงไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $row = (new ApprovalRepository(getDB()))->find($ref);
    $cas = $row ? (json_decode((string) ($row['cas_data'] ?? ''), true) ?: []) : [];
    echo json_encode([
        'ok'            => true,
        'found'         => (bool) $row,
        'status'        => $row ? ApprovalRepository::statusKind($row['status']) : null,
        'approver_name' => $row['approver_name'] ?? null,
        // อีเมลที่ CAS ส่งมาถูกปิดบางส่วนมาแล้ว (เช่น so****72@gmail.com) ส่งต่อตามนั้น
        'approver_email'    => $cas['HeadShowEmail'] ?? null,
        'approver_position' => $cas['Position'] ?? null,
        'approver_org'      => $cas['Organization'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    send_error_response($e, 'ไม่สามารถตรวจสอบผลได้ในขณะนี้', 502);
}
