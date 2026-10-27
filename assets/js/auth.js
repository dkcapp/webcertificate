// assets/js/auth.js
// ระบบ login/logout admin และเช็คสถานะ session
// role: "admin" = แก้ไขได้ทั้งหมด, "viewer" = ดูอย่างเดียว (ซ่อนปุ่มเพิ่ม/แก้/ลบ; ฝั่ง server กันซ้ำอีกชั้น)

function adminRole() {
  return sessionStorage.getItem("admin_role") || "admin";
}

function isViewer() {
  return sessionStorage.getItem("is_admin") === "true" && adminRole() === "viewer";
}

async function checkAdminSession() {
  const cached = sessionStorage.getItem("is_admin");
  if (cached === "true") setAdminUI(true);
  try {
    const res = await fetch("backend/auth.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "action=check",
    });
    const data = await res.json();
    if (data.is_admin) {
      sessionStorage.setItem("is_admin", "true");
      sessionStorage.setItem("admin_role", data.role || "admin");
    } else {
      sessionStorage.removeItem("is_admin");
      sessionStorage.removeItem("admin_role");
    }
    setAdminUI(data.is_admin);
  } catch (e) {
    if (cached === "true") setAdminUI(true);
  }
}
function openLoginModal() {
  document.getElementById("login-modal").classList.add("open");
  document.getElementById("inp-user").focus();
  document.getElementById("login-error").textContent = "";
  document.getElementById("inp-user").value = "";
  document.getElementById("inp-pass").value = "";
}
function closeLoginModal() {
  document.getElementById("login-modal").classList.remove("open");
}
async function doLogin() {
  const btn = document.getElementById("btn-do-login");
  const errEl = document.getElementById("login-error");
  const user = document.getElementById("inp-user").value.trim();
  const pass = document.getElementById("inp-pass").value;
  if (!user || !pass) {
    errEl.textContent = "กรุณากรอก username และ password";
    return;
  }
  btn.disabled = true;
  btn.textContent = "กำลังตรวจสอบ...";
  errEl.textContent = "";
  try {
    const res = await fetch("backend/auth.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=login&username=${encodeURIComponent(user)}&password=${encodeURIComponent(pass)}`,
    });
    const data = await res.json();
    if (data.ok) {
      sessionStorage.setItem("is_admin", "true");
      sessionStorage.setItem("admin_role", data.role || "admin");
      closeLoginModal();
      setAdminUI(true);
    } else {
      errEl.textContent = data.error || "เกิดข้อผิดพลาด";
    }
  } catch (e) {
    errEl.textContent = "ไม่สามารถติดต่อ server ได้";
  }
  btn.disabled = false;
  btn.textContent = "เข้าสู่ระบบ";
}
async function adminLogout() {
  sessionStorage.removeItem("is_admin");
  sessionStorage.removeItem("admin_role");
  await fetch("backend/auth.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "action=logout",
  });
  setAdminUI(false);
}
document.getElementById("login-modal").addEventListener("click", function (e) {
  if (e.target === this) closeLoginModal();
});

window.setAdminUI = function (isAdmin) {
  const strip = document.getElementById("admin-tab-strip");
  const footerBtn = document.getElementById("footer-admin-btn");
  const viewer = !!isAdmin && adminRole() === "viewer";
  document.body.classList.toggle("role-viewer", viewer);
  let badge = document.getElementById("viewer-badge");
  if (viewer && !badge) {
    badge = document.createElement("span");
    badge.id = "viewer-badge";
    badge.className = "viewer-badge";
    badge.title = "บัญชีนี้ดูข้อมูลได้อย่างเดียว ไม่สามารถเพิ่ม แก้ไข หรือลบได้";
    badge.textContent = "โหมดดูอย่างเดียว";
    strip.prepend(badge);
  } else if (!viewer && badge) {
    badge.remove();
  }
  if (isAdmin) {
    strip.classList.add("visible");
    if (footerBtn) footerBtn.textContent = "ออกจากระบบ";
  } else {
    strip.classList.remove("visible");
    if (footerBtn) footerBtn.textContent = "ผู้ดูแลระบบ";
    switchTab("download");
  }
};
checkAdminSession();