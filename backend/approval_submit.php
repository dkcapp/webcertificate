<?php
// backend/approval_submit.php
// ส่งคำขออนุมัติถึงหัวหน้ากองผ่าน CAS (ผู้ขอ = บัญชี AD ที่ล็อกอิน) แล้วบันทึกลง Neon
// ใช้ข้อมูลใน session ที่ approval_start.php เก็บไว้ ถ้าส่งไม่สำเร็จจะเก็บไว้ให้กด "ลองส่งใหม่" (เรียกไฟล์นี้อีกครั้ง)

require_once __DIR__ . '/lib/load_env.php';
require_once __DIR__ . '/lib/user_session.php';
require_once __DIR__ . '/lib/CasClient.php';
require_once __DIR__ . '/lib/ApprovalRepository.php';
require_once __DIR__ . '/../database/config.php';

user_session_start();

$p = $_SESSION['pending'] ?? null;
if (!is_array($p) || empty($p['ref'])) {
    dkc_redirect_thankyou(['e' => 'missing']);
}
if (empty($_SESSION['user']['username'])) {
    dkc_redirect('/backend/oauth_login.php');
}

$ref    = (string) $p['ref'];
$name   = (string) ($p['name'] ?? '');
$course = (string) ($p['course'] ?? '');
$type   = (string) ($p['type'] ?? '');
$me     = (string) $_SESSION['user']['username'];
$meName = (string) ($_SESSION['user']['display_name'] ?? '');

if ($name === '' || $course === '' || $type === '') {
    unset($_SESSION['pending']);
    dkc_redirect_thankyou(['e' => 'missing']);
}

try {
    $repo = new ApprovalRepository(getDB());

    // กันส่งซ้ำ: รหัสนี้มีแล้ว → ของเราแสดงของเดิม / ของคนอื่นปฏิเสธ
    $existing = $repo->find($ref);
    if ($existing) {
        unset($_SESSION['pending']);
        if ($existing['requester_ad'] === $me) dkc_redirect_thankyou(['ref' => $ref]);
        dkc_redirect_thankyou(['ref' => $ref, 'e' => 'dup']);
    }

    $subject = mb_substr("ขออนุมัติสมัครอบรม: $course", 0, 200, 'UTF-8');
    $title   = 'ระบบสมัครอบรมคอมพิวเตอร์';
    // ข้อความธรรมดา: ใช้เก็บลง Neon และเป็นข้อความสำรองเมื่อปิดโหมด HTML
    $message = mb_substr("$title\nขออนุมัติให้ $name เข้าอบรมหลักสูตร $course", 0, 2000, 'UTF-8');
    // ข้อความที่ส่งไป CAS: ตั้ง CAS_MSG_HTML=1 บน Render เพื่อส่งแบบ HTML (ปิด/ลบตัวแปร = กลับเป็นข้อความธรรมดา)
    $sendMsg = $message;
    if (getenv('CAS_MSG_HTML') === '1') {
        $e   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); // กันแทรกโค้ดจากชื่อ/คอร์สที่ผู้ใช้กรอก
        $lab = 'padding:4px 14px 4px 0;color:#64748b;vertical-align:top;white-space:nowrap';
        $val = 'padding:4px 0;color:#0f172a';
        $sendMsg = '<div style="font-family:Tahoma,Arial,sans-serif;font-size:15px;line-height:1.5;color:#0f172a">'
            . '<div style="font-size:18px;font-weight:bold;color:#1d4ed8;padding-bottom:6px;border-bottom:2px solid #1d4ed8;margin-bottom:10px">' . $e($title) . '</div>'
            . '<div style="margin-bottom:6px">ขออนุมัติให้ผู้สมัครเข้าอบรม</div>'
            . '<table style="border-collapse:collapse">'
            . '<tr><td style="' . $lab . '">ผู้สมัคร</td><td style="' . $val . ';font-weight:bold">' . $e($name) . '</td></tr>'
            . '<tr><td style="' . $lab . '">หลักสูตร</td><td style="' . $val . '">' . $e($course) . '</td></tr>'
            . '</table></div>';
    }

    try {
        $cas = CasClient::fromEnv();
        $r = $cas->sendRequest($ref, 1, $subject, $sendMsg, $me, dkc_app_url() . '/backend/cas_callback.php');
    } catch (Throwable $e) {
        error_log('[approval_submit] CAS request failed: ' . $e->getMessage());
        dkc_redirect_thankyou(['ref' => $ref, 'e' => 'upstream']);
    }

    if ($r === null) {
        dkc_redirect_thankyou(['ref' => $ref, 'e' => 'upstream']);
    }
    if (!CasClient::isOk($r)) {
        error_log('[approval_submit] CAS rejected: ' . substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 300));
        dkc_redirect_thankyou(['ref' => $ref, 'e' => 'cas']);
    }

    // CAS รับคำขอแล้ว → ต้องบันทึกลง Neon (ถ้าพลาด ห้ามให้ส่งซ้ำ จึงล้าง pending และแจ้งให้ติดต่อเจ้าหน้าที่)
    $d = $r['data'];
    unset($_SESSION['pending']);
    try {
        $repo->insert([
            'ref'            => $ref,
            'requester_ad'   => $me,
            'requester_name' => $meName,
            'applicant_name' => $name,
            'course'         => $course,
            'member_type'    => $type,
            'approve_type'   => 1,
            'subject'        => $subject,
            'message'        => $message,
            'request_key'    => dkc_clip($d['RequestKey'] ?? '', 100),
            'approver_ad'    => dkc_clip($d['ADApprover'] ?? '', 100),
            'approver_name'  => dkc_clip($d['HeadFullName'] ?? '', 255),
            'cas_data'       => json_encode($d, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('[approval_submit] DB insert failed after CAS success (ref=' . $ref . '): ' . $e->getMessage());
        dkc_redirect_thankyou(['ref' => $ref, 'e' => 'db']);
    }

    dkc_redirect_thankyou(['ref' => $ref]);
} catch (Throwable $e) {
    error_log('[approval_submit] ' . $e->getMessage());
    dkc_redirect_thankyou(['ref' => $ref, 'e' => 'upstream']);
}
