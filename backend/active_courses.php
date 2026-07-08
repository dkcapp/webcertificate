<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../database/config.php';
$pdo = getDB();

// ====== ตั้งค่า JotForm ผ่าน Environment Variables (ตั้งค่าจริงไว้ที่ Render Dashboard) ======
define('JOTFORM_API_KEY', getenv('JOTFORM_API_KEY') ?: '');
define('JOTFORM_FORM_ID', getenv('JOTFORM_FORM_ID') ?: '');
define('JOTFORM_FIELD_ID', getenv('JOTFORM_FIELD_ID') ?: '');
// ===============================================================================

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ดึงรายการคอร์สทั้งหมด + สถานะว่าเปิดรับสมัครไหม
if ($action === 'list') {
    $rows = $pdo->query("
        SELECT c.id, c.short_name, c.year_be,
               CASE WHEN ac.course_id IS NOT NULL THEN true ELSE false END as is_active
        FROM courses c
        LEFT JOIN active_courses ac ON ac.course_id = c.id
        ORDER BY c.year_be DESC, c.short_name ASC
    ")->fetchAll();
    echo json_encode(['ok' => true, 'data' => $rows]);
    exit;
}

// เปิด/ปิดคอร์ส
if ($action === 'toggle') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $active    = $_POST['active'] === 'true';
    if (!$course_id) {
        echo json_encode(['ok' => false, 'error' => 'ไม่พบ course_id']);
        exit;
    }
    if ($active) {
        $pdo->prepare("INSERT INTO active_courses (course_id) VALUES (:id) ON CONFLICT (course_id) DO NOTHING")
            ->execute([':id' => $course_id]);
    } else {
        $pdo->prepare("DELETE FROM active_courses WHERE course_id = :id")
            ->execute([':id' => $course_id]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// Sync ไปยัง JotForm โดยตรง (ไม่ผ่าน Google Apps Script อีกต่อไป)
if ($action === 'sync_jotform') {
    if (!JOTFORM_API_KEY || !JOTFORM_FORM_ID || !JOTFORM_FIELD_ID) {
        echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า JOTFORM_API_KEY / JOTFORM_FORM_ID / JOTFORM_FIELD_ID ใน environment variables']);
        exit;
    }

    // 1. ดึงคอร์สที่เปิดรับสมัครอยู่จาก Neon
    $rows = $pdo->query("
        SELECT c.short_name
        FROM active_courses ac
        INNER JOIN courses c ON c.id = ac.course_id
        ORDER BY c.short_name ASC
    ")->fetchAll();

    if (!$rows) {
        echo json_encode(['ok' => false, 'error' => 'ไม่มีคอร์สที่เปิดรับสมัครอยู่ กรุณาเปิดอย่างน้อย 1 คอร์สก่อน sync']);
        exit;
    }

    // 2. รวมชื่อคอร์สด้วย | ตามรูปแบบที่ JotForm dropdown ต้องการ
    $courseNames = implode('|', array_map(fn($r) => $r['short_name'], $rows));

    // 3. เรียก JotForm API เพื่ออัปเดต options ของ dropdown field
    $jfUrl = "https://api.jotform.com/form/" . JOTFORM_FORM_ID . "/question/" . JOTFORM_FIELD_ID . "?apiKey=" . JOTFORM_API_KEY;

    $ch = curl_init($jfUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'question[options]' => $courseNames,
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['ok' => false, 'error' => 'เชื่อมต่อ JotForm ไม่สำเร็จ: ' . $curlError]);
        exit;
    }

    $jfResult = json_decode($result, true);
    $success = $httpCode === 200 && isset($jfResult['responseCode']) && $jfResult['responseCode'] === 200;

    echo json_encode([
        'ok' => $success,
        'synced_courses' => $rows ? array_column($rows, 'short_name') : [],
        'jotform_response' => $jfResult,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown action']);