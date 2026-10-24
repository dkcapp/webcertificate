<?php
// thankyou.php
// ปลายทาง Thank You ของ JotForm แบบ "Redirect + Send Post Data" (JotForm ส่งค่าทุกช่องมาแบบ POST)
// สร้าง "ใบสรุปข้อมูลการสมัคร" เก็บไว้ใน sessionStorage ของแท็บผู้สมัครเท่านั้น แล้วพาไป thankyou.html
//   - เซิร์ฟเวอร์ไม่บันทึก/ไม่ log ข้อมูลส่วนตัว และไม่มีข้อมูลในลิงก์นอกจาก name/course/type (เหมือนเดิม)
//   - ใช้ location.replace เพื่อให้รีเฟรชแล้วไม่ถามส่งฟอร์มซ้ำ
// ถ้าเปิดแบบ GET (เช่น JotForm ยังตั้งเป็นลิงก์ thankyou.html?... แบบเดิม) จะส่งต่อไป thankyou.html พร้อม query เดิม

require_once __DIR__ . '/backend/lib/JotformFields.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$clip = function ($v, int $n): string {
    $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
    return mb_substr($v, 0, $n, 'UTF-8');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $q = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: thankyou.html' . ($q !== '' ? '?' . $q : ''), true, 302);
    exit;
}

// บางกรณี JotForm ส่งค่าทั้งหมดมาใน rawRequest (JSON) เหมือน webhook
$raw = $_POST;
if (isset($_POST['rawRequest']) && is_string($_POST['rawRequest'])) {
    $decoded = json_decode($_POST['rawRequest'], true);
    if (is_array($decoded)) $raw = $decoded + $_POST;
}
$f = JotformFields::extract($raw);

$name   = $clip($f['first_name'] . ' ' . $f['last_name'], 100);
$course = $clip($f['course'], 200);
$type   = $clip($f['member_type'], 100);

// YYYY-MM-DD (ค.ศ.) → "5 มี.ค. 2533" สำหรับแสดงในใบสรุป
$thaiDate = function (string $d): string {
    if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $d, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return $d;
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int) $m[3] . ' ' . $months[(int) $m[2] - 1] . ' ' . ((int) $m[1] + 543);
};
$f['birth_date'] = $thaiDate($f['birth_date']);
$f['apply_date'] = $thaiDate($f['apply_date']);

$rows = [];
foreach (JotformFields::labels() as $key => $meta) {
    $value = $clip($f[$key] ?? '', 300);
    if ($value === '') continue;
    $rows[] = ['section' => $meta['section'], 'label' => $meta['label'], 'value' => $value];
}

$sid = (string) ($_POST['submission_id'] ?? $_POST['submissionID'] ?? '');
$summary = [
    'v'            => 1,
    'name'         => $name,
    'course'       => $course,
    'type'         => $type,
    'submission'   => preg_match('/^\d{5,25}$/', $sid) ? $sid : '',
    'submitted_at' => (function () use ($thaiDate) {
        $now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
        return $thaiDate($now->format('Y-m-d')) . ' ' . $now->format('H:i');
    })(),
    'rows'         => $rows,
];

$params = array_filter(['name' => $name, 'course' => $course, 'type' => $type], 'strlen');
$target = 'thankyou.html' . ($params ? '?' . http_build_query($params) : '');

$json = json_encode($summary, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$jsTarget = json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>กำลังไปหน้าขอบคุณ…</title>
</head>
<body style="font-family:sans-serif;padding:24px;color:#475569">
  <p>กำลังไปหน้าขอบคุณ… <a href="<?= $h($target) ?>">คลิกที่นี่หากหน้าไม่เปลี่ยน</a></p>
  <script>
    try { sessionStorage.setItem("jf_summary", JSON.stringify(<?= $json ?>)); } catch (e) {}
    location.replace(<?= $jsTarget ?>);
  </script>
</body>
</html>
