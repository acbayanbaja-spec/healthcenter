-- Run once on an existing healthcenter_db (new installs already have this column)
USE healthcenter_db;
ALTER TABLE users ADD COLUMN agreed_at DATETIME NULL;
