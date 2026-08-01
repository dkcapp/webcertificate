<?php
require_once __DIR__ . '/lib/require_admin.php';
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/lib/CourseRepository.php';

$pdo        = getDB();
$courseRepo = new CourseRepository($pdo);

// ====== ตั้งค่า JotForm ผ่าน Environment Variables (ตั้งค่าจริงไว้ที่ Render Dashboard) ======
define('JOTFORM_API_KEY', getenv('JOTFORM_API_KEY') ?: '');
define('JOTFORM_FORM_ID', getenv('JOTFORM_FORM_ID') ?: '');
define('JOTFORM_FIELD_ID', getenv('JOTFORM_FIELD_ID') ?: '');
// ===============================================================================

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // ดึงรายการคอร์สทั้งหมด + สถานะว่าเปิดรับสมัครไหม
    if ($action === 'list') {
        echo json_encode(['ok' => true, 'data' => $courseRepo->listWithActiveFlag()]);
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
        $active ? $courseRepo->activate($course_id) : $courseRepo->deactivate($course_id);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Sync ไปยัง JotForm โดยตรง (ไม่ผ่าน Google Apps Script อีกต่อไป)
    if ($action === 'sync_jotform') {
        if (!JOTFORM_API_KEY || !JOTFORM_FORM_ID || !JOTFORM_FIELD_ID) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า JOTFORM_API_KEY / JOTFORM_FORM_ID / JOTFORM_FIELD_ID ใน environment variables']);
            exit;
        }

        $activeCourseNames = $courseRepo->listActiveLongKeys();
        if (!$activeCourseNames) {
            echo json_encode(['ok' => false, 'error' => 'ไม่มีคอร์สที่เปิดรับสมัครอยู่ กรุณาเปิดอย่างน้อย 1 คอร์สก่อน sync']);
            exit;
        }

        $courseNames = implode('|', $activeCourseNames);
        $jfUrl = "https://api.jotform.com/form/" . JOTFORM_FORM_ID . "/question/" . JOTFORM_FIELD_ID . "?apiKey=" . JOTFORM_API_KEY;

        $ch = curl_init($jfUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'question[options]' => $courseNames,
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result    = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            echo json_encode(['ok' => false, 'error' => 'เชื่อมต่อ JotForm ไม่สำเร็จ: ' . $curlError]);
            exit;
        }

        $jfResult = json_decode($result, true);
        $success  = $httpCode === 200 && isset($jfResult['responseCode']) && $jfResult['responseCode'] === 200;

        echo json_encode([
            'ok'                => $success,
            'synced_courses'    => $activeCourseNames,
            'jotform_response'  => $jfResult,
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown action']);
} catch (Throwable $e) {
    send_error_response($e, 'เกิดข้อผิดพลาดในการประมวลผล กรุณาลองใหม่อีกครั้ง');
}