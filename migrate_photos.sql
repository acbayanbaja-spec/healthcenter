-- Run once on an existing healthcenter_db (new installs already have these columns)
USE healthcenter_db;
ALTER TABLE users
  ADD COLUMN profile_photo VARCHAR(255) NULL,
  ADD COLUMN cover_photo VARCHAR(255) NULL;
