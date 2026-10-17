-- database/schema.sql
-- สร้างตารางทั้งหมดที่ระบบนี้ใช้งาน เขียนด้วย PostgreSQL syntax (ใช้กับ Neon โดยตรง)

-- ตารางคอร์ส: 1 แถว = 1 คอร์ส (รวมวันที่อบรมด้วย)
CREATE TABLE IF NOT EXISTS courses (
    id             SERIAL PRIMARY KEY,
    long_key       VARCHAR(500) NOT NULL,                 -- ชื่อยาว (ชื่อคอร์ส + วันที่อบรม รวมกัน) ต้องไม่ซ้ำกัน
    short_name     VARCHAR(255) NOT NULL,                  -- ชื่อย่อของคอร์ส ใช้ผูกกับ dropdown ใน JotForm ต้องตรงเป๊ะ
    training_date  VARCHAR(255),                           -- ข้อความวันที่อบรม เช่น "วันที่ 5-7 พ.ค. 2568"
    year_be        VARCHAR(4),                             -- ปี พ.ศ. 4 หลัก ใช้กรองข้อมูล
    verify_url     VARCHAR(500),                           -- ลิงก์ปลายทางสำหรับ QR Code บนใบประกาศของคอร์สนี้
    created_at     TIMESTAMP NOT NULL DEFAULT now(),
    CONSTRAINT uniq_long_key UNIQUE (long_key)
);

CREATE INDEX IF NOT EXISTS idx_courses_year        ON courses (year_be);
CREATE INDEX IF NOT EXISTS idx_courses_short_name  ON courses (short_name);

-- ตารางผู้เรียน: 1 แถว = 1 คน ผูกกับคอร์สผ่าน course_id
CREATE TABLE IF NOT EXISTS students (
    id                  SERIAL PRIMARY KEY,
    airtable_id         VARCHAR(50),                            -- เก็บ record id เดิมจาก Airtable ไว้อ้างอิง (ถ้ามี)
    course_id           INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    first_name          VARCHAR(255) NOT NULL,                  -- ชื่อ
    last_name           VARCHAR(255),                           -- นามสกุล (หรือฉายา)
    member_type         VARCHAR(50),                            -- ประเภทสมาชิก (พระภิกษุ/อุบาสก/อุบาสิกา ฯลฯ)
    apply_date          VARCHAR(50),                            -- วันที่สมัคร
    birth_date          VARCHAR(50),                            -- วัน/เดือน/ปี เกิด
    age                 VARCHAR(50),                            -- อายุ
    royal_title         VARCHAR(255),                           -- พรรษาที่
    education_level     VARCHAR(255),                           -- ระดับการศึกษาสูงสุด
    faculty             VARCHAR(255),                           -- คณะ
    major               VARCHAR(255),                           -- สาขา
    institution         VARCHAR(255),                           -- สถาบัน
    department          VARCHAR(255),                           -- หน่วยงาน กอง/ศูนย์
    office              VARCHAR(255),                           -- สำนักงาน
    position            VARCHAR(255),                           -- ตำแหน่ง
    phone_internal      VARCHAR(50),                            -- เบอร์ภายใน
    phone_mobile        VARCHAR(50),                            -- เบอร์มือถือ
    email               VARCHAR(255),                           -- Email
    head_status         VARCHAR(255),                           -- สถานะหัวหน้ากอง
    attendance          VARCHAR(255),                           -- การเข้าอบรม
    last_modified_time  VARCHAR(50),                            -- เวลาแก้ไขล่าสุด (เดิมจาก Airtable)
    created_at          TIMESTAMP NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_students_course ON students (course_id);
CREATE INDEX IF NOT EXISTS idx_students_name   ON students (first_name, last_name);

-- ตารางคอร์สที่เปิดรับสมัครอยู่ตอนนี้ (junction table แบบง่าย)
-- มีแถวในตารางนี้ = คอร์สนั้นเปิดรับสมัคร / ไม่มีแถว = ปิด
CREATE TABLE IF NOT EXISTS active_courses (
    course_id   INTEGER PRIMARY KEY REFERENCES courses(id) ON DELETE CASCADE,
    created_at  TIMESTAMP NOT NULL DEFAULT now()
);
-- ตารางคำขออนุมัติจากหัวหน้ากอง (CAS) — ดูไฟล์ database/approval_requests.sql
CREATE TABLE IF NOT EXISTS approval_requests (
    external_ref_id  VARCHAR(100) PRIMARY KEY,              -- รหัสอ้างอิง JF-<epoch ms>-<hex8> (ใช้เป็น ExternalRefID ของ CAS)
    requester_ad     VARCHAR(100) NOT NULL,                  -- บัญชี AD ของผู้ขอ (คนที่ล็อกอิน)
    requester_name   VARCHAR(255),                           -- ชื่อที่แสดงของผู้ขอ
    applicant_name   VARCHAR(255),                           -- ชื่อ-นามสกุลผู้สมัครจากฟอร์ม
    course           VARCHAR(500),                           -- ชื่อคอร์สจากฟอร์ม
    member_type      VARCHAR(255),                           -- ประเภทสมาชิกจากฟอร์ม
    student_id       INTEGER REFERENCES students(id) ON DELETE SET NULL, -- ผู้เรียนที่ตรงกัน (เดาจากชื่อ+คอร์ส ว่างได้)
    approve_type     SMALLINT NOT NULL DEFAULT 1,
    subject          VARCHAR(255) NOT NULL,                  -- หัวข้อที่ส่งถึงหัวหน้ากอง
    message          TEXT NOT NULL,                          -- ข้อความที่ส่งถึงหัวหน้ากอง
    request_key      VARCHAR(100),                           -- RequestKey จาก CAS
    approver_ad      VARCHAR(100),                           -- บัญชี AD หัวหน้ากอง
    approver_name    VARCHAR(255),                           -- ชื่อหัวหน้ากอง
    status           VARCHAR(30) NOT NULL DEFAULT 'PENDING', -- PENDING / APPROVE / REJECT
    approve_step     VARCHAR(30),
    cas_data         TEXT,                                   -- JSON ตอบกลับจาก CAS ตอนส่งคำขอ
    created_at       TIMESTAMP NOT NULL DEFAULT (now() AT TIME ZONE 'UTC'),
    updated_at       TIMESTAMP NOT NULL DEFAULT (now() AT TIME ZONE 'UTC')
);

CREATE INDEX IF NOT EXISTS idx_ar_requester ON approval_requests (requester_ad);
CREATE INDEX IF NOT EXISTS idx_ar_created   ON approval_requests (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_ar_status    ON approval_requests (status);
CREATE INDEX IF NOT EXISTS idx_ar_student   ON approval_requests (student_id);
