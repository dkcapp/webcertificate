-- database/schema.sql
-- รันไฟล์นี้ครั้งเดียวเพื่อสร้าง database และตารางทั้งหมด
-- วิธีรัน: เปิด phpMyAdmin (ที่เห็นในรูป tab "vm14/localhost/webcert | phpM...")
--         -> เลือก database ใหม่ -> Import -> เลือกไฟล์นี้
-- หรือรันผ่าน mysql command line:
--   mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS webcertificate
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_thai_520_w2;

USE webcertificate;

-- ตารางคอร์ส: 1 แถว = 1 คอร์ส (รวมวันที่อบรมด้วย)
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    long_key VARCHAR(500) NOT NULL COMMENT 'ตรงกับ "โปรแกรมที่สมัคร" ใน Airtable (ชื่อยาว+วันที่)',
    short_name VARCHAR(255) NOT NULL COMMENT 'ตรงกับ "ชื่อโปรแกรม" ใน Airtable (ชื่อสั้น)',
    training_date VARCHAR(255) DEFAULT NULL COMMENT 'ตรงกับ "วันที่อบรม" เช่น วันที่ 5-7 พ.ค. 2569',
    year_be VARCHAR(4) DEFAULT NULL COMMENT 'ปี พ.ศ. 4 หลัก ดึงจาก training_date สำหรับกรอง',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_long_key (long_key),
    INDEX idx_year (year_be),
    INDEX idx_short_name (short_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

-- ตารางผู้เรียน: 1 แถว = 1 คน ผูกกับคอร์สผ่าน course_id
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    airtable_id VARCHAR(50) DEFAULT NULL COMMENT 'rec... ของ Airtable เก็บไว้อ้างอิง',
    course_id INT NOT NULL,
    first_name VARCHAR(255) NOT NULL COMMENT 'ชื่อ',
    last_name VARCHAR(255) DEFAULT NULL COMMENT 'นามสกุล (ฉายา)',
    member_type VARCHAR(50) DEFAULT NULL COMMENT 'ประเภทสมาชิก (พระภิกษุ/อุบาสก/อุบาสิกา ฯลฯ)',
    apply_date VARCHAR(50) DEFAULT NULL COMMENT 'วันที่สมัคร',
    birth_date VARCHAR(50) DEFAULT NULL COMMENT 'วัน/เดือน/ปี เกิด',
    age VARCHAR(50) DEFAULT NULL COMMENT 'อายุ',
    royal_title VARCHAR(255) DEFAULT NULL COMMENT 'พรรษาที่',
    education_level VARCHAR(255) DEFAULT NULL COMMENT 'ระดับการศึกษาสูงสุด',
    faculty VARCHAR(255) DEFAULT NULL COMMENT 'คณะ',
    major VARCHAR(255) DEFAULT NULL COMMENT 'สาขา',
    institution VARCHAR(255) DEFAULT NULL COMMENT 'สถาบัน',
    department VARCHAR(255) DEFAULT NULL COMMENT 'หน่วยงาน กอง/ศูนย์',
    office VARCHAR(255) DEFAULT NULL COMMENT 'สำนักงาน',
    position VARCHAR(255) DEFAULT NULL COMMENT 'ตำแหน่ง',
    phone_internal VARCHAR(50) DEFAULT NULL COMMENT 'เบอร์ภายใน',
    phone_mobile VARCHAR(50) DEFAULT NULL COMMENT 'เบอร์มือถือ',
    email VARCHAR(255) DEFAULT NULL COMMENT 'Email',
    head_status VARCHAR(255) DEFAULT NULL COMMENT 'สถานะหัวหน้ากอง',
    attendance VARCHAR(255) DEFAULT NULL COMMENT 'การเข้าอบรม',
    last_modified_time VARCHAR(50) DEFAULT NULL COMMENT 'Last modified time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course (course_id),
    INDEX idx_name (first_name, last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

-- เติมภายหลัง
ALTER TABLE courses ADD COLUMN verify_url VARCHAR(500) DEFAULT NULL COMMENT 'ลิงก์ปลายทางสำหรับ QR code ของคอร์สนี้' AFTER year_be;