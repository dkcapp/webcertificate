<?php
// backend/approval_result.php
// endpoint สาธารณะสำหรับหน้า thankyou.html: ถามผลคำขออนุมัติตามรหัสอ้างอิง
// เรียก Portal แบบ server-to-server (คีย์อยู่ฝั่ง server) ตอบเฉพาะ สถานะ + ชื่อหัวหน้ากอง
// รหัสอ้างอิงสุ่มเดาไม่ได้ (JF-<เวลา>-<hex8>) และไม่ส่งข้อมูลผู้สมัครกลับ

require_once __DIR__ . '/lib/load_env.php';
require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/lib/PortalClient.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ref = isset($_GET['ref']) ? (string) $_GET['ref'] : '';
$base = rtrim(getenv('PORTAL_BASE_URL') ?: '', '/');

if (!preg_match('/^JF-\d{10,14}-[a-f0-9]{8}$/', $ref)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'รหัสอ้างอิงไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $portal = PortalClient::fromEnv();
    $r = $portal->getRequest($ref);
    echo json_encode([
        'ok'            => true,
        'found'         => !empty($r['found']),
        'status'        => $r['status'] ?? null,
        'approver_name' => $r['approver_name'] ?? null,
        'portal_url'    => $base,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    send_error_response($e, 'ไม่สามารถตรวจสอบผลได้ในขณะนี้', 502);
}
