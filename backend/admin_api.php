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

    // ===== ดึงข้อมูลจาก Airtable มาแสดงพรีวิว แบบ group ตามคอร์ส (ยังไม่บันทึกอะไร) =====
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

        // จัดกลุ่ม record ทั้งหมดตามชื่อคอร์ส (ชื่อโปรแกรม)
        $courseGroups = []; // key = ชื่อคอร์สจาก Airtable, value = ['course_info' => ..., 'students' => [...]]

        foreach ($records as $rec) {
            $f          = $rec['fields'] ?? [];
            $courseName = trim($f[AIRTABLE_COURSE_FIELD] ?? '');
            if ($courseName === '') {
                continue; // ไม่มีชื่อคอร์ส ข้ามไป (ไม่รู้จะจัดกลุ่มลงไหน)
            }

            if (!isset($courseGroups[$courseName])) {
                $existingCourse = $courseRepo->findByShortNameOrLongKey($courseName);
                $courseGroups[$courseName] = [
                    'course_name'  => $courseName,
                    'is_existing'  => (bool)$existingCourse,
                    'existing_id'  => $existingCourse['id'] ?? null,
                    'students'     => [],
                ];
            }

            // ถ้านักเรียนคนนี้เคยนำเข้าไปแล้ว ก็ยังคงแสดงในรายการ แต่ทำเครื่องหมายไว้ (ไม่ติ๊กให้อัตโนมัติ)
            $mapped = [];
            foreach (AIRTABLE_FIELD_MAP as $atKey => $ourKey) {
                $mapped[$ourKey] = $f[$atKey] ?? '';
            }

            $courseGroups[$courseName]['students'][] = [
                'airtable_id'      => $rec['id'],
                'already_imported' => isset($existingIds[$rec['id']]),
                'fields'           => $mapped,
            ];
        }

        // แยกผลลัพธ์เป็น 2 กลุ่ม: คอร์สใหม่ / คอร์สที่มีอยู่แล้ว พร้อมนับจำนวนนักเรียนที่ยังไม่เคยนำเข้า
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

            // 2) ดึงข้อมูลจาก Airtable ใหม่อีกครั้ง เพื่อความถูกต้อง (ไม่เชื่อข้อมูลจาก client ตรงๆ)
            $client      = new AirtableClient($apiKey, $baseId, $tableId);
            $records     = $client->fetchAllRecords();
            $existingIds = $studentRepo->getExistingAirtableIds();
            $selectedSet = array_flip($selectedIds);

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
                if (isset($existingIds[$rec['id']])) {
                    $skipped[] = ['airtable_id' => $rec['id'], 'reason' => 'นำเข้าไปแล้วก่อนหน้านี้'];
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