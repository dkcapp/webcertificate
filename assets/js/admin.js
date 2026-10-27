// assets/js/admin.js
// ตรรกะหน้า Admin Panel ทั้งหมด: จัดการคอร์ส, จัดการรายชื่อผู้เรียน, เปิดรับสมัคร + sync JotForm, คำขออนุมัติ

// ===== Toast Notification (แทนที่ alert() แบบเดิม) =====
const TOAST_ICONS = {
  success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
  error: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
  info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
};

function ensureToastContainer() {
  let el = document.getElementById("toast-container");
  if (!el) {
    el = document.createElement("div");
    el.id = "toast-container";
    document.body.appendChild(el);
  }
  return el;
}

function showToast(message, type = "success", title = "") {
  const container = ensureToastContainer();
  const defaultTitles = { success: "สำเร็จ", error: "เกิดข้อผิดพลาด", info: "แจ้งเตือน" };
  const toastTitle = title || defaultTitles[type] || "";

  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <div class="toast-icon"><svg viewBox="0 0 24 24">${TOAST_ICONS[type] || TOAST_ICONS.info}</svg></div>
    <div class="toast-body">
      <div class="toast-title">${escHtml(toastTitle)}</div>
      <div class="toast-message">${escHtml(message)}</div>
    </div>
    <button class="toast-close" onclick="dismissToast(this.closest('.toast'))">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>`;

  container.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("show"));

  const timer = setTimeout(() => dismissToast(toast), 4000);
  toast.dataset.timer = timer;
}

function dismissToast(toast) {
  if (!toast) return;
  clearTimeout(toast.dataset.timer);
  toast.classList.remove("show");
  toast.classList.add("hide");
  setTimeout(() => toast.remove(), 300);
}

let currentTab = "download";
let currentAdminSection = "courses";
let editContext = null;

function switchTab(tab) {
  currentTab = tab;
  document
    .getElementById("tab-download")
    .classList.toggle("active", tab === "download");
  document
    .getElementById("tab-manage")
    .classList.toggle("active", tab === "manage");
  document
    .getElementById("tab-approvals")
    .classList.toggle("active", tab === "approvals");
  const dlView = document.getElementById("view-download");
  const adminView = document.getElementById("admin-data-view");
  const apView = document.getElementById("view-approvals");
  dlView.style.display = tab === "download" ? "flex" : "none";
  adminView.classList.toggle("visible", tab === "manage");
  apView.classList.toggle("visible", tab === "approvals");
  if (tab === "manage") {
    if (currentAdminSection === "courses") loadCoursesTable();
    else loadStudentsTable();
  } else if (tab === "approvals") {
    loadApprovalsPage();
  }
}

// ===== แท็บคำขออนุมัติ (อ่านจาก Neon: approval_requests) =====
// ตัวกรอง: สถานะ / ค้นหา / ช่วงวันที่ / คอร์ส / ปี พ.ศ. — ดูรายละเอียด, ลบทีละรายการหรือหลายรายการ,
// ตรวจสถานะล่าสุดจาก CAS และส่งออก CSV (ไม่มีการแก้ไขข้อมูลคำขอ)
const AP_EMPTY_FILTERS = { q: "", status: "", course: "", year_be: "", date_from: "", date_to: "" };
let approvalFilters = { ...AP_EMPTY_FILTERS };
let approvalSearchTimer = null;
let apPerPage = 20;
let apPage = 1;
let apItems = [];
let apSelected = new Set();
const AP_STATUS_LABEL = { approved: "อนุมัติแล้ว", rejected: "ไม่อนุมัติ", pending: "รออนุมัติ" };
const AP_ICON = {
  trash: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
  eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
  refresh: '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
  x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
  search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
};
const apSvg = (name) => `<svg viewBox="0 0 24 24">${AP_ICON[name]}</svg>`;

function formatThaiDateTime(utcStr) {
  if (!utcStr) return "—";
  const d = new Date(String(utcStr).replace(" ", "T") + (/[zZ]|[+-]\d\d:?\d\d$/.test(utcStr) ? "" : "Z"));
  if (isNaN(d)) return escHtml(String(utcStr));
  return d.toLocaleString("th-TH", {
    timeZone: "Asia/Bangkok",
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function apStatusKind(status) {
  const st = String(status || "").toUpperCase();
  return st.startsWith("APPROVE") ? "approved" : st.startsWith("REJECT") ? "rejected" : "pending";
}

function loadApprovalsPage() {
  const f = approvalFilters;
  const area = document.getElementById("approvals-content-area");
  area.innerHTML = `
<div class="admin-page-title">
  <h2>คำขออนุมัติ</h2>
  <span class="badge-count" id="ap-count-badge">0 รายการ</span>
  <div class="ap-title-actions">
    <button class="btn-sm btn-edit" onclick="fetchApprovals(apPage)" title="โหลดข้อมูลใหม่">${apSvg("refresh")} รีเฟรช</button>
    <button class="btn-sm btn-primary admin-write" onclick="exportApprovals()" title="ส่งออกตามตัวกรองปัจจุบัน">${apSvg("download")} ส่งออก CSV</button>
  </div>
</div>

<div class="ap-stat-grid" id="ap-stats">
  ${["", "pending", "approved", "rejected"].map((k) => `
  <button type="button" class="ap-stat ${k || "all"}" data-status="${k}" onclick="setApprovalStatus('${k}')">
    <span class="ap-stat-label">${k ? AP_STATUS_LABEL[k] : "ทั้งหมด"}</span>
    <span class="ap-stat-num" id="ap-stat-${k || "all"}">—</span>
    <span class="ap-stat-bar"><span id="ap-bar-${k || "all"}"></span></span>
  </button>`).join("")}
</div>

<div class="ap-filter-panel">
  <div class="admin-search ap-search">
    ${apSvg("search")}
    <input type="text" id="ap-search" placeholder="ค้นหาชื่อผู้สมัคร / ผู้ขอ / หัวหน้ากอง / รหัสอ้างอิง..." oninput="onApprovalSearchInput()" autocomplete="off" value="${escHtml(f.q)}">
  </div>
  <div class="ap-filter-fields">
    <label class="ap-field"><span>ตั้งแต่วันที่</span>
      <input type="date" id="ap-date-from" class="ap-date" value="${escHtml(f.date_from)}" onchange="onApprovalFilterChange()"></label>
    <label class="ap-field"><span>ถึงวันที่</span>
      <input type="date" id="ap-date-to" class="ap-date" value="${escHtml(f.date_to)}" onchange="onApprovalFilterChange()"></label>
    <label class="ap-field"><span>ช่วงเวลาด่วน</span>
      <select id="ap-quick-range" class="year-filter-select" onchange="applyApprovalQuickRange(this.value)">
        <option value="">— เลือก —</option>
        <option value="today">วันนี้</option>
        <option value="7">7 วันล่าสุด</option>
        <option value="30">30 วันล่าสุด</option>
        <option value="month">เดือนนี้</option>
      </select></label>
    <label class="ap-field ap-field-wide"><span>คอร์ส</span>
      <select id="ap-course" class="year-filter-select" onchange="onApprovalFilterChange()"><option value="">ทุกคอร์ส</option></select></label>
    <label class="ap-field"><span>ปี (พ.ศ.)</span>
      <select id="ap-year" class="year-filter-select" onchange="onApprovalFilterChange()"><option value="">ทุกปี</option></select></label>
    <label class="ap-field"><span>แสดงต่อหน้า</span>
      <select id="ap-per-page" class="year-filter-select" onchange="apPerPage = +this.value; fetchApprovals(1)">
        ${[20, 50, 100].map((n) => `<option value="${n}" ${apPerPage === n ? "selected" : ""}>${n}</option>`).join("")}
      </select></label>
  </div>
  <div class="ap-active-filters" id="ap-active-filters"></div>
</div>

<div class="ap-bulk-bar admin-write" id="ap-bulk-bar" hidden>
  <span id="ap-bulk-count">เลือก 0 รายการ</span>
  <div class="ap-bulk-actions">
    <button class="btn-sm btn-edit" onclick="refreshApprovalStatus([...apSelected])" title="ถามสถานะล่าสุดจาก CAS">${apSvg("refresh")} ตรวจสถานะจาก CAS</button>
    <button class="btn-sm btn-danger" onclick="confirmDeleteApprovals([...apSelected])">${apSvg("trash")} ลบที่เลือก</button>
    <button class="btn-sm ap-btn-ghost" onclick="clearApprovalSelection()">ยกเลิกการเลือก</button>
  </div>
</div>

<div class="table-card"><div style="overflow-x:auto">
<table class="data-table ap-table">
  <thead><tr>
    <th class="ap-col-check admin-write"><input type="checkbox" id="ap-check-all" onchange="toggleApprovalSelectAll(this.checked)" title="เลือกทั้งหมดในหน้านี้"></th>
    <th>วันที่ขอ</th><th>ผู้สมัคร</th><th>คอร์ส</th><th>ประเภท</th>
    <th>ผู้ขอ (AD)</th><th>หัวหน้ากอง</th><th>สถานะ</th><th>อัปเดตล่าสุด</th><th></th>
  </tr></thead>
  <tbody id="ap-tbody"></tbody>
</table></div></div>
<div class="pagination" id="ap-pagination"></div>`;

  document.getElementById("ap-tbody").addEventListener("click", onApprovalRowClick);
  document.getElementById("ap-tbody").addEventListener("change", onApprovalRowCheck);
  fetchApprovals(apPage);
}

function onApprovalSearchInput() {
  clearTimeout(approvalSearchTimer);
  approvalSearchTimer = setTimeout(() => {
    approvalFilters.q = document.getElementById("ap-search").value.trim();
    fetchApprovals(1);
  }, 350);
}

function onApprovalFilterChange() {
  const val = (id) => (document.getElementById(id)?.value || "").trim();
  approvalFilters.date_from = val("ap-date-from");
  approvalFilters.date_to = val("ap-date-to");
  approvalFilters.course = val("ap-course");
  approvalFilters.year_be = val("ap-year");
  // วันที่เริ่มต้องไม่เกินวันที่สิ้นสุด — ถ้ากรอกกลับด้านให้สลับให้
  if (approvalFilters.date_from && approvalFilters.date_to && approvalFilters.date_from > approvalFilters.date_to) {
    [approvalFilters.date_from, approvalFilters.date_to] = [approvalFilters.date_to, approvalFilters.date_from];
    document.getElementById("ap-date-from").value = approvalFilters.date_from;
    document.getElementById("ap-date-to").value = approvalFilters.date_to;
  }
  const quick = document.getElementById("ap-quick-range");
  if (quick) quick.value = "";
  fetchApprovals(1);
}

// วันที่ตามเวลาไทย รูปแบบ YYYY-MM-DD (ตรงกับค่า input type=date)
function apThaiDate(d) {
  return d.toLocaleDateString("sv-SE", { timeZone: "Asia/Bangkok" });
}

function applyApprovalQuickRange(kind) {
  if (!kind) return;
  const now = new Date();
  let from = new Date(now);
  if (kind === "7") from.setDate(now.getDate() - 6);
  else if (kind === "30") from.setDate(now.getDate() - 29);
  else if (kind === "month") from = new Date(apThaiDate(now).slice(0, 8) + "01T00:00:00+07:00");
  approvalFilters.date_from = apThaiDate(from);
  approvalFilters.date_to = apThaiDate(now);
  document.getElementById("ap-date-from").value = approvalFilters.date_from;
  document.getElementById("ap-date-to").value = approvalFilters.date_to;
  fetchApprovals(1);
}

function setApprovalStatus(status) {
  approvalFilters.status = status;
  fetchApprovals(1);
}

function clearApprovalFilter(key) {
  if (key === "all") approvalFilters = { ...AP_EMPTY_FILTERS };
  else approvalFilters[key] = "";
  loadApprovalsPage(); // วาดใหม่ให้ช่องกรอกตรงกับค่าในตัวกรอง
}

function renderApprovalFilterTags() {
  const f = approvalFilters;
  const fmtDate = (s) => new Date(s + "T00:00:00").toLocaleDateString("th-TH", { day: "numeric", month: "short", year: "numeric" });
  const tags = [];
  if (f.status) tags.push(["status", "สถานะ: " + AP_STATUS_LABEL[f.status]]);
  if (f.q) tags.push(["q", "ค้นหา: " + f.q]);
  if (f.date_from) tags.push(["date_from", "ตั้งแต่ " + fmtDate(f.date_from)]);
  if (f.date_to) tags.push(["date_to", "ถึง " + fmtDate(f.date_to)]);
  if (f.course) tags.push(["course", "คอร์ส: " + f.course]);
  if (f.year_be) tags.push(["year_be", "ปี " + f.year_be]);
  const box = document.getElementById("ap-active-filters");
  if (!box) return;
  box.innerHTML = tags.length
    ? tags.map(([k, label]) => `<span class="ap-tag">${escHtml(label)}<button type="button" onclick="clearApprovalFilter('${k}')" title="ล้างตัวกรองนี้">${apSvg("x")}</button></span>`).join("") +
      `<button type="button" class="ap-tag-clear" onclick="clearApprovalFilter('all')">ล้างตัวกรองทั้งหมด</button>`
    : "";
}

function apFilterParams() {
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries(approvalFilters)) if (v) params.set(k, v);
  return params;
}

function fillApprovalSelect(id, values, current, allLabel) {
  const sel = document.getElementById(id);
  if (!sel) return;
  const list = values.map(String);
  // ค่าที่เลือกไว้แต่ไม่มีในรายการแล้ว (เช่น ลบคำขอสุดท้ายของคอร์สนั้น) ยังคงแสดงไว้
  if (current && !list.includes(current)) list.unshift(current);
  sel.innerHTML = `<option value="">${allLabel}</option>` +
    list.map((v) => `<option value="${escHtml(v)}" ${v === current ? "selected" : ""}>${escHtml(v)}</option>`).join("");
}

async function fetchApprovals(page = 1) {
  const tbody = document.getElementById("ap-tbody");
  if (!tbody) return;
  tbody.innerHTML =
    '<tr><td colspan="10" style="text-align:center;color:var(--text3);padding:1.5rem">กำลังโหลด...</td></tr>';
  const params = apFilterParams();
  params.set("action", "list_approval_requests");
  params.set("page", page);
  params.set("per_page", apPerPage);
  let result;
  try {
    const res = await fetch("backend/admin_api.php?" + params.toString());
    result = await res.json();
  } catch (e) {
    result = { ok: false, error: "โหลดข้อมูลไม่สำเร็จ" };
  }
  renderApprovalFilterTags();
  if (!result.ok) {
    tbody.innerHTML = `<tr><td colspan="10" style="color:var(--red);padding:1.5rem;text-align:center">❌ ${escHtml(result.error || "เกิดข้อผิดพลาด")}</td></tr>`;
    return;
  }
  const d = result.data;
  const totalPages = Math.max(1, Math.ceil((d.total || 0) / (d.per_page || apPerPage)));
  if (page > totalPages && d.total > 0) return fetchApprovals(totalPages); // หน้าเกินหลังลบ → ไปหน้าสุดท้าย

  apPage = d.page || page;
  apItems = d.items || [];
  apSelected.clear();
  updateApprovalBulkBar();

  // การ์ดสรุป (นับตามตัวกรองอื่น ไม่รวมสถานะ)
  const s = d.summary || {};
  const total = s.total || 0;
  for (const k of ["all", "pending", "approved", "rejected"]) {
    const n = k === "all" ? total : s[k] || 0;
    document.getElementById("ap-stat-" + k).textContent = n.toLocaleString("th-TH");
    document.getElementById("ap-bar-" + k).style.width = (total ? (n / total) * 100 : 0) + "%";
  }
  document.querySelectorAll("#ap-stats .ap-stat").forEach((el) =>
    el.classList.toggle("active", el.dataset.status === approvalFilters.status));
  document.getElementById("ap-count-badge").textContent = `${(d.total || 0).toLocaleString("th-TH")} รายการ`;

  const opt = d.options || {};
  fillApprovalSelect("ap-course", opt.courses || [], approvalFilters.course, "ทุกคอร์ส");
  fillApprovalSelect("ap-year", opt.years || [], approvalFilters.year_be, "ทุกปี");

  const hasFilter = Object.values(approvalFilters).some(Boolean);
  tbody.innerHTML =
    apItems
      .map((r) => {
        const kind = apStatusKind(r.status);
        const ref = escHtml(r.external_ref_id);
        return `<tr data-ref="${ref}">
    <td class="ap-col-check admin-write"><input type="checkbox" class="ap-row-check" value="${ref}"></td>
    <td style="white-space:nowrap">${formatThaiDateTime(r.created_at)}</td>
    <td><div class="ap-name">${escHtml(r.applicant_name || "—")}</div><div class="cell-id">${ref}</div></td>
    <td class="ap-course-cell">${escHtml(r.course || "—")}</td>
    <td class="cell-muted">${escHtml(r.member_type || "—")}</td>
    <td>${escHtml(r.requester_name || r.requester_ad || "—")}<div class="cell-muted" style="font-size:11px">${escHtml(r.requester_ad || "")}</div></td>
    <td>${escHtml(r.approver_name || r.approver_ad || "—")}<div class="cell-muted" style="font-size:11px">${escHtml(r.approver_ad || "")}</div></td>
    <td><span class="ap-status ${kind}">${AP_STATUS_LABEL[kind]}</span></td>
    <td style="white-space:nowrap">${formatThaiDateTime(r.updated_at)}</td>
    <td><div class="actions">
      <button class="btn-sm btn-edit" data-act="view" title="ดูรายละเอียด">${apSvg("eye")}</button>
      <button class="btn-sm btn-danger admin-write" data-act="delete" title="ลบ">${apSvg("trash")}</button>
    </div></td>
  </tr>`;
      })
      .join("") ||
    `<tr><td colspan="10" style="text-align:center;color:var(--text3);padding:1.5rem">${hasFilter ? "ไม่พบคำขอที่ตรงกับตัวกรอง" : "ยังไม่มีคำขออนุมัติ"}</td></tr>`;

  const from = d.total ? (apPage - 1) * d.per_page + 1 : 0;
  const to = Math.min(d.total, apPage * d.per_page);
  document.getElementById("ap-pagination").innerHTML = `
  <button class="page-btn" onclick="fetchApprovals(${apPage - 1})" ${apPage <= 1 ? "disabled" : ""}><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
  <span class="page-info">หน้า ${apPage} / ${totalPages} · แสดง ${from}–${to} จาก ${d.total}</span>
  <button class="page-btn" onclick="fetchApprovals(${apPage + 1})" ${apPage >= totalPages ? "disabled" : ""}><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>`;
}

function onApprovalRowClick(e) {
  const btn = e.target.closest("button[data-act]");
  if (!btn) return;
  const ref = btn.closest("tr").dataset.ref;
  if (btn.dataset.act === "view") openApprovalDetail(ref);
  else if (btn.dataset.act === "delete") confirmDeleteApprovals([ref]);
}

function onApprovalRowCheck(e) {
  if (!e.target.classList.contains("ap-row-check")) return;
  if (e.target.checked) apSelected.add(e.target.value);
  else apSelected.delete(e.target.value);
  updateApprovalBulkBar();
}

function toggleApprovalSelectAll(checked) {
  document.querySelectorAll("#ap-tbody .ap-row-check").forEach((cb) => {
    cb.checked = checked;
    if (checked) apSelected.add(cb.value);
    else apSelected.delete(cb.value);
  });
  updateApprovalBulkBar();
}

function clearApprovalSelection() {
  toggleApprovalSelectAll(false);
}

function updateApprovalBulkBar() {
  const n = apSelected.size;
  const bar = document.getElementById("ap-bulk-bar");
  if (bar) bar.hidden = n === 0;
  const cnt = document.getElementById("ap-bulk-count");
  if (cnt) cnt.textContent = `เลือก ${n} รายการ`;
  document.querySelectorAll("#ap-tbody tr[data-ref]").forEach((tr) =>
    tr.classList.toggle("ap-selected", apSelected.has(tr.dataset.ref)));
  const all = document.getElementById("ap-check-all");
  const boxes = document.querySelectorAll("#ap-tbody .ap-row-check").length;
  if (all) {
    all.checked = boxes > 0 && n === boxes;
    all.indeterminate = n > 0 && n < boxes;
  }
}

// ===== Modal ของแท็บคำขออนุมัติ (สร้างครั้งละ 1 อัน ปิดด้วย Esc / คลิกพื้นหลัง) =====
function openApprovalModal(html, wide = false) {
  closeApprovalModal();
  const ov = document.createElement("div");
  ov.className = "modal-overlay open";
  ov.id = "ap-modal";
  ov.innerHTML = `<div class="modal-box ${wide ? "ap-modal-wide" : ""}">${html}</div>`;
  ov.addEventListener("click", (e) => { if (e.target === ov) closeApprovalModal(); });
  document.addEventListener("keydown", apModalEsc);
  document.body.appendChild(ov);
  return ov;
}

function apModalEsc(e) {
  if (e.key === "Escape") closeApprovalModal();
}

function closeApprovalModal() {
  document.getElementById("ap-modal")?.remove();
  document.removeEventListener("keydown", apModalEsc);
}

function confirmDeleteApprovals(refs) {
  if (!refs.length) return;
  const names = refs.map((ref) => apItems.find((r) => r.external_ref_id === ref)?.applicant_name || ref);
  const preview = names.slice(0, 5).map((n) => `<li>${escHtml(n)}</li>`).join("") +
    (names.length > 5 ? `<li class="cell-muted">และอีก ${names.length - 5} รายการ</li>` : "");
  const ov = openApprovalModal(`
  <div class="modal-icon ap-icon-danger">${apSvg("trash")}</div>
  <div>
    <div class="modal-title">ยืนยันลบคำขออนุมัติ ${refs.length} รายการ</div>
    <div class="modal-sub">ลบแล้วกู้คืนไม่ได้ · ลบเฉพาะข้อมูลในเว็บนี้ ไม่ได้ยกเลิกคำขอที่ส่งถึงหัวหน้ากองแล้ว</div>
  </div>
  <ul class="ap-delete-list">${preview}</ul>
  <div class="ap-modal-btns">
    <button class="btn-modal-cancel" onclick="closeApprovalModal()">ยกเลิก</button>
    <button class="btn-sm btn-danger ap-btn-confirm" id="ap-confirm-delete">${apSvg("trash")} ลบ ${refs.length} รายการ</button>
  </div>`);
  ov.querySelector("#ap-confirm-delete").onclick = () => deleteApprovals(refs, ov.querySelector("#ap-confirm-delete"));
}

async function deleteApprovals(refs, btn) {
  btn.disabled = true;
  btn.textContent = "กำลังลบ...";
  const fd = new FormData();
  fd.append("action", "delete_approvals");
  refs.forEach((r) => fd.append("refs[]", r));
  let result;
  try {
    const res = await fetch("backend/admin_api.php", { method: "POST", body: fd });
    result = await res.json();
  } catch (e) {
    result = { ok: false, error: "เชื่อมต่อเซิร์ฟเวอร์ไม่ได้" };
  }
  closeApprovalModal();
  if (result.ok) {
    showToast(`ลบคำขออนุมัติแล้ว ${result.deleted} รายการ`);
    fetchApprovals(apPage);
  } else {
    showToast(result.error || "ลบไม่สำเร็จ", "error");
  }
}

async function refreshApprovalStatus(refs, btn) {
  if (!refs.length) return;
  if (refs.length > 50) {
    showToast("ตรวจสถานะได้ครั้งละไม่เกิน 50 รายการ", "error");
    return;
  }
  if (btn) btn.disabled = true;
  showToast(`กำลังตรวจสถานะ ${refs.length} รายการจาก CAS...`, "info");
  const fd = new FormData();
  fd.append("action", "refresh_approval_status");
  refs.forEach((r) => fd.append("refs[]", r));
  let result;
  try {
    const res = await fetch("backend/admin_api.php", { method: "POST", body: fd });
    result = await res.json();
  } catch (e) {
    result = { ok: false, error: "เชื่อมต่อเซิร์ฟเวอร์ไม่ได้" };
  }
  if (btn) btn.disabled = false;
  if (!result.ok) {
    showToast(result.error || "ตรวจสถานะไม่สำเร็จ", "error");
    return;
  }
  showToast(
    `อัปเดตสถานะแล้ว ${result.updated} รายการ` + (result.failed ? ` · ถาม CAS ไม่สำเร็จ ${result.failed} รายการ` : ""),
    result.failed ? "info" : "success"
  );
  await fetchApprovals(apPage);
  if (document.getElementById("ap-modal") && refs.length === 1) openApprovalDetail(refs[0]);
}

async function openApprovalDetail(ref) {
  openApprovalModal('<div class="cell-muted" style="text-align:center;padding:1rem">กำลังโหลด...</div>', true);
  let result;
  try {
    const res = await fetch("backend/admin_api.php?action=get_approval&ref=" + encodeURIComponent(ref));
    result = await res.json();
  } catch (e) {
    result = { ok: false, error: "โหลดข้อมูลไม่สำเร็จ" };
  }
  if (!result.ok) {
    closeApprovalModal();
    showToast(result.error || "โหลดข้อมูลไม่สำเร็จ", "error");
    return;
  }
  const r = result.data;
  const kind = r.status_kind || apStatusKind(r.status);
  const row = (label, value) =>
    `<div class="ap-dt">${label}</div><div class="ap-dd">${value === undefined || value === null || value === "" ? '<span class="cell-muted">—</span>' : value}</div>`;
  const ov = openApprovalModal(`
  <div class="ap-detail-head">
    <div>
      <div class="modal-title">${escHtml(r.applicant_name || "—")}</div>
      <div class="cell-id">${escHtml(r.external_ref_id)}</div>
    </div>
    <span class="ap-status ${kind}">${AP_STATUS_LABEL[kind]}</span>
  </div>
  <div class="ap-detail-section">ข้อมูลการสมัคร</div>
  <div class="ap-dl">
    ${row("หลักสูตร", escHtml(r.course))}
    ${row("ประเภทสมาชิก", escHtml(r.member_type))}
    ${row("ผู้เรียนในระบบ", r.student_id ? `#${escHtml(r.student_id)} <span class="cell-muted">(จับคู่จากชื่อ+คอร์ส)</span>` : '<span class="cell-muted">ยังจับคู่ไม่ได้</span>')}
  </div>
  <div class="ap-detail-section">ผู้ขอ / หัวหน้ากอง</div>
  <div class="ap-dl">
    ${row("ผู้ขอ (AD)", escHtml([r.requester_name, r.requester_ad && `(${r.requester_ad})`].filter(Boolean).join(" ")))}
    ${row("หัวหน้ากอง", escHtml([r.approver_name, r.approver_ad && `(${r.approver_ad})`].filter(Boolean).join(" ")))}
    ${row("อีเมล", escHtml(r.approver_email))}
    ${row("ตำแหน่ง", escHtml(r.approver_position))}
    ${row("สังกัด", escHtml(r.approver_org))}
  </div>
  <div class="ap-detail-section">สถานะ (CAS)</div>
  <div class="ap-dl">
    ${row("สถานะดิบ", escHtml(r.status))}
    ${row("ขั้นอนุมัติ", escHtml(r.approve_step))}
    ${row("RequestKey", escHtml(r.request_key))}
    ${row("วันที่ขอ", formatThaiDateTime(r.created_at))}
    ${row("อัปเดตล่าสุด", formatThaiDateTime(r.updated_at))}
  </div>
  <div class="ap-detail-section">ข้อความที่ส่งถึงหัวหน้ากอง</div>
  <div class="ap-message"><b>${escHtml(r.subject)}</b>\n${escHtml(r.message)}</div>
  <div class="ap-modal-btns">
    <button class="btn-sm btn-danger admin-write" id="ap-d-delete">${apSvg("trash")} ลบ</button>
    <button class="btn-sm btn-edit admin-write" id="ap-d-refresh">${apSvg("refresh")} ตรวจสถานะจาก CAS</button>
    <button class="btn-modal-cancel" onclick="closeApprovalModal()">ปิด</button>
  </div>`, true);
  ov.querySelector("#ap-d-delete").onclick = () => confirmDeleteApprovals([r.external_ref_id]);
  ov.querySelector("#ap-d-refresh").onclick = (e) => refreshApprovalStatus([r.external_ref_id], e.currentTarget);
}

function exportApprovals() {
  const params = apFilterParams();
  params.set("action", "export_approvals");
  window.location.href = "backend/admin_api.php?" + params.toString();
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
  <button class="btn-sm btn-primary admin-write" onclick="openAddCourse()">
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
      <button class="btn-sm btn-edit" onclick="openEditCourse(${r.id})"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span class="admin-write">แก้ไข</span><span class="viewer-only">ดู</span></button>
      <button class="btn-sm btn-danger admin-write" onclick="deleteCourse(${r.id},'${escHtml(r.short_name)}')"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
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
    `${isViewer() ? "ข้อมูลคอร์ส" : "แก้ไขคอร์ส"}: ${row.short_name}`;
  document.getElementById("edit-modal-body").innerHTML =
    `<div class="edit-grid">
    <div class="edit-field full"><label>ชื่อย่อ (short_name) *</label><input type="text" id="ef-short_name" value="${escHtml(row.short_name)}"></div>
    <div class="edit-field full"><label>ชื่อยาว (long_key) *</label><input type="text" id="ef-long_key" value="${escHtml(row.long_key || "")}"></div>
    <div class="edit-field full"><label>วันที่อบรม</label><input type="text" id="ef-training_date" value="${escHtml(row.training_date || "")}"></div>
    <div class="edit-field"><label>ปี พ.ศ.</label><input type="text" id="ef-year_be" value="${escHtml(row.year_be || "")}" placeholder="เช่น 2568" maxlength="4" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div>
    <div class="edit-field"><label>Verify URL (QR code)</label><input type="text" id="ef-verify_url" value="${escHtml(row.verify_url || "")}"></div>
  </div>`;
  openEditModal();
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
  openEditModal();
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
  else showToast(result.error, "error");
}

// ===== รายชื่อผู้เรียน: จัดกลุ่มตามคอร์ส (แบบ Group by ของ Airtable) =====
// โหลดรายการคอร์ส + จำนวนคนก่อน (list_student_groups) แล้วค่อยดึงรายชื่อเมื่อกางคอร์สนั้น (list_students)
const SG_PER_PAGE = 500;
const SG_COLS = 12;
const SG_TYPE_COLOR = { "พระภิกษุ": "pink", "สามเณร": "orange", "อาสาสมัคร": "blue", "อุบาสก": "teal", "อุบาสิกา": "purple" };
const SG_PALETTE = ["pink", "blue", "green", "purple", "orange", "teal"];
let sgFilters = { q: "", year: "" };
let sgGroups = [];
let sgExpanded = new Set(); // id คอร์สที่กางอยู่
let sgRows = {}; // id คอร์ส → { rows, total, page }
let sgSearchTimer = null;

async function loadStudentsTable() {
  // ถ้าหน้าเปิดอยู่แล้ว (เช่น หลังบันทึก/ลบ) โหลดข้อมูลใหม่โดยคงคอร์สที่กางไว้
  if (document.getElementById("sg-table")) return sgReload();

  const area = document.getElementById("admin-content-area");
  area.innerHTML = '<div style="color:var(--text3);padding:2rem;font-size:13px;">กำลังโหลด...</div>';
  let courses = [];
  try {
    const res = await fetch("backend/admin_api.php?action=list_courses");
    const result = await res.json();
    courses = result.ok ? result.data : [];
  } catch (e) {}
  const years = [...new Set(courses.map((c) => c.year_be).filter(Boolean))].sort((a, b) => b - a);

  area.innerHTML = `
<div class="admin-page-title">
  <h2><svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:var(--blue);fill:none;stroke-width:2;vertical-align:-3px;margin-right:6px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>รายชื่อผู้เรียน</h2>
  <span class="badge-count" id="students-count-badge"></span>
</div>
<div class="admin-toolbar">
  <div class="admin-search">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="ค้นหาชื่อหรือนามสกุล..." id="student-search" oninput="onStudentSearchInput()" autocomplete="off" value="${escHtml(sgFilters.q)}">
  </div>
  <select id="student-year-filter" class="year-filter-select" onchange="onStudentYearChange()">
    <option value="">ทุกปี</option>
    ${years.map((y) => `<option value="${escHtml(y)}" ${sgFilters.year === y ? "selected" : ""}>พ.ศ. ${escHtml(y)}</option>`).join("")}
  </select>
  <button class="btn-sm btn-edit" onclick="sgExpandAll()">กางทั้งหมด</button>
  <button class="btn-sm btn-edit" onclick="sgCollapseAll()">พับทั้งหมด</button>
  <button class="btn-sm btn-primary admin-write" onclick="openAddStudent()">
    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>เพิ่มรายชื่อ
  </button>
</div>
<div class="table-card sg-card"><div class="sg-scroll">
<table class="data-table sg-table" id="sg-table">
  <thead><tr>
    <th class="sg-num">#</th><th>ชื่อ</th><th>นามสกุล</th><th>วันที่สมัคร</th><th>ประเภท</th><th>อายุ</th>
    <th>ระดับการศึกษา</th><th>หน่วยงาน</th><th>ตำแหน่ง</th><th>มือถือ</th><th>อีเมล</th><th class="sg-act"></th>
  </tr></thead>
</table></div></div>`;

  document.getElementById("sg-table").addEventListener("click", onStudentGroupClick);
  sgReload();
}

function onStudentSearchInput() {
  clearTimeout(sgSearchTimer);
  sgSearchTimer = setTimeout(() => {
    sgFilters.q = document.getElementById("student-search").value.trim();
    sgExpanded.clear(); // มีคำค้น → กางทุกคอร์สที่ตรง (ใน sgReload) / ล้างคำค้น → พับทั้งหมด
    sgReload();
  }, 300);
}

function onStudentYearChange() {
  sgFilters.year = document.getElementById("student-year-filter").value;
  sgReload();
}

// ปีใหม่ → เก่า แล้วตามวันที่อบรม (ใหม่ → เก่า) ภายในปีเดียวกัน
function sgSortGroups(groups) {
  return groups.sort(
    (a, b) =>
      (parseInt(b.year_be, 10) || 0) - (parseInt(a.year_be, 10) || 0) ||
      parseTrainingDateKey(b.training_date) - parseTrainingDateKey(a.training_date) ||
      String(a.long_key || a.short_name).localeCompare(String(b.long_key || b.short_name), "th"),
  );
}

async function sgReload() {
  const table = document.getElementById("sg-table");
  if (!table) return;
  const params = new URLSearchParams({ action: "list_student_groups" });
  if (sgFilters.q) params.set("q", sgFilters.q);
  if (sgFilters.year) params.set("year_be", sgFilters.year);
  let result;
  try {
    const res = await fetch("backend/admin_api.php?" + params.toString());
    result = await res.json();
  } catch (e) {
    result = { ok: false, error: "ติดต่อ server ไม่สำเร็จ" };
  }
  table.querySelectorAll("tbody").forEach((tb) => tb.remove());
  if (!result.ok) {
    table.insertAdjacentHTML("beforeend", `<tbody><tr><td colspan="${SG_COLS}" style="color:var(--red);padding:1.5rem;text-align:center">❌ ${escHtml(result.error || "เกิดข้อผิดพลาด")}</td></tr></tbody>`);
    return;
  }

  sgGroups = sgSortGroups(result.data || []);
  sgRows = {};
  const ids = new Set(sgGroups.map((g) => String(g.id)));
  if (sgFilters.q) sgGroups.forEach((g) => sgExpanded.add(String(g.id)));
  sgExpanded = new Set([...sgExpanded].filter((id) => ids.has(id)));

  document.getElementById("students-count-badge").textContent =
    `รวม ${(result.total || 0).toLocaleString("th-TH")} คน · ${sgGroups.length} คอร์ส`;

  table.insertAdjacentHTML(
    "beforeend",
    sgGroups.map((g) => `<tbody class="sg-group" data-id="${escHtml(g.id)}">${sgHeadRow(g)}</tbody>`).join("") ||
      `<tbody><tr><td colspan="${SG_COLS}" style="text-align:center;color:var(--text3);padding:1.5rem">${sgFilters.q ? "ไม่พบผู้เรียนที่ตรงกับคำค้น" : "ยังไม่มีคอร์ส"}</td></tr></tbody>`,
  );
  // โหลดรายชื่อของคอร์สที่กางอยู่ทีละคอร์ส
  for (const id of sgExpanded) await sgLoadRows(id);
}

function sgHeadRow(g) {
  const open = sgExpanded.has(String(g.id));
  const meta = [g.training_date, g.year_be ? "พ.ศ. " + g.year_be : ""].filter(Boolean).join(" · ");
  return `<tr class="sg-head ${open ? "open" : ""}" data-act="toggle">
  <td colspan="${SG_COLS}"><div class="sg-head-inner">
    <span class="sg-chev"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span>
    <div class="sg-head-text">
      <div class="sg-label">โปรแกรมที่สมัคร</div>
      <span class="sg-course" title="${escHtml(g.long_key || g.short_name)}">${escHtml(g.long_key || g.short_name)}</span>
    </div>
    <span class="sg-count">${g.student_count} คน</span>
    ${meta ? `<span class="sg-meta">${escHtml(meta)}</span>` : ""}
  </div></td>
</tr>`;
}

function onStudentGroupClick(e) {
  const more = e.target.closest("[data-act='more']");
  if (more) return sgLoadRows(more.closest("tbody").dataset.id, true);
  const head = e.target.closest("tr.sg-head");
  if (head) sgToggle(head.closest("tbody").dataset.id);
}

function sgToggle(id) {
  if (sgExpanded.has(id)) {
    sgExpanded.delete(id);
    sgRenderRows(id);
  } else {
    sgExpanded.add(id);
    sgRows[id] ? sgRenderRows(id) : sgLoadRows(id);
  }
}

async function sgExpandAll() {
  for (const g of sgGroups) {
    const id = String(g.id);
    if (sgExpanded.has(id)) continue;
    sgExpanded.add(id);
    if (sgRows[id]) sgRenderRows(id);
    else await sgLoadRows(id);
  }
}

function sgCollapseAll() {
  const ids = [...sgExpanded];
  sgExpanded.clear();
  ids.forEach(sgRenderRows);
}

async function sgLoadRows(id, more = false) {
  const tbody = document.querySelector(`#sg-table tbody[data-id="${CSS.escape(id)}"]`);
  if (!tbody) return;
  const prev = more ? sgRows[id] : null;
  const page = prev ? prev.page + 1 : 1;
  if (!prev) {
    sgClearRows(tbody);
    tbody.insertAdjacentHTML("beforeend", `<tr class="sg-row sg-note"><td colspan="${SG_COLS}">กำลังโหลด...</td></tr>`);
  }
  tbody.querySelector(".sg-head").classList.add("open");

  const params = new URLSearchParams({ action: "list_students", course_id: id, page, per_page: SG_PER_PAGE, sort: "id", dir: "asc" });
  if (sgFilters.q) params.set("q", sgFilters.q);
  let result;
  try {
    const res = await fetch("backend/admin_api.php?" + params.toString());
    result = await res.json();
  } catch (e) {
    result = { ok: false, error: "ติดต่อ server ไม่สำเร็จ" };
  }
  if (!result.ok) {
    sgClearRows(tbody);
    tbody.insertAdjacentHTML("beforeend", `<tr class="sg-row sg-note"><td colspan="${SG_COLS}" style="color:var(--red)">❌ ${escHtml(result.error || "เกิดข้อผิดพลาด")}</td></tr>`);
    return;
  }
  sgRows[id] = { rows: (prev ? prev.rows : []).concat(result.data || []), total: result.total || 0, page };
  sgRenderRows(id);
}

