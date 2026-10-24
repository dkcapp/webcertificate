<?php
// backend/jotform_webhook.php
// ปลายทางที่ JotForm ยิง Webhook มาโดยตรงเมื่อมีคนกด Submit ฟอร์ม
// แทนที่ Google Apps Script (doPost) เดิม -> บันทึกเข้า Neon Postgres โดยตรง

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/lib/CourseRepository.php';
require_once __DIR__ . '/lib/StudentRepository.php';
require_once __DIR__ . '/lib/JotformFields.php';

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

    // แกะฟิลด์ตาม field id ของฟอร์มบริษัท (231301336713444) — mapping อยู่ใน lib/JotformFields.php
    $f = JotformFields::extract($raw);
    $first_name      = $f['first_name'];
    $last_name       = $f['last_name'];
    $member_type     = $f['member_type'];
    $age_or_royal    = $f['age']; // "อายุ / พรรษาที่" รวมช่องเดียว
    $education_level = $f['education_level'];
    $faculty         = $f['faculty'];
    $major           = $f['major'];
    $institution     = $f['institution'];
    $department      = $f['department'];
    $office          = $f['office'];
    $position        = $f['position'];
    $phone_internal  = $f['phone_internal'];
    $phone_mobile    = $f['phone_mobile'];
    $email           = $f['email'];
    $attendance      = $f['fee'];
    $birth_date      = $f['birth_date'] !== '' ? $f['birth_date'] : null;
    $apply_date      = $f['apply_date'] !== '' ? $f['apply_date'] : null;
    $courseName      = $f['course'];

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