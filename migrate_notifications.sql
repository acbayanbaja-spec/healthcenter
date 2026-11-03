-- Run once on an existing healthcenter_db (new installs already include this table)
USE healthcenter_db;
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