function sgClearRows(tbody) {
  tbody.querySelectorAll(".sg-row").forEach((tr) => tr.remove());
}

// "2026-07-21" → "21/7/2026" (รูปแบบเดียวกับ Airtable) ค่าอื่นแสดงตามเดิม
function sgDate(v) {
  const m = /^(\d{4})-(\d{1,2})-(\d{1,2})/.exec(v || "");
  return m ? `${+m[3]}/${+m[2]}/${m[1]}` : v || "";
}

function sgPill(text, color) {
  return text ? `<span class="sg-pill ${color}">${escHtml(text)}</span>` : "";
}

function sgTypeColor(type) {
  if (SG_TYPE_COLOR[type]) return SG_TYPE_COLOR[type];
  let h = 0;
  for (const ch of String(type)) h = (h * 31 + ch.codePointAt(0)) >>> 0;
  return SG_PALETTE[h % SG_PALETTE.length];
}

function sgRenderRows(id) {
  const tbody = document.querySelector(`#sg-table tbody[data-id="${CSS.escape(id)}"]`);
  if (!tbody) return;
  sgClearRows(tbody);
  const open = sgExpanded.has(id);
  tbody.querySelector(".sg-head").classList.toggle("open", open);
  if (!open || !sgRows[id]) return;

  const { rows, total } = sgRows[id];
  let html = rows
    .map((r, i) => {
      const name = `${r.first_name} ${r.last_name || ""}`.trim();
      return `<tr class="sg-row">
    <td class="sg-num">${i + 1}</td>
    <td><strong style="font-weight:600">${escHtml(r.first_name)}</strong></td>
    <td>${escHtml(r.last_name || "")}</td>
    <td class="cell-muted" style="white-space:nowrap">${escHtml(sgDate(r.apply_date))}</td>
    <td>${sgPill(r.member_type, sgTypeColor(r.member_type))}</td>
    <td class="cell-muted">${escHtml(r.age || "")}</td>
    <td>${sgPill(r.education_level, "yellow")}</td>
    <td class="cell-muted sg-clip" title="${escHtml(r.department || "")}">${escHtml(r.department || "")}</td>
    <td class="cell-muted sg-clip" title="${escHtml(r.position || "")}">${escHtml(r.position || "")}</td>
    <td class="cell-muted" style="white-space:nowrap">${escHtml(r.phone_mobile || "")}</td>
    <td class="cell-muted">${escHtml(r.email || "")}</td>
    <td class="sg-act"><div class="actions">
      <button class="btn-sm btn-edit" onclick="openEditStudent(${r.id})" title="${isViewer() ? "ดูข้อมูล" : "แก้ไข"}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      <button class="btn-sm btn-danger admin-write" onclick="deleteStudent(${r.id}, ${escHtml(JSON.stringify(name))})"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
    </div></td>
  </tr>`;
    })
    .join("");
  if (!rows.length) html = `<tr class="sg-row sg-note"><td colspan="${SG_COLS}">ยังไม่มีผู้เรียนในคอร์สนี้</td></tr>`;
  else if (rows.length < total)
    html += `<tr class="sg-row sg-note"><td colspan="${SG_COLS}">แสดง ${rows.length} จาก ${total} คน · <button type="button" class="sg-more" data-act="more">โหลดเพิ่ม</button></td></tr>`;
  tbody.insertAdjacentHTML("beforeend", html);
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
    `${isViewer() ? "ข้อมูลผู้เรียน" : "แก้ไขข้อมูล"}: ${r.first_name} ${r.last_name || ""}`;
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
  openEditModal();
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
  openEditModal();
}

