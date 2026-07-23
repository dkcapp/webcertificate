<?php
// backend/lib/StudentRepository.php
// รวม SQL ทั้งหมดที่เกี่ยวกับตาราง students ไว้ที่เดียว
// ไฟล์ endpoint (admin_api.php, api.php, jotform_webhook.php) เรียกใช้ผ่าน class นี้
// โดยไม่ต้องรู้รายละเอียดว่าข้างในเขียน SQL แบบไหน

class StudentRepository
{
    private PDO $pdo;

    // whitelist คอลัมน์ที่ยอม sort ได้ ป้องกัน SQL injection ผ่าน ORDER BY
    private const SORTABLE_COLUMNS = [
        'id'           => 's.id',
        'course_name'  => 'c.short_name',
        'first_name'   => 's.first_name',
        'last_name'    => 's.last_name',
        'member_type'  => 's.member_type',
        'apply_date'   => 's.apply_date',
        'age'          => 'CAST(s.age AS INTEGER)',
        'department'   => 's.department',
        'office'       => 's.office',
        'position'     => 's.position',
        'phone_mobile' => 's.phone_mobile',
        'email'        => 's.email',
    ];

    // ฟิลด์ที่แก้ไขได้ตอนเพิ่มผู้เรียนใหม่ (รวม first_name)
    private const INSERTABLE_FIELDS = [
        'first_name', 'last_name', 'member_type', 'email', 'phone_mobile', 'phone_internal',
        'birth_date', 'age', 'royal_title', 'education_level', 'faculty', 'major', 'institution',
        'department', 'office', 'position', 'head_status', 'attendance',
    ];

    // ฟิลด์ที่แก้ไขได้ตอนอัปเดตผู้เรียน (ไม่รวม royal_title ตามพฤติกรรมเดิม)
    private const UPDATABLE_FIELDS = [
        'first_name', 'last_name', 'member_type', 'email', 'phone_mobile', 'phone_internal',
        'birth_date', 'age', 'education_level', 'faculty', 'major', 'institution',
        'department', 'office', 'position', 'head_status', 'attendance',
    ];

