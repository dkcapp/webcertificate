// assets/js/certificate.js
// ตรรกะหน้าดาวน์โหลดใบประกาศ: โหลดข้อมูล, dropdown เลือกปี/คอร์ส/ชื่อ, พรีวิว, ดาวน์โหลด PNG

document.getElementById("footer-year").textContent =
  new Date().getFullYear() + 2543;

const CERT_BG = "assets/images/main1.png";
const COL_FIRSTNAME = "ชื่อ";
const COL_LASTNAME = "นามสกุล (ฉายา)";
const COL_COURSE_NAME = "ชื่อโปรแกรม";
const COL_COURSE_LONGNAME = "ชื่อโปรแกรมเต็ม";
const COL_DATE = "วันที่อบรม";

let allRecords = [];
let filteredByCourse = [];
let dropdownOpen = false;
let courseDropdownOpen = false;
let courseMap = {};
let allCourseKeys = [];
let selectedCourse = "";
let selectedYear = "";

async function loadAirtable() {
  const statusEl = document.getElementById("api-status");
  try {
    const res = await fetch("backend/api.php");
    const result = await res.json();
    if (!result.ok) throw new Error(result.error || "โหลดข้อมูลไม่สำเร็จ");
    if (!result.records || result.records.length === 0)
      throw new Error("ยังไม่มีข้อมูลในระบบ");
    allRecords = result.records;
    statusEl.className = "status-bar ok";
    statusEl.innerHTML = `<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><span>โหลดสำเร็จ ${allRecords.length} รายการ</span>`;
    buildYearDropdown();
  } catch (e) {
    statusEl.className = "status-bar error";
    statusEl.innerHTML = `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span>${e.message}</span>`;
  }
}

function buildYearDropdown() {
  const years = new Set();
  allRecords.forEach((r) => {
    const y = r.fields["year_be"];
    if (y) years.add(y);
  });
  renderYearItems(Array.from(years).sort((a, b) => b - a));
}
function renderYearItems(years) {
  const list = document.getElementById("year-dropdown-list");
  list.innerHTML = "";
  if (!years.length) {
    list.innerHTML = '<div class="dropdown-item empty">ไม่พบข้อมูลปี</div>';
    return;
  }
  years.forEach((y) => {
    const div = document.createElement("div");
    div.className = "dropdown-item" + (y === selectedYear ? " active" : "");
    div.textContent = "พ.ศ. " + y;
    div.onclick = () => selectYear(y);
    list.appendChild(div);
  });
}
function toggleYearDropdown() {
  const box = document.getElementById("year-dropdown-box");
  const panel = document.getElementById("year-dropdown-panel");
  const open = box.classList.contains("open");
  box.classList.toggle("open", !open);
  panel.classList.toggle("open", !open);
}
function closeYearDropdown() {
  document.getElementById("year-dropdown-box").classList.remove("open");
  document.getElementById("year-dropdown-panel").classList.remove("open");
}
function selectYear(year) {
  selectedYear = year;
  document.getElementById("year-dropdown-label").textContent = "พ.ศ. " + year;
  document.getElementById("year-dropdown-selected").classList.add("chosen");
  closeYearDropdown();
  selectedCourse = "";
  document.getElementById("course-dropdown-label").textContent =
    "— เลือกหลักสูตร —";
  document
    .getElementById("course-dropdown-selected")
    .classList.remove("chosen");
  document.getElementById("name-dropdown-label").textContent =
    "— เลือกคอร์สก่อน —";
  document.getElementById("name-dropdown-selected").classList.remove("chosen");
  document.getElementById("inp-name").value = "";
  document.getElementById("cert-container").style.display = "none";
  document.getElementById("cert-empty").style.display = "flex";
  document.getElementById("name-dropdown-box").classList.add("disabled");
  document.getElementById("btn-dl").disabled = true;
  document.getElementById("course-dropdown-box").classList.remove("disabled");
  buildCourseDropdown();
}

