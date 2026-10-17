<?php
// backend/cas_callback.php
// CAS เรียกกลับ (GET) เมื่อหัวหน้ากองกดอนุมัติ/ปฏิเสธ — ไม่ต้องล็อกอิน
// ใช้สถานะจริงจากการถาม CAS ซ้ำ (reqstat) เท่านั้น ไม่เชื่อค่า status ใน query string (ปลอมได้)

require_once __DIR__ . '/lib/load_env.php';
require_once __DIR__ . '/lib/CasClient.php';
require_once __DIR__ . '/lib/ApprovalRepository.php';
require_once __DIR__ . '/../database/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$ref  = (string) ($_GET['ExternalRefID'] ?? '');
$kind = 'unknown';

if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $ref)) {
    try {
        $d = CasClient::fromEnv()->fetchStatus($ref);
        if ($d) {
            $kind = ApprovalRepository::statusKind($d['Status'] ?? '');
            try {
                (new ApprovalRepository(getDB()))->updateStatus(
                    $ref,
                    (string) $d['Status'],
                    isset($d['ApproveStep']) ? (string) $d['ApproveStep'] : null,
                    !empty($d['Approver_ADUser']) ? (string) $d['Approver_ADUser'] : null
                );
            } catch (Throwable $e) {
                error_log('[cas_callback] DB update failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('[cas_callback] ' . $e->getMessage());
    }
}

$view = [
    'approved' => ['#22c55e', 'อนุมัติเรียบร้อยแล้ว'],
    'rejected' => ['#ef4444', 'ปฏิเสธการอนุมัติ'],
    'pending'  => ['#f59e0b', 'บันทึกผลแล้ว รอการอนุมัติขั้นถัดไป'],
    'unknown'  => ['#6b7280', 'ไม่สามารถยืนยันสถานะได้ในขณะนี้'],
][$kind];
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ผลการดำเนินการ</title>
  <style>
    body { font-family: 'Sarabun', 'Prompt', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background-color: #f3f4f6; }
    .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; margin: 16px; }
    h1 { color: <?= $h($view[0]) ?>; margin-bottom: 1rem; font-size: 1.5rem; }
    p { color: #4b5563; word-break: break-all; }
  </style>
</head>
<body>
  <div class="card">
    <h1><?= $h($view[1]) ?></h1>
    <p>รหัสอ้างอิง: <?= $h($ref) ?></p>
    <p>ท่านสามารถปิดหน้านี้ได้ทันที</p>
  </div>
</body>
</html>
