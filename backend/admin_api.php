<?php
require_once __DIR__ . '/lib/require_admin.php';
require_once __DIR__ . '/../database/config.php';
$pdo = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list_courses') {
    $rows = $pdo->query("
        SELECT c.*, COUNT(s.id) as student_count
        FROM courses c
        LEFT JOIN students s ON s.course_id = c.id
        GROUP BY c.id
        ORDER BY c.year_be DESC, c.short_name ASC
    ")->fetchAll();
    echo json_encode(['ok' => true, 'data' => $rows]);
    exit;
}

if ($action === 'add_course') {
    $short_name    = trim($_POST['short_name'] ?? '');
    $long_key      = trim($_POST['long_key'] ?? '');
    $training_date = trim($_POST['training_date'] ?? '');
    $year_be       = trim($_POST['year_be'] ?? '');
    $verify_url    = trim($_POST['verify_url'] ?? '');
    if (!$short_name || !$long_key) {
        echo json_encode(['ok' => false, 'error' => 'ชื่อย่อและชื่อยาวห้ามว่าง']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO courses (long_key, short_name, training_date, year_be, verify_url) VALUES (:lk,:sn,:td,:yb,:vu)");
    $stmt->execute([':lk' => $long_key, ':sn' => $short_name, ':td' => $training_date ?: null, ':yb' => $year_be ?: null, ':vu' => $verify_url ?: null]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($action === 'update_course') {
    $id           = (int)($_POST['id'] ?? 0);
    $short_name   = trim($_POST['short_name'] ?? '');
    $long_key     = trim($_POST['long_key'] ?? '');
    $training_date = trim($_POST['training_date'] ?? '');
    $year_be      = trim($_POST['year_be'] ?? '');
    $verify_url   = trim($_POST['verify_url'] ?? '');
    if (!$id || !$short_name) {
        echo json_encode(['ok' => false, 'error' => 'ข้อมูลไม่ครบ']);
        exit;
    }
    $pdo->prepare("UPDATE courses SET short_name=:sn,long_key=:lk,training_date=:td,year_be=:yb,verify_url=:vu WHERE id=:id")
        ->execute([':sn' => $short_name, ':lk' => $long_key ?: null, ':td' => $training_date ?: null, ':yb' => $year_be ?: null, ':vu' => $verify_url ?: null, ':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete_course') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ไม่พบ id']);
        exit;
    }
    $pdo->prepare("DELETE FROM courses WHERE id=:id")->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'list_students') {
    $course_id = (int)($_GET['course_id'] ?? 0);
    $year_be   = trim($_GET['year_be'] ?? '');
    $q         = trim($_GET['q'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $limit     = 50;
    $offset    = ($page - 1) * $limit;

    // whitelist คอลัมน์ที่ยอม sort ได้ ป้องกัน SQL injection ผ่าน ORDER BY
    $sortableColumns = [
        'id'           => 's.id',
        'course_name'  => 'c.short_name',
        'first_name'   => 's.first_name',
        'last_name'    => 's.last_name',
        'member_type'  => 's.member_type',
        'apply_date'   => 's.apply_date',
        'age' => 'CAST(s.age AS INTEGER)',
        'department'   => 's.department',
        'office'       => 's.office',
        'position'     => 's.position',
        'phone_mobile' => 's.phone_mobile',
        'email'        => 's.email',
    ];
    $sortKey = $_GET['sort'] ?? 'first_name';
    $sortCol = $sortableColumns[$sortKey] ?? $sortableColumns['first_name'];
    $sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

    $conditions = [];
    $params     = [];
    if ($course_id) {
        $conditions[] = 's.course_id = :cid';
        $params[':cid'] = $course_id;
    }
    if ($year_be !== '') {
        $conditions[] = 'c.year_be = :yb';
        $params[':yb'] = $year_be;
    }
    if ($q !== '') {
        $conditions[] = '(s.first_name LIKE :q1 OR s.last_name LIKE :q2)';
        $params[':q1'] = '%' . $q . '%';
        $params[':q2'] = '%' . $q . '%';
    }
    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $total = $pdo->prepare("SELECT COUNT(*) FROM students s INNER JOIN courses c ON c.id = s.course_id $where");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $pdo->prepare("
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
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(), 'total' => $totalCount, 'page' => $page, 'limit' => $limit]);
    exit;
}

if ($action === 'get_student') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch();
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
    $fields = [
        'first_name',
        'last_name',
        'member_type',
        'email',
        'phone_mobile',
        'phone_internal',
        'birth_date',
        'age',
        'royal_title',
        'education_level',
        'faculty',
        'major',
        'institution',
        'department',
        'office',
        'position',
        'head_status',
        'attendance'
    ];
    $cols = 'course_id,' . implode(',', $fields);
    $phs  = ':course_id,' . implode(',', array_map(fn($f) => ":$f", $fields));
    $params = [':course_id' => $course_id];
    foreach ($fields as $f) $params[":$f"] = trim($_POST[$f] ?? '') ?: null;
    $params[':first_name'] = $first_name;
    $pdo->prepare("INSERT INTO students ($cols) VALUES ($phs)")->execute($params);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($action === 'update_student') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ไม่พบ id']);
        exit;
    }
    $fields = [
        'first_name',
        'last_name',
        'member_type',
        'email',
        'phone_mobile',
        'phone_internal',
        'birth_date',
        'age',
        'education_level',
        'faculty',
        'major',
        'institution',
        'department',
        'office',
        'position',
        'head_status',
        'attendance'
    ];
    $sets   = implode(',', array_map(fn($f) => "$f=:$f", $fields));
    $params = [':id' => $id];
    foreach ($fields as $f) $params[":$f"] = $_POST[$f] ?? null;
    $pdo->prepare("UPDATE students SET $sets WHERE id=:id")->execute($params);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete_student') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ไม่พบ id']);
        exit;
    }
    $pdo->prepare("DELETE FROM students WHERE id=:id")->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown action']);