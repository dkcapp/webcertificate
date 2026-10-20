<?php
// backend/jf_probe.php  (ไฟล์ทดสอบชั่วคราว — ลบทิ้งเมื่อทดสอบเสร็จ)
// ใช้ดูว่า JotForm ส่งอะไรมาตอน "Redirect + Send Post Data" (เช่น submission_id, ลิงก์/hash ของ PDF หรือไม่)
// ไม่บันทึกข้อมูลส่วนตัว: log เฉพาะชื่อ field + ความยาวค่า (ยกเว้น field ที่ชื่อเกี่ยวกับ pdf/hash/link/url/id จะ log 70 ตัวอักษรแรก)

$method = $_SERVER['REQUEST_METHOD'] ?? '-';
$lines  = ["[jf_probe] method=$method type=" . ($_SERVER['CONTENT_TYPE'] ?? '-')];

$show = function (string $label, array $data, int $depth = 0) use (&$show, &$lines) {
    foreach ($data as $k => $v) {
        $key = $label . $k;
        if (is_array($v)) {
            if ($depth < 2) $show($key . '.', $v, $depth + 1);
            else $lines[] = "  $key = [array]";
            continue;
        }
        $v = (string) $v;
        $len = mb_strlen($v, 'UTF-8');
        if (preg_match('/pdf|hash|link|url|submission|sid|form_?id|id$/i', (string) $k)) {
            $lines[] = "  $key (len=$len) = " . mb_substr($v, 0, 70, 'UTF-8') . ($len > 70 ? '...' : '');
        } else {
            $lines[] = "  $key (len=$len)";
        }
    }
};

if ($_GET)  { $lines[] = ' GET:';  $show('', $_GET); }
if ($_POST) { $lines[] = ' POST:'; $show('', $_POST); }

$raw = file_get_contents('php://input');
if ($raw !== '' && !$_POST) {
    $j = json_decode($raw, true);
    if (is_array($j)) { $lines[] = ' JSON body:'; $show('', $j); }
    else $lines[] = ' raw body length=' . strlen($raw);
}

error_log(implode("\n", $lines));

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:24px">'
   . '<h2>jf_probe: บันทึกแล้ว</h2><p>ไปดูผลที่ Render → Logs (ค้นหาคำว่า <code>jf_probe</code>)</p>';
