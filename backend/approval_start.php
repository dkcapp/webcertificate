<?php
// backend/approval_start.php
// จุดเริ่มต้นขออนุมัติ: thankyou.html ส่งผู้ใช้ที่เลือกประเภทต้องขออนุมัติมาที่นี่ พร้อม ?name=&course=&type=
// เก็บข้อมูลไว้ใน session ฝั่ง server (ไม่ต้องแนบไปกับ URL ตอนล็อกอิน) แล้วพาไปล็อกอิน DKC (ถ้ายังไม่ได้ล็อกอิน)
// หรือส่งคำขอทันที (ถ้าล็อกอินอยู่แล้ว)

require_once __DIR__ . '/lib/user_session.php';

user_session_start();

$name   = dkc_clip($_GET['name']   ?? '', 100);
$course = dkc_clip($_GET['course'] ?? '', 200);
$type   = dkc_clip($_GET['type']   ?? '', 100);

if ($name === '' || $course === '' || $type === '') {
    dkc_redirect_thankyou(['e' => 'missing']);
}

// รหัสอ้างอิงเดายาก: JF-<epoch ms>-<hex 8 ตัว> (ใช้เป็น ExternalRefID ของ CAS และกันส่งซ้ำ)
$ref = 'JF-' . (string) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(4));

$_SESSION['applicant'] = ['name' => $name, 'course' => $course, 'type' => $type];
$_SESSION['pending'] = [
    'ref'    => $ref,
    'name'   => $name,
    'course' => $course,
    'type'   => $type,
];

dkc_redirect(empty($_SESSION['user']) ? '/backend/oauth_login.php' : '/backend/approval_submit.php');
