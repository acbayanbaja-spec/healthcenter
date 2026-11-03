-- Run once on an existing healthcenter_db (new installs already include this)
USE healthcenter_db;
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
