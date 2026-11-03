-- Run once on an existing healthcenter_db (new installs already include this table)
USE healthcenter_db;
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