function buildCourseDropdown() {
  const recordsInYear = selectedYear
    ? allRecords.filter((r) => r.fields["year_be"] === selectedYear)
    : allRecords;
  courseMap = {};
  recordsInYear.forEach((r) => {
    const k = r.fields[COL_COURSE_NAME];
    if (!k) return;
    if (!courseMap[k])
      courseMap[k] = {
        records: [],
        date: r.fields[COL_DATE] || "",
        verify_url: r.fields["verify_url"] || "",
        longName: r.fields[COL_COURSE_LONGNAME] || "",
      };
    courseMap[k].records.push(r);
  });
  allCourseKeys = Object.keys(courseMap).sort((a, b) => a.localeCompare(b, "th"));
  renderCourseItems(allCourseKeys);
}
function renderCourseItems(keys) {
  const list = document.getElementById("course-dropdown-list");
  list.innerHTML = "";
  if (!keys.length) {
    list.innerHTML = '<div class="dropdown-item empty">ไม่พบหลักสูตร</div>';
    return;
  }
  keys.forEach((k) => {
    const div = document.createElement("div");
    div.className = "dropdown-item" + (k === selectedCourse ? " active" : "");
    div.textContent = k;
    div.onclick = () => selectCourse(k);
    list.appendChild(div);
  });
}
function toggleCourseDropdown() {
  const box = document.getElementById("course-dropdown-box");
  if (box.classList.contains("disabled")) return;
  courseDropdownOpen = !courseDropdownOpen;
  box.classList.toggle("open", courseDropdownOpen);
  document
    .getElementById("course-dropdown-panel")
    .classList.toggle("open", courseDropdownOpen);
  if (courseDropdownOpen) {
    document.getElementById("course-search-input").value = "";
    renderCourseItems(allCourseKeys);
  }
}
function closeCourseDropdown() {
  courseDropdownOpen = false;
  document.getElementById("course-dropdown-box").classList.remove("open");
  document.getElementById("course-dropdown-panel").classList.remove("open");
}
function filterCourseDropdown() {
  const q = document
    .getElementById("course-search-input")
    .value.trim()
    .toLowerCase();
  renderCourseItems(
    q ? allCourseKeys.filter((k) => k.toLowerCase().includes(q)) : allCourseKeys,
  );
}
function selectCourse(course) {
  selectedCourse = course;
  document.getElementById("course-dropdown-label").textContent = course;
  document.getElementById("course-dropdown-selected").classList.add("chosen");
  closeCourseDropdown();
  onCourseChangeValue(course);
}
function onCourseChangeValue(course) {
  document.getElementById("inp-name").value = "";
  resetNameDropdown();
  if (!course) {
    document.getElementById("cert-container").style.display = "none";
    document.getElementById("cert-empty").style.display = "flex";
    document.getElementById("name-dropdown-box").classList.add("disabled");
    filteredByCourse = [];
    updateCert();
    return;
  }
  document.getElementById("cert-bg").src = CERT_BG;
  document.getElementById("cert-container").style.display = "block";
  document.getElementById("cert-empty").style.display = "none";
  document.getElementById("name-dropdown-box").classList.remove("disabled");
  filteredByCourse = (courseMap[course] || {}).records || [];
  renderDropdownItems(filteredByCourse);
  const cnt = document.getElementById("name-count");
  cnt.textContent =
    filteredByCourse.length > 0
      ? `พบ ${filteredByCourse.length} คน ในหลักสูตรนี้`
      : "ไม่พบรายชื่อในหลักสูตรนี้";
  document.getElementById("name-dropdown-label").textContent =
    "— เลือกชื่อผู้รับ —";
  updateCert();
}

function toggleDropdown() {
  const box = document.getElementById("name-dropdown-box");
  if (box.classList.contains("disabled")) return;
  dropdownOpen = !dropdownOpen;
  box.classList.toggle("open", dropdownOpen);
  document
    .getElementById("name-dropdown-panel")
    .classList.toggle("open", dropdownOpen);
  if (dropdownOpen) {
    document.getElementById("name-search-input").value = "";
    renderDropdownItems(filteredByCourse);
  }
}
function closeDropdown() {
  dropdownOpen = false;
  document.getElementById("name-dropdown-box").classList.remove("open");
  document.getElementById("name-dropdown-panel").classList.remove("open");
}
function filterDropdown() {
  const q = document
    .getElementById("name-search-input")
    .value.trim()
    .toLowerCase();
  renderDropdownItems(
    q
      ? filteredByCourse.filter((r) => {
          const fn = (r.fields[COL_FIRSTNAME] || "").toLowerCase();
          const ln = (r.fields[COL_LASTNAME] || "").toLowerCase();
          return fn.includes(q) || ln.includes(q);
        })
      : filteredByCourse,
  );
}
function renderDropdownItems(records) {
  const list = document.getElementById("name-dropdown-list");
  list.innerHTML = "";
  if (!records.length) {
    list.innerHTML = '<div class="dropdown-item empty">ไม่พบรายชื่อ</div>';
    return;
  }
  records.slice(0, 200).forEach((r) => {
    const fn = r.fields[COL_FIRSTNAME] || "";
    const ln = r.fields[COL_LASTNAME] || "";
    const fullName = (fn + " " + ln).trim();
    if (!fullName) return;
    const div = document.createElement("div");
    div.className = "dropdown-item";
    div.textContent = fullName;
    div.onclick = () => selectName(fullName);
    list.appendChild(div);
  });
}
function selectName(name) {
  document.getElementById("inp-name").value = name;
  document.getElementById("name-dropdown-label").textContent = name;
  document.getElementById("name-dropdown-selected").classList.add("chosen");
  document
    .querySelectorAll("#name-dropdown-list .dropdown-item")
    .forEach((d) => {
      d.classList.toggle("active", d.textContent === name);
    });
  closeDropdown();
  updateCert();
}
function resetNameDropdown() {
  document.getElementById("name-dropdown-label").textContent =
    "— เลือกชื่อผู้รับ —";
  document
    .getElementById("name-dropdown-selected")
    .classList.remove("chosen");
  document.getElementById("name-dropdown-list").innerHTML = "";
  document.getElementById("name-count").textContent = "";
  closeDropdown();
}

