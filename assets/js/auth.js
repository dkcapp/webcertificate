// assets/js/auth.js
// ระบบ login/logout admin และเช็คสถานะ session

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
    if (data.is_admin) sessionStorage.setItem("is_admin", "true");
    else sessionStorage.removeItem("is_admin");
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