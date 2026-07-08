<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database/config.php';

try {
    $pdo = getDB();

    $rows = $pdo->query("
        SELECT
            s.id AS student_id, s.airtable_id,
            s.first_name, s.last_name, s.member_type, s.apply_date,
            s.birth_date, s.age, s.royal_title, s.education_level,
            s.faculty, s.major, s.institution, s.department, s.office,
            s.position, s.phone_internal, s.phone_mobile, s.email,
            s.head_status, s.attendance, s.last_modified_time,
            c.short_name AS course_name, c.training_date, c.year_be, c.verify_url
        FROM students s
        INNER JOIN courses c ON c.id = s.course_id
        ORDER BY c.year_be DESC, c.short_name ASC, s.first_name ASC
    ")->fetchAll();

    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'id' => $row['airtable_id'] ?: ('s' . $row['student_id']),
            'fields' => [
                'ชื่อ'                 => $row['first_name'],
                'นามสกุล (ฉายา)'       => $row['last_name'],
                'ชื่อโปรแกรม'           => $row['course_name'],
                'วันที่อบรม'            => $row['training_date'],
                'ประเภทสมาชิก'         => $row['member_type'],
                'วันที่สมัคร'           => $row['apply_date'],
                'วัน/เดือน/ปี เกิด'    => $row['birth_date'],
                'อายุ'                 => $row['age'],
                'พรรษาที่'             => $row['royal_title'],
                'ระดับการศึกษาสูงสุด'   => $row['education_level'],
                'คณะ'                  => $row['faculty'],
                'สาขา'                 => $row['major'],
                'สถาบัน'               => $row['institution'],
                'หน่วยงาน กอง/ศูนย์'   => $row['department'],
                'สำนัก'                => $row['office'],
                'ตำแหน่ง'              => $row['position'],
                'เบอร์ภายใน'           => $row['phone_internal'],
                'เบอร์มือถือ'          => $row['phone_mobile'],
                'Email'                => $row['email'],
                'สถานะหัวหน้ากอง'      => $row['head_status'],
                'การเข้าอบรม'          => $row['attendance'],
                'Last modified time'   => $row['last_modified_time'],
                'year_be'              => $row['year_be'],
                'verify_url'           => $row['verify_url'],
            ],
        ];
    }

    echo json_encode(['ok' => true, 'count' => count($records), 'records' => $records], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
