-- Run in the SALES staging database, not swform.
-- Additive and rerunnable: works whether the old 001 has run or not.
-- No existing columns, rows, old inbox table or old lead-ID column are changed.
-- MySQL DDL autocommits; take a backup first. No stored-routine privileges needed.

SET @sr_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' AND COLUMN_NAME='source')=0,
 'ALTER TABLE prospects ADD COLUMN source VARCHAR(80) NULL', 'SELECT 1');
PREPARE sr_stmt FROM @sr_ddl; EXECUTE sr_stmt; DEALLOCATE PREPARE sr_stmt;

SET @sr_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' AND COLUMN_NAME='employee_range')=0,
 'ALTER TABLE prospects ADD COLUMN employee_range VARCHAR(16) NULL', 'SELECT 1');
PREPARE sr_stmt FROM @sr_ddl; EXECUTE sr_stmt; DEALLOCATE PREPARE sr_stmt;

SET @sr_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' AND COLUMN_NAME='switch_review_data')=0,
 'ALTER TABLE prospects ADD COLUMN switch_review_data JSON NULL', 'SELECT 1');
PREPARE sr_stmt FROM @sr_ddl; EXECUTE sr_stmt; DEALLOCATE PREPARE sr_stmt;

SET @sr_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' AND COLUMN_NAME='switch_lead_id')=0,
 'ALTER TABLE prospects ADD COLUMN switch_lead_id CHAR(36) NULL', 'SELECT 1');
PREPARE sr_stmt FROM @sr_ddl; EXECUTE sr_stmt; DEALLOCATE PREPARE sr_stmt;

SET @sr_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' AND COLUMN_NAME='switch_review_new')=0,
 'ALTER TABLE prospects ADD COLUMN switch_review_new TINYINT(1) NULL', 'SELECT 1');
PREPARE sr_stmt FROM @sr_ddl; EXECUTE sr_stmt; DEALLOCATE PREPARE sr_stmt;

SET @sr_ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' AND COLUMN_NAME='switch_lead_id' AND NON_UNIQUE=0 AND INDEX_NAME IN
 (SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='prospects' GROUP BY INDEX_NAME HAVING COUNT(*)=1))=0,
 'ALTER TABLE prospects ADD UNIQUE KEY uq_prospects_switch_lead_id (switch_lead_id)', 'SELECT 1');
PREPARE sr_stmt FROM @sr_ddl; EXECUTE sr_stmt; DEALLOCATE PREPARE sr_stmt;

-- Existing switch_review_lead_id from old 001 is intentionally retained unused.
-- Do not migrate old imported data blindly: reconcile it before enabling pickup
-- if any old-plan imports occurred. No such imports were deployed by this work.
-- employee_count stays unchanged; imports omit it (existing unknown/default = 0).
