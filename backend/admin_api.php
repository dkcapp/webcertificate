<?php
// โหลด .env อัตโนมัติถ้ารันบนเครื่อง local
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

require_once __DIR__ . '/lib/require_admin.php';
require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/lib/CourseRepository.php';
require_once __DIR__ . '/lib/StudentRepository.php';
require_once __DIR__ . '/lib/AirtableClient.php';
require_once __DIR__ . '/lib/ApprovalRepository.php';
require_once __DIR__ . '/lib/CasClient.php';

$pdo         = getDB();
$courseRepo  = new CourseRepository($pdo);
$studentRepo = new StudentRepository($pdo);

// กันไว้: ถ้า PHP ถูกตัดกลางคัน (เช่น timeout) ระหว่างเปิด transaction ค้างไว้
// ให้ rollback อัตโนมัติตอน script จบ ป้องกันไม่ให้ transaction ที่พังค้างไปกระทบ request ถัดไป
register_shutdown_function(function() use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});

// ผูก field ของ Airtable เข้ากับชื่อคอลัมน์ในตาราง students ของเรา
// (key ฝั่งซ้าย = ชื่อ field จริงใน Airtable, value ฝั่งขวา = ชื่อคอลัมน์ใน Neon)
const AIRTABLE_FIELD_MAP = [
    'ชื่อ'                 => 'first_name',
    'นามสกุล (ฉายา)'       => 'last_name',
    'ประเภทสมาชิก'         => 'member_type',
    'วันที่สมัคร'           => 'apply_date',
    'วัน/เดือน/ปี เกิด'    => 'birth_date',
    'อายุ'                 => 'age',
    'พรรษาที่'             => 'royal_title',
    'ระดับการศึกษาสูงสุด'   => 'education_level',
    'คณะ'                  => 'faculty',
    'สาขา'                 => 'major',
    'สถาบัน'               => 'institution',
    'หน่วยงาน กอง/ศูนย์'   => 'department',
    'สำนัก'                => 'office',
    'ตำแหน่ง'              => 'position',
    'เบอร์ภายใน'           => 'phone_internal',
    'เบอร์มือถือ'          => 'phone_mobile',
    'Email'                => 'email',
    'สถานะหัวหน้ากอง'      => 'head_status',
    'การเข้าอบรม'          => 'attendance',
    'Last modified time'   => 'last_modified_time',
];
const AIRTABLE_COURSE_FIELD = 'โปรแกรมที่สมัคร'; // field ที่ใช้จับคู่กับ courses.long_key

/** ตัวกรองของแท็บคำขออนุมัติจาก query string (ใช้ร่วมกันระหว่างรายการและส่งออก CSV) */
function approval_filters_from(array $src): array
{
    $f = [];
    foreach (['status', 'q', 'course', 'year_be', 'date_from', 'date_to'] as $k) {
        $f[$k] = trim((string) ($src[$k] ?? ''));
    }
    return $f;
}

/** รายการรหัสอ้างอิงจาก POST refs[] (หรือ JSON string) กรองเฉพาะรูปแบบที่ถูกต้อง */
function approval_refs_from_post(int $max): array
{
    $refs = $_POST['refs'] ?? [];
    if (!is_array($refs)) $refs = json_decode((string) $refs, true) ?: [];
    $refs = array_filter(array_map('strval', $refs), fn($r) => preg_match('/^[A-Za-z0-9_-]{1,100}$/', $r));
    return array_slice(array_values(array_unique($refs)), 0, $max);
}