document.addEventListener("click", (e) => {
  const nameWrap = document
    .getElementById("name-dropdown-box")
    ?.closest(".dropdown-wrap");
  if (nameWrap && !nameWrap.contains(e.target)) closeDropdown();
  const cWrap = document.getElementById("course-dropdown-wrap");
  if (cWrap && !cWrap.contains(e.target)) closeCourseDropdown();
  const yWrap = document.getElementById("year-dropdown-wrap");
  if (yWrap && !yWrap.contains(e.target)) closeYearDropdown();
});

function updateQRCode() {
  const qrArea = document.getElementById("cert-qr-area");
  const qrCont = document.getElementById("qrcode");
  const url = (courseMap[selectedCourse] || {}).verify_url || "";
  if (!url) {
    qrArea.style.display = "none";
    return;
  }
  qrCont.innerHTML = "";
  new QRCode(qrCont, {
    text: url,
    width: 256,
    height: 256,
    colorDark: "#000000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M,
  });
  qrArea.style.display = "block";
}

function getDisplayCourseName(course) {
  const info = courseMap[course] || {};
  const longName = info.longName || "";
  const idx = longName.indexOf("วันที่");
  if (idx > 0) {
    return longName.slice(0, idx).trim();
  }
  // ไม่เจอคำว่า "วันที่" ในชื่อยาว -> fallback ใช้ชื่อสั้นแทน กันเกียรติบัตรพัง
  return course;
}

function fitDescText() {
  const el = document.querySelector(".cert-desc-text");
  if (!el) return;
  el.style.setProperty("--desc-scale", "1");
  requestAnimationFrame(() => {
    const maxLines = 2;
    const minScale = 0.7;
    const step = 0.03;
    let scale = 1;
    let lineHeight = parseFloat(getComputedStyle(el).lineHeight);
    let target = lineHeight * maxLines + 1;
    let guard = 0;
    while (el.scrollHeight > target && scale > minScale && guard < 30) {
      scale -= step;
      el.style.setProperty("--desc-scale", scale.toFixed(3));
      lineHeight = parseFloat(getComputedStyle(el).lineHeight);
      target = lineHeight * maxLines + 1;
      guard++;
    }
  });
}

function updateCert() {
  const name = document.getElementById("inp-name").value.trim();
  const disp = document.getElementById("disp-name");
  const info = courseMap[selectedCourse] || {};
  document.getElementById("disp-course").textContent =
    selectedCourse || "ชื่อคอร์ส";
  if (name) {
    disp.textContent = name;
    disp.classList.remove("placeholder-text");
  } else {
    disp.textContent = "ชื่อ-นามสกุล";
    disp.classList.add("placeholder-text");
  }
  if (selectedCourse) {
    document.getElementById("disp-course-inline").textContent = getDisplayCourseName(selectedCourse);
    document.getElementById("disp-date-inline").textContent = info.date || "";
  }
  updateQRCode();
  fitDescText();
  document.getElementById("btn-dl").disabled = !(name && selectedCourse);
}

async function downloadCert() {
  const btn = document.getElementById("btn-dl");
  btn.innerHTML =
    '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2;animation:spin 1s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> กำลังสร้างไฟล์...';
  btn.disabled = true;
  try {
    const canvas = await html2canvas(document.getElementById("cert-container"), {
      scale: 3,
      useCORS: true,
      allowTaint: true,
      backgroundColor: null,
    });
    const name = document
      .getElementById("inp-name")
      .value.trim()
      .replace(/\s+/g, "_");
    const link = document.createElement("a");
    link.download = "certificate_" + name + ".png";
    link.href = canvas.toDataURL("image/png");
    link.click();
  } catch (e) {
    alert("เกิดข้อผิดพลาด: " + e.message);
  }
  btn.innerHTML =
    '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> ดาวน์โหลดใบประกาศ (PNG)';
  updateCert();
}

function adjustPos() {
  const vC = document.getElementById("sl-course").value;
  document.getElementById("sl-course-val").textContent = vC + "%";
  document.querySelector(".cert-course-area").style.top = vC + "%";
  const vN = document.getElementById("sl-name").value;
  document.getElementById("sl-name-val").textContent = vN + "%";
  document.querySelector(".cert-name-area").style.bottom = vN + "%";
  const vD = document.getElementById("sl-desc").value;
  document.getElementById("sl-desc-val").textContent = vD + "%";
  document.querySelector(".cert-desc-area").style.bottom = vD + "%";
}

loadAirtable();