    // ฟิลด์ทั้งหมดที่รับมาจาก Airtable ตอนนำเข้า (ครบกว่า webhook เพราะ Airtable มีข้อมูลละเอียดกว่า)
    private const AIRTABLE_FIELDS = [
        'first_name', 'last_name', 'member_type', 'apply_date', 'birth_date', 'age',
        'royal_title', 'education_level', 'faculty', 'major', 'institution',
        'department', 'office', 'position', 'phone_internal', 'phone_mobile', 'email',
        'head_status', 'attendance', 'last_modified_time',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function isSortable(string $key): bool
    {
        return isset(self::SORTABLE_COLUMNS[$key]);
    }

    /** แบ่งหน้ารายชื่อผู้เรียน รองรับค้นหา/กรองปี/กรองคอร์ส/เรียงลำดับ (หน้า admin "รายชื่อผู้เรียน") */
    public function paginate(array $filters, string $sortKey, string $sortDir, int $page, int $limit): array
    {
        $sortCol = self::SORTABLE_COLUMNS[$sortKey] ?? self::SORTABLE_COLUMNS['first_name'];
        $sortDir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $offset  = ($page - 1) * $limit;

        $conditions = [];
        $params     = [];
        if (!empty($filters['course_id'])) {
            $conditions[] = 's.course_id = :cid';
            $params[':cid'] = $filters['course_id'];
        }
        if (!empty($filters['year_be'])) {
            $conditions[] = 'c.year_be = :yb';
            $params[':yb'] = $filters['year_be'];
        }
        if (!empty($filters['q'])) {
            $conditions[] = '(s.first_name LIKE :q1 OR s.last_name LIKE :q2)';
            $params[':q1'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
        }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $totalStmt = $this->pdo->prepare("SELECT COUNT(*) FROM students s INNER JOIN courses c ON c.id = s.course_id $where");
        $totalStmt->execute($params);
        $total = (int)$totalStmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT s.id, s.course_id, s.airtable_id, s.first_name, s.last_name,
                   s.member_type, s.apply_date, s.age, s.department, s.office,
                   s.position, s.email, s.phone_mobile, s.attendance,
                   c.short_name as course_name, c.year_be
            FROM students s
            INNER JOIN courses c ON c.id = s.course_id
            $where
            ORDER BY $sortCol $sortDir, s.id ASC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** เพิ่มผู้เรียนใหม่ (ใช้ในหน้า admin เพิ่มรายชื่อด้วยมือ) */
    public function add(array $data): int
    {
        $fields = self::INSERTABLE_FIELDS;
        $cols   = 'course_id,' . implode(',', $fields);
        $phs    = ':course_id,' . implode(',', array_map(fn($f) => ":$f", $fields));

        $params = [':course_id' => $data['course_id']];
        foreach ($fields as $f) {
            $params[":$f"] = trim($data[$f] ?? '') ?: null;
        }
        $params[':first_name'] = $data['first_name']; // ชื่อเป็นฟิลด์บังคับ ผ่านการเช็คมาแล้วจากผู้เรียก

        $stmt = $this->pdo->prepare("INSERT INTO students ($cols) VALUES ($phs) RETURNING id");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $fields = self::UPDATABLE_FIELDS;
        $sets   = implode(',', array_map(fn($f) => "$f=:$f", $fields));
        $params = [':id' => $id];
        foreach ($fields as $f) {
            $params[":$f"] = $data[$f] ?? null;
        }
        $this->pdo->prepare("UPDATE students SET $sets WHERE id=:id")->execute($params);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare("DELETE FROM students WHERE id=:id")->execute([':id' => $id]);
    }

    /** บันทึกผู้สมัครใหม่จาก JotForm Webhook */
    public function insertFromWebhook(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO students
                (course_id, first_name, last_name, member_type, apply_date, department, office, position, phone_mobile, email)
            VALUES
                (:course_id, :first_name, :last_name, :member_type, :apply_date, :department, :office, :position, :phone_mobile, :email)
            RETURNING id
        ");
        $stmt->execute([
            ':course_id'    => $data['course_id'],
            ':first_name'   => $data['first_name'],
            ':last_name'    => $data['last_name'] ?: null,
            ':member_type'  => $data['member_type'] ?: null,
            ':apply_date'   => $data['apply_date'],
            ':department'   => $data['department'] ?: null,
            ':office'       => $data['office'] ?: null,
            ':position'     => $data['position'] ?: null,
            ':phone_mobile' => $data['phone_mobile'] ?: null,
            ':email'        => $data['email'] ?: null,
        ]);
        return (int)$stmt->fetchColumn();
    }

    /** ดึงรายชื่อผู้เรียนทั้งหมดพร้อมข้อมูลคอร์ส (หน้า public ดาวน์โหลดใบประกาศ) */
    public function listAllWithCourse(): array
    {
        return $this->pdo->query("
            SELECT
                s.id AS student_id, s.airtable_id,
                s.first_name, s.last_name, s.member_type, s.apply_date,
                s.birth_date, s.age, s.royal_title, s.education_level,
                s.faculty, s.major, s.institution, s.department, s.office,
                s.position, s.phone_internal, s.phone_mobile, s.email,
                s.head_status, s.attendance, s.last_modified_time,
                c.short_name AS course_name, c.training_date, c.year_be, c.verify_url
            FROM students s
            INNER JOIN courses c ON c.id = s.course_id
            ORDER BY c.year_be DESC, c.short_name ASC, s.first_name ASC
        ")->fetchAll();
    }

    /**
     * ดึง airtable_id ทั้งหมดที่มีอยู่แล้วใน Neon (เฉพาะแถวที่เคยผูก airtable_id ไว้)
     * คืนค่าเป็น array แบบ ['recXXXX' => true, 'recYYYY' => true, ...] เพื่อเช็คแบบ O(1)
     * ใช้เทียบกับข้อมูลจาก Airtable ว่าอันไหน "เคยนำเข้าแล้ว"
     */
    public function getExistingAirtableIds(): array
    {
        $rows = $this->pdo->query("
            SELECT airtable_id FROM students WHERE airtable_id IS NOT NULL
        ")->fetchAll();

        $ids = [];
        foreach ($rows as $row) {
            $ids[$row['airtable_id']] = true;
        }
        return $ids;
    }

    /**
     * นำเข้าผู้เรียน 1 คนจาก Airtable — มีฟิลด์ครบกว่า insertFromWebhook()
     * เพราะ Airtable เก็บข้อมูลละเอียดกว่า (การศึกษา, คณะ, สาขา, สถาบัน ฯลฯ)
     * บันทึก airtable_id ไว้ด้วยเพื่อกันนำเข้าซ้ำในอนาคต
     */
    public function insertFromAirtable(array $data): int
    {
        $fields = self::AIRTABLE_FIELDS;
        $cols   = 'course_id,airtable_id,' . implode(',', $fields);
        $phs    = ':course_id,:airtable_id,' . implode(',', array_map(fn($f) => ":$f", $fields));

        $params = [
            ':course_id'   => $data['course_id'],
            ':airtable_id' => $data['airtable_id'],
        ];
        foreach ($fields as $f) {
            $params[":$f"] = $data[$f] !== '' && $data[$f] !== null ? $data[$f] : null;
        }

        $stmt = $this->pdo->prepare("INSERT INTO students ($cols) VALUES ($phs) RETURNING id");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}