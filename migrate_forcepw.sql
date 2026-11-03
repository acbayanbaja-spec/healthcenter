-- Run once on an existing healthcenter_db (new installs already have this column)
USE healthcenter_db;
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;