// เปิด modal แก้ไข — ถ้าเป็นผู้ดูอย่างเดียว ช่องทั้งหมดอ่านได้อย่างเดียว (ปุ่มบันทึกถูกซ่อนด้วย .admin-write)
function openEditModal() {
  const viewer = isViewer();
  document.querySelectorAll("#edit-modal-body input, #edit-modal-body select, #edit-modal-body textarea").forEach((el) => {
    if (el.tagName === "SELECT") el.disabled = viewer;
    else el.readOnly = viewer;
  });
  const back = document.querySelector("#edit-modal-overlay .btn-modal-back");
  if (back) back.textContent = viewer ? "ปิด" : "ยกเลิก";
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
  else showToast(result.error, "error");
}

async function saveEdit() {
  if (!editContext || isViewer()) return;
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
      showToast("กรุณาเลือกนักเรียนอย่างน้อย 1 คน", "error", "ยังไม่ได้เลือก");
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
      showToast(
        `นำเข้าสำเร็จ ${result.imported} คน` + (result.skipped?.length ? ` (ข้าม ${result.skipped.length} คน)` : ""),
        "success",
        "นำเข้าข้อมูลสำเร็จ"
      );
      await fetchAirtablePreview();
    } else loadStudentsTable();
  } else {
    showToast(result.error, "error");
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
        <td>
          <strong>${escHtml(r.long_key)}</strong><br>
          <span style="font-size:11px;color:var(--text3)">${escHtml(r.short_name)}</span>
        </td>
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

  openEditModal();
}

function toggleSelectAllAirtableModal(select) {
  document
    .querySelectorAll("#airtable-modal-student-list input[type=checkbox]:not(:disabled)")
    .forEach((cb) => (cb.checked = select));
}