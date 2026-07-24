// assets/js/admin.js
// ตรรกะหน้า Admin Panel ทั้งหมด: จัดการคอร์ส, จัดการรายชื่อผู้เรียน, เปิดรับสมัคร + sync JotForm

let currentTab = "download";
let currentAdminSection = "courses";
let editContext = null;
let studentsPage = 1;
let studentMgmtCourses = [];
let studentFilters = { q: "", year: "", courseId: "" };
let studentSort = { col: "first_name", dir: "asc" };
let studentSearchTimer = null;

function switchTab(tab) {
  currentTab = tab;
  document
    .getElementById("tab-download")
    .classList.toggle("active", tab === "download");
  document
    .getElementById("tab-manage")
    .classList.toggle("active", tab === "manage");
  const dlView = document.getElementById("view-download");
  const adminView = document.getElementById("admin-data-view");
  if (tab === "download") {
    dlView.style.display = "flex";
    adminView.classList.remove("visible");
  } else {
    dlView.style.display = "none";
    adminView.classList.add("visible");
    if (currentAdminSection === "courses") loadCoursesTable();
    else loadStudentsTable();
  }
}

function adminNav(section) {
  currentAdminSection = section;
  document
    .getElementById("nav-courses")
    .classList.toggle("active", section === "courses");
  document
    .getElementById("nav-students")
    .classList.toggle("active", section === "students");
  if (section === "courses") loadCoursesTable();
  else if (section === "students") loadStudentsTable();
  else if (section === "active") loadActiveCoursesAdmin();
  else if (section === "airtable") loadAirtableImportPage();
}

let courseTableData = [];
let courseTableSort = { col: null, asc: true };

const THAI_MONTHS = [
  "ม.ค.",
  "ก.พ.",
  "มี.ค.",
  "เม.ย.",
  "พ.ค.",
  "มิ.ย.",
  "ก.ค.",
  "ส.ค.",
  "ก.ย.",
  "ต.ค.",
  "พ.ย.",
  "ธ.ค.",
];
function parseTrainingDateKey(str) {
  if (!str) return 0;
  const yearMatch = str.match(/\d{4}/);
  const year = yearMatch ? parseInt(yearMatch[0], 10) : 0;
  let month = 0;
  for (let i = 0; i < THAI_MONTHS.length; i++) {
    if (str.indexOf(THAI_MONTHS[i]) !== -1) {
      month = i + 1;
      break;
    }
  }
  const withoutYear = yearMatch ? str.replace(yearMatch[0], "") : str;
  const dayMatch = withoutYear.match(/\d{1,2}/);
  const day = dayMatch ? parseInt(dayMatch[0], 10) : 0;
  return year * 10000 + month * 100 + day;
}

async function loadCoursesTable() {
  const area = document.getElementById("admin-content-area");
  area.innerHTML =
    '<div style="color:var(--text3);padding:2rem;font-size:13px;">กำลังโหลด...</div>';
  const res = await fetch("backend/admin_api.php?action=list_courses");
  const result = await res.json();
  if (!result.ok) {
    area.innerHTML = `<div style="color:var(--red);padding:2rem;font-size:13px;">❌ ${result.error}</div>`;
    return;
  }
  courseTableData = result.data;
  courseTableSort = { col: null, asc: true };

  const years = [
    ...new Set(result.data.map((r) => r.year_be).filter(Boolean)),
  ].sort((a, b) => b - a);

  area.innerHTML = `
<div class="admin-page-title">
  <h2><svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:var(--blue);fill:none;stroke-width:2;vertical-align:-3px;margin-right:6px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>คอร์สทั้งหมด</h2>
  <span id="courses-count-badge" class="badge-count">${courseTableData.length} คอร์ส</span>
</div>
<div class="admin-toolbar">
  <div class="admin-search">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="ค้นหาชื่อย่อ หรือ ชื่อเต็ม..." id="course-search" oninput="renderCourseTable()" autocomplete="off">
  </div>
  <select id="course-year-filter" class="year-filter-select" onchange="renderCourseTable()">
    <option value="">ทุกปี</option>
    ${years.map((y) => `<option value="${y}">พ.ศ. ${y}</option>`).join("")}
  </select>
  <button class="btn-sm btn-primary" onclick="openAddCourse()">
    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>เพิ่มคอร์สใหม่
  </button>
</div>
<div class="table-card"><div style="overflow-x:auto">
<table class="data-table">
  <thead><tr>
    <th onclick="sortCourseTable('id')" style="cursor:pointer;user-select:none">ID <span id="sort-id"></span></th>
    <th onclick="sortCourseTable('short_name')" style="cursor:pointer;user-select:none">ชื่อย่อ <span id="sort-short_name"></span></th>
    <th onclick="sortCourseTable('long_key')" style="cursor:pointer;user-select:none">ชื่อยาว <span id="sort-long_key"></span></th>
    <th onclick="sortCourseTable('training_date')" style="cursor:pointer;user-select:none">วันที่อบรม <span id="sort-training_date"></span></th>
    <th onclick="sortCourseTable('year_be')" style="cursor:pointer;user-select:none">ปี พ.ศ. <span id="sort-year_be"></span></th>
    <th onclick="sortCourseTable('student_count')" style="cursor:pointer;user-select:none">ผู้เรียน <span id="sort-student_count"></span></th>
    <th>Verify URL</th>
    <th>จัดการ</th>
  </tr></thead>
  <tbody id="courses-tbody"></tbody>
</table></div></div>`;
  renderCourseTable();
}

