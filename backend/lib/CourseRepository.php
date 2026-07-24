<?php
// backend/lib/CourseRepository.php
// รวม SQL ทั้งหมดที่เกี่ยวกับตาราง courses และ active_courses ไว้ที่เดียว
// ไฟล์ endpoint (admin_api.php, active_courses.php, jotform_webhook.php) เรียกใช้ผ่าน class นี้
// โดยไม่ต้องรู้รายละเอียดว่าข้างในเขียน SQL แบบไหน

class CourseRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** ดึงคอร์สทั้งหมดพร้อมนับจำนวนผู้เรียน (หน้า admin "คอร์สทั้งหมด") */
    public function listWithStudentCount(): array
    {
        return $this->pdo->query("
            SELECT c.*, COUNT(s.id) as student_count
            FROM courses c
            LEFT JOIN students s ON s.course_id = c.id
            GROUP BY c.id
            ORDER BY c.year_be DESC, c.short_name ASC
        ")->fetchAll();
    }

    /** ดึงคอร์สทั้งหมดพร้อม flag ว่าเปิดรับสมัครอยู่ไหม (หน้า admin "เปิดรับสมัคร") */
    public function listWithActiveFlag(): array
    {
        return $this->pdo->query("
            SELECT c.id, c.short_name, c.year_be,
                   CASE WHEN ac.course_id IS NOT NULL THEN true ELSE false END as is_active
            FROM courses c
            LEFT JOIN active_courses ac ON ac.course_id = c.id
            ORDER BY c.year_be DESC, c.short_name ASC
        ")->fetchAll();
    }

    /** ดึงชื่อคอร์สที่เปิดรับสมัครอยู่ทั้งหมด (ใช้ตอน sync ไป JotForm) */
    public function listActiveShortNames(): array
    {
        $rows = $this->pdo->query("
            SELECT c.short_name
            FROM active_courses ac
            INNER JOIN courses c ON c.id = ac.course_id
            ORDER BY c.short_name ASC
        ")->fetchAll();
        return array_column($rows, 'short_name');
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM courses WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** ค้นหาคอร์สจากชื่อย่อแบบตรงเป๊ะ (ใช้ตอน jotform webhook หา course_id จากชื่อคอร์สที่เลือกใน dropdown) */
    public function findByShortName(string $shortName): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM courses WHERE short_name = :name LIMIT 1");
        $stmt->execute([':name' => $shortName]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** ค้นหาคอร์สจากชื่อ โดยเทียบกับทั้ง short_name และ long_key แบบตรงเป๊ะ
     *  ใช้ตอนนำเข้าจาก Airtable เพื่อเช็คว่าคอร์สนี้มีอยู่แล้วในระบบหรือยัง (คืนค่าทั้งแถว ไม่ใช่แค่ id) */
    public function findByShortNameOrLongKey(string $name): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM courses WHERE short_name = :name OR long_key = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function add(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO courses (long_key, short_name, training_date, year_be, verify_url)
            VALUES (:lk, :sn, :td, :yb, :vu)
            RETURNING id
        ");
        $stmt->execute([
            ':lk' => $data['long_key'],
            ':sn' => $data['short_name'],
            ':td' => $data['training_date'] ?: null,
            ':yb' => $data['year_be'] ?: null,
            ':vu' => $data['verify_url'] ?: null,
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $this->pdo->prepare("
            UPDATE courses SET short_name=:sn, long_key=:lk, training_date=:td, year_be=:yb, verify_url=:vu
            WHERE id=:id
        ")->execute([
            ':sn' => $data['short_name'],
            ':lk' => $data['long_key'] ?: null,
            ':td' => $data['training_date'] ?: null,
            ':yb' => $data['year_be'] ?: null,
            ':vu' => $data['verify_url'] ?: null,
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare("DELETE FROM courses WHERE id=:id")->execute([':id' => $id]);
    }

    /** เปิดรับสมัครคอร์ส (เพิ่มแถวในตาราง active_courses) */
    public function activate(int $courseId): void
    {
        $this->pdo->prepare("INSERT INTO active_courses (course_id) VALUES (:id) ON CONFLICT (course_id) DO NOTHING")
            ->execute([':id' => $courseId]);
    }

    /** ปิดรับสมัครคอร์ส (ลบแถวออกจากตาราง active_courses) */
    public function deactivate(int $courseId): void
    {
        $this->pdo->prepare("DELETE FROM active_courses WHERE course_id = :id")->execute([':id' => $courseId]);
    }
}