<?php
// backend/lib/ApprovalMessage.php
// สร้างข้อความที่ส่งถึงหัวหน้ากอง (ผ่าน CAS) ทั้งแบบข้อความธรรมดาและแบบ HTML

class ApprovalMessage
{
    public const TITLE = 'ระบบสมัครอบรมคอมพิวเตอร์';

    /** @return array{plain:string, html:string} */
    public static function build(string $name, string $course): array
    {
        $title = self::TITLE;
        $plain = mb_substr("$title\nขออนุมัติให้ $name เข้าอบรมหลักสูตร $course", 0, 2000, 'UTF-8');

        // escape ทุกค่า: ชื่อ/คอร์สมาจากสิ่งที่ผู้ใช้กรอก กันแทรกโค้ดเข้าอีเมลหัวหน้ากอง
        $e   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lab = 'padding:4px 14px 4px 0;color:#64748b;vertical-align:top;white-space:nowrap';
        $val = 'padding:4px 0;color:#0f172a';
        $html = '<div style="font-family:Tahoma,Arial,sans-serif;font-size:15px;line-height:1.5;color:#0f172a">'
            . '<div style="font-size:18px;font-weight:bold;color:#1d4ed8;padding-bottom:6px;border-bottom:2px solid #1d4ed8;margin-bottom:10px">' . $e($title) . '</div>'
            . '<div style="margin-bottom:6px">ขออนุมัติให้ผู้สมัครเข้าอบรม</div>'
            . '<table style="border-collapse:collapse">'
            . '<tr><td style="' . $lab . '">ผู้สมัคร</td><td style="' . $val . ';font-weight:bold">' . $e($name) . '</td></tr>'
            . '<tr><td style="' . $lab . '">หลักสูตร</td><td style="' . $val . '">' . $e($course) . '</td></tr>'
            . '</table></div>';

        return ['plain' => $plain, 'html' => $html];
    }
}