function sortCourseTable(col) {
  if (courseTableSort.col === col) courseTableSort.asc = !courseTableSort.asc;
  else {
    courseTableSort.col = col;
    courseTableSort.asc = true;
  }
  renderCourseTable();
}

function renderCourseTable() {
  const q = (document.getElementById("course-search")?.value || "")
    .trim()
    .toLowerCase();
  const yearFilter = document.getElementById("course-year-filter")?.value || "";

  let rows = courseTableData.filter((r) => {
    const matchYear = !yearFilter || r.year_be === yearFilter;
    const matchSearch =
      !q ||
      (r.short_name || "").toLowerCase().includes(q) ||
      (r.long_key || "").toLowerCase().includes(q);
    return matchYear && matchSearch;
  });

  const col = courseTableSort.col;
  if (col) {
    rows = [...rows].sort((a, b) => {
      let va = a[col] ?? "";
      let vb = b[col] ?? "";
      if (col === "id" || col === "student_count" || col === "year_be") {
        va = Number(va) || 0;
        vb = Number(vb) || 0;
      } else if (col === "training_date") {
        va = parseTrainingDateKey(va);
        vb = parseTrainingDateKey(vb);
      } else {
        va = String(va).toLowerCase();
        vb = String(vb).toLowerCase();
      }
      return courseTableSort.asc
        ? va > vb
          ? 1
          : va < vb
            ? -1
            : 0
        : va < vb
          ? 1
          : va > vb
            ? -1
            : 0;
    });
  }

  [
    "id",
    "short_name",
    "long_key",
    "training_date",
    "year_be",
    "student_count",
  ].forEach((c) => {
    const el = document.getElementById("sort-" + c);
    if (el) el.textContent = col === c ? (courseTableSort.asc ? " ▲" : " ▼") : "";
  });

  const badge = document.getElementById("courses-count-badge");
  if (badge) badge.textContent = rows.length + " คอร์ส";

  const tbody = document.getElementById("courses-tbody");
  if (!tbody) return;
  tbody.innerHTML =
    rows
      .map(
        (r) => `<tr data-id="${r.id}" data-name="${escHtml(r.short_name)}">
    <td class="cell-id">${r.id}</td>
    <td><strong style="font-weight:600">${escHtml(r.short_name)}</strong></td>
    <td class="cell-muted cell-truncate" title="${escHtml(r.long_key)}">${escHtml(r.long_key)}</td>
    <td class="cell-muted" style="white-space:nowrap">${escHtml(r.training_date || "—")}</td>
    <td>${r.year_be || "—"}</td>
    <td>${r.student_count} คน</td>
    <td class="cell-link">${r.verify_url ? `<a href="${escHtml(r.verify_url)}" target="_blank"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>ลิงก์</a>` : '<span class="cell-muted">—</span>'}</td>
    <td><div class="actions">
      <button class="btn-sm btn-edit" onclick="openEditCourse(${r.id})"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>แก้ไข</button>
      <button class="btn-sm btn-danger" onclick="deleteCourse(${r.id},'${escHtml(r.short_name)}')"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
    </div></td>
  </tr>`,
      )
      .join("") ||
    '<tr><td colspan="8" style="text-align:center;color:var(--text3);padding:1.5rem">ไม่พบข้อมูล</td></tr>';
}

function filterCourseTable() {
  renderCourseTable();
}

async function openEditCourse(id) {
  const res = await fetch("backend/admin_api.php?action=list_courses");
  const result = await res.json();
  const row = result.data.find((r) => r.id === id);
  if (!row) return;
  editContext = { type: "course", id };
  document.getElementById("edit-modal-title").textContent =
    `แก้ไขคอร์ส: ${row.short_name}`;
  document.getElementById("edit-modal-body").innerHTML =
    `<div class="edit-grid">
    <div class="edit-field full"><label>ชื่อย่อ (short_name) *</label><input type="text" id="ef-short_name" value="${escHtml(row.short_name)}"></div>
    <div class="edit-field full"><label>ชื่อยาว (long_key) *</label><input type="text" id="ef-long_key" value="${escHtml(row.long_key || "")}"></div>
    <div class="edit-field full"><label>วันที่อบรม</label><input type="text" id="ef-training_date" value="${escHtml(row.training_date || "")}"></div>
    <div class="edit-field"><label>ปี พ.ศ.</label><input type="text" id="ef-year_be" value="${escHtml(row.year_be || "")}" placeholder="เช่น 2568" maxlength="4" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div>
    <div class="edit-field"><label>Verify URL (QR code)</label><input type="text" id="ef-verify_url" value="${escHtml(row.verify_url || "")}"></div>
  </div>`;
  document.getElementById("edit-modal-overlay").classList.add("open");
}

