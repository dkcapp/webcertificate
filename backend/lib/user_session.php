<?php
// backend/lib/user_session.php
// เริ่ม session ของ "ผู้ใช้ที่ล็อกอินผ่าน DKC OAuth" (แยกจาก session ของแอดมินโดยสิ้นเชิง — คนละชื่อคุกกี้)
// ใช้: require_once แล้วเรียก user_session_start() ก่อนอ่าน/เขียน $_SESSION

function dkc_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function user_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('DKCUSER');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => dkc_is_https(),
        'httponly' => true,
        'samesite' => 'Lax', // ต้องเป็น Lax เพื่อให้คุกกี้ติดมาตอน DKC redirect กลับ (top-level GET)
    ]);
    session_start();
}

/** URL หลักของเว็บนี้ (ไม่มี / ท้าย) ใช้ APP_URL ถ้าตั้งไว้ ไม่งั้นเดาจาก request */
function dkc_app_url(): string
{
    $env = rtrim(getenv('APP_URL') ?: '', '/');
    if ($env !== '') return $env;
    $scheme = dkc_is_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** ไปหน้า Thank you พร้อมพารามิเตอร์ (ใช้ path ของเว็บนี้เสมอ ไม่รับ URL จากผู้ใช้) */
function dkc_redirect_thankyou(array $params = []): void
{
    // แนบชื่อ/คอร์ส/ประเภท ของผู้สมัครที่เก็บไว้ใน session (ให้หน้า Thank you ทักทายและแสดงหลักสูตรได้ และกด "ลองส่งใหม่" ได้)
    if (!empty($_SESSION['applicant']) && is_array($_SESSION['applicant'])) {
        foreach (['name', 'course', 'type'] as $k) {
            if (!isset($params[$k]) && !empty($_SESSION['applicant'][$k])) $params[$k] = $_SESSION['applicant'][$k];
        }
    }
    $q = $params ? '?' . http_build_query($params) : '';
    header('Location: ' . dkc_app_url() . '/thankyou.html' . $q, true, 302);
    exit;
}

function dkc_redirect(string $path): void
{
    header('Location: ' . dkc_app_url() . $path, true, 302);
    exit;
}

/** ตัดช่องว่างซ้ำและจำกัดความยาว (นับเป็นตัวอักษร ไม่ใช่ไบต์) */
function dkc_clip($v, int $n): string
{
    $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
    return mb_substr($v, 0, $n, 'UTF-8');
}
