<?php
// backend/lib/JotformFields.php
// mapping ฟิลด์ของฟอร์มบริษัท (231301336713444) ไว้ที่เดียว ใช้ร่วมกันระหว่าง
//   - jotform_webhook.php (ข้อมูลใน rawRequest)
//   - thankyou.php        (ข้อมูลจาก "Send Post Data" ตอน redirect มาหน้าขอบคุณ)
// จับคู่ด้วยเลข field id (key รูปแบบ q<id>_<unique name>) และสำรองด้วย unique name
// ถ้าฟอร์มเปลี่ยน field id ให้แก้ตาราง FIELDS ที่นี่ที่เดียว (ดู README §8.1ข)

class JotformFields
{
    // key => [field id, unique name, ชนิด (text|phone|date), หัวข้อภาษาไทย, หมวดในใบสรุป]
    public const FIELDS = [
        'first_name'      => [110, 'typeA110',    'text',  'ชื่อ',                         'ข้อมูลผู้สมัคร'],
        'last_name'       => [111, 'input111',    'text',  'นามสกุล (ฉายา)',               'ข้อมูลผู้สมัคร'],
        'member_type'     => [17,  'typeA',       'text',  'ประเภทสมาชิกองค์กร',           'ข้อมูลผู้สมัคร'],
        'age'             => [60,  'input60',     'text',  'อายุ / พรรษาที่',              'ข้อมูลผู้สมัคร'],
        'birth_date'      => [98,  'input98',     'date',  'วัน/เดือน/ปี เกิด',            'ข้อมูลผู้สมัคร'],
        'education_level' => [61,  'input61',     'text',  'ระดับการศึกษาสูงสุด',          'การศึกษา'],
        'faculty'         => [90,  'input90',     'text',  'คณะ',                          'การศึกษา'],
        'major'           => [79,  'input79',     'text',  'สาขา',                         'การศึกษา'],
        'institution'     => [75,  'input75',     'text',  'สถาบัน/โรงเรียน',              'การศึกษา'],
        'department'      => [31,  'typeA31',     'text',  'สังกัดหน่วยงาน/กอง/ศูนย์',      'หน่วยงาน'],
        'office'          => [32,  'input59',     'text',  'สำนัก',                        'หน่วยงาน'],
        'position'        => [33,  'typeA33',     'text',  'ตำแหน่ง',                      'หน่วยงาน'],
        'phone_internal'  => [38,  'input38',     'phone', 'เบอร์ภายใน',                   'ช่องทางติดต่อ'],
        'phone_mobile'    => [39,  'phoneNumber', 'phone', 'เบอร์มือถือ',                  'ช่องทางติดต่อ'],
        'email'           => [82,  'email82',     'text',  'Email',                        'ช่องทางติดต่อ'],
        'course'          => [93,  'input93',     'text',  'โปรแกรมที่ต้องการสมัครเรียน',  'การสมัคร'],
        'fee'             => [104, 'input104',    'text',  'ค่าสมัครอบรม',                 'การสมัคร'],
        'apply_date'      => [9,   'date',        'date',  'วันที่ส่งแบบฟอร์ม',            'การสมัคร'],
    ];

    /**
     * แกะค่าทุกฟิลด์จากข้อมูลดิบของ JotForm คืน [key => string] (ว่าง = '')
     * วันที่คืนรูปแบบ YYYY-MM-DD (เหมือนที่ webhook เคยบันทึก)
     */
    public static function extract(array $raw): array
    {
        $byId   = [];
        $byName = [];
        foreach ($raw as $k => $v) {
            $k = (string) $k;
            if (preg_match('/^q(\d+)_(.*)$/', $k, $m)) {
                $byId[(int) $m[1]] = $v;
                $byName[strtolower($m[2])] = $v;
            } else {
                $byName[strtolower($k)] = $v;
            }
        }

        $out = [];
        foreach (self::FIELDS as $key => [$id, $name, $kind]) {
            $v = $byId[$id] ?? $byName[strtolower($name)] ?? '';
            $out[$key] = self::normalize($v, $kind);
        }
        return $out;
    }

    /** หัวข้อและหมวดของแต่ละฟิลด์ (ใช้แสดงในใบสรุป) */
    public static function labels(): array
    {
        $out = [];
        foreach (self::FIELDS as $key => [, , , $label, $section]) {
            $out[$key] = ['label' => $label, 'section' => $section];
        }
        return $out;
    }

    private static function normalize($v, string $kind): string
    {
        if ($kind === 'phone' && is_array($v)) {
            $v = $v['full'] ?? trim(($v['area'] ?? '') . ' ' . ($v['phone'] ?? ''));
        } elseif ($kind === 'date' && is_array($v)) {
            if (empty($v['year']) || empty($v['month']) || empty($v['day'])) return '';
            $v = $v['year'] . '-' . $v['month'] . '-' . $v['day'];
        }
        if (is_array($v)) {
            $v = implode(' ', array_filter(array_map(fn($x) => is_scalar($x) ? trim((string) $x) : '', $v), 'strlen'));
        }
        return trim((string) $v);
    }
}
