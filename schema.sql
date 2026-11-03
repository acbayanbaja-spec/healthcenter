-- Health Center system: full install. Safe to import more than once (existing data is kept).
CREATE DATABASE IF NOT EXISTS healthcenter_db CHARACTER SET utf8mb4;
USE healthcenter_db;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  agreed_at DATETIME NULL,
  profile_photo VARCHAR(255) NULL,
  cover_photo VARCHAR(255) NULL,
  role ENUM('admin','staff','doctor','patient') NOT NULL DEFAULT 'staff',
  doctor_id INT NULL,
  patient_id INT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS patients (
  patient_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  birth_date DATE NOT NULL,
  gender ENUM('Male','Female','Other') NOT NULL,
  phone VARCHAR(20),
  address VARCHAR(255),
  sms_optin TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS doctors (
  doctor_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  specialty VARCHAR(100) NOT NULL,
  phone VARCHAR(20),
  schedule VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS appointments (
  appointment_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  appt_date DATE NOT NULL,
  appt_time TIME NOT NULL,
  reason VARCHAR(255),
  status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  UNIQUE KEY no_double_booking (doctor_id, appt_date, appt_time),
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS history_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_name VARCHAR(100) NOT NULL,
  action VARCHAR(20) NOT NULL,
  table_name VARCHAR(30) NOT NULL,
  record_id INT NULL,
  details TEXT,
  INDEX idx_record (table_name, record_id),
  INDEX idx_time (logged_at)
);

CREATE TABLE IF NOT EXISTS notifications (
  notif_id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL,
  appointment_id INT NULL,
  patient_id INT NULL,
  phone VARCHAR(30),
  type VARCHAR(20) NOT NULL,
  message VARCHAR(500) NOT NULL,
  status ENUM('Pending','Sent','Simulated','Failed','Skipped') NOT NULL DEFAULT 'Pending',
  provider VARCHAR(20),
  error VARCHAR(255) NULL,
  sent_at DATETIME NULL,
  INDEX idx_appt (appointment_id, type),
  INDEX idx_created (created_at)
);

-- Sample data, added only when the table is empty. Login: admin / password
INSERT INTO users (username, password, full_name, role)
  SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM users);

INSERT INTO doctors (full_name, specialty, phone, schedule)
  SELECT * FROM (
    SELECT 'Dr. Ana Reyes' AS a, 'General Medicine' AS b, '0917-111-2222' AS c, 'Mon-Fri 8AM-12PM' AS d
    UNION ALL SELECT 'Dr. Mark Cruz', 'Pediatrics', '0917-333-4444', 'Mon-Wed 1PM-5PM'
  ) AS seed WHERE NOT EXISTS (SELECT 1 FROM doctors);

INSERT INTO patients (full_name, birth_date, gender, phone, address)
  SELECT * FROM (
    SELECT 'Juan Dela Cruz' AS a, '1990-05-14' AS b, 'Male' AS c, '0918-555-0001' AS d, '12 Rizal St.' AS e
    UNION ALL SELECT 'Maria Santos', '2015-09-02', 'Female', '0918-555-0002', '45 Mabini Ave.'
  ) AS seed WHERE NOT EXISTS (SELECT 1 FROM patients);

CREATE TABLE IF NOT EXISTS doctor_availability (
  avail_id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  weekday TINYINT NOT NULL,            -- 0 = Sunday ... 6 = Saturday
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  INDEX idx_doc_day (doctor_id, weekday),
  FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS doctor_days_off (
  off_id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  off_date DATE NOT NULL,
  reason VARCHAR(100),
  UNIQUE KEY one_off (doctor_id, off_date),
  FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
);

-- Sample working hours for the two sample doctors (only if they have none yet)
INSERT INTO doctor_availability (doctor_id, weekday, start_time, end_time)
  SELECT d.doctor_id, w.n, '08:00:00', '12:00:00' FROM doctors d
  JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) w
  WHERE d.full_name = 'Dr. Ana Reyes' AND NOT EXISTS (SELECT 1 FROM doctor_availability a WHERE a.doctor_id = d.doctor_id);
INSERT INTO doctor_availability (doctor_id, weekday, start_time, end_time)
  SELECT d.doctor_id, w.n, '13:00:00', '17:00:00' FROM doctors d
  JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3) w
  WHERE d.full_name = 'Dr. Mark Cruz' AND NOT EXISTS (SELECT 1 FROM doctor_availability a WHERE a.doctor_id = d.doctor_id);
