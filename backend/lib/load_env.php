<?php
// backend/lib/load_env.php
// โหลด .env อัตโนมัติถ้ารันบนเครื่อง local (บน Render ไม่มีไฟล์ .env ก็ไม่เป็นไร)
// require_once ไฟล์นี้ไว้บนสุดของทุก endpoint ที่ต้องการ environment variables

$_envFile = __DIR__ . '/../../.env';
if (file_exists($_envFile)) {
    $lines = file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}
unset($_envFile, $lines, $line, $key, $value);