# ระบบออกใบประกาศนียบัตร — ศูนย์อบรมคอมพิวเตอร์ วัดพระธรรมกาย

ระบบออกใบประกาศนียบัตรให้ผู้เข้าอบรม พร้อมระบบจัดการหลักสูตร/รายชื่อผู้เรียน และรับสมัครผ่านฟอร์มออนไลน์ของบริษัทแบบอัตโนมัติ (บันทึกเข้าทั้ง Airtable เดิมและระบบใหม่พร้อมกัน)

**เว็บไซต์ใช้งานจริง:** https://comtrain-webcertificate.onrender.com/

---

## สารบัญ
1. [ภาพรวมระบบทำอะไรได้บ้าง](#ภาพรวมระบบทำอะไรได้บ้าง)
2. [สถาปัตยกรรมระบบ (System Architecture)](#สถาปัตยกรรมระบบ-system-architecture)
3. [เทคโนโลยีที่ใช้](#เทคโนโลยีที่ใช้)
4. [โครงสร้างไฟล์ + คำอธิบายทีละไฟล์](#โครงสร้างไฟล์--คำอธิบายทีละไฟล์)
5. [โครงสร้างฐานข้อมูล](#โครงสร้างฐานข้อมูล)
6. [Data Flow แต่ละสถานการณ์](#data-flow-แต่ละสถานการณ์)
7. [การเชื่อมต่อ JotForm ของบริษัท (Dual-write เข้า Airtable + เว็บใหม่)](#การเชื่อมต่อ-jotform-ของบริษัท-dual-write-เข้า-airtable--เว็บใหม่)
8. [วิธีรันโปรเจกต์บนเครื่องตัวเอง (Local Setup)](#วิธีรันโปรเจกต์บนเครื่องตัวเอง-local-setup)
9. [Environment Variables ที่ต้องตั้งค่า](#environment-variables-ที่ต้องตั้งค่า)
10. [ข้อจำกัดและวิธีแก้ที่ใช้อยู่](#ข้อจำกัดและวิธีแก้ที่ใช้อยู่)
11. [ปัญหาที่เคยเจอ และวิธีแก้ (Troubleshooting Log)](#ปัญหาที่เคยเจอ-และวิธีแก้-troubleshooting-log)
12. [ประวัติการพัฒนา](#ประวัติการพัฒนา)

---

## ภาพรวมระบบทำอะไรได้บ้าง

ระบบนี้มี 3 กลุ่มผู้ใช้งาน:

### 1. ผู้เข้าอบรม (หน้าแรกของเว็บ — Public)
เลือก **ปีที่อบรม → หลักสูตร → ชื่อตัวเอง** แล้วกดดาวน์โหลดใบประกาศนียบัตรเป็นไฟล์ภาพ (PNG) ได้ทันที ระบบจะดึงชื่อ-นามสกุลมาใส่ในใบประกาศให้อัตโนมัติ พร้อม QR Code สำหรับตรวจสอบความถูกต้อง (ถ้าหลักสูตรนั้นตั้งค่า verify URL ไว้)

### 2. เจ้าหน้าที่ (Admin Panel — ต้อง Login)
- **จัดการหลักสูตร** — เพิ่ม/แก้ไข/ลบหลักสูตร, วันที่อบรม, ปี พ.ศ., ลิงก์ QR verify (มีทั้งชื่อสั้น `short_name` และชื่อยาว `long_key`)
- **จัดการรายชื่อผู้เรียน** — ดู/ค้นหา/แก้ไข/ลบ พร้อมแบ่งหน้าและเรียงลำดับ
- **เปิด/ปิดรับสมัคร** — เลือกหลักสูตรที่เปิดให้สมัคร แล้วกด "Sync to JotForm" เพื่ออัปเดต dropdown ของฟอร์มให้ตรงกับหลักสูตรที่เปิดอยู่จริง (แสดงและ sync เป็น**ชื่อยาว** `long_key` ไม่ใช่ชื่อย่ออีกต่อไป — ดูหัวข้อ [Data Flow ข้อ 3](#3-admin-เปิดปิดคอร์สแล้ว-sync-ไปยัง-jotform))
- **นำเข้าข้อมูลจาก Airtable** — ดึงรายชื่อคอร์สและผู้สมัครจาก Airtable (ทั้งชุดข้อมูลเก่า และรายการที่ตกหล่นจาก webhook เพราะยังไม่มีคอร์สในระบบ) เปรียบเทียบกับคอร์สในระบบ แบ่งเป็น "คอร์สใหม่" และ "คอร์สที่มีอยู่แล้ว" แล้วเลือกนำเข้าเป็นรายคอร์ส

### 3. ผู้สมัครเรียนใหม่ (ภายนอกบริษัท ผ่าน JotForm ของบริษัท)
กรอกฟอร์มสมัครที่ **https://form.jotform.com/231301336713444** (ฟอร์มจริงของบริษัท ไม่ใช่ฟอร์มทดลองอีกต่อไป) เลือกหลักสูตรจาก dropdown แล้วกด Submit — ข้อมูลจะถูกส่งเข้า **2 ที่พร้อมกันโดยอัตโนมัติ**:
1. **Airtable ของบริษัท** (การเชื่อมต่อเดิม ไม่ถูกแตะต้อง ทำงานเหมือนเดิมทุกประการ)
2. **Neon (ระบบเว็บใหม่นี้)** ผ่าน Webhook ที่เพิ่มเข้ามาเสริม

> ⚠️ **สำคัญ:** แอดมินยังคงไปเปิด/ปิดคอร์สและแก้ไข dropdown ที่หน้า **JotForm โดยตรง** เหมือนระบบเดิม ไม่ได้ทำผ่านเว็บนี้อีกต่อไป (ฟีเจอร์ "เปิดรับสมัคร" ในเว็บยังมีโค้ดอยู่ แต่ไม่ได้ใช้งานจริงกับฟอร์มบริษัท) — ดูรายละเอียดที่หัวข้อ [การเชื่อมต่อ JotForm ของบริษัท](#การเชื่อมต่อ-jotform-ของบริษัท-dual-write-เข้า-airtable--เว็บใหม่)

---

## สถาปัตยกรรมระบบ (System Architecture)

![System Architecture Diagram](assets/docs/architecture-diagram.png)

### สถาปัตยกรรมภายในฝั่ง Backend

โค้ดฝั่ง PHP แบ่งเป็น 3 ชั้นตามหลัก separation of concerns:

```
ไฟล์ endpoint (admin_api.php, api.php, active_courses.php, jotform_webhook.php)
        │  รับ HTTP request, validate input เบื้องต้น, ตอบ JSON กลับ
        ▼
Repository layer (backend/lib/CourseRepository.php, StudentRepository.php)
        │  รวม SQL ทั้งหมดไว้ที่เดียว ไม่ปนกับ HTTP handling
        ▼
Database (Neon PostgreSQL)
```

พร้อมด้วยไฟล์ shared ที่ endpoint ทุกตัวเรียกใช้ร่วมกัน:
- **`backend/lib/load_env.php`** — โหลดไฟล์ `.env` เข้า environment อัตโนมัติตอนรันบนเครื่อง local (บน Render ข้ามไปเลย เพราะไม่มีไฟล์ `.env`)
- **`backend/lib/require_admin.php`** — middleware เช็คสิทธิ์ admin กลาง ใช้ที่เดียวไม่ต้องเขียนซ้ำทุกไฟล์
- **`backend/lib/error_handler.php`** — จัดการ error กลาง log รายละเอียดจริงไว้ฝั่ง server เท่านั้น ส่งข้อความทั่วไปกลับไปหา client (ไม่เผยรายละเอียด database ให้ผู้ไม่หวังดี)

### สถาปัตยกรรมภายในฝั่ง Frontend

`index.html` เหลือแค่ markup ล้วน ๆ ไม่มี CSS/JavaScript ฝังอยู่ในไฟล์แล้ว โดยแยกออกเป็น:

```
index.html (markup เท่านั้น)
   ├── assets/css/style.css        สไตล์ทั้งหมดของเว็บ
   └── assets/js/
         ├── certificate.js        หน้าดาวน์โหลดใบประกาศ (โหลดข้อมูล, dropdown, พรีวิว, ดาวน์โหลด PNG)
         ├── auth.js               ระบบ login/logout admin
         └── admin.js              ตรรกะหน้า Admin Panel ทั้งหมด (ตารางคอร์ส, ตารางผู้เรียน, เปิดรับสมัคร, นำเข้า Airtable)
```

---

## เทคโนโลยีที่ใช้

| ส่วน | เทคโนโลยี | หน้าที่ |
|---|---|---|
| **Frontend** | HTML + CSS + Vanilla JavaScript (แยกไฟล์ตาม responsibility) | หน้าดาวน์โหลดใบประกาศ + หน้า Admin Panel |
| **สร้างภาพใบประกาศ** | [html2canvas](https://html2canvas.hertzen.com/) | แปลง HTML เป็นไฟล์ภาพ PNG ให้ดาวน์โหลด |
| **QR Code** | [qrcodejs](https://github.com/davidshimjs/qrcodejs) | สร้าง QR Code บนใบประกาศ |
| **Backend** | PHP 8.2 (ไม่ใช้ framework, ใช้ PDO ตรง ๆ) | ประมวลผล API ทั้งหมด แบ่งเป็น Controller + Repository layer |
| **Database** | [Neon](https://neon.tech) — PostgreSQL แบบ Serverless | เก็บข้อมูลหลักสูตรและผู้เรียนทั้งหมด |
| **Hosting** | [Render](https://render.com) — Web Service แบบ Docker (แผนฟรี) | รันเว็บ PHP ให้มี public URL |
| **Container** | Docker (`php:8.2-apache` + extension `pdo_pgsql`) | กำหนด environment ให้ Render รันได้ตรงตามที่ต้องการ |
| **รับสมัครออนไลน์** | [JotForm](https://www.jotform.com) — **ฟอร์มบริษัทจริง `231301336713444`** | ฟอร์มให้คนภายนอกกรอกสมัครเรียน ส่งข้อมูลเข้า Airtable บริษัท (เดิม) + Webhook ของเว็บนี้ (ใหม่) พร้อมกัน |
| **นำเข้าข้อมูลเก่า/ตกหล่น** | [Airtable REST API](https://airtable.com/developers/web/api/introduction) | ดึงรายชื่อผู้สมัครและคอร์สจาก Airtable บริษัท นำเข้าสู่ Neon (ทั้งข้อมูลเก่าครั้งเดียว และรายการที่ webhook หาไม่คอร์สเจอ) |
| **Keep-alive** | [UptimeRobot](https://uptimerobot.com) | ปิงเว็บทุก 5 นาที ป้องกัน Render sleep หลัง idle 15 นาที |

---

## โครงสร้างไฟล์ + คำอธิบายทีละไฟล์

```
webcertificate/
│   .env                    ← สร้างเองบนเครื่อง local (ห้าม commit ขึ้น GitHub)
│   .gitignore
│   Dockerfile
│   index.html
│   README.md
│
├───assets
│   ├───css
│   │       style.css
│   │
│   ├───docs
│   │       architecture-diagram.png
│   │
│   ├───images
│   │       line-qr.png
│   │       logo.png
│   │       main1.png
│   │
│   └───js
│           admin.js
│           auth.js
│           certificate.js
│
├───backend
│   │   active_courses.php
│   │   admin_api.php
│   │   api.php
│   │   auth.php
│   │   jotform_webhook.php     ← รับ webhook จากฟอร์มบริษัท 231301336713444
│   │
│   └───lib
│           AirtableClient.php
│           CourseRepository.php
│           error_handler.php
│           load_env.php
│           require_admin.php
│           StudentRepository.php
│
└───database
        config.php
        schema.sql
        seed.sql
```

### `Dockerfile`
ตั้งค่า container ที่ Render ใช้รันเว็บ: ใช้ image ตั้งต้น `php:8.2-apache`, ติดตั้ง `pdo_pgsql` extension (จำเป็นสำหรับต่อ Neon), คัดลอกโค้ดเข้า `/var/www/html/`, ตั้งให้ Apache ฟัง port ที่ Render กำหนดผ่าน environment variable `PORT`

### `.env` (ไม่ได้อยู่ใน Git)
ไฟล์เก็บ environment variables สำหรับรันบนเครื่อง local เท่านั้น ถูก ignore ไว้ใน `.gitignore` แล้ว ห้าม commit ขึ้น GitHub เด็ดขาด บน Render ตั้งค่าผ่าน Dashboard → Environment แทน

### `index.html`
Markup ล้วน ๆ ของหน้าเว็บทั้งหมด แบ่งเป็น 2 ส่วนหลักที่สลับกันด้วยแท็บ: หน้าดาวน์โหลดใบประกาศ (public) และหน้า Admin Panel (ต้อง login) ไม่มี CSS/JavaScript ฝังอยู่ในไฟล์นี้เลย

### `assets/css/style.css`
สไตล์ทั้งหมดของเว็บ (สี, layout, responsive, animation) แยกออกจาก `index.html` เพื่อให้แก้ไขและอ่านง่ายขึ้น

### `assets/js/certificate.js`
ตรรกะหน้าดาวน์โหลดใบประกาศ: โหลดรายชื่อจาก `api.php`, สร้าง dropdown เลือกปี/หลักสูตร/ชื่อ, อัปเดตพรีวิวใบประกาศแบบ real-time, สร้าง QR Code, และแปลงใบประกาศเป็นไฟล์ PNG ผ่าน html2canvas

### `assets/js/auth.js`
ระบบ login/logout admin ฝั่ง frontend: เปิด/ปิด modal login, เรียก `backend/auth.php`, เก็บสถานะ session ไว้ใน `sessionStorage`, และแสดง/ซ่อนแท็บ admin ตามสถานะ

### `assets/js/admin.js`
ตรรกะหน้า Admin Panel ทั้งหมด: สลับแท็บ, ตารางคอร์ส (ค้นหา/กรอง/เรียงลำดับ), ตารางรายชื่อผู้เรียน (ค้นหา/กรอง/เรียงลำดับ/แบ่งหน้า), modal เพิ่ม/แก้ไขข้อมูล, หน้าเปิดรับสมัคร (**แสดงชื่อคอร์สแบบยาว `long_key` เป็นหลัก + ชื่อย่อ `short_name` กำกับไว้ด้านล่างตัวเล็ก**) + ปุ่ม Sync to JotForm และหน้านำเข้าข้อมูลจาก Airtable (แสดงผลแบบกลุ่มคอร์ส)

### `backend/lib/load_env.php`
โหลดไฟล์ `.env` เข้า environment อัตโนมัติตอนรันบนเครื่อง local — ทุก endpoint `require_once` ไฟล์นี้ไว้บนสุด บน Render ไม่มีไฟล์ `.env` ก็ข้ามไปเลยโดยไม่มีผลอะไร ออกแบบมาให้ใช้งานได้ทั้งสองสภาพแวดล้อมโดยไม่ต้องแก้โค้ด

### `backend/lib/require_admin.php`
Middleware กลาง — เริ่ม session, ตั้ง response header เป็น JSON, เช็คว่า login เป็น admin อยู่ไหม ถ้าไม่ใช่ตอบ 403 ทันที ทุกไฟล์ endpoint ที่ต้องการสิทธิ์ admin (`admin_api.php`, `active_courses.php`) เรียกใช้ไฟล์นี้แค่บรรทัดเดียวแทนเขียนโค้ดเช็คซ้ำ

### `backend/lib/error_handler.php`
ฟังก์ชันกลาง `send_error_response()` — log รายละเอียด exception จริงไว้ฝั่ง server ผ่าน `error_log()` (ดูได้ที่ Render → Logs) แต่ส่งข้อความทั่วไปกลับไปหา client เท่านั้น ป้องกันไม่ให้รายละเอียดโครงสร้าง database รั่วไหลออกไป

### `backend/lib/AirtableClient.php`
ตัวเชื่อมต่อ Airtable REST API — ดึง records ทั้งหมดจาก table ที่กำหนด วน pagination อัตโนมัติ (Airtable จำกัด 100 แถวต่อ request) อ่าน credential จาก environment variables

### `backend/lib/CourseRepository.php`
รวม SQL ทั้งหมดที่เกี่ยวกับตาราง `courses` และ `active_courses` ไว้ที่เดียว — endpoint files เรียกใช้ผ่าน class นี้แทนเขียน SQL เอง เมธอดหลัก:

| เมธอด | หน้าที่ |
|---|---|
| `listWithStudentCount()` | ดึงคอร์สทั้งหมดพร้อมนับจำนวนผู้เรียน (หน้า "คอร์สทั้งหมด") |
| `listWithActiveFlag()` | ดึงคอร์สทั้งหมดพร้อม flag เปิด/ปิดรับสมัคร **คืนค่าทั้ง `short_name` และ `long_key`** (หน้า "เปิดรับสมัคร") |
| `listActiveLongKeys()` | ดึง**ชื่อยาว**ของคอร์สที่เปิดรับสมัครอยู่ทั้งหมด ใช้ตอน sync ไป JotForm *(เดิมชื่อ `listActiveShortNames()` — เปลี่ยนให้ sync เป็นชื่อยาวแทนชื่อย่อ)* |
| `findByShortName()` | ค้นหาคอร์สจากชื่อย่อแบบตรงเป๊ะ |
| `findByShortNameOrLongKey()` | ค้นหาคอร์สจากชื่อ โดยเทียบได้ทั้ง `short_name` และ `long_key` — ใช้ในหน้านำเข้า Airtable และใน `jotform_webhook.php` (กันเคส dropdown ใน JotForm เปลี่ยนไปมาระหว่างชื่อสั้น/ยาว) |
| `add()` / `update()` / `delete()` | จัดการหลักสูตร |
| `activate()` / `deactivate()` | เปิด/ปิดรับสมัครคอร์ส |

### `backend/lib/StudentRepository.php`
รวม SQL ทั้งหมดที่เกี่ยวกับตาราง `students` ไว้ที่เดียว (แบ่งหน้า+ค้นหา+กรอง+เรียงลำดับ, add, update, delete, insert จาก webhook, insert จาก Airtable, ดึงรายชื่อทั้งหมดสำหรับหน้า public)

> หมายเหตุ: `jotform_webhook.php` เปลี่ยนมาเรียก `insertFromAirtable()` แทน `insertFromWebhook()` แล้ว เพราะฟอร์มบริษัทมีฟิลด์ละเอียดกว่าฟอร์มทดลองเดิม (มีคณะ/สาขา/สถาบัน/วันเกิด/ระดับการศึกษาด้วย) — ดูรายละเอียดที่หัวข้อ [การเชื่อมต่อ JotForm ของบริษัท](#การเชื่อมต่อ-jotform-ของบริษัท-dual-write-เข้า-airtable--เว็บใหม่)

### `backend/auth.php`
ระบบ login ของ admin ใช้ PHP session (`$_SESSION['is_admin']`) อ่าน username/password จาก environment variables (`ADMIN_USER`, `ADMIN_PASS`) — `action=login`, `action=logout`, `action=check`

### `backend/api.php`
Endpoint สาธารณะ (ไม่ต้อง login) ที่หน้าดาวน์โหลดใบประกาศเรียกใช้ตอนโหลดหน้าเว็บ — ดึงรายชื่อผู้เรียนทั้งหมดผ่าน `StudentRepository::listAllWithCourse()` จัดรูปแบบ JSON คล้าย Airtable เดิม

### `backend/admin_api.php`
Endpoint หลักสำหรับ CRUD ทั้งหมดในหน้า Admin (ต้อง login ผ่าน `require_admin.php`) เป็น thin controller ที่เรียกใช้ `CourseRepository` และ `StudentRepository`:

| Action | หน้าที่ |
|---|---|
| `list_courses` | ดึงคอร์สทั้งหมด พร้อมนับจำนวนผู้เรียนต่อคอร์ส |
| `add_course` / `update_course` / `delete_course` | จัดการหลักสูตร |
| `list_students` | ดึงรายชื่อผู้เรียนแบบแบ่งหน้า รองรับค้นหา/กรองปี/กรองคอร์ส/เรียงลำดับ (whitelist คอลัมน์ป้องกัน SQL Injection ผ่าน `ORDER BY`) |
| `get_student` | ดึงข้อมูลผู้เรียน 1 คนแบบละเอียด |
| `add_student` / `update_student` / `delete_student` | จัดการรายชื่อผู้เรียน |
| `airtable_preview` | ดึงข้อมูลจาก Airtable มา group ตามคอร์ส เปรียบเทียบกับคอร์สในระบบ แยกเป็น "คอร์สใหม่" / "คอร์สที่มีอยู่แล้ว" (query database แค่ครั้งเดียว ไม่ว่าจะมีกี่ record) — ใช้เมนูนี้ดึงทั้งข้อมูลเก่าทั้งหมด และผู้สมัครที่ webhook เคยหาคอร์สไม่เจอมาก่อน |
| `airtable_import_course` | สร้างคอร์สใหม่ (ถ้าจำเป็น) + นำเข้านักเรียนที่เลือกในคอร์สนั้น ในทีเดียวภายใน transaction เดียวกัน |

### `backend/active_courses.php`
Endpoint สำหรับหน้า "เปิดรับสมัคร" (ต้อง login) อ่าน JotForm API Key/Form ID/Field ID จาก environment variables — **ปัจจุบันไม่ได้ใช้งานคู่กับฟอร์มบริษัทจริง** (แอดมินเปิด/ปิดคอร์สที่หน้า JotForm โดยตรงแทน) แต่โค้ดยังคงเก็บไว้เผื่อกลับมาใช้ในอนาคต:

| Action | หน้าที่ |
|---|---|
| `list` | ดึงคอร์สทั้งหมด พร้อม flag `is_active` |
| `toggle` | เปิด/ปิดคอร์ส |
| `sync_jotform` | ดึง**ชื่อยาว** (`long_key`) ของคอร์สที่เปิดอยู่จาก Neon ผ่าน `CourseRepository::listActiveLongKeys()` → เรียก JotForm API โดยตรงเพื่ออัปเดตตัวเลือกใน dropdown |

### `backend/jotform_webhook.php`
**Endpoint สำคัญที่สุดในระบบ** — จุดเดียวที่ต้องเปิดรับ request จากภายนอกอินเทอร์เน็ตจริง ๆ (ไม่ต้อง login เพราะ JotForm server เป็นคนเรียก)

**รับข้อมูลจากฟอร์มบริษัท `231301336713444`** (ไม่ใช่ฟอร์มทดลอง `261701468554460` อีกต่อไป — ฟอร์มทดลองเลิกใช้แล้ว) ทำงานดังนี้:

```
รับข้อมูลดิบจาก JotForm ผ่าน field 'rawRequest'
        → แกะฟิลด์ตาม field ID ของฟอร์มบริษัท (ดูตาราง mapping ในหัวข้อถัดไป)
        → CourseRepository::findByShortNameOrLongKey() หา course_id
              ├─ เจอ    → StudentRepository::insertFromAirtable() บันทึกเข้า Neon
              └─ ไม่เจอ → ไม่บันทึก, ตอบ error พร้อมข้อความแนะนำให้ไปที่เมนู
                          "นำเข้าข้อมูลจาก Airtable" เพื่อเพิ่มคอร์สนี้ก่อน
        → log ผลลัพธ์ผ่าน error_log() ทุกครั้ง (ทั้งสำเร็จและ error)
```

> รายละเอียด field mapping ทั้งหมด และการตั้งค่า Webhook integration ฝั่ง JotForm ดูที่หัวขัด [การเชื่อมต่อ JotForm ของบริษัท](#การเชื่อมต่อ-jotform-ของบริษัท-dual-write-เข้า-airtable--เว็บใหม่)

### `database/schema.sql`
คำสั่งสร้างตารางทั้งหมด เขียนด้วย **PostgreSQL syntax จริง** (`SERIAL`, `REFERENCES ... ON DELETE CASCADE` ฯลฯ) ตรงกับโครงสร้างที่ใช้งานจริงบน Neon

### `database/seed.sql`
ข้อมูลตัวอย่างสำหรับ demo/ทดสอบระบบ — **ทุกชื่อ/เบอร์/อีเมลเป็นข้อมูลสมมติทั้งหมด** ใช้รันหลัง `schema.sql` เพื่อทดลองใช้งานระบบโดยไม่ต้องพึ่งข้อมูลจริงของบริษัท

### `database/config.php`
อ่านค่าการเชื่อมต่อ Neon จาก **Environment Variables** (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`) ไม่มี credential ฝังอยู่ในโค้ดเลย และมีฟังก์ชัน `getDB()` คืนค่า PDO connection แบบ singleton

---

## โครงสร้างฐานข้อมูล

### ตาราง `courses`
| คอลัมน์ | ความหมาย |
|---|---|
| `id` | primary key (SERIAL) |
| `long_key` | ชื่อยาว ต้องไม่ซ้ำกัน (UNIQUE) — ใช้ match กับ field "โปรแกรมที่สมัคร" ใน Airtable **และปัจจุบันเป็นค่าที่แสดง/sync ไปที่ dropdown ของ JotForm ด้วย** |
| `short_name` | ชื่อย่อ — เดิมใช้ match กับ dropdown ใน JotForm ปัจจุบันใช้เป็นข้อมูลอ้างอิงเสริมในหน้า admin เท่านั้น |
| `training_date` | วันที่อบรม (ข้อความอิสระ) |
| `year_be` | ปี พ.ศ. 4 หลัก ใช้กรองข้อมูล |
| `verify_url` | ลิงก์ปลายทางของ QR Code บนใบประกาศ |

> **หมายเหตุเรื่อง `long_key`:** เป็นข้อความอิสระที่แอดมินกรอกเอง ไม่มีรูปแบบบังคับ บางคอร์สอาจมีชื่อย่อกำกับในวงเล็บ บางคอร์สไม่มี ขึ้นอยู่กับตอนกรอกข้อมูล ไม่กระทบการทำงานของระบบ

### ตาราง `students`
เก็บข้อมูลผู้เรียนแต่ละคน ผูกกับหลักสูตรผ่าน `course_id` (Foreign Key, `NOT NULL`, `ON DELETE CASCADE`) มีฟิลด์ข้อมูลส่วนตัวครบถ้วน (ชื่อ, เบอร์, อีเมล, หน่วยงาน, ตำแหน่ง, คณะ, สาขา, สถาบัน, วันเกิด ฯลฯ) และมี `airtable_id` สำหรับกันนำเข้าซ้ำจาก Airtable (ค่าจะเป็น `NULL` สำหรับแถวที่เข้ามาผ่าน webhook โดยตรง)

### ตาราง `active_courses`
ตารางเชื่อม (junction table) เก็บแค่ `course_id` ที่กำลังเปิดรับสมัครอยู่ — ถ้ามีแถวในนี้แปลว่าคอร์สนั้นเปิดรับสมัคร ถ้าไม่มีแปลว่าปิด (ปัจจุบันไม่ได้ผูกกับการเปิด/ปิดจริงบน JotForm ของบริษัทแล้ว เพราะแอดมินไปจัดการที่ JotForm โดยตรง)

---

## Data Flow แต่ละสถานการณ์

### 1. ผู้เข้าอบรมดาวน์โหลดใบประกาศ
```
เปิดเว็บ → certificate.js เรียก api.php → StudentRepository::listAllWithCourse()
        → เลือกปี/คอร์ส/ชื่อ (กรองฝั่ง frontend) → พรีวิวใบประกาศ real-time
        → กด "ดาวน์โหลด" → html2canvas แปลงเป็น PNG
```

### 2. ผู้สมัครใหม่กรอกฟอร์ม JotForm บริษัท (Dual-write)
```
[ผู้สมัคร] กรอกฟอร์ม 231301336713444 + เลือกหลักสูตร → กด Submit
        │
        ├──→ (integration เดิม) Airtable บริษัท — บันทึกตามปกติ ไม่เปลี่ยนแปลง
        │
        └──→ (integration ใหม่) Webhook → jotform_webhook.php
                   → decode rawRequest, แกะฟิลด์ตาม field id ของฟอร์มบริษัท
                   → CourseRepository::findByShortNameOrLongKey() หา course_id
                        ├─ เจอ    → StudentRepository::insertFromAirtable() → ตอบ {ok:true, id:...}
                        └─ ไม่เจอ → ไม่บันทึก → ตอบ {ok:false, error:"..."} (Airtable ยังบันทึกได้ปกติ)
```
รายละเอียดเต็มดูที่หัวข้อ [การเชื่อมต่อ JotForm ของบริษัท](#การเชื่อมต่อ-jotform-ของบริษัท-dual-write-เข้า-airtable--เว็บใหม่)

### 3. Admin เปิด/ปิดคอร์สแล้ว Sync ไปยัง JotForm *(ฟีเจอร์นี้ไม่ได้ใช้กับฟอร์มบริษัทแล้ว — เก็บโค้ดไว้เผื่ออนาคต)*
```
[Admin] ติ๊กเปิด/ปิดคอร์ส → admin.js เรียก active_courses.php?action=toggle
        → CourseRepository::activate()/deactivate()
[Admin] กด "Sync to JotForm" → active_courses.php?action=sync_jotform
        → CourseRepository::listActiveLongKeys()   ← ดึงเป็น "ชื่อยาว" แล้ว
        → รวมชื่อคอร์สด้วย '|' → เรียก JotForm API (POST /form/{id}/question/{field})
        → JotForm อัปเดตตัวเลือกใน dropdown ของฟอร์มจริง
```
> แอดมินปัจจุบันเปิด/ปิดคอร์สโดยเข้าไปแก้ dropdown ที่หน้า JotForm ของบริษัทโดยตรงแทน ไม่ผ่านเมนูนี้

### 4. Admin นำเข้าข้อมูลจาก Airtable (ทั้งของเก่า และรายการที่ webhook หาไม่เจอคอร์ส)
```
[Admin] กด "ดึงข้อมูลจาก Airtable"
        → airtable_preview: AirtableClient::fetchAllRecords() (วน pagination จนครบ)
        → ดึง courses ทั้งหมดจาก Neon ครั้งเดียว → index เป็น map ใน PHP
        → group records ตามชื่อคอร์ส → match กับ map → แยก new_courses / existing_courses
        → แสดงตาราง 2 กลุ่ม พร้อมจำนวนคนที่รอนำเข้า

[Admin] กดปุ่ม "สร้างคอร์ส + นำเข้า" หรือ "นำเข้านักเรียน"
        → เปิด modal: ถ้าคอร์สใหม่ให้กรอกข้อมูลเพิ่ม, ติ๊กเลือกนักเรียน
        → airtable_import_course (transaction เดียว):
           1. สร้างคอร์สใหม่ถ้าจำเป็น (courseRepo::add)
           2. ดึงข้อมูลจาก Airtable ใหม่อีกครั้ง (verify ฝั่ง server)
           3. insert นักเรียนที่เลือกไว้ทีละคน (studentRepo::insertFromAirtable)
        → commit หรือ rollback ถ้าพัง
```
> **ใช้เมนูนี้เป็นทางแก้เวลา webhook หาคอร์สไม่เจอ** — เมื่อแอดมินเพิ่มคอร์สใหม่ใน JotForm dropdown ก่อนที่จะมาสร้างคอร์สในเว็บนี้ ผู้สมัครที่เลือกคอร์สนั้นจะยังเข้า Airtable ปกติ แต่ตกหล่นจาก Neon — แอดมินมาสร้างคอร์สให้ตรงชื่อ แล้วกดดึงข้อมูลจาก Airtable มานำเข้าย้อนหลังได้จากเมนูนี้

---

## การเชื่อมต่อ JotForm ของบริษัท (Dual-write เข้า Airtable + เว็บใหม่)

### ภาพรวม

ระบบเดิมของบริษัทใช้ JotForm (`231301336713444`) ส่งข้อมูลเข้า Airtable (`appI37LT5wFrI4ITA`) อย่างเดียว ระบบใหม่ **เพิ่ม Webhook เข้าไปเสริมคู่ขนาน** โดยไม่แตะ Airtable integration เดิมเลย ทำให้พอมีคนกด Submit ฟอร์ม ข้อมูลจะเข้าทั้ง 2 ที่พร้อมกันอัตโนมัติ:

```
[คนกรอกฟอร์ม] → JotForm 231301336713444
                     ├──→ Airtable appI37LT5wFrI4ITA  (integration เดิม ไม่เปลี่ยนแปลง)
                     └──→ Webhook → jotform_webhook.php → Neon (integration ใหม่)
```

### วิธีตั้งค่า Webhook ฝั่ง JotForm

1. เข้า https://www.jotform.com/ → login ด้วยบัญชีที่เข้าถึงฟอร์มบริษัทได้
2. เปิดฟอร์ม `231301336713444` → แท็บ **Settings → Integrations**
3. ตรวจสอบว่า **Airtable** integration เดิมยังอยู่ (ไม่แตะต้อง)
4. ค้นหา **"Webhooks"** → เพิ่ม URL ปลายทาง:
   ```
   https://comtrain-webcertificate.onrender.com/backend/jotform_webhook.php
   ```
5. Save — ควรเห็น 2 integrations พร้อมกัน (Airtable + Webhooks)

### Field Mapping ของฟอร์มบริษัท (231301336713444)

| ข้อมูล | field id | JotForm key ที่ส่งมาใน `rawRequest` |
|---|---|---|
| ชื่อ | 110 | `q110_typeA110` |
| นามสกุล (ฉายา) | 111 | `q111_input111` |
| ประเภทสมาชิกองค์กร | 17 | `q17_typeA` |
| อายุ / พรรษาที่ (รวมช่องเดียว) | 60 | `q60_input60` |
| ระดับการศึกษาสูงสุด | 61 | `q61_input61` |
| คณะ | 90 | `q90_input90` |
| สาขา | 79 | `q79_input79` |
| สถาบัน/โรงเรียน | 75 | `q75_input75` |
| สังกัดหน่วยงาน/กอง/ศูนย์ | 31 | `q31_typeA31` |
| สำนัก | 32 | `q32_input59` |
| ตำแหน่ง | 33 | `q33_typeA33` |
| เบอร์ภายใน | 38 | `q38_input38['full']` ⚠️ เป็น array (`control_phone`) ต้องดึง `['full']` |
| เบอร์มือถือ | 39 | `q39_phoneNumber['full']` |
| Email | 82 | `q82_email82` |
| วันเกิด | 98 | `q98_input98` (object: year/month/day) |
| **โปรแกรมที่ต้องการสมัครเรียน (dropdown คอร์ส)** | 93 | `q93_input93` |
| วันที่ส่งแบบฟอร์ม | 9 | `q9_date` (object: year/month/day) |
| ค่าสมัครอบรม | 104 | `q104_input104` |

> ฟอร์มนี้ไม่มีช่อง "พรรษาที่" แยกจาก "อายุ" (รวมเป็นช่องเดียว) และไม่มีช่อง "สถานะหัวหน้ากอง" — ทั้งสองปล่อยเป็น `NULL` ในตาราง `students`

### พฤติกรรมเมื่อหาคอร์สไม่เจอ

ผู้สมัครจะ**ไม่เห็น error ใด ๆ**เลยครับ (JotForm แสดงหน้า Thank you ปกติเสมอ ไม่ว่า webhook ปลายทางจะสำเร็จหรือไม่) และ **Airtable บันทึกได้ปกติเสมอ** เพราะเป็น integration อิสระจาก webhook

ฝั่งเว็บนี้จะไม่สร้างคอร์สใหม่อัตโนมัติ (ตัดสินใจไว้แล้วว่าให้ข้อมูลคอร์สสะอาดสมบูรณ์เสมอ ดีกว่า auto-create แล้วต้องมาเก็บงานทีหลัง) แต่จะ:
1. ไม่บันทึกข้อมูลผู้สมัครคนนั้นลง Neon
2. Log ข้อความ error ไว้ (ดูได้ 2 ที่ — ดูหัวข้อถัดไป)
3. ตอบกลับ JotForm ด้วยข้อความแนะนำให้แอดมินไปที่เมนู "นำเข้าข้อมูลจาก Airtable" เพื่อเพิ่มคอร์สนั้นเข้าระบบ แล้วดึงข้อมูลย้อนหลังมาได้

### วิธีเช็คว่ามีเคสหาคอร์สไม่เจอไหม

**ที่ 1 — Render Logs:** Dashboard → service → แท็บ Logs → ค้นหา `jotform_webhook`
- `[jotform_webhook] OK: ...` = บันทึกสำเร็จ
- `[jotform_webhook] ERROR: ไม่พบคอร์สชื่อ '...' ในระบบ` = ตกหล่น ต้องไปสร้างคอร์สแล้วนำเข้าย้อนหลัง

**ที่ 2 — JotForm เอง:** เปิดฟอร์ม → Settings → Integrations → Webhooks → ดู Log/History ของ integration นี้โดยเฉพาะ (กรองมาแล้วเฉพาะ webhook นี้ ไม่ปนกับ log อื่นของเว็บ)

---

## วิธีรันโปรเจกต์บนเครื่องตัวเอง (Local Setup)

1. **เตรียม PostgreSQL database** — ใช้ [Neon](https://neon.tech) (ฟรี) หรือ Postgres ที่ติดตั้งเองก็ได้

2. **สร้างตารางและข้อมูลตัวอย่าง**
   ```bash
   psql "postgresql://<user>:<pass>@<host>/<dbname>" -f database/schema.sql
   psql "postgresql://<user>:<pass>@<host>/<dbname>" -f database/seed.sql
   ```

3. **สร้างไฟล์ `.env`** ที่ root ของโปรเจกต์ (ดูรายการตัวแปรทั้งหมดที่หัวข้อถัดไป):
   ```env
   ADMIN_USER=admin
   ADMIN_PASS=your_password

   DB_HOST=your-neon-host
   DB_PORT=5432
   DB_NAME=neondb
   DB_USER=neondb_owner
   DB_PASS=your_db_password

   AIRTABLE_API_KEY=your_airtable_key
   AIRTABLE_BASE_ID=your_base_id
   AIRTABLE_TABLE_ID=your_table_id

   JOTFORM_API_KEY=your_jotform_key
   JOTFORM_FORM_ID=your_form_id
   JOTFORM_FIELD_ID=your_field_id
   ```

4. **ตรวจสอบ PHP extension**
   ```bash
   php -m | findstr pgsql
   ```
   ต้องเห็น `pdo_pgsql` และ `pgsql` — ถ้าไม่เจอให้เปิดใน `php.ini`

5. **แก้ไข `php.ini` สำหรับ SSL** (Windows เท่านั้น) — ดาวน์โหลด `cacert.pem` จาก [curl.se/ca/cacert.pem](https://curl.se/ca/cacert.pem) แล้วตั้งค่า:
   ```ini
   curl.cainfo = "C:\php\cacert.pem"
   openssl.cafile = "C:\php\cacert.pem"
   ```

6. **รันเว็บด้วย PHP built-in server**
   ```bash
   php -S localhost:8000
   ```
   เปิด `http://localhost:8000`

7. **(ทางเลือก) รันผ่าน Docker เหมือนบน Render**
   ```bash
   docker build -t webcertificate .
   docker run -p 8080:10000 -e PORT=10000 --env-file .env webcertificate
   ```

> หมายเหตุ: ฟีเจอร์ที่พึ่งพา JotForm (`active_courses.php?action=sync_jotform`, `jotform_webhook.php`) จะทำงานได้เต็มรูปแบบก็ต่อเมื่อตั้งค่า `JOTFORM_API_KEY`/`JOTFORM_FORM_ID`/`JOTFORM_FIELD_ID` ให้ตรงกับฟอร์มจริงของคุณเองด้วย และต้อง map field id ให้ตรงกับฟอร์มที่ใช้จริง (ดูตาราง mapping ในหัวข้อ [การเชื่อมต่อ JotForm ของบริษัท](#การเชื่อมต่อ-jotform-ของบริษัท-dual-write-เข้า-airtable--เว็บใหม่))

---

## Environment Variables ที่ต้องตั้งค่า

| ตัวแปร | คำอธิบาย | ใช้ในไฟล์ |
|---|---|---|
| `DB_HOST` | Host ของ Neon database | `database/config.php` |
| `DB_PORT` | Port (ปกติ `5432`) | `database/config.php` |
| `DB_NAME` | ชื่อ database | `database/config.php` |
| `DB_USER` | Username | `database/config.php` |
| `DB_PASS` | Password | `database/config.php` |
| `ADMIN_USER` | Username สำหรับ login admin | `backend/auth.php` |
| `ADMIN_PASS` | Password สำหรับ login admin | `backend/auth.php` |
| `AIRTABLE_API_KEY` | Personal Access Token จาก Airtable | `backend/admin_api.php` |
| `AIRTABLE_BASE_ID` | Base ID ของ Airtable (ขึ้นต้นด้วย `app`) | `backend/admin_api.php` |
| `AIRTABLE_TABLE_ID` | Table ID ของ Airtable (ขึ้นต้นด้วย `tbl`) | `backend/admin_api.php` |
| `JOTFORM_API_KEY` | API Key จาก JotForm account (ใช้เฉพาะฟีเจอร์ sync ที่ปัจจุบันไม่ได้ใช้กับฟอร์มบริษัท) | `backend/active_courses.php` |
| `JOTFORM_FORM_ID` | ID ของฟอร์มสมัครเรียนใน JotForm | `backend/active_courses.php` |
| `JOTFORM_FIELD_ID` | ID ของ field dropdown เลือกคอร์สในฟอร์ม | `backend/active_courses.php` |

บน Render ตั้งค่าได้ที่ Dashboard → service → Environment tab

---

## ข้อจำกัดและวิธีแก้ที่ใช้อยู่

### Render แผนฟรี "หลับ" หลัง idle 15 นาที
เมื่อไม่มีคนเข้าใช้ 15 นาที Render จะปิด container ชั่วคราว คนแรกที่กลับมาเข้าเว็บต้องรอ ~30-50 วินาทีให้ container ตื่น **วิธีแก้ที่ใช้อยู่:** ตั้ง **UptimeRobot** ปิง URL ทุก 5 นาที ทำให้เว็บไม่เคย idle ครบ 15 นาที

### SSL Certificate บนเครื่อง Windows
PHP บน Windows อาจไม่มี SSL certificate bundle ทำให้ `curl` ต่อ Airtable API ไม่ได้ **วิธีแก้:** ดาวน์โหลด `cacert.pem` จาก [curl.se/ca/cacert.pem](https://curl.se/ca/cacert.pem) แล้วตั้งค่าใน `php.ini` (ดูรายละเอียดที่ขั้นตอน Local Setup ข้อ 5)

### Webhook ตกหล่นเมื่อคอร์สยังไม่มีในระบบ
เพราะแอดมินไปเพิ่ม/แก้ dropdown ที่หน้า JotForm โดยตรง โดยไม่ผ่านเว็บนี้ก่อน ผู้สมัครที่เลือกคอร์สใหม่ (ที่ยังไม่มีในตาราง `courses`) จะเข้า Airtable ปกติ แต่ไม่เข้า Neon **วิธีแก้ที่ใช้อยู่:** เช็ค Render Logs หรือ JotForm webhook log เป็นระยะ แล้วใช้เมนู "นำเข้าข้อมูลจาก Airtable" สร้างคอร์สที่ตกหล่น + นำเข้าย้อนหลัง

---

## ปัญหาที่เคยเจอ และวิธีแก้ (Troubleshooting Log)

| ปัญหา | สาเหตุ | วิธีแก้ |
|---|---|---|
| หน้า Airtable preview แสดงแค่คอร์สละ 1 คน | field ที่ใช้ group คือ "ชื่อโปรแกรม" ซึ่งส่วนใหญ่ว่าง — field จริงที่มีข้อมูลครบคือ "โปรแกรมที่สมัคร" (Single select) | แก้ `AIRTABLE_COURSE_FIELD` ใน `admin_api.php` จาก `ชื่อโปรแกรม` เป็น `โปรแกรมที่สมัคร` |
| `Cannot read properties of undefined (reading 'filter')` | browser โหลด `admin.js` เวอร์ชันเก่าจาก cache | กด `Ctrl+Shift+R` หรือเพิ่ม `?v=N` ต่อท้ายชื่อไฟล์ JS ใน `index.html` |
| `Maximum execution time exceeded` บนเครื่อง local | query database ทีละ record (829 ครั้ง) ช้าเกิน 30 วินาที | แก้ให้ดึง courses ทั้งหมดมาครั้งเดียวแล้ว match ใน PHP + เพิ่ม `max_execution_time` ใน `php.ini` |
| `SSL certificate verify failed` บนเครื่อง Windows | PHP ไม่มี CA bundle | ดาวน์โหลด `cacert.pem` และตั้งค่า `curl.cainfo` / `openssl.cafile` ใน `php.ini` |
| admin credentials ไม่ถูกรู้จักบนเครื่อง local | `auth.php` และ endpoint อื่นไม่ได้โหลด `.env` | สร้าง `load_env.php` รวมโค้ดโหลด `.env` ไว้ที่เดียว แล้วให้ทุกไฟล์ `require_once` แค่บรรทัดเดียว |
| `airtable_preview` ช้ามากบนเครื่อง local | เรียก `findByShortNameOrLongKey()` ทุก record = query หลายร้อยครั้ง | เปลี่ยนเป็นดึง courses ทั้งหมดมาครั้งเดียว index เป็น PHP array map แล้วค้นหาใน memory แทน |
| Sync ไป JotForm แล้ว dropdown ยังโชว์ชื่อย่อ ไม่ใช่ชื่อยาวตามที่ต้องการ | `listActiveShortNames()` และหน้า admin ดึง/แสดง `short_name` อยู่ | เปลี่ยนเป็น `listActiveLongKeys()` (query คอลัมน์ `long_key`) ทั้งใน `CourseRepository`, `active_courses.php`, และตาราง "เปิดรับสมัคร" ใน `admin.js` |
| Webhook ตอบ `500 Internal Server Error` ทุกครั้งที่มีคน submit ฟอร์มบริษัท (`trim(): Argument #1 ($string) must be of type string, array given`) | field "เบอร์ภายใน" (id 38) เป็น type `control_phone` เหมือน field เบอร์มือถือ ส่งมาเป็น array ไม่ใช่ string ธรรมดา แต่โค้ดเขียน `trim($raw['q38_input38'])` ตรง ๆ | แก้เป็น `trim($raw['q38_input38']['full'] ?? '')` ให้ดึงค่าจาก array เหมือนที่ทำกับเบอร์มือถือ |
| ย้ายไปใช้ JotForm ฟอร์มบริษัท (231301336713444) แล้ว webhook หาคอร์สไม่เจอทั้งที่มีคอร์สนั้นในระบบแล้ว | field id ของฟอร์มบริษัทไม่เหมือนฟอร์มทดลองเดิม (`q93_input115` เดิม → ที่จริงคือ `q93_input93` ในฟอร์มใหม่) รวมถึงฟิลด์อื่น ๆ ทั้งหมดเปลี่ยน id ตามฟอร์ม | ดึง field id จริงผ่าน `GET https://api.jotform.com/form/{formId}/questions?apiKey=...` แล้ว map ใหม่ทั้งหมดใน `jotform_webhook.php` ตามฟอร์มที่ใช้งานจริง |

---

## ประวัติการพัฒนา

ระบบเดิมของศูนย์อบรมคอมพิวเตอร์ยังไม่มีระบบออกใบประกาศนียบัตรออนไลน์ให้ผู้เข้าอบรมเลย ส่วนการรับสมัครทำได้แค่ให้ผู้สมัครกรอกฟอร์มผ่านลิงก์ **JotForm** แล้วข้อมูลจะถูกบันทึกเก็บไว้ใน **Airtable** เพียงเท่านั้น ไม่มีระบบจัดการหลักสูตร ไม่มีระบบดึงรายชื่อมาออกใบประกาศ และไม่มี Admin Panel ใด ๆ

โปรเจกต์นี้จึงถูกสร้างขึ้นมาใหม่ทั้งหมด เพื่อให้ผู้เข้าอบรมสามารถดึงรายชื่อคอร์สต่าง ๆ และรายชื่อของตัวเองมา **ออกใบประกาศนียบัตรออนไลน์ได้ด้วยตัวเอง** โดยช่วงแรกทดลองต่อกับฟอร์ม JotForm ของตัวเองก่อน (261701468554460) เพื่อทดสอบ flow การรับสมัคร → เก็บลง Neon (PostgreSQL) ทั้งหมด

หลังจากทดสอบระบบผ่านเรียบร้อย จึงย้ายมาเชื่อมกับ **ฟอร์มจริงของบริษัท (231301336713444)** ที่มีอยู่เดิมและเชื่อมกับ Airtable บริษัทอยู่แล้ว โดยออกแบบให้เป็น **dual-write**: เพิ่ม Webhook integration เข้าไปคู่ขนานกับ Airtable integration เดิม โดยไม่แตะของเดิมเลย ทำให้เมื่อมีคนสมัครเรียนใหม่ ข้อมูลจะเข้าทั้ง Airtable (ระบบเดิมของบริษัท) และ Neon (ระบบใหม่) พร้อมกันอัตโนมัติ แอดมินยังคงเปิด/ปิดคอร์สที่หน้า JotForm ได้ตามเดิมทุกประการ ไม่ต้องเปลี่ยนพฤติกรรมการทำงาน

เพื่อรองรับกรณีที่แอดมินเพิ่มคอร์สใหม่ใน JotForm dropdown ก่อนที่จะมาสร้างคอร์สในเว็บใหม่ (ทำให้ webhook หาคอร์สไม่เจอชั่วคราว) ระบบออกแบบให้**ไม่ auto-create คอร์ส**เพื่อรักษาความสะอาดของข้อมูล แต่จะบันทึก log ไว้ให้ตรวจสอบได้ และมีฟีเจอร์ "นำเข้าข้อมูลจาก Airtable" คอยเป็นทางแก้ย้อนหลัง — กด import จาก Airtable ได้ทุกเมื่อเพื่อดึงรายชื่อที่ตกหล่นกลับเข้าระบบ

ส่วนปัญหาเว็บ "หลับ" ของ hosting แผนฟรีก็แก้ด้วย UptimeRobot ทำให้ใช้งานได้ต่อเนื่องโดยไม่ต้องเสียค่าใช้จ่ายเพิ่ม