/** กัน CSV/Formula injection เมื่อเปิดใน Excel (ค่าที่ขึ้นต้นด้วย = + - @) */
function csv_safe($v): string
{
    $v = (string) ($v ?? '');
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ผู้ดูอย่างเดียว (VIEWER_USER) เรียกได้เฉพาะ action ที่อ่านข้อมูล ที่เหลือต้องเป็นแอดมิน
const VIEWER_ACTIONS = ['list_courses', 'list_students', 'get_student', 'list_approval_requests', 'get_approval'];
if (!in_array($action, VIEWER_ACTIONS, true)) {
    require_full_admin();
}

try {
    if ($action === 'list_courses') {
        echo json_encode(['ok' => true, 'data' => $courseRepo->listWithStudentCount()]);
        exit;
    }

    if ($action === 'add_course') {
        $short_name = trim($_POST['short_name'] ?? '');
        $long_key   = trim($_POST['long_key'] ?? '');
        if (!$short_name || !$long_key) {
            echo json_encode(['ok' => false, 'error' => 'ชื่อย่อและชื่อยาวห้ามว่าง']);
            exit;
        }
        $id = $courseRepo->add([
            'short_name'    => $short_name,
            'long_key'      => $long_key,
            'training_date' => trim($_POST['training_date'] ?? ''),
            'year_be'       => trim($_POST['year_be'] ?? ''),
            'verify_url'    => trim($_POST['verify_url'] ?? ''),
        ]);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'update_course') {
        $id         = (int)($_POST['id'] ?? 0);
        $short_name = trim($_POST['short_name'] ?? '');
        if (!$id || !$short_name) {
            echo json_encode(['ok' => false, 'error' => 'ข้อมูลไม่ครบ']);
            exit;
        }
        $courseRepo->update($id, [
            'short_name'    => $short_name,
            'long_key'      => trim($_POST['long_key'] ?? ''),
            'training_date' => trim($_POST['training_date'] ?? ''),
            'year_be'       => trim($_POST['year_be'] ?? ''),
            'verify_url'    => trim($_POST['verify_url'] ?? ''),
        ]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete_course') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ไม่พบ id']);
            exit;
        }
        $courseRepo->delete($id);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'list_students') {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;

        $sortKey = $_GET['sort'] ?? 'first_name';
        if (!StudentRepository::isSortable($sortKey)) {
            $sortKey = 'first_name';
        }

        $result = $studentRepo->paginate(
            [
                'course_id' => (int)($_GET['course_id'] ?? 0),
                'year_be'   => trim($_GET['year_be'] ?? ''),
                'q'         => trim($_GET['q'] ?? ''),
            ],
            $sortKey,
            $_GET['dir'] ?? 'asc',
            $page,
            $limit
        );

        echo json_encode([
            'ok'    => true,
            'data'  => $result['data'],
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
        ]);
        exit;
    }

    if ($action === 'get_student') {
        $id   = (int)($_GET['id'] ?? 0);
        $data = $studentRepo->find($id);
        echo json_encode(['ok' => (bool)$data, 'data' => $data]);
        exit;
    }

    if ($action === 'add_student') {
        $course_id  = (int)($_POST['course_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        if (!$course_id || !$first_name) {
            echo json_encode(['ok' => false, 'error' => 'course_id และชื่อห้ามว่าง']);
            exit;
        }
        $id = $studentRepo->add(array_merge($_POST, [
            'course_id'  => $course_id,
            'first_name' => $first_name,
        ]));
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'update_student') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ไม่พบ id']);
            exit;
        }
        $studentRepo->update($id, $_POST);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete_student') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ไม่พบ id']);
            exit;
        }
        $studentRepo->delete($id);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ===== ดึงข้อมูลจาก Airtable มาแสดงพรีวิว แบบ group ตามคอร์ส (ยังไม่บันทึกอะไร) =====
    if ($action === 'airtable_preview') {
        $apiKey  = getenv('AIRTABLE_API_KEY') ?: '';
        $baseId  = getenv('AIRTABLE_BASE_ID') ?: '';
        $tableId = getenv('AIRTABLE_TABLE_ID') ?: '';
        if (!$apiKey || !$baseId || !$tableId) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า AIRTABLE_API_KEY / AIRTABLE_BASE_ID / AIRTABLE_TABLE_ID ใน environment variables']);
            exit;
        }

        $client              = new AirtableClient($apiKey, $baseId, $tableId);
        $records             = $client->fetchAllRecords();
        $existingEmailCourse = $studentRepo->getExistingByEmailAndCourse();

        // ดึงคอร์สทั้งหมดมาครั้งเดียว แล้ว index ด้วย short_name และ long_key
        $allCourses      = $courseRepo->listWithStudentCount();
        $courseByShort   = [];
        $courseByLongKey = [];
        foreach ($allCourses as $c) {
            if ($c['short_name']) $courseByShort[trim($c['short_name'])]  = $c;
            if ($c['long_key'])   $courseByLongKey[trim($c['long_key'])]  = $c;
        }

        $courseGroups = [];
        foreach ($records as $airtableRec) {
            $f          = $airtableRec['fields'] ?? [];
            $courseName = trim($f[AIRTABLE_COURSE_FIELD] ?? '');
            if ($courseName === '') continue;

            if (!isset($courseGroups[$courseName])) {
                $existingCourse = $courseByShort[$courseName] ?? $courseByLongKey[$courseName] ?? null;
                $courseGroups[$courseName] = [
                    'course_name' => $courseName,
                    'is_existing' => (bool)$existingCourse,
                    'existing_id' => $existingCourse['id'] ?? null,
                    'students'    => [],
                ];
            }

            $mapped = [];
            foreach (AIRTABLE_FIELD_MAP as $atKey => $ourKey) {
                $mapped[$ourKey] = $f[$atKey] ?? '';
            }

            // เช็คด้วย email + course_id แทน airtable_id
            // ครอบคลุมทั้งคนที่เข้ามาจาก webhook (ไม่มี airtable_id) และจาก Airtable import
            $targetCourseId  = $courseGroups[$courseName]['existing_id'] ?? 0;
            $emailFromAirtable = strtolower(trim($f['Email'] ?? ''));
            $emailCourseKey  = $emailFromAirtable . '|' . $targetCourseId;
            $alreadyImported = $targetCourseId > 0
                && $emailFromAirtable !== ''
                && isset($existingEmailCourse[$emailCourseKey]);

            $courseGroups[$courseName]['students'][] = [
                'airtable_id'      => $airtableRec['id'],
                'already_imported' => $alreadyImported,
                'fields'           => $mapped,
            ];
        }

        $newCourses      = [];
        $existingCourses = [];
        foreach ($courseGroups as $group) {
            $pendingCount = 0;
            foreach ($group['students'] as $s) {
                if (!$s['already_imported']) $pendingCount++;
            }
            $entry = [
                'course_name'   => $group['course_name'],
                'existing_id'   => $group['existing_id'],
                'students'      => $group['students'],
                'total_count'   => count($group['students']),
                'pending_count' => $pendingCount,
            ];
            if ($group['is_existing']) {
                $existingCourses[] = $entry;
            } else {
                $newCourses[] = $entry;
            }
        }

        echo json_encode([
            'ok'               => true,
            'new_courses'      => $newCourses,
            'existing_courses' => $existingCourses,
        ]);
        exit;
    }

    // ===== สร้างคอร์ส (ถ้าเป็นคอร์สใหม่) + นำเข้านักเรียนที่เลือกในคอร์สนั้น ในทีเดียว =====
    if ($action === 'airtable_import_course') {
        $courseName = trim($_POST['course_name'] ?? '');
        $createNew  = ($_POST['create_new'] ?? '') === '1';

        $selectedIds = $_POST['airtable_ids'] ?? [];
        if (!is_array($selectedIds)) {
            $selectedIds = json_decode($selectedIds, true) ?: [];
        }

        if ($courseName === '' || empty($selectedIds)) {
            echo json_encode(['ok' => false, 'error' => 'ข้อมูลไม่ครบ (ต้องมีชื่อคอร์สและเลือกนักเรียนอย่างน้อย 1 คน)']);
            exit;
        }

        $apiKey  = getenv('AIRTABLE_API_KEY') ?: '';
        $baseId  = getenv('AIRTABLE_BASE_ID') ?: '';
        $tableId = getenv('AIRTABLE_TABLE_ID') ?: '';
        if (!$apiKey || !$baseId || !$tableId) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า Airtable environment variables']);
            exit;
        }

        // ดึงข้อมูลจาก Airtable ก่อน "นอก" transaction (เป็น network call ห้ามค้างอยู่ระหว่างเปิด transaction กับ DB)
        $client              = new AirtableClient($apiKey, $baseId, $tableId);
        $records             = $client->fetchAllRecords();
        $existingEmailCourse = $studentRepo->getExistingByEmailAndCourse();
        $selectedSet         = array_flip($selectedIds);

        $pdo->beginTransaction();
        try {
            // 1) หา หรือ สร้าง course_id
            if ($createNew) {
                $shortName = trim($_POST['short_name'] ?? '');
                $longKey   = trim($_POST['long_key'] ?? '');
                if (!$shortName || !$longKey) {
                    throw new Exception('กรุณากรอกชื่อย่อและชื่อยาวของคอร์สใหม่');
                }
                $courseId = $courseRepo->add([
                    'short_name'    => $shortName,
                    'long_key'      => $longKey,
                    'training_date' => trim($_POST['training_date'] ?? ''),
                    'year_be'       => trim($_POST['year_be'] ?? ''),
                    'verify_url'    => trim($_POST['verify_url'] ?? ''),
                ]);
            } else {
                $existing = $courseRepo->findByShortNameOrLongKey($courseName);
                if (!$existing) {
                    throw new Exception("ไม่พบคอร์ส '$courseName' ในระบบ (course_id)");
                }
                $courseId = (int)$existing['id'];
            }

            $imported = 0;
            $skipped  = [];

            foreach ($records as $rec) {
                if (!isset($selectedSet[$rec['id']])) {
                    continue; // ไม่ได้ถูกเลือกไว้
                }
                $f = $rec['fields'] ?? [];
                $recCourseName = trim($f[AIRTABLE_COURSE_FIELD] ?? '');
                if ($recCourseName !== $courseName) {
                    continue; // กันเคสข้อมูลไม่ตรงคอร์สที่กำลัง import
                }
                $emailFromAirtable = strtolower(trim($f['Email'] ?? ''));
                $emailCourseKey    = $emailFromAirtable . '|' . $courseId;
                if ($emailFromAirtable !== '' && isset($existingEmailCourse[$emailCourseKey])) {
                    $skipped[] = ['airtable_id' => $rec['id'], 'reason' => 'มีข้อมูลคนนี้ในคอร์สนี้อยู่แล้ว (email ซ้ำ)'];
                    continue;
                }

                $firstName = trim($f['ชื่อ'] ?? '');
                if ($firstName === '') {
                    $skipped[] = ['airtable_id' => $rec['id'], 'reason' => 'ไม่มีชื่อผู้สมัคร'];
                    continue;
                }

                $data = [
                    'course_id'   => $courseId,
                    'airtable_id' => $rec['id'],
                    'first_name'  => $firstName,
                ];
                foreach (AIRTABLE_FIELD_MAP as $atKey => $ourKey) {
                    if ($ourKey === 'first_name') continue;
                    $data[$ourKey] = trim($f[$atKey] ?? '') ?: null;
                }

                $studentRepo->insertFromAirtable($data);
                $imported++;
            }

            $pdo->commit();
            echo json_encode([
                'ok'        => true,
                'course_id' => $courseId,
                'imported'  => $imported,
                'skipped'   => $skipped,
            ]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ===== นำเข้าเฉพาะรายการที่ Admin เลือกไว้ =====
    if ($action === 'airtable_import') {
        $selectedIds = $_POST['airtable_ids'] ?? [];
        if (!is_array($selectedIds)) {
            $selectedIds = json_decode($selectedIds, true) ?: [];
        }
        if (empty($selectedIds)) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้เลือกรายการที่จะนำเข้า']);
            exit;
        }
        $selectedSet = array_flip($selectedIds);

        $apiKey  = getenv('AIRTABLE_API_KEY') ?: '';
        $baseId  = getenv('AIRTABLE_BASE_ID') ?: '';
        $tableId = getenv('AIRTABLE_TABLE_ID') ?: '';
        if (!$apiKey || !$baseId || !$tableId) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า Airtable environment variables']);
            exit;
        }

        $client        = new AirtableClient($apiKey, $baseId, $tableId);
        $records       = $client->fetchAllRecords();
        $existingPairs = $studentRepo->getExistingAirtableCoursePairs();

        $imported = 0;
        $skipped  = [];

        foreach ($records as $rec) {
            if (!isset($selectedSet[$rec['id']])) {
                continue; // ไม่ได้ถูกเลือกไว้
            }

            $f          = $rec['fields'] ?? [];
            $courseName = trim($f[AIRTABLE_COURSE_FIELD] ?? '');
            $course     = $courseName !== '' ? $courseRepo->findByShortName($courseName) : null;
            if (!$course) {
                $skipped[] = ['airtable_id' => $rec['id'], 'reason' => "ไม่พบคอร์สชื่อ '$courseName' ในระบบ"];
                continue;
            }

            if (isset($existingPairs[$rec['id'] . '::' . $course['id']])) {
                $skipped[] = ['airtable_id' => $rec['id'], 'reason' => 'นำเข้าไปแล้วก่อนหน้านี้ (คอร์สนี้)'];
                continue;
            }

            $firstName = trim($f['ชื่อ'] ?? '');
            if ($firstName === '') {
                $skipped[] = ['airtable_id' => $rec['id'], 'reason' => 'ไม่มีชื่อผู้สมัคร'];
                continue;
            }

            $data = [
                'course_id'   => (int)$course['id'],
                'airtable_id' => $rec['id'],
                'first_name'  => $firstName,
            ];
            foreach (AIRTABLE_FIELD_MAP as $atKey => $ourKey) {
                if ($ourKey === 'first_name') continue;
                $data[$ourKey] = trim($f[$atKey] ?? '') ?: null;
            }

            $studentRepo->insertFromAirtable($data);
            $imported++;
        }

        echo json_encode(['ok' => true, 'imported' => $imported, 'skipped' => $skipped]);
        exit;
    }

    if ($action === 'list_approval_requests') {
        $data = (new ApprovalRepository($pdo))->listPage(
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 20),
            approval_filters_from($_GET)
        );
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== รายละเอียดคำขอ 1 รายการ (ดูอย่างเดียว ไม่มีแก้ไข) =====
    if ($action === 'get_approval') {
        $row = (new ApprovalRepository($pdo))->find((string) ($_GET['ref'] ?? ''));
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'ไม่พบคำขอนี้'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $cas = json_decode((string) ($row['cas_data'] ?? ''), true) ?: [];
        unset($row['cas_data']);
        $row['approver_email']    = $cas['HeadShowEmail'] ?? null;
        $row['approver_position'] = $cas['Position'] ?? null;
        $row['approver_org']      = $cas['Organization'] ?? null;
        $row['status_kind']       = ApprovalRepository::statusKind($row['status']);
        echo json_encode(['ok' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== ลบคำขอ (1 รายการหรือหลายรายการ) =====
    if ($action === 'delete_approvals') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'ต้องใช้ POST']);
            exit;
        }
        $refs = approval_refs_from_post(500);
        if (!$refs) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้เลือกรายการที่จะลบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $deleted = (new ApprovalRepository($pdo))->deleteMany($refs);
        error_log('[admin_api] delete_approvals: ' . $deleted . ' row(s): ' . implode(',', $refs));
        echo json_encode(['ok' => true, 'deleted' => $deleted]);
        exit;
    }

    // ===== ถามสถานะล่าสุดจาก CAS (กรณี callback ไม่มาถึง) แล้วอัปเดต Neon =====
    if ($action === 'refresh_approval_status') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'ต้องใช้ POST']);
            exit;
        }
        $refs = approval_refs_from_post(50);
        if (!$refs) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้เลือกรายการ'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $repo    = new ApprovalRepository($pdo);
        $cas     = CasClient::fromEnv();
        $updated = 0;
        $failed  = 0;
        foreach ($refs as $ref) {
            if (!$repo->find($ref)) continue;
            $d = $cas->fetchStatus($ref);
            if (!$d) { $failed++; continue; }
            $repo->updateStatus(
                $ref,
                (string) $d['Status'],
                isset($d['ApproveStep']) ? (string) $d['ApproveStep'] : null,
                !empty($d['Approver_ADUser']) ? (string) $d['Approver_ADUser'] : null
            );
            $updated++;
        }
        echo json_encode(['ok' => true, 'updated' => $updated, 'failed' => $failed]);
        exit;
    }

    // ===== ส่งออก CSV ตามตัวกรองปัจจุบัน (เปิดด้วย Excel ได้ ภาษาไทยไม่เพี้ยน) =====
    if ($action === 'export_approvals') {
        $rows = (new ApprovalRepository($pdo))->listAll(approval_filters_from($_GET));
        $toThai = function (?string $utc): string {
            if (!$utc) return '';
            $dt = new DateTime($utc, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Bangkok'));
            return $dt->format('Y-m-d H:i');
        };
        $label = ['approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ', 'pending' => 'รออนุมัติ'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="approval_requests_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่าน UTF-8
        fputcsv($out, ['วันที่ขอ', 'ผู้สมัคร', 'คอร์ส', 'ประเภท', 'ผู้ขอ (AD)', 'ชื่อผู้ขอ', 'หัวหน้ากอง (AD)', 'ชื่อหัวหน้ากอง', 'สถานะ', 'สถานะดิบ (CAS)', 'อัปเดตล่าสุด', 'รหัสอ้างอิง']);
        foreach ($rows as $r) {
            fputcsv($out, array_map('csv_safe', [
                $toThai($r['created_at']),
                $r['applicant_name'],
                $r['course'],
                $r['member_type'],
                $r['requester_ad'],
                $r['requester_name'],
                $r['approver_ad'],
                $r['approver_name'],
                $label[ApprovalRepository::statusKind($r['status'])],
                $r['status'],
                $toThai($r['updated_at']),
                $r['external_ref_id'],
            ]));
        }
        fclose($out);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown action']);
} catch (Throwable $e) {
    send_error_response($e, 'เกิดข้อผิดพลาดในการประมวลผล กรุณาลองใหม่อีกครั้ง');
}
