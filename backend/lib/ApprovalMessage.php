<?php
// backend/lib/ApprovalMessage.php
// สร้างข้อความที่ส่งถึงหัวหน้ากอง (ผ่าน CAS) ทั้งแบบข้อความธรรมดา, HTML (อีเมล), และ LINE Flex

class ApprovalMessage
{
    public const TITLE = 'ระบบสมัครอบรมคอมพิวเตอร์';

    /**
     * @return array{plain:string, html:string, flex:array}
     *   plain — plain-text fallback ทุก channel และเก็บลง Neon
     *   html  — HTML สำหรับอีเมล (MsgHTMLForHead)
     *   flex  — LINE Flex body content (MsgFlexForHead) — body content only (box), ไม่ใช่ bubble
     */
    public static function build(string $name, string $course): array
    {
        $title = self::TITLE;
        $plain = mb_substr("$title\nขออนุมัติให้ $name เข้าอบรมหลักสูตร $course", 0, 2000, 'UTF-8');

        // --- HTML (อีเมล) ---
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

        // --- LINE Flex (MsgFlexForHead) ---
        // ส่งเป็น body content only (box) — CAS จะ wrap เป็น bubble เอง
        // ไม่ใส่ bubble wrapper และไม่ใส่ key "body" ด้านนอก
        $flex = [
            'type'     => 'box',
            'layout'   => 'vertical',
            'spacing'  => 'md',
            'contents' => [
                // หัวเรื่อง
                [
                    'type'   => 'text',
                    'text'   => $title,
                    'weight' => 'bold',
                    'size'   => 'md',
                    'color'  => '#1d4ed8',
                    'wrap'   => true,
                ],
                // เส้นคั่น
                ['type' => 'separator', 'margin' => 'sm'],
                // ข้อความนำ
                [
                    'type'   => 'text',
                    'text'   => 'ขออนุมัติให้ผู้สมัครเข้าอบรม',
                    'size'   => 'sm',
                    'color'  => '#334155',
                    'margin' => 'sm',
                    'wrap'   => true,
                ],
                // ผู้สมัคร
                [
                    'type'     => 'box',
                    'layout'   => 'horizontal',
                    'margin'   => 'sm',
                    'contents' => [
                        [
                            'type'  => 'text',
                            'text'  => 'ผู้สมัคร',
                            'size'  => 'sm',
                            'color' => '#64748b',
                            'flex'  => 2,
                        ],
                        [
                            'type'   => 'text',
                            'text'   => mb_substr($name, 0, 100, 'UTF-8'),
                            'size'   => 'sm',
                            'color'  => '#0f172a',
                            'weight' => 'bold',
                            'flex'   => 5,
                            'wrap'   => true,
                        ],
                    ],
                ],
                // หลักสูตร
                [
                    'type'     => 'box',
                    'layout'   => 'horizontal',
                    'margin'   => 'xs',
                    'contents' => [
                        [
                            'type'  => 'text',
                            'text'  => 'หลักสูตร',
                            'size'  => 'sm',
                            'color' => '#64748b',
                            'flex'  => 2,
                        ],
                        [
                            'type'  => 'text',
                            'text'  => mb_substr($course, 0, 200, 'UTF-8'),
                            'size'  => 'sm',
                            'color' => '#0f172a',
                            'flex'  => 5,
                            'wrap'  => true,
                        ],
                    ],
                ],
            ],
        ];

        return ['plain' => $plain, 'html' => $html, 'flex' => $flex];
    }
}