function openAddCourse() {
  editContext = { type: "course", id: null };
  document.getElementById("edit-modal-title").textContent = "เพิ่มคอร์สใหม่";
  document.getElementById("edit-modal-body").innerHTML =
    `<div class="edit-grid">
    <div class="edit-field full"><label>ชื่อย่อ (short_name) *</label><input type="text" id="ef-short_name" placeholder="เช่น คอมพิวเตอร์เบื้องต้น"></div>
    <div class="edit-field full"><label>ชื่อยาว (long_key) *</label><input type="text" id="ef-long_key" placeholder="เช่น คอมพิวเตอร์เบื้องต้น 1-3 ม.ค. 2568"></div>
    <div class="edit-field full"><label>วันที่อบรม</label><input type="text" id="ef-training_date" placeholder="เช่น วันที่ 1-3 ม.ค. 2568"></div>
    <div class="edit-field"><label>ปี พ.ศ.</label><input type="text" id="ef-year_be" placeholder="เช่น 2568" maxlength="4" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div>
    <div class="edit-field"><label>Verify URL (QR code)</label><input type="text" id="ef-verify_url" placeholder="https://..."></div>
  </div>`;
  document.getElementById("edit-modal-overlay").classList.add("open");
}

async function deleteCourse(id, name) {
  if (
    !confirm(
      `ยืนยันลบคอร์ส "${name}"?\nจะลบรายชื่อผู้เรียนในคอร์สนี้ทั้งหมดด้วย`,
    )
  )
    return;
  const fd = new FormData();
  fd.append("action", "delete_course");
  fd.append("id", id);
  const res = await fetch("backend/admin_api.php", {
    method: "POST",
    body: fd,
  });
  const result = await res.json();
  if (result.ok) loadCoursesTable();
  else alert("เกิดข้อผิดพลาด: " + result.error);
}

async function loadStudentsTable(page) {
  if (page !== undefined) studentsPage = page;
  const area = document.getElementById("admin-content-area");
  area.innerHTML =
    '<div style="color:var(--text3);padding:2rem;font-size:13px;">กำลังโหลด...</div>';

  const cRes = await fetch("backend/admin_api.php?action=list_courses");
  const cResult = await cRes.json();
  studentMgmtCourses = cResult.ok ? cResult.data : [];
  const years = [
    ...new Set(studentMgmtCourses.map((c) => c.year_be).filter(Boolean)),
  ].sort((a, b) => b - a);

  area.innerHTML = `
<div class="admin-page-title">
  <h2><svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:var(--blue);fill:none;stroke-width:2;vertical-align:-3px;margin-right:6px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>รายชื่อผู้เรียน</h2>
  <span class="badge-count" id="students-count-badge">0 คน</span>
</div>
<div class="admin-toolbar">
  <div class="admin-search">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="ค้นหาชื่อหรือนามสกุล..." id="student-search" oninput="onStudentSearchInput()" autocomplete="off">
  </div>
  <select id="student-year-filter" class="year-filter-select" onchange="onStudentYearChange()">
    <option value="">ทุกปี</option>
    ${years.map((y) => `<option value="${y}">พ.ศ. ${y}</option>`).join("")}
  </select>
  <select id="student-course-filter" class="year-filter-select" onchange="onStudentCourseChange()" disabled>
    <option value="">— เลือกปีก่อน —</option>
  </select>
  <button class="btn-sm btn-primary" onclick="openAddStudent()">
    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>เพิ่มรายชื่อ
  </button>
</div>
<div class="table-card"><div style="overflow-x:auto">
<table class="data-table">
  <thead><tr>
    <th onclick="sortStudentsTable('id')" style="cursor:pointer;user-select:none">รหัส <span id="ssort-id"></span></th>
    <th onclick="sortStudentsTable('course_name')" style="cursor:pointer;user-select:none">ชื่อคอร์ส <span id="ssort-course_name"></span></th>
    <th onclick="sortStudentsTable('first_name')" style="cursor:pointer;user-select:none">ชื่อ <span id="ssort-first_name"></span></th>
    <th onclick="sortStudentsTable('last_name')" style="cursor:pointer;user-select:none">นามสกุล <span id="ssort-last_name"></span></th>
    <th onclick="sortStudentsTable('member_type')" style="cursor:pointer;user-select:none">ประเภท <span id="ssort-member_type"></span></th>
    <th onclick="sortStudentsTable('apply_date')" style="cursor:pointer;user-select:none">วันที่สมัคร <span id="ssort-apply_date"></span></th>
    <th onclick="sortStudentsTable('age')" style="cursor:pointer;user-select:none">อายุ <span id="ssort-age"></span></th>
    <th onclick="sortStudentsTable('department')" style="cursor:pointer;user-select:none">หน่วยงาน <span id="ssort-department"></span></th>
    <th onclick="sortStudentsTable('office')" style="cursor:pointer;user-select:none">สำนัก <span id="ssort-office"></span></th>
    <th onclick="sortStudentsTable('position')" style="cursor:pointer;user-select:none">ตำแหน่ง <span id="ssort-position"></span></th>
    <th onclick="sortStudentsTable('phone_mobile')" style="cursor:pointer;user-select:none">มือถือ <span id="ssort-phone_mobile"></span></th>
    <th onclick="sortStudentsTable('email')" style="cursor:pointer;user-select:none">อีเมล <span id="ssort-email"></span></th>
    <th>จัดการ</th>
  </tr></thead>
  <tbody id="students-tbody"></tbody>
</table></div></div>
<div class="pagination" id="students-pagination"></div>`;

  fetchStudentsRows();
}

function onStudentSearchInput() {
  clearTimeout(studentSearchTimer);
  studentSearchTimer = setTimeout(() => {
    studentFilters.q = document.getElementById("student-search").value.trim();
    fetchStudentsRows(1);
  }, 300);
}

function onStudentYearChange() {
  const year = document.getElementById("student-year-filter").value;
  studentFilters.year = year;
  studentFilters.courseId = "";

  const courseSelect = document.getElementById("student-course-filter");
  if (!year) {
    courseSelect.disabled = true;
    courseSelect.innerHTML = '<option value="">— เลือกปีก่อน —</option>';
  } else {
    const coursesInYear = studentMgmtCourses.filter((c) => c.year_be === year);
    courseSelect.disabled = false;
    courseSelect.innerHTML =
      '<option value="">ทุกคอร์ส</option>' +
      coursesInYear
        .map((c) => `<option value="${c.id}">${escHtml(c.short_name)}</option>`)
        .join("");
  }
  fetchStudentsRows(1);
}

function onStudentCourseChange() {
  studentFilters.courseId = document.getElementById(
    "student-course-filter",
  ).value;
  fetchStudentsRows(1);
}

function sortStudentsTable(col) {
  if (studentSort.col === col)
    studentSort.dir = studentSort.dir === "asc" ? "desc" : "asc";
  else {
    studentSort.col = col;
    studentSort.dir = "asc";
  }
  fetchStudentsRows(1);
}

async function fetchStudentsRows(page) {
  if (page !== undefined) studentsPage = page;
  const tbody = document.getElementById("students-tbody");
  if (!tbody) return;
  tbody.innerHTML =
    '<tr><td colspan="13" style="text-align:center;color:var(--text3);padding:1.5rem">กำลังโหลด...</td></tr>';

  const params = {
    action: "list_students",
    page: studentsPage,
    sort: studentSort.col,
    dir: studentSort.dir,
  };
  if (studentFilters.q) params.q = studentFilters.q;
  if (studentFilters.year) params.year_be = studentFilters.year;
  if (studentFilters.courseId) params.course_id = studentFilters.courseId;

  let result;
  try {
    const res = await fetch(
      "backend/admin_api.php?" + new URLSearchParams(params),
    );
    result = await res.json();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="13" style="color:var(--red);padding:1.5rem">❌ ติดต่อ server ไม่สำเร็จ หรือได้รับข้อมูลที่ไม่ถูกต้อง (${e.message})</td></tr>`;
    return;
  }
  if (!result.ok) {
    tbody.innerHTML = `<tr><td colspan="13" style="color:var(--red);padding:1.5rem">❌ ${result.error}</td></tr>`;
    return;
  }

  const { data, total, limit } = result;
  const totalPages = Math.ceil(total / limit) || 1;

  document.getElementById("students-count-badge").textContent = total + " คน";

  [
    "id",
    "course_name",
    "first_name",
    "last_name",
    "member_type",
    "apply_date",
    "age",
    "department",
    "office",
    "position",
    "phone_mobile",
    "email",
  ].forEach((c) => {
    const el = document.getElementById("ssort-" + c);
    if (el)
      el.textContent =
        studentSort.col === c
          ? studentSort.dir === "asc"
            ? " ▲"
            : " ▼"
          : "";
  });

  tbody.innerHTML =
    data
      .map(
        (r) => `<tr>
    <td class="cell-id">${r.id}</td>
    <td style="font-size:12px;color:var(--blue);white-space:nowrap;font-weight:500">${escHtml(r.course_name)}</td>
    <td><strong style="font-weight:600">${escHtml(r.first_name)}</strong></td>
    <td>${escHtml(r.last_name || "—")}</td>
    <td class="cell-muted">${escHtml(r.member_type || "—")}</td>
    <td class="cell-muted" style="white-space:nowrap">${escHtml(r.apply_date || "—")}</td>
    <td class="cell-muted" style="text-align:center">${escHtml(r.age || "—")}</td>
    <td class="cell-muted">${escHtml(r.department || "—")}</td>
    <td class="cell-muted">${escHtml(r.office || "—")}</td>
    <td class="cell-muted">${escHtml(r.position || "—")}</td>
    <td class="cell-muted" style="white-space:nowrap">${escHtml(r.phone_mobile || "—")}</td>
    <td class="cell-muted">${escHtml(r.email || "—")}</td>
    <td><div class="actions">
      <button class="btn-sm btn-edit" onclick="openEditStudent(${r.id})"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      <button class="btn-sm btn-danger" onclick="deleteStudent(${r.id},'${escHtml(r.first_name + " " + (r.last_name || ""))}')"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
    </div></td>
  </tr>`,
      )
      .join("") ||
    '<tr><td colspan="13" style="text-align:center;color:var(--text3);padding:1.5rem">ไม่พบข้อมูล</td></tr>';

  document.getElementById("students-pagination").innerHTML = `
  <button class="page-btn" onclick="fetchStudentsRows(${studentsPage - 1})" ${studentsPage <= 1 ? "disabled" : ""}><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
  <span class="page-info">หน้า ${studentsPage} / ${totalPages}</span>
  <button class="page-btn" onclick="fetchStudentsRows(${studentsPage + 1})" ${studentsPage >= totalPages ? "disabled" : ""}><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>`;
}

async function fetchCoursesForSelect(selectedId = "") {
  const res = await fetch("backend/admin_api.php?action=list_courses");
  const result = await res.json();
  const courses = result.ok ? result.data : [];
  return `<select id="ef-course_id" style="width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:8px 11px;font-size:13px;font-family:'Sarabun',sans-serif;color:var(--text);outline:none;">
    ${courses.map((c) => `<option value="${c.id}" ${c.id == selectedId ? "selected" : ""}>${escHtml(c.short_name)} (${c.year_be || "—"})</option>`).join("")}
  </select>`;
}

async function openEditStudent(id) {
  const res = await fetch(`backend/admin_api.php?action=get_student&id=${id}`);
  const result = await res.json();
  if (!result.ok || !result.data) return;
  const r = result.data;
  editContext = { type: "student", id };
  const courseSelect = await fetchCoursesForSelect(r.course_id);
  document.getElementById("edit-modal-title").textContent =
    `แก้ไขข้อมูล: ${r.first_name} ${r.last_name || ""}`;
  document.getElementById("edit-modal-body").innerHTML =
    `<div class="edit-grid">
    <div class="edit-field full"><label>คอร์สที่ลงเรียน *</label>${courseSelect}</div>
    ${ef("first_name", "ชื่อ *", r.first_name)}${ef("last_name", "นามสกุล (ฉายา)", r.last_name)}
    ${ef("member_type", "ประเภทสมาชิก", r.member_type)}${ef("apply_date", "วันที่สมัคร", r.apply_date)}
    ${ef("birth_date", "วันเกิด", r.birth_date)}${ef("age", "อายุ", r.age)}
    ${ef("royal_title", "พรรษาที่", r.royal_title)}${ef("education_level", "ระดับการศึกษา", r.education_level)}
    ${ef("faculty", "คณะ", r.faculty)}${ef("major", "สาขา", r.major)}
    ${ef("institution", "สถาบัน", r.institution)}${ef("department", "หน่วยงาน", r.department)}
    ${ef("office", "สำนัก", r.office)}${ef("position", "ตำแหน่ง", r.position)}
    ${ef("phone_internal", "เบอร์ภายใน", r.phone_internal)}${ef("phone_mobile", "เบอร์มือถือ", r.phone_mobile)}
    ${ef("email", "Email", r.email, "full")}
    ${ef("head_status", "สถานะหัวหน้ากอง", r.head_status)}${ef("attendance", "การเข้าอบรม", r.attendance)}
  </div>`;
  document.getElementById("edit-modal-overlay").classList.add("open");
}

async function openAddStudent() {
  editContext = { type: "student", id: null };
  const courseSelect = await fetchCoursesForSelect("");
  document.getElementById("edit-modal-title").textContent = "เพิ่มรายชื่อผู้เรียนใหม่";
  document.getElementById("edit-modal-body").innerHTML =
    `<div class="edit-grid">
    <div class="edit-field full"><label>คอร์สที่ลงเรียน *</label>${courseSelect}</div>
    ${ef("first_name", "ชื่อ *", "")}${ef("last_name", "นามสกุล (ฉายา)", "")}
    ${ef("member_type", "ประเภทสมาชิก", "")}${ef("apply_date", "วันที่สมัคร", "")}
    ${ef("birth_date", "วันเกิด", "")}${ef("age", "อายุ", "")}
    ${ef("royal_title", "พรรษาที่", "")}${ef("education_level", "ระดับการศึกษา", "")}
    ${ef("faculty", "คณะ", "")}${ef("major", "สาขา", "")}
    ${ef("institution", "สถาบัน", "")}${ef("department", "หน่วยงาน", "")}
    ${ef("office", "สำนัก", "")}${ef("position", "ตำแหน่ง", "")}
    ${ef("phone_internal", "เบอร์ภายใน", "")}${ef("phone_mobile", "เบอร์มือถือ", "")}
    ${ef("email", "Email", "", "full")}
    ${ef("head_status", "สถานะหัวหน้ากอง", "")}${ef("attendance", "การเข้าอบรม", "")}
  </div>`;
  document.getElementById("edit-modal-overlay").classList.add("open");
}

function ef(field, label, value, extra = "") {
  return `<div class="edit-field ${extra}"><label>${label}</label><input type="text" id="ef-${field}" value="${escHtml(value || "")}"></div>`;
}

async function deleteStudent(id, name) {
  if (!confirm(`ยืนยันลบ "${name}"?`)) return;
  const fd = new FormData();
  fd.append("action", "delete_student");
  fd.append("id", id);
  const res = await fetch("backend/admin_api.php", {
    method: "POST",
    body: fd,
  });
  const result = await res.json();
  if (result.ok) loadStudentsTable();
  else alert("เกิดข้อผิดพลาด: " + result.error);
}

async function saveEdit() {
  if (!editContext) return;
  const btn = document.getElementById("btn-edit-save");
  btn.disabled = true;
  btn.textContent = "กำลังบันทึก...";
  const fd = new FormData();
  const isNew = editContext.id === null;
  const editType = editContext.type;
  if (editType === "course") {
    fd.append("action", isNew ? "add_course" : "update_course");
    if (!isNew) fd.append("id", editContext.id);
    ["short_name", "long_key", "training_date", "year_be", "verify_url"].forEach(
      (f) => fd.append(f, document.getElementById("ef-" + f)?.value || ""),
    );
  } else if (editType === "airtable_course") {
    // บันทึกแบบนำเข้าคอร์สจาก Airtable (สร้างคอร์สใหม่ถ้าจำเป็น + นำเข้านักเรียนที่เลือก)
    const checkedBoxes = document.querySelectorAll(
      "#airtable-modal-student-list input[type=checkbox]:checked",
    );
    const selectedIds = Array.from(checkedBoxes).map((cb) => cb.dataset.airtableId);

    if (selectedIds.length === 0) {
      alert("กรุณาเลือกนักเรียนอย่างน้อย 1 คน");
      btn.disabled = false;
      btn.innerHTML =
        '<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:#fff;fill:none;stroke-width:2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> บันทึก';
      return;
    }

    fd.append("action", "airtable_import_course");
    fd.append("course_name", editContext.courseName);
    fd.append("create_new", editContext.isNew ? "1" : "0");
    selectedIds.forEach((id) => fd.append("airtable_ids[]", id));

    if (editContext.isNew) {
      ["short_name", "long_key", "training_date", "year_be", "verify_url"].forEach(
        (f) => fd.append(f, document.getElementById("ef-" + f)?.value || ""),
      );
    }
  } else {
    fd.append("action", isNew ? "add_student" : "update_student");
    if (!isNew) fd.append("id", editContext.id);
    fd.append("course_id", document.getElementById("ef-course_id")?.value || "");
    [
      "first_name",
      "last_name",
      "member_type",
      "apply_date",
      "email",
      "phone_mobile",
      "phone_internal",
      "birth_date",
      "age",
      "royal_title",
      "education_level",
      "faculty",
      "major",
      "institution",
      "department",
      "office",
      "position",
      "head_status",
      "attendance",
    ].forEach((f) =>
      fd.append(f, document.getElementById("ef-" + f)?.value || ""),
    );
  }
  const res = await fetch("backend/admin_api.php", {
    method: "POST",
    body: fd,
  });
  const result = await res.json();
  btn.disabled = false;
  btn.innerHTML =
    '<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:#fff;fill:none;stroke-width:2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> บันทึก';
  if (result.ok) {
    closeEditModal();
    if (editType === "course") await loadCoursesTable();
    else if (editType === "airtable_course") {
      alert(`นำเข้าสำเร็จ ${result.imported} คน` + (result.skipped?.length ? ` (ข้าม ${result.skipped.length} คน)` : ""));
      await fetchAirtablePreview();
    } else loadStudentsTable();
  } else {
    alert("เกิดข้อผิดพลาด: " + result.error);
  }
}

function closeEditModal() {
  document.getElementById("edit-modal-overlay").classList.remove("open");
  editContext = null;
}
document
  .getElementById("edit-modal-overlay")
  .addEventListener("click", (e) => {
    if (e.target === document.getElementById("edit-modal-overlay"))
      closeEditModal();
  });

async function loadActiveCoursesAdmin() {
  const area = document.getElementById("admin-content-area");
  area.innerHTML =
    '<div style="color:var(--text3);padding:2rem;font-size:13px;">กำลังโหลด...</div>';

  const res = await fetch("backend/active_courses.php?action=list");
  const result = await res.json();
  if (!result.ok) {
    area.innerHTML = `<div style="color:var(--red);padding:2rem">❌ ${result.error}</div>`;
    return;
  }

  area.innerHTML = `
<div class="admin-page-title">
  <h2>เปิดรับสมัครคอร์ส</h2>
</div>
<div style="margin-bottom:1rem">
  <button class="btn-sm btn-primary" id="btn-sync-jotform" onclick="syncJotform()">
    <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:#fff;fill:none;stroke-width:2">
      <polyline points="23 4 23 10 17 10"/>
      <polyline points="1 20 1 14 7 14"/>
      <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
    </svg>Sync to JotForm
  </button>
  <span id="sync-status" style="margin-left:10px;font-size:12px;color:var(--text3)"></span>
</div>
<div class="table-card">
  <table class="data-table">
    <thead><tr>
      <th>ID</th><th>ชื่อคอร์ส</th><th>ปี พ.ศ.</th><th>เปิดรับสมัคร</th>
    </tr></thead>
    <tbody>
      ${result.data
        .map(
          (r) => `<tr>
        <td class="cell-id">${r.id}</td>
        <td><strong>${escHtml(r.short_name)}</strong></td>
        <td>${r.year_be || "—"}</td>
        <td>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" ${r.is_active ? "checked" : ""}
              onchange="toggleActiveCourse(${r.id}, this.checked)"
              style="width:16px;height:16px;cursor:pointer">
            <span style="font-size:12px;color:${r.is_active ? "var(--green)" : "var(--text3)"}">
              ${r.is_active ? "เปิดรับสมัคร" : "ปิด"}
            </span>
          </label>
        </td>
      </tr>`,
        )
        .join("")}
    </tbody>
  </table>
</div>`;
}

async function toggleActiveCourse(courseId, active) {
  const fd = new FormData();
  fd.append("action", "toggle");
  fd.append("course_id", courseId);
  fd.append("active", active);
  const res = await fetch("backend/active_courses.php", {
    method: "POST",
    body: fd,
  });
  const result = await res.json();
  if (!result.ok) alert("เกิดข้อผิดพลาด: " + result.error);
}

async function syncJotform() {
  const btn = document.getElementById("btn-sync-jotform");
  const status = document.getElementById("sync-status");
  btn.disabled = true;
  status.textContent = "กำลัง Sync...";
  try {
    const res = await fetch("backend/active_courses.php?action=sync_jotform");
    const result = await res.json();
    status.textContent = result.ok
      ? "✅ Sync สำเร็จ (" + (result.synced_courses || []).length + " คอร์ส)"
      : "❌ " + (result.error || "Sync ไม่สำเร็จ");
  } catch (err) {
    status.textContent = "❌ เกิดข้อผิดพลาด";
  }
  btn.disabled = false;
  setTimeout(() => (status.textContent = ""), 5000);
}

function footerAdminAction() {
  const isAdmin = sessionStorage.getItem("is_admin") === "true";
  if (isAdmin) adminLogout();
  else openLoginModal();
}

function escHtml(str) {
  return String(str || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

// ===== นำเข้าข้อมูลจาก Airtable (มุมมองกลุ่มคอร์ส) =====

let airtableData = { new_courses: [], existing_courses: [] };

function loadAirtableImportPage() {
  const area = document.getElementById("admin-content-area");
  area.innerHTML = `
<div class="admin-page-title">
  <h2>นำเข้าข้อมูลจาก Airtable</h2>
</div>
<p style="font-size:13px;color:var(--text3);margin-bottom:1rem;line-height:1.7">
  ดึงรายชื่อคอร์สและผู้สมัครจากระบบ Airtable เดิม เปรียบเทียบกับคอร์สที่มีอยู่แล้วในระบบนี้
  แล้วเลือกนำเข้าเป็นรายคอร์ส (คอร์สใหม่จะให้กรอกข้อมูลเพิ่มเติมก่อนสร้าง ส่วนคอร์สที่มีอยู่แล้วนำเข้านักเรียนได้ทันที)
</p>
<button class="btn-sm btn-primary" id="btn-airtable-fetch" onclick="fetchAirtablePreview()">
  <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:#fff;fill:none;stroke-width:2">
    <polyline points="23 4 23 10 17 10"/>
    <polyline points="1 20 1 14 7 14"/>
    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
  </svg>ดึงข้อมูลจาก Airtable
</button>
<div id="airtable-content" style="margin-top:1.2rem"></div>`;
}

async function fetchAirtablePreview() {
  const btn = document.getElementById("btn-airtable-fetch");
  const content = document.getElementById("airtable-content");
  btn.disabled = true;
  btn.textContent = "กำลังดึงข้อมูล...";
  content.innerHTML =
    '<div style="color:var(--text3);padding:1rem;font-size:13px;">กำลังโหลด...</div>';

  try {
    const res = await fetch("backend/admin_api.php?action=airtable_preview");
    const result = await res.json();
    if (!result.ok) {
      content.innerHTML = `<div style="color:var(--red);padding:1rem;font-size:13px;">❌ ${result.error}</div>`;
      return;
    }
    airtableData = result;
    renderAirtableCourseGroups();
  } catch (e) {
    content.innerHTML = `<div style="color:var(--red);padding:1rem;font-size:13px;">❌ เชื่อมต่อไม่สำเร็จ: ${e.message}</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML =
      '<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:#fff;fill:none;stroke-width:2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>ดึงข้อมูลจาก Airtable';
  }
}

