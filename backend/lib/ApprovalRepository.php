<?php
// backend/lib/ApprovalRepository.php
// รวม SQL ทั้งหมดของตาราง approval_requests (คำขออนุมัติจากหัวหน้ากอง/CAS) ไว้ที่เดียว

class ApprovalRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** APPROVE* → approved, REJECT* → rejected, อื่น ๆ → pending */
    public static function statusKind(?string $status): string
    {
        $u = strtoupper((string) $status);
        if (strpos($u, 'APPROVE') === 0) return 'approved';
        if (strpos($u, 'REJECT') === 0) return 'rejected';
        return 'pending';
    }

    public function find(string $ref): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM approval_requests WHERE external_ref_id = :ref');
        $st->execute([':ref' => $ref]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * บันทึกคำขอใหม่ (ไม่ซ้ำถ้า ref มีแล้ว) คืน true ถ้าเพิ่มสำเร็จ
     * $d keys: ref, requester_ad, requester_name, applicant_name, course, member_type,
     *          approve_type, subject, message, request_key, approver_ad, approver_name, cas_data
     */
    public function insert(array $d): bool
    {
        $st = $this->pdo->prepare(
            "INSERT INTO approval_requests
               (external_ref_id, requester_ad, requester_name, applicant_name, course, member_type, student_id,
                approve_type, subject, message, request_key, approver_ad, approver_name, status, cas_data)
             VALUES
               (:ref, :req_ad, :req_name, :app_name, :course, :mtype, :student_id,
                :atype, :subject, :message, :rkey, :appr_ad, :appr_name, 'PENDING', :cas)
             ON CONFLICT (external_ref_id) DO NOTHING"
        );
        $st->execute([
            ':ref'        => $d['ref'],
            ':req_ad'     => $d['requester_ad'],
            ':req_name'   => $d['requester_name'] ?? null,
            ':app_name'   => $d['applicant_name'] ?? null,
            ':course'     => $d['course'] ?? null,
            ':mtype'      => $d['member_type'] ?? null,
            ':student_id' => $this->findStudentId($d['applicant_name'] ?? '', $d['course'] ?? ''),
            ':atype'      => (int) ($d['approve_type'] ?? 1),
            ':subject'    => $d['subject'],
            ':message'    => $d['message'],
            ':rkey'       => $d['request_key'] ?? null,
            ':appr_ad'    => $d['approver_ad'] ?? null,
            ':appr_name'  => $d['approver_name'] ?? null,
            ':cas'        => $d['cas_data'] ?? null,
        ]);
        return $st->rowCount() === 1;
    }

    /** อัปเดตสถานะจากผลที่ CAS ยืนยัน (และลองผูกผู้เรียนอีกครั้งถ้ายังไม่ได้ผูก) */
    public function updateStatus(string $ref, string $status, ?string $step, ?string $approverAd): void
    {
        $st = $this->pdo->prepare(
            "UPDATE approval_requests
                SET status = :status,
                    approve_step = :step,
                    approver_ad = COALESCE(:appr_ad, approver_ad),
                    updated_at = (now() AT TIME ZONE 'UTC')
              WHERE external_ref_id = :ref"
        );
        $st->execute([
            ':status'  => strtoupper($status),
            ':step'    => $step,
            ':appr_ad' => $approverAd,
            ':ref'     => $ref,
        ]);

        $row = $this->find($ref);
        if ($row && $row['student_id'] === null) {
            $sid = $this->findStudentId((string) $row['applicant_name'], (string) $row['course']);
            if ($sid !== null) {
                $up = $this->pdo->prepare('UPDATE approval_requests SET student_id = :sid WHERE external_ref_id = :ref');
                $up->execute([':sid' => $sid, ':ref' => $ref]);
            }
        }
    }

    /**
     * หา students.id ที่ตรงกับ "ชื่อ นามสกุล" + คอร์ส (ชื่อสั้นหรือชื่อยาว) เอาแถวล่าสุด
     * คืน null ถ้าไม่พบ หรือหาไม่ชัดเจน (เป็นการเดา ไม่ใช่การรับประกัน)
     */
    public function findStudentId(string $fullName, string $course): ?int
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName));
        $course   = trim($course);
        if ($fullName === '' || $course === '') return null;
        $st = $this->pdo->prepare(
            "SELECT s.id
               FROM students s
               JOIN courses c ON c.id = s.course_id
              WHERE TRIM(REGEXP_REPLACE(COALESCE(s.first_name,'') || ' ' || COALESCE(s.last_name,''), '\s+', ' ', 'g')) = :name
                AND (c.short_name = :course OR c.long_key = :course)
              ORDER BY s.id DESC
              LIMIT 1"
        );
        $st->execute([':name' => $fullName, ':course' => $course]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    // created_at เก็บเป็น UTC → แปลงเป็นเวลาไทยก่อนกรองวันที่/ปี
    private const LOCAL_TS = "(created_at + INTERVAL '7 hours')";

    /**
     * สร้าง WHERE จากตัวกรองของแท็บแอดมิน
     * $f keys: status (approved|rejected|pending|''), q, course, year_be (พ.ศ.), date_from, date_to (YYYY-MM-DD เวลาไทย)
     * $withStatus = false ใช้ตอนนับสรุป (การ์ดสถานะต้องนับตามตัวกรองอื่น แต่ไม่ถูกกรองด้วยสถานะเอง)
     */
    private function buildWhere(array $f, bool $withStatus = true): array
    {
        $where  = [];
        $params = [];

        $status = $withStatus ? (string) ($f['status'] ?? '') : '';
        if ($status === 'approved') {
            $where[] = "UPPER(status) LIKE 'APPROVE%'";
        } elseif ($status === 'rejected') {
            $where[] = "UPPER(status) LIKE 'REJECT%'";
        } elseif ($status === 'pending') {
            $where[] = "UPPER(status) NOT LIKE 'APPROVE%' AND UPPER(status) NOT LIKE 'REJECT%'";
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(external_ref_id ILIKE :q OR requester_ad ILIKE :q OR requester_name ILIKE :q
                         OR applicant_name ILIKE :q OR course ILIKE :q OR subject ILIKE :q
                         OR approver_name ILIKE :q OR approver_ad ILIKE :q)";
            $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
        }

        $course = trim((string) ($f['course'] ?? ''));
        if ($course !== '') {
            $where[] = 'course = :course';
            $params[':course'] = $course;
        }

        $year = (string) ($f['year_be'] ?? '');
        if (preg_match('/^\d{4}$/', $year)) {
            $where[] = 'EXTRACT(YEAR FROM ' . self::LOCAL_TS . ') = :year_ce';
            $params[':year_ce'] = (int) $year - 543;
        }

        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $d = (string) ($f[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
                $where[] = 'CAST(' . self::LOCAL_TS . " AS DATE) $op CAST(:$key AS DATE)";
                $params[":$key"] = $d;
            }
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * รายการ + สรุปสำหรับแท็บแอดมิน
     * summary นับตามตัวกรองปัจจุบัน (ยกเว้นสถานะ) และ options คือค่าที่มีให้เลือกในตัวกรองคอร์ส/ปี
     */
    public function listPage(int $page, int $perPage, array $filters): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        [$whereSql, $params] = $this->buildWhere($filters);

        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM approval_requests $whereSql");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $st = $this->pdo->prepare(
            "SELECT external_ref_id, requester_ad, requester_name, applicant_name, course, member_type,
                    approve_type, subject, approver_ad, approver_name, status, approve_step,
                    student_id,
                    to_char(created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at,
                    to_char(updated_at, 'YYYY-MM-DD HH24:MI:SS') AS updated_at
               FROM approval_requests $whereSql
              ORDER BY approval_requests.created_at DESC
              LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();
        $items = $st->fetchAll();

        [$sumWhere, $sumParams] = $this->buildWhere($filters, false);
        $sum = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN UPPER(status) LIKE 'APPROVE%' THEN 1 ELSE 0 END), 0) AS approved,
                    COALESCE(SUM(CASE WHEN UPPER(status) LIKE 'REJECT%'  THEN 1 ELSE 0 END), 0) AS rejected
               FROM approval_requests $sumWhere"
        );
        $sum->execute($sumParams);
        $sum = $sum->fetch();
        $t = (int) $sum['total'];
        $a = (int) $sum['approved'];
        $r = (int) $sum['rejected'];

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'summary'  => ['total' => $t, 'approved' => $a, 'rejected' => $r, 'pending' => $t - $a - $r],
            'options'  => $this->filterOptions(),
        ];
    }

    /** ตัวเลือกของ dropdown: คอร์สที่มีคำขอ และปี พ.ศ. ที่มีคำขอ (ใหม่ → เก่า) */
    public function filterOptions(): array
    {
        $courses = $this->pdo->query(
            "SELECT DISTINCT course FROM approval_requests WHERE course IS NOT NULL AND course <> '' ORDER BY course"
        )->fetchAll(PDO::FETCH_COLUMN);
        $years = $this->pdo->query(
            'SELECT DISTINCT CAST(EXTRACT(YEAR FROM ' . self::LOCAL_TS . ') AS INT) + 543 AS y
               FROM approval_requests ORDER BY y DESC'
        )->fetchAll(PDO::FETCH_COLUMN);
        return ['courses' => $courses, 'years' => array_map('intval', $years)];
    }

    /** ทุกแถวตามตัวกรอง (ไม่แบ่งหน้า) สำหรับส่งออก CSV */
    public function listAll(array $filters, int $max = 10000): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $st = $this->pdo->prepare(
            "SELECT external_ref_id, applicant_name, course, member_type, requester_ad, requester_name,
                    approver_ad, approver_name, status, approve_step, request_key,
                    to_char(created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at,
                    to_char(updated_at, 'YYYY-MM-DD HH24:MI:SS') AS updated_at
               FROM approval_requests $whereSql
              ORDER BY approval_requests.created_at DESC
              LIMIT :lim"
        );
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $max, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /**
     * ลบคำขอตามรายการรหัส คืนจำนวนที่ลบจริง
     * (ลบเฉพาะใน Neon — ไม่ได้ยกเลิกคำขอที่ CAS ถ้าหัวหน้ากองกดผลภายหลัง cas_callback จะไม่พบแถวและไม่ทำอะไร)
     */
    public function deleteMany(array $refs): int
    {
        $refs = array_values(array_unique(array_filter(array_map('strval', $refs), fn($r) => $r !== '')));
        if (!$refs) return 0;
        $ph = implode(',', array_fill(0, count($refs), '?'));
        $st = $this->pdo->prepare("DELETE FROM approval_requests WHERE external_ref_id IN ($ph)");
        $st->execute($refs);
        return $st->rowCount();
    }
}
