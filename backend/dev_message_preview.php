<?php
// backend/dev_message_preview.php   (หน้าดูตัวอย่างชั่วคราวสำหรับพัฒนา — ลบไฟล์นี้ทิ้งเมื่อพอใจแล้ว)
// เปิดได้เฉพาะแอดมินที่ล็อกอินอยู่ ดูตัวอย่างข้อความอีเมลถึงหัวหน้ากอง: ?name=...&course=...

require_once __DIR__ . '/lib/load_env.php';
require_once __DIR__ . '/lib/ApprovalMessage.php';

session_start();
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('ต้องล็อกอินแอดมินก่อน (เปิดหน้าแอดมินและล็อกอิน แล้วเปิดลิงก์นี้อีกครั้ง)');
}

$name   = trim((string) ($_GET['name']   ?? 'ทดสอบ ทดสอบ'));
$course = trim((string) ($_GET['course'] ?? 'Photoshop วันที่ 25-27 เม.ย. 2566'));
$m  = ApprovalMessage::build($name, $course);
$on = getenv('CAS_MSG_HTML') === '1';
$h  = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

function frame(string $label, string $bodyHtml, $h): void { ?>
  <div class="mail">
    <div class="lab"><?= $h($label) ?></div>
    <div class="hd"><b>OAuthDKC</b> &lt;fdnet4141@dhammakaya.center&gt;<br>หัวข้อ: ขออนุมัติสมัครอบรม: …</div>
    <div class="bd"><?= $bodyHtml ?>
      <div style="margin-top:22px"><span class="b1">อนุมัติ</span> <span class="b2">ไม่อนุมัติ</span></div>
    </div>
  </div>
<?php } ?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตัวอย่างข้อความถึงหัวหน้ากอง (ชั่วคราว)</title>
<style>
 body{font-family:Tahoma,Arial,sans-serif;background:#eef2f7;margin:0;padding:20px;color:#0f172a}
 h1{font-size:20px;margin:0 0 8px}.note{color:#475569;font-size:14px;max-width:780px;line-height:1.6}
 .st{display:inline-block;padding:3px 10px;border-radius:999px;font-size:13px;color:#fff;background:<?= $on ? '#16a34a' : '#9ca3af' ?>}
 .row{display:flex;gap:20px;flex-wrap:wrap;margin-top:16px}
 .mail{flex:1 1 380px;background:#fff;border:1px solid #d0d7e2;border-radius:10px;overflow:hidden}
 .lab{background:#1e3a5f;color:#fff;padding:8px 14px;font-size:13px}
 .hd{padding:12px 16px;font-size:12px;color:#475569;border-bottom:1px solid #e5eaf2;line-height:1.7}
 .bd{padding:18px 16px;font-size:14px}
 .b1,.b2{display:inline-block;padding:7px 18px;color:#fff;font-size:18px;text-decoration:underline}
 .b1{background:#46b6d8}.b2{background:#f0a73a}
 pre{background:#fff;border:1px solid #d0d7e2;border-radius:10px;padding:12px;font-size:12px;white-space:pre-wrap;word-break:break-all}
 form{margin:12px 0}input{padding:6px 8px;font-size:14px;width:260px;max-width:100%}button{padding:6px 14px}
</style></head><body>
<h1>ตัวอย่างข้อความอีเมลถึงหัวหน้ากอง <small style="font-weight:normal;color:#b45309">(หน้าชั่วคราว ลบทิ้งได้)</small></h1>
<p class="note">โหมดที่ระบบส่งไป CAS ตอนนี้: <span class="st"><?= $on ? 'HTML (CAS_MSG_HTML=1)' : 'ข้อความธรรมดา (ยังไม่ได้เปิด CAS_MSG_HTML)' ?></span><br>
กรอบด้านล่างเป็นภาพจำลองจากข้อความที่โค้ดสร้างจริง แต่หน้าตาในอีเมลจริงขึ้นกับว่า CAS แสดงแท็ก HTML หรือไม่ ปุ่ม อนุมัติ/ไม่อนุมัติ เป็นส่วนที่ CAS เติมเอง</p>
<form method="get">
  <input name="name" value="<?= $h($name) ?>" placeholder="ชื่อ นามสกุล">
  <input name="course" value="<?= $h($course) ?>" placeholder="หลักสูตร">
  <button>ดูตัวอย่าง</button>
</form>
<div class="row">
  <?php frame('แบบ HTML', $m['html'], $h); ?>
  <?php frame('แบบข้อความธรรมดา (ที่หัวหน้ากองเห็นตอนนี้ถ้ายังไม่เปิด HTML)', nl2br($h($m['plain'])), $h); ?>
</div>
<p class="note" style="margin-top:20px">โค้ด HTML ที่ส่งจริง:</p>
<pre><?= $h($m['html']) ?></pre>
</body></html>