function renderAirtableCourseGroups() {
  const content = document.getElementById("airtable-content");
  const { new_courses, existing_courses } = airtableData;

  content.innerHTML = `
<div style="display:flex;gap:8px;align-items:center;margin-bottom:1rem;flex-wrap:wrap">
  <span class="badge-count" style="color:var(--green)">คอร์สใหม่ ${new_courses.length} คอร์ส</span>
  <span class="badge-count">คอร์สที่มีอยู่แล้ว ${existing_courses.length} คอร์ส</span>
</div>

<h3 style="font-size:14px;margin:1rem 0 0.6rem;color:var(--text)">คอร์สใหม่ที่ยังไม่มีในระบบ</h3>
<div class="table-card"><div style="overflow-x:auto">
<table class="data-table">
  <thead><tr><th>ชื่อคอร์ส (จาก Airtable)</th><th>จำนวนผู้สมัคร</th><th>ยังไม่ได้นำเข้า</th><th>จัดการ</th></tr></thead>
  <tbody>
    ${
      new_courses.length
        ? new_courses
            .map(
              (c) => `<tr>
        <td><strong style="font-weight:600">${escHtml(c.course_name)}</strong></td>
        <td class="cell-muted">${c.total_count} คน</td>
        <td>${c.pending_count > 0 ? `<span style="color:var(--green);font-size:12px">${c.pending_count} คนใหม่</span>` : '<span class="cell-muted">—</span>'}</td>
        <td><button class="btn-sm btn-primary" onclick='openAirtableCourseModal(${JSON.stringify(c.course_name)}, true)'>สร้างคอร์ส + นำเข้า</button></td>
      </tr>`,
            )
            .join("")
        : '<tr><td colspan="4" style="text-align:center;color:var(--text3);padding:1.2rem">ไม่พบคอร์สใหม่</td></tr>'
    }
  </tbody>
</table></div></div>

<h3 style="font-size:14px;margin:1.4rem 0 0.6rem;color:var(--text)">คอร์สที่มีอยู่แล้วในระบบ</h3>
<div class="table-card"><div style="overflow-x:auto">
<table class="data-table">
  <thead><tr><th>ชื่อคอร์ส (จาก Airtable)</th><th>จำนวนผู้สมัคร</th><th>ยังไม่ได้นำเข้า</th><th>จัดการ</th></tr></thead>
  <tbody>
    ${
      existing_courses.length
        ? existing_courses
            .map(
              (c) => `<tr>
        <td><strong style="font-weight:600">${escHtml(c.course_name)}</strong></td>
        <td class="cell-muted">${c.total_count} คน</td>
        <td>${c.pending_count > 0 ? `<span style="color:var(--green);font-size:12px">${c.pending_count} คนใหม่</span>` : '<span class="cell-muted">ไม่มีคนใหม่</span>'}</td>
        <td>${
          c.pending_count > 0
            ? `<button class="btn-sm btn-primary" onclick='openAirtableCourseModal(${JSON.stringify(c.course_name)}, false)'>นำเข้านักเรียน</button>`
            : '<span class="cell-muted" style="font-size:12px">—</span>'
        }</td>
      </tr>`,
            )
            .join("")
        : '<tr><td colspan="4" style="text-align:center;color:var(--text3);padding:1.2rem">ไม่พบคอร์สที่มีอยู่แล้ว</td></tr>'
    }
  </tbody>
</table></div></div>`;
}

