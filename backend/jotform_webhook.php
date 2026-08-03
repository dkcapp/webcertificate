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

    // แกะฟิลด์ตาม field id ของฟอร์มบริษัท (231301336713444)
    $first_name      = trim($raw['q110_typeA110'] ?? '');
    $last_name       = trim($raw['q111_input111'] ?? '');
    $member_type     = trim($raw['q17_typeA'] ?? '');
    $age_or_royal    = trim($raw['q60_input60'] ?? ''); // "อายุ / พรรษาที่" รวมช่องเดียว
    $education_level = trim($raw['q61_input61'] ?? '');
    $faculty         = trim($raw['q90_input90'] ?? '');
    $major           = trim($raw['q79_input79'] ?? '');
    $institution     = trim($raw['q75_input75'] ?? '');
    $department      = trim($raw['q31_typeA31'] ?? '');
    $office          = trim($raw['q32_input59'] ?? '');
    $position        = trim($raw['q33_typeA33'] ?? '');
    $phone_internal  = trim($raw['q38_input38']['full'] ?? '');
    $phone_mobile    = trim($raw['q39_phoneNumber']['full'] ?? '');
    $email           = trim($raw['q82_email82'] ?? '');
    $attendance      = trim($raw['q104_input104'] ?? '');

    $birth_date = null;
    if (!empty($raw['q98_input98']['year']) && !empty($raw['q98_input98']['month']) && !empty($raw['q98_input98']['day'])) {
        $birth_date = $raw['q98_input98']['year'] . '-' . $raw['q98_input98']['month'] . '-' . $raw['q98_input98']['day'];
    }

    $apply_date = null;
    if (!empty($raw['q9_date']['year']) && !empty($raw['q9_date']['month']) && !empty($raw['q9_date']['day'])) {
        $apply_date = $raw['q9_date']['year'] . '-' . $raw['q9_date']['month'] . '-' . $raw['q9_date']['day'];
    }

    $courseName = trim($raw['q93_input93'] ?? '');

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
        echo json_encode([
            'ok'    => false,
            'error' => "ไม่พบคอร์สชื่อ '$courseName' ในระบบเว็บไซต์ กรุณาไปที่เมนู 'นำเข้าข้อมูลจาก Airtable' เพื่อเพิ่มคอร์สนี้เข้าระบบก่อน (ข้อมูลผู้สมัครคนนี้ยังถูกบันทึกใน Airtable ของบริษัทตามปกติ ไม่หายไปไหน)",
        ]);
        exit;
    }

    $newId = $studentRepo->insertFromAirtable([
        'course_id'          => (int)$course['id'],
        'airtable_id'        => null,
        'first_name'         => $first_name,
        'last_name'          => $last_name,
        'member_type'        => $member_type,
        'apply_date'         => $apply_date,
        'birth_date'         => $birth_date,
        'age'                => $age_or_royal,
        'royal_title'        => null,
        'education_level'    => $education_level,
        'faculty'            => $faculty,
        'major'              => $major,
        'institution'        => $institution,
        'department'         => $department,
        'office'             => $office,
        'position'           => $position,
        'phone_internal'     => $phone_internal,
        'phone_mobile'       => $phone_mobile,
        'email'              => $email,
        'head_status'        => null,
        'attendance'         => $attendance,
        'last_modified_time' => null,
    ]);

    jf_log("OK: เพิ่มผู้สมัคร '$first_name $last_name' (id=$newId, course='$courseName', course_id={$course['id']})");

    echo json_encode(['ok' => true, 'id' => $newId]);
} catch (Throwable $e) {
    send_error_response($e, 'ไม่สามารถบันทึกข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้งหรือติดต่อผู้ดูแลระบบ');
}