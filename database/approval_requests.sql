-- database/approval_requests.sql
-- ตารางคำขออนุมัติจากหัวหน้ากอง (CAS) — รันครั้งเดียวที่ Neon (SQL Editor) ก่อนใช้งานระบบขออนุมัติ
-- ปลอดภัยที่จะรันซ้ำ (IF NOT EXISTS)

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
