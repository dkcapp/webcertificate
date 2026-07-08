<?php
// database/config.php
// อ่านค่าการเชื่อมต่อ database จาก Environment Variables
// (ตั้งค่าจริงไว้ที่ Render Dashboard -> Environment ไม่ใช่ในไฟล์นี้)
// ตอนรันบนเครื่อง local ให้ตั้งค่า environment variable เองก่อนรัน หรือดู config.example.php

define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (!DB_HOST || !DB_NAME || !DB_USER) {
            throw new Exception('Database environment variables ยังไม่ได้ตั้งค่า (DB_HOST, DB_NAME, DB_USER, DB_PASS)');
        }
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}