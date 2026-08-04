<?php
// backend/api.php
// Endpoint สาธารณะ (ไม่ต้อง login) — ใช้โดยหน้าดาวน์โหลดใบประกาศ

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/lib/StudentRepository.php';

try {
    $pdo         = getDB();
    $studentRepo = new StudentRepository($pdo);
    $rows        = $studentRepo->listAllWithCourse();

    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'id' => $row['airtable_id'] ?: ('s' . $row['student_id']),
            'fields' => [
                'ชื่อ'                 => $row['first_name'],
                'นามสกุล (ฉายา)'       => $row['last_name'],
                'ชื่อโปรแกรม'           => $row['course_name'],
                'ชื่อโปรแกรมเต็ม'       => $row['course_long_name'],
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
} catch (Throwable $e) {
    send_error_response($e, 'ไม่สามารถโหลดข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง');
}