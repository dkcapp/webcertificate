<?php
require_once __DIR__ . '/lib/require_admin.php';
require_once __DIR__ . '/lib/error_handler.php';
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/lib/CourseRepository.php';
require_once __DIR__ . '/lib/StudentRepository.php';
require_once __DIR__ . '/lib/AirtableClient.php';

$pdo         = getDB();
$courseRepo  = new CourseRepository($pdo);
$studentRepo = new StudentRepository($pdo);

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
const AIRTABLE_COURSE_FIELD = 'ชื่อโปรแกรม'; // field ที่ใช้จับคู่กับ courses.short_name

$action = $_GET['action'] ?? $_POST['action'] ?? '';

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

    // ===== ดึงข้อมูลจาก Airtable มาแสดงพรีวิว (ยังไม่บันทึกอะไร) =====
    if ($action === 'airtable_preview') {
        $apiKey  = getenv('AIRTABLE_API_KEY') ?: '';
        $baseId  = getenv('AIRTABLE_BASE_ID') ?: '';
        $tableId = getenv('AIRTABLE_TABLE_ID') ?: '';
        if (!$apiKey || !$baseId || !$tableId) {
            echo json_encode(['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า AIRTABLE_API_KEY / AIRTABLE_BASE_ID / AIRTABLE_TABLE_ID ใน environment variables']);
            exit;
        }

        $client      = new AirtableClient($apiKey, $baseId, $tableId);
        $records     = $client->fetchAllRecords();
        $existingIds = $studentRepo->getExistingAirtableIds();

        $rows = [];
        foreach ($records as $rec) {
            $f          = $rec['fields'] ?? [];
            $courseName = trim($f[AIRTABLE_COURSE_FIELD] ?? '');
            $course     = $courseName !== '' ? $courseRepo->findByShortName($courseName) : null;

            $mapped = [];
            foreach (AIRTABLE_FIELD_MAP as $atKey => $ourKey) {
                $mapped[$ourKey] = $f[$atKey] ?? '';
            }

            $rows[] = [
                'airtable_id'      => $rec['id'],
                'course_name'      => $courseName,
                'course_found'     => (bool)$course,
                'already_imported' => isset($existingIds[$rec['id']]),
                'fields'           => $mapped,
            ];
        }

        echo json_encode(['ok' => true, 'count' => count($rows), 'data' => $rows]);
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

        $client      = new AirtableClient($apiKey, $baseId, $tableId);
        $records     = $client->fetchAllRecords();
        $existingIds = $studentRepo->getExistingAirtableIds();

        $imported = 0;
        $skipped  = [];

        foreach ($records as $rec) {
            if (!isset($selectedSet[$rec['id']])) {
                continue; // ไม่ได้ถูกเลือกไว้
            }
            if (isset($existingIds[$rec['id']])) {
                $skipped[] = ['airtable_id' => $rec['id'], 'reason' => 'นำเข้าไปแล้วก่อนหน้านี้'];
                continue;
            }

            $f          = $rec['fields'] ?? [];
            $courseName = trim($f[AIRTABLE_COURSE_FIELD] ?? '');
            $course     = $courseName !== '' ? $courseRepo->findByShortName($courseName) : null;
            if (!$course) {
                $skipped[] = ['airtable_id' => $rec['id'], 'reason' => "ไม่พบคอร์สชื่อ '$courseName' ในระบบ"];
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

    echo json_encode(['ok' => false, 'error' => 'unknown action']);
} catch (Throwable $e) {
    send_error_response($e, 'เกิดข้อผิดพลาดในการประมวลผล กรุณาลองใหม่อีกครั้ง');
}