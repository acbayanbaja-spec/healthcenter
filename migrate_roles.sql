-- Run once on an existing healthcenter_db (new installs already have these columns)
USE healthcenter_db;
ALTER TABLE users
  ADD COLUMN role ENUM('admin','staff','doctor','patient') NOT NULL DEFAULT 'staff',
  ADD COLUMN doctor_id INT NULL,
  ADD COLUMN patient_id INT NULL;
UPDATE users SET role = 'admin' WHERE username = 'admin';
