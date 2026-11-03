-- DEMO accounts for testing each role. Password for all three: demo1234
-- Import in phpMyAdmin AFTER schema.sql. Do NOT use these on a real system: delete them in Users when you finish testing.
USE healthcenter_db;

INSERT INTO users (username, password, full_name, role)
  SELECT 'staff1', '$2y$10$KecuamVqlAWa42p4QXXid.1OYe1LDEAIvMqHZ10VzqLBvffn7ov5O', 'Front Desk Staff', 'staff' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'staff1');

INSERT INTO users (username, password, full_name, role, doctor_id)
  SELECT 'doctor1', '$2y$10$KecuamVqlAWa42p4QXXid.1OYe1LDEAIvMqHZ10VzqLBvffn7ov5O', 'Dr. Ana Reyes', 'doctor', (SELECT doctor_id FROM doctors WHERE full_name = 'Dr. Ana Reyes' LIMIT 1) FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'doctor1');

INSERT INTO users (username, password, full_name, role, patient_id)
  SELECT 'patient1', '$2y$10$KecuamVqlAWa42p4QXXid.1OYe1LDEAIvMqHZ10VzqLBvffn7ov5O', 'Juan Dela Cruz', 'patient', (SELECT patient_id FROM patients WHERE full_name = 'Juan Dela Cruz' LIMIT 1) FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'patient1');

-- Two sample visits (tomorrow and in 3 days) so the doctor dashboard and patient portal have something to show. The first is added only if there are no appointments yet.
INSERT INTO appointments (patient_id, doctor_id, appt_date, appt_time, reason)
  SELECT (SELECT patient_id FROM patients WHERE full_name = 'Juan Dela Cruz' LIMIT 1),
         (SELECT doctor_id FROM doctors WHERE full_name = 'Dr. Ana Reyes' LIMIT 1),
         DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', 'General check-up' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM appointments);

INSERT INTO appointments (patient_id, doctor_id, appt_date, appt_time, reason)
  SELECT (SELECT patient_id FROM patients WHERE full_name = 'Maria Santos' LIMIT 1),
         (SELECT doctor_id FROM doctors WHERE full_name = 'Dr. Mark Cruz' LIMIT 1),
         DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:30:00', 'Vaccination' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE reason = 'Vaccination');
