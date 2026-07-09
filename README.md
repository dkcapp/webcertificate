# ระบบออกใบประกาศนียบัตร — ศูนย์อบรมคอมพิวเตอร์ วัดพระธรรมกาย

ระบบออกใบประกาศนียบัตรให้ผู้เข้าอบรม พร้อมระบบจัดการหลักสูตร/รายชื่อผู้เรียน และรับสมัครผ่านฟอร์มออนไลน์แบบอัตโนมัติ

**เว็บไซต์ใช้งานจริง:** https://webcertificate.onrender.com

---

## สารบัญ
1. [ภาพรวมระบบทำอะไรได้บ้าง](#ภาพรวมระบบทำอะไรได้บ้าง)
2. [สถาปัตยกรรมระบบ (System Architecture)](#สถาปัตยกรรมระบบ-system-architecture)
3. [เทคโนโลยีที่ใช้](#เทคโนโลยีที่ใช้)
4. [โครงสร้างไฟล์ + คำอธิบายทีละไฟล์](#โครงสร้างไฟล์--คำอธิบายทีละไฟล์)
5. [โครงสร้างฐานข้อมูล](#โครงสร้างฐานข้อมูล)
6. [Data Flow แต่ละสถานการณ์](#data-flow-แต่ละสถานการณ์)
7. [วิธีรันโปรเจกต์บนเครื่องตัวเอง (Local Setup)](#วิธีรันโปรเจกต์บนเครื่องตัวเอง-local-setup)
8. [Environment Variables ที่ต้องตั้งค่า](#environment-variables-ที่ต้องตั้งค่า)
9. [ข้อจำกัดและวิธีแก้ที่ใช้อยู่](#ข้อจำกัดและวิธีแก้ที่ใช้อยู่)
10. [ปัญหาที่เคยเจอ และวิธีแก้ (Troubleshooting Log)](#ปัญหาที่เคยเจอ-และวิธีแก้-troubleshooting-log)
11. [ประวัติการพัฒนา](#ประวัติการพัฒนา)

---

## ภาพรวมระบบทำอะไรได้บ้าง

ระบบนี้มี 3 กลุ่มผู้ใช้งาน:

### 1. ผู้เข้าอบรม (หน้าแรกของเว็บ — Public)
เลือก **ปีที่อบรม → หลักสูตร → ชื่อตัวเอง** แล้วกดดาวน์โหลดใบประกาศนียบัตรเป็นไฟล์ภาพ (PNG) ได้ทันที ระบบจะดึงชื่อ-นามสกุลมาใส่ในใบประกาศให้อัตโนมัติ พร้อม QR Code สำหรับตรวจสอบความถูกต้อง (ถ้าหลักสูตรนั้นตั้งค่า verify URL ไว้)

### 2. เจ้าหน้าที่ (Admin Panel — ต้อง Login)
- **จัดการหลักสูตร** — เพิ่ม/แก้ไข/ลบหลักสูตร, วันที่อบรม, ปี พ.ศ., ลิงก์ QR verify
- **จัดการรายชื่อผู้เรียน** — ดู/ค้นหา/แก้ไข/ลบ พร้อมแบ่งหน้าและเรียงลำดับ
- **เปิด/ปิดรับสมัคร** — เลือกหลักสูตรที่เปิดให้สมัครผ่านฟอร์มออนไลน์ แล้วกด "Sync to JotForm" เพื่ออัปเดต dropdown ของฟอร์มให้ตรงกับหลักสูตรที่เปิดอยู่จริง

### 3. ผู้สมัครเรียนใหม่ (ภายนอกบริษัท ผ่าน JotForm)
กรอกฟอร์มสมัครใน JotForm เลือกหลักสูตรจาก dropdown (sync มาจากระบบ admin) กด Submit แล้วข้อมูลเข้าฐานข้อมูลทันทีโดยไม่ต้องมีคนกรอกซ้ำ

---

## สถาปัตยกรรมระบบ (System Architecture)

![System Architecture Diagram](/assets/docs/architecture-diagram.png)

จุดสำคัญของสถาปัตยกรรมนี้คือ **แยกตามทิศทางการเชื่อมต่อ**: ส่วนไหนต้อง "รับ" การเชื่อมต่อจากภายนอกอินเทอร์เน็ต (ต้องมี public URL) กับส่วนไหนแค่ "เชื่อมต่อออกไปหา" บริการอื่น (รันจากที่ไหนก็ได้)

### ทำไมต้องออกแบบแบบนี้ (เหตุผลเชิงสถาปัตยกรรม)

| คำถาม | คำตอบ |
|---|---|
| ทำไมต้องมี public hosting (Render) ทั้งที่คนใช้จริงอยู่แค่ในบริษัท? | เพราะ **JotForm ต้องยิง Webhook เข้ามาหา `jotform_webhook.php` ได้** — นี่คือ "ขาเข้า" (inbound) จากอินเทอร์เน็ตภายนอก server ที่อยู่หลัง firewall บริษัท (intranet-only) จะไม่มีทางถูกเรียกถึงได้เลย |
| ทำไม PHP เชื่อมกับ Neon ได้จากทุกที่ (ทั้ง localhost และ Render)? | เพราะการเชื่อมต่อไปหา Neon เป็น **"ขาออก" (outbound)** — server เราเป็นฝ่ายโทรออกไปหา Neon เอง ไม่ว่าจะรันจากที่ไหนก็ทำได้ตราบใดที่มี internet และ credential ถูกต้อง |
| ทำไมไม่ใช้ Google Apps Script เหมือนเดิม? | ระบบเดิมใช้ Apps Script เป็น "ตัวกลางที่มี public URL ฟรีจาก Google" คอยรับ Webhook แทน แล้วค่อยต่อไปหา Supabase อีกที ปัจจุบันตัดตัวกลางนี้ออก ให้ PHP ของเรารับ Webhook เองตรง ๆ ลดจุดเชื่อมต่อที่ต้องดูแล |

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

พร้อมด้วย 2 ไฟล์ shared ที่ endpoint ทุกตัวเรียกใช้ร่วมกัน:
- **`backend/lib/require_admin.php`** — middleware เช็คสิทธิ์ admin กลาง ใช้ที่เดียวไม่ต้องเขียนซ้ำทุกไฟล์
- **`backend/lib/error_handler.php`** — จัดการ error กลาง log รายละเอียดจริงไว้ฝั่ง server เท่านั้น ส่งข้อความทั่วไปกลับไปหา client (ไม่เผยรายละเอียด database ให้ผู้ไม่หวังดี)

---

## เทคโนโลยีที่ใช้

| ส่วน | เทคโนโลยี | หน้าที่ |
|---|---|---|
| **Frontend** | HTML + CSS + Vanilla JavaScript (ไฟล์เดียว `index.html`) | หน้าดาวน์โหลดใบประกาศ + หน้า Admin Panel |
| **สร้างภาพใบประกาศ** | [html2canvas](https://html2canvas.hertzen.com/) | แปลง HTML เป็นไฟล์ภาพ PNG ให้ดาวน์โหลด |
| **QR Code** | [qrcodejs](https://github.com/davidshimjs/qrcodejs) | สร้าง QR Code บนใบประกาศ |
| **Backend** | PHP 8.2 (ไม่ใช้ framework, ใช้ PDO ตรง ๆ) | ประมวลผล API ทั้งหมด แบ่งเป็น Controller + Repository layer |
| **Database** | [Neon](https://neon.tech) — PostgreSQL แบบ Serverless | เก็บข้อมูลหลักสูตรและผู้เรียนทั้งหมด |
| **Hosting** | [Render](https://render.com) — Web Service แบบ Docker (แผนฟรี) | รันเว็บ PHP ให้มี public URL |
| **Container** | Docker (`php:8.2-apache` + extension `pdo_pgsql`) | กำหนด environment ให้ Render รันได้ตรงตามที่ต้องการ |
| **รับสมัครออนไลน์** | [JotForm](https://www.jotform.com) | ฟอร์มให้คนภายนอกกรอกสมัครเรียน + JotForm API สำหรับแก้ไข dropdown |
| **Keep-alive** | [UptimeRobot](https://uptimerobot.com) | ปิงเว็บทุก 5 นาที ป้องกัน Render sleep หลัง idle 15 นาที |

---

## โครงสร้างไฟล์ + คำอธิบายทีละไฟล์

```
webcertificate/
│   .gitignore
│   Dockerfile
│   index.html
│   README.md
│   
├───assets
│   ├───docs
│   │       architecture-diagram.png
│   │       
│   └───images
│           line-qr.png
│           logo.png
│           main1.png
│           
├───backend
│   │   active_courses.php
│   │   admin_api.php
│   │   api.php
│   │   auth.php
│   │   jotform_webhook.php
│   │   
│   └───lib
│           CourseRepository.php
│           error_handler.php
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

### `index.html`
ไฟล์เดียวที่รวมทั้งหน้า UI ทั้งหมด แบ่งเป็น 2 ส่วนหลักที่สลับกันด้วยแท็บ: หน้าดาวน์โหลดใบประกาศ (public) และหน้า Admin Panel (ต้อง login)

### `backend/lib/require_admin.php`
Middleware กลาง — เริ่ม session, ตั้ง response header เป็น JSON, เช็คว่า login เป็น admin อยู่ไหม ถ้าไม่ใช่ตอบ 403 ทันที ทุกไฟล์ endpoint ที่ต้องการสิทธิ์ admin (`admin_api.php`, `active_courses.php`) เรียกใช้ไฟล์นี้แค่บรรทัดเดียวแทนเขียนโค้ดเช็คซ้ำ

### `backend/lib/error_handler.php`
ฟังก์ชันกลาง `send_error_response()` — log รายละเอียด exception จริงไว้ฝั่ง server ผ่าน `error_log()` (ดูได้ที่ Render → Logs) แต่ส่งข้อความทั่วไปกลับไปหา client เท่านั้น ป้องกันไม่ให้รายละเอียดโครงสร้าง database รั่วไหลออกไป

### `backend/lib/CourseRepository.php`
รวม SQL ทั้งหมดที่เกี่ยวกับตาราง `courses` และ `active_courses` ไว้ที่เดียว (list, add, update, delete, ค้นหาจากชื่อ, เปิด/ปิดรับสมัคร) — endpoint files เรียกใช้ผ่าน class นี้แทนเขียน SQL เอง

### `backend/lib/StudentRepository.php`
รวม SQL ทั้งหมดที่เกี่ยวกับตาราง `students` ไว้ที่เดียว (แบ่งหน้า+ค้นหา+กรอง+เรียงลำดับ, add, update, delete, insert จาก webhook, ดึงรายชื่อทั้งหมดสำหรับหน้า public)

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

### `backend/active_courses.php`
Endpoint สำหรับหน้า "เปิดรับสมัคร" (ต้อง login) อ่าน JotForm API Key/Form ID/Field ID จาก environment variables:

| Action | หน้าที่ |
|---|---|
| `list` | ดึงคอร์สทั้งหมด พร้อม flag `is_active` |
| `toggle` | เปิด/ปิดคอร์ส |
| `sync_jotform` | ดึงคอร์สที่เปิดอยู่จาก Neon → เรียก JotForm API โดยตรงเพื่ออัปเดตตัวเลือกใน dropdown |

### `backend/jotform_webhook.php`
**Endpoint สำคัญที่สุดตัวหนึ่ง** — จุดเดียวในระบบที่ต้องเปิดรับ request จากภายนอกอินเทอร์เน็ตจริง ๆ (ไม่ต้อง login เพราะ JotForm server เป็นคนเรียก)

ทำงานดังนี้: รับข้อมูลดิบจาก JotForm ผ่าน field `rawRequest` → แกะฟิลด์ตาม field ID ของฟอร์ม → ค้นหา `course_id` จากชื่อคอร์สผ่าน `CourseRepository::findByShortName()` → ถ้าหาไม่เจอตอบ error ทันที (ป้องกัน insert พังเพราะ `course_id` เป็น `NOT NULL`) → บันทึกผ่าน `StudentRepository::insertFromWebhook()` → log ผลลัพธ์ผ่าน `error_log()`

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
| `long_key` | ชื่อยาว ต้องไม่ซ้ำกัน (UNIQUE) |
| `short_name` | ชื่อย่อ — **ใช้ match กับ dropdown ใน JotForm ต้องตรงเป๊ะ** |
| `training_date` | วันที่อบรม (ข้อความอิสระ) |
| `year_be` | ปี พ.ศ. ใช้กรอง |
| `verify_url` | ลิงก์ปลายทางของ QR Code บนใบประกาศ |

### ตาราง `students`
เก็บข้อมูลผู้เรียนแต่ละคน ผูกกับหลักสูตรผ่าน `course_id` (Foreign Key, `NOT NULL`, `ON DELETE CASCADE`) มีฟิลด์ข้อมูลส่วนตัวครบถ้วน (ชื่อ, เบอร์, อีเมล, หน่วยงาน, ตำแหน่ง ฯลฯ)

### ตาราง `active_courses`
ตารางเชื่อม (junction table) เก็บแค่ `course_id` ที่กำลังเปิดรับสมัครอยู่ — ถ้ามีแถวในนี้แปลว่าคอร์สนั้นเปิดรับสมัคร ถ้าไม่มีแปลว่าปิด

---

## Data Flow แต่ละสถานการณ์

### 1. ผู้เข้าอบรมดาวน์โหลดใบประกาศ
```
เปิดเว็บ → api.php → StudentRepository::listAllWithCourse() → เลือกปี/คอร์ส/ชื่อ (กรองฝั่ง frontend)
        → พรีวิวใบประกาศ real-time → กด "ดาวน์โหลด" → html2canvas แปลงเป็น PNG
```

### 2. ผู้สมัครใหม่กรอกฟอร์ม JotForm
```
[ผู้สมัคร] กรอกฟอร์ม + เลือกหลักสูตร → กด Submit
        → JotForm ยิง Webhook (HTTP POST พร้อม field 'rawRequest')
        → jotform_webhook.php → decode rawRequest, แกะฟิลด์
        → CourseRepository::findByShortName() หา course_id
        → StudentRepository::insertFromWebhook()
        → ตอบกลับ {ok:true, id:...} ให้ JotForm
```

### 3. Admin เปิด/ปิดคอร์สแล้ว Sync ไปยัง JotForm
```
[Admin] ติ๊กเปิด/ปิดคอร์ส → active_courses.php?action=toggle → CourseRepository::activate()/deactivate()
[Admin] กด "Sync to JotForm" → active_courses.php?action=sync_jotform
        → CourseRepository::listActiveShortNames()
        → รวมชื่อคอร์สด้วย '|' → เรียก JotForm API (POST /form/{id}/question/{field})
        → JotForm อัปเดตตัวเลือกใน dropdown ของฟอร์มจริง
```

---

## วิธีรันโปรเจกต์บนเครื่องตัวเอง (Local Setup)

1. **เตรียม PostgreSQL database** — ใช้ [Neon](https://neon.tech) (ฟรี) หรือ Postgres ที่ติดตั้งเองก็ได้

2. **สร้างตารางและข้อมูลตัวอย่าง**
   ```
   psql "postgresql://<user>:<pass>@<host>/<dbname>" -f database/schema.sql
   psql "postgresql://<user>:<pass>@<host>/<dbname>" -f database/seed.sql
   ```

3. **ตั้งค่า Environment Variables** (ดูรายการทั้งหมดที่หัวข้อถัดไป) — ถ้ารันด้วย PHP built-in server บนเครื่อง สามารถตั้งผ่าน `.env` + ตัว loader เอง หรือ export ผ่าน terminal ก่อนรัน เช่น:
   ```bash
   export DB_HOST=your-neon-host
   export DB_PORT=5432
   export DB_NAME=neondb
   export DB_USER=your-user
   export DB_PASS=your-password
   export ADMIN_USER=admin
   export ADMIN_PASS=your-chosen-password
   ```

4. **รันเว็บด้วย PHP built-in server**
   ```
   php -S localhost:8000
   ```
   เปิด `http://localhost:8000` — ควรเห็นข้อมูลตัวอย่างจาก `seed.sql`

5. **(ทางเลือก) รันผ่าน Docker เหมือนบน Render**
   ```
   docker build -t webcertificate .
   docker run -p 8080:10000 -e PORT=10000 --env-file .env webcertificate
   ```

> หมายเหตุ: ฟีเจอร์ที่พึ่งพา JotForm (`active_courses.php?action=sync_jotform`, `jotform_webhook.php`) จะทำงานได้เต็มรูปแบบก็ต่อเมื่อตั้งค่า `JOTFORM_API_KEY`/`JOTFORM_FORM_ID`/`JOTFORM_FIELD_ID` ให้ตรงกับฟอร์มจริงของคุณเองด้วย

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
| `JOTFORM_API_KEY` | API Key จาก JotForm account | `backend/active_courses.php` |
| `JOTFORM_FORM_ID` | ID ของฟอร์มสมัครเรียนใน JotForm | `backend/active_courses.php` |
| `JOTFORM_FIELD_ID` | ID ของ field dropdown เลือกคอร์สในฟอร์ม | `backend/active_courses.php` |

บน Render ตั้งค่าได้ที่ Dashboard → service → Environment tab

---

## ข้อจำกัดและวิธีแก้ที่ใช้อยู่

### Render แผนฟรี "หลับ" หลัง idle 15 นาที
เมื่อไม่มีคนเข้าใช้ 15 นาที Render จะปิด container ชั่วคราว คนแรกที่กลับมาเข้าเว็บต้องรอ ~30-50 วินาทีให้ container ตื่น **วิธีแก้ที่ใช้อยู่:** ตั้ง **UptimeRobot** ปิง `https://webcertificate.onrender.com/` ทุก 5 นาที ทำให้เว็บไม่เคย idle ครบ 15 นาที

### Admin session หลุดบ่อย
เกิดจากเหตุผลเดียวกับข้างบน — container restart ทำให้ไฟล์ session หายไปด้วย ตอนนี้ที่ไม่ค่อย sleep แล้วปัญหานี้ควรเกิดน้อยลงมาก

---

## ปัญหาที่เคยเจอ และวิธีแก้ (Troubleshooting Log)

| ปัญหา | สาเหตุ | วิธีแก้ |
|---|---|---|
| `Unexpected token '<', "..." is not valid JSON` ตอนเปิดเว็บครั้งแรกบน Render | `.gitignore` กัน `database/config.php` ไว้ ทำให้ไฟล์ config ไม่ถูก push ขึ้น GitHub → `require_once` หาไฟล์ไม่เจอ → PHP โยน Fatal Error เป็นหน้า HTML แทน JSON | ลบบรรทัด `database/config.php` ออกจาก `.gitignore` (ก่อนจะย้ายไปใช้ environment variables ในภายหลัง) |
| `❌ Unauthorized` หลัง deploy โค้ดใหม่ทั้งที่เพิ่ง login | Container restart ตอน deploy ทำให้ PHP session หายไปหมด | Login ใหม่อีกครั้ง |
| `POST /backend/jotform_webhook.php` ได้ 500 ทุกครั้งที่ JotForm ยิงมาจริง | `SQLSTATE[23505]: Unique violation ... duplicate key value violates unique constraint "students_pkey"` — ตอนย้ายข้อมูลจาก Supabase มา Neon โดย insert พร้อม id เดิม แต่ไม่ได้อัปเดต sequence ให้ตรงกับข้อมูลจริง | รัน SQL รีเซ็ต sequence ให้ตรงกับ `MAX(id)` ปัจจุบันของตาราง (`SELECT setval(pg_get_serial_sequence('students','id'), (SELECT MAX(id) FROM students))`) |
| เขียน log ไฟล์ไม่ได้ ขึ้น 404 ตลอด | โฟลเดอร์ `backend/` บน Render ไม่มีสิทธิ์เขียนไฟล์ใหม่ให้ PHP process | เปลี่ยนมาใช้ `error_log()` แทนการเขียนไฟล์ — log จะไปโผล่ที่ Render → Logs โดยตรง |
| เว็บดับ/โหลดช้ามากเป็นระยะ | พฤติกรรมปกติของ Render แผนฟรี (spin down เมื่อ idle เกิน 15 นาที) | ตั้ง UptimeRobot ปิงทุก 5 นาที |
| Database error ชั่วคราวทั้งที่โค้ดไม่ได้แก้อะไร | Neon เกิด incident ฝั่ง infrastructure เอง (เช็คได้ที่ [neonstatus.com](https://neonstatus.com)) | รอ Neon แก้ปัญหาฝั่งเขาเสร็จ ไม่ใช่ปัญหาฝั่งโค้ด |

---

## ประวัติการพัฒนา

ระบบเดิมใช้ **Supabase (PostgreSQL)** ร่วมกับ **Google Apps Script** เป็นตัวกลางเชื่อมระหว่าง JotForm กับฐานข้อมูล ต่อมาได้ย้ายฐานข้อมูลทั้งหมดมาที่ **Neon** และตัดการพึ่งพา Google Apps Script ออก โดยให้ JotForm ยิง Webhook มาที่ PHP backend ของระบบเองโดยตรง

หลังจากนั้นได้ปรับปรุงคุณภาพโค้ดเพิ่มอีก 2 รอบ:
- **ย้าย credential ทั้งหมด** (database password, JotForm API key, admin password) ออกจากโค้ด ไปเป็น **Environment Variables** พร้อมล้าง git history เก่าที่เคยมี credential ฝังอยู่
- **Refactor โครงสร้าง backend** แยกเป็น Controller + Repository layer, สร้าง middleware กลางสำหรับเช็คสิทธิ์ admin, และทำ error handler กลางที่ไม่เปิดเผยรายละเอียด database ให้ client เห็น

ส่วนปัญหาเว็บ "หลับ" ของ hosting แผนฟรีก็แก้ด้วย UptimeRobot ทำให้ใช้งานได้ต่อเนื่องโดยไม่ต้องเสียค่าใช้จ่ายเพิ่ม