-- =============================================================================
-- MariaDB users for efact multi-tenant platform
-- =============================================================================
--
-- Run this script as root/admin on your MariaDB instance:
--
--   mariadb -u root -p < scripts/setup-mariadb-users.sql
--
-- IMPORTANT: Replace the placeholder passwords before running!
--
-- Users created:
--   1. efact_provision  - Provisioning: creates tenant DBs, users, runs migrations
--   2. efact_backup     - Backups: mariadb-dump, binary logs, retention pruning
--   3. efact_monitor    - OTEL Collector: read-only metrics scraping
--
-- The provisioning user gets broad access because it needs to CREATE DATABASE
-- with arbitrary names. Each tenant DB user gets scoped grants at provision time.
--
-- =============================================================================

-- ------------------------------------
-- 1. PROVISIONING USER
-- ------------------------------------

CREATE USER IF NOT EXISTS 'efact_provision'@'%'
    IDENTIFIED BY 'CHANGE_ME_provision_password';

-- Create/drop/alter any database (needed for tenant provisioning and migrations)
GRANT ALL PRIVILEGES ON *.* TO 'efact_provision'@'%'
    WITH GRANT OPTION;

-- Note: WITH GRANT OPTION is required so efact_provision can grant
-- per-tenant users access to their own databases during provisioning.

-- ------------------------------------
-- 2. BACKUP USER
-- ------------------------------------

CREATE USER IF NOT EXISTS 'efact_backup'@'%'
    IDENTIFIED BY 'CHANGE_ME_backup_password';

-- mariadb-dump: read all databases for full backups
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES, EVENT, CREATE, ALTER, DROP
    ON *.* TO 'efact_backup'@'%';

-- Binary log access for PITR (point-in-time recovery)
GRANT REPLICATION CLIENT, REPLICATION SLAVE ON *.* TO 'efact_backup'@'%';

-- FLUSH BINARY LOGS (rotate binlogs before backup)
GRANT RELOAD ON *.* TO 'efact_backup'@'%';

-- SHOW MASTER STATUS, SHOW BINARY LOGS
GRANT BINLOG MONITOR ON *.* TO 'efact_backup'@'%';

-- Read/write backup tracking tables in control plane
GRANT SELECT, INSERT, UPDATE, DELETE
    ON efact.tenant_backups TO 'efact_backup'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON efact.binlog_backups TO 'efact_backup'@'%';
GRANT SELECT ON efact.tenants TO 'efact_backup'@'%';

-- ------------------------------------
-- 3. OTEL MONITORING USER
-- ------------------------------------

CREATE USER IF NOT EXISTS 'efact_monitor'@'%'
    IDENTIFIED BY 'CHANGE_ME_monitor_password';

-- MySQL receiver: read performance_schema, process list, global status
GRANT SELECT, PROCESS, REPLICATION CLIENT ON *.* TO 'efact_monitor'@'%';

-- ------------------------------------
-- Apply
-- ------------------------------------

FLUSH PRIVILEGES;

-- ------------------------------------
-- Verify
-- ------------------------------------

SELECT User, Host FROM mysql.user
WHERE User IN ('efact_provision', 'efact_backup', 'efact_otel')
ORDER BY User;

SHOW GRANTS FOR 'efact_provision'@'%';
SHOW GRANTS FOR 'efact_backup'@'%';
SHOW GRANTS FOR 'efact_otel'@'%';
