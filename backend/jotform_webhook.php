<?php
// backend/jotform_webhook.php
// ปลายทางที่ JotForm ยิง Webhook มาโดยตรงเมื่อมีคนกด Submit ฟอร์ม
// แทนที่ Google Apps Script (doPost) เดิม -> บันทึกเข้า Neon Postgres โดยตรง

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../database/config.php';

// เก็บ log ผ่าน error_log() แทนการเขียนไฟล์ (บน Render โฟลเดอร์นี้อาจไม่มีสิทธิ์เขียนไฟล์ใหม่)
// error_log() จะไปโผล่ใน Render Logs ให้เห็นได้เลย
function jf_log($message) {
    error_log('[jotform_webhook] ' . $message);
}

try {
    $pdo = getDB();

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

    // หา course_id จากชื่อคอร์สที่เลือกใน dropdown
    $course_id = null;
    if ($courseName !== '') {
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE short_name = :name LIMIT 1");
        $stmt->execute([':name' => $courseName]);
        $courseRow = $stmt->fetch();
        if ($courseRow) {
            $course_id = (int)$courseRow['id'];
        }
    }

    if ($course_id === null) {
        // course_id เป็น NOT NULL ในตาราง students ถ้าหาไม่เจอต้องหยุดตรงนี้ ห้าม insert ต่อ
        jf_log("ERROR: ไม่พบคอร์สชื่อ '$courseName' ในระบบ - raw: " . $rawRequestJson);
        echo json_encode(['ok' => false, 'error' => "ไม่พบคอร์สชื่อ '$courseName' ในระบบ กรุณาตรวจสอบว่าตั้งชื่อคอร์สใน dropdown ตรงกับ short_name ในตาราง courses"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO students
            (course_id, first_name, last_name, member_type, apply_date, department, office, position, phone_mobile, email)
        VALUES
            (:course_id, :first_name, :last_name, :member_type, :apply_date, :department, :office, :position, :phone_mobile, :email)
        RETURNING id
    ");
    $stmt->execute([
        ':course_id'    => $course_id,
        ':first_name'   => $first_name,
        ':last_name'    => $last_name ?: null,
        ':member_type'  => $member_type ?: null,
        ':apply_date'   => $apply_date,
        ':department'   => $department ?: null,
        ':office'       => $office ?: null,
        ':position'     => $position ?: null,
        ':phone_mobile' => $phone_mobile ?: null,
        ':email'        => $email ?: null,
    ]);

    $newId = (int)$stmt->fetchColumn();
    jf_log("OK: เพิ่มผู้สมัคร '$first_name $last_name' (id=$newId, course='$courseName', course_id=" . var_export($course_id, true) . ")");

    echo json_encode(['ok' => true, 'id' => $newId]);
} catch (Throwable $e) {
    jf_log('EXCEPTION: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}