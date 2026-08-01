<?php
// backend/jotform_webhook.php
// ปลายทางที่ JotForm ยิง Webhook มาโดยตรงเมื่อมีคนกด Submit ฟอร์ม
// แทนที่ Google Apps Script (doPost) เดิม -> บันทึกเข้า Neon Postgres โดยตรง

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/lib/CourseRepository.php';
require_once __DIR__ . '/lib/StudentRepository.php';

function jf_log($message) {
    error_log('[jotform_webhook] ' . $message);
}

try {
    $pdo         = getDB();
    $courseRepo  = new CourseRepository($pdo);
    $studentRepo = new StudentRepository($pdo);

    // JotForm ส่งข้อมูล field จริงมาใน field ชื่อ rawRequest (multipart/form-data หรือ x-www-form-urlencoded)
    $rawRequestJson = $_POST['rawRequest'] ?? '{}';
    $raw = json_decode($rawRequestJson, true);
    if (!is_array($raw)) {
        $raw = [];
    }

    // แกะฟิลด์ตาม field id ของฟอร์ม (เหมือนกับใน Code.gs เดิมทุกตัว)
    $first_name   = trim($raw['q110_typeA110'] ?? '');
    $last_name    = trim($raw['q111_input111'] ?? '');
    $member_type  = trim($raw['q17_typeA'] ?? '');
    $department   = trim($raw['q60_input60'] ?? '');
    $office       = trim($raw['q32_input59'] ?? '');
    $position     = trim($raw['q90_input90'] ?? '');
    $phone_mobile = trim($raw['q39_phoneNumber']['full'] ?? '');
    $email        = trim($raw['q82_email82'] ?? '');

    $apply_date = null;
    if (!empty($raw['q9_date']['year']) && !empty($raw['q9_date']['month']) && !empty($raw['q9_date']['day'])) {
        $apply_date = $raw['q9_date']['year'] . '-' . $raw['q9_date']['month'] . '-' . $raw['q9_date']['day'];
    }

    $courseName = trim($raw['q93_input115'] ?? '');

    if ($first_name === '') {
        jf_log('ERROR: ไม่มีชื่อผู้สมัคร (first_name ว่าง) - raw: ' . $rawRequestJson);
        echo json_encode(['ok' => false, 'error' => 'ไม่พบชื่อผู้สมัคร']);
        exit;
    }

    // หา course_id จากชื่อคอร์สที่เลือกใน dropdown (เทียบได้ทั้งชื่อสั้นและชื่อยาว กันเคส dropdown เปลี่ยนไปมา)
    $course = $courseName !== '' ? $courseRepo->findByShortNameOrLongKey($courseName) : null;

    if (!$course) {
        // course_id เป็น NOT NULL ในตาราง students ถ้าหาไม่เจอต้องหยุดตรงนี้ ห้าม insert ต่อ
        jf_log("ERROR: ไม่พบคอร์สชื่อ '$courseName' ในระบบ - raw: " . $rawRequestJson);
        echo json_encode(['ok' => false, 'error' => "ไม่พบคอร์สชื่อ '$courseName' ในระบบ กรุณาตรวจสอบว่าตั้งชื่อคอร์สใน dropdown ตรงกับ short_name ในตาราง courses"]);
        exit;
    }

    $newId = $studentRepo->insertFromWebhook([
        'course_id'    => (int)$course['id'],
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'member_type'  => $member_type,
        'apply_date'   => $apply_date,
        'department'   => $department,
        'office'       => $office,
        'position'     => $position,
        'phone_mobile' => $phone_mobile,
        'email'        => $email,
    ]);

    jf_log("OK: เพิ่มผู้สมัคร '$first_name $last_name' (id=$newId, course='$courseName', course_id={$course['id']})");

    echo json_encode(['ok' => true, 'id' => $newId]);
} catch (Throwable $e) {
    send_error_response($e, 'ไม่สามารถบันทึกข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้งหรือติดต่อผู้ดูแลระบบ');
}