-- Run once on an existing healthcenter_db (new installs already have this column)
USE healthcenter_db;
ALTER TABLE patients ADD COLUMN sms_optin TINYINT(1) NOT NULL DEFAULT 1;