function findAirtableCourseGroup(courseName, isNew) {
  const list = isNew ? airtableData.new_courses : airtableData.existing_courses;
  return list.find((c) => c.course_name === courseName);
}

function openAirtableCourseModal(courseName, isNew) {
  const group = findAirtableCourseGroup(courseName, isNew);
  if (!group) return;

  editContext = { type: "airtable_course", courseName, isNew };

  document.getElementById("edit-modal-title").textContent = isNew
    ? `สร้างคอร์สใหม่: ${courseName}`
    : `นำเข้านักเรียน: ${courseName}`;

  const courseFieldsHtml = isNew
    ? `<div class="edit-grid" style="margin-bottom:1rem">
        <div class="edit-field full"><label>ชื่อย่อ (short_name) *</label><input type="text" id="ef-short_name" value="${escHtml(courseName)}"></div>
        <div class="edit-field full"><label>ชื่อยาว (long_key) *</label><input type="text" id="ef-long_key" value="${escHtml(courseName)}"></div>
        <div class="edit-field full"><label>วันที่อบรม</label><input type="text" id="ef-training_date" placeholder="เช่น วันที่ 1-3 ม.ค. 2568"></div>
        <div class="edit-field"><label>ปี พ.ศ.</label><input type="text" id="ef-year_be" placeholder="เช่น 2568" maxlength="4" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div>
        <div class="edit-field"><label>Verify URL (QR code)</label><input type="text" id="ef-verify_url" placeholder="https://..."></div>
      </div>`
    : "";

  const studentRowsHtml = group.students
    .map((s) => {
      const disabled = s.already_imported;
      return `<tr style="${disabled ? "opacity:0.5" : ""}">
        <td><input type="checkbox" data-airtable-id="${s.airtable_id}" ${disabled ? "disabled" : "checked"}
              style="width:15px;height:15px;cursor:${disabled ? "default" : "pointer"}"></td>
        <td><strong style="font-weight:600">${escHtml(s.fields.first_name)}</strong></td>
        <td>${escHtml(s.fields.last_name || "—")}</td>
        <td class="cell-muted">${escHtml(s.fields.apply_date || "—")}</td>
        <td class="cell-muted">${escHtml(s.fields.email || "—")}</td>
        <td>${disabled ? '<span style="color:var(--text3);font-size:12px">นำเข้าแล้ว</span>' : '<span style="color:var(--green);font-size:12px">ใหม่</span>'}</td>
      </tr>`;
    })
    .join("");

  document.getElementById("edit-modal-body").innerHTML = `
    ${courseFieldsHtml}
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:0.6rem">
      <span style="font-size:13px;color:var(--text3)">รายชื่อผู้สมัคร (${group.total_count} คน)</span>
      <div style="flex:1"></div>
      <button type="button" class="btn-sm btn-edit" onclick="toggleSelectAllAirtableModal(true)">เลือกทั้งหมด</button>
      <button type="button" class="btn-sm btn-edit" onclick="toggleSelectAllAirtableModal(false)">ไม่เลือกเลย</button>
    </div>
    <div class="table-card"><div style="overflow-x:auto;max-height:320px;overflow-y:auto">
    <table class="data-table" id="airtable-modal-student-list">
      <thead><tr><th></th><th>ชื่อ</th><th>นามสกุล (ฉายา)</th><th>วันที่สมัคร</th><th>อีเมล</th><th>สถานะ</th></tr></thead>
      <tbody>${studentRowsHtml}</tbody>
    </table></div></div>`;

  document.getElementById("edit-modal-overlay").classList.add("open");
}

function toggleSelectAllAirtableModal(select) {
  document
    .querySelectorAll("#airtable-modal-student-list input[type=checkbox]:not(:disabled)")
    .forEach((cb) => (cb.checked = select));
}