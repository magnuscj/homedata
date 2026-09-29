-- migrate_sensorconfig_unique.sql
--
-- Purpose: make `sensorid` the enforced natural key of `sensorconfig` so the
-- rest of the system can express "upsert without clobbering a real name" as a
-- single race-free SQL statement (INSERT ... ON DUPLICATE KEY UPDATE keyed on
-- sensorid). Without a UNIQUE(sensorid) constraint there is nothing stopping
-- duplicate rows per sensor and no key to anchor a non-clobbering upsert on.
--
-- This script is IDEMPOTENT and NON-DESTRUCTIVE to real names:
--   1. De-duplicate rows sharing a sensorid, keeping the "best" row:
--        a named row (sensorname <> 'name') wins over a placeholder;
--        among equals, the lowest id wins (stable/oldest).
--   2. Add UNIQUE(sensorid) only if it is not already present.
--
-- Safe to run repeatedly (startup, redeploy, manual). Requires the 6-column
-- schema (id, sensorid, sensorname, color, visible, type).

USE mydb;

-- Step 1: de-duplicate by sensorid, preserving the most-named / oldest row.
-- Rank rows within each sensorid: named rows first, then lowest id.
-- Delete every row that is NOT the top-ranked one for its sensorid.
DELETE sc
FROM sensorconfig AS sc
JOIN (
    SELECT id
    FROM (
        SELECT
            id,
            ROW_NUMBER() OVER (
                PARTITION BY sensorid
                ORDER BY (sensorname <> 'name') DESC, id ASC
            ) AS rn
        FROM sensorconfig
    ) ranked
    WHERE ranked.rn > 1
) AS dupes ON dupes.id = sc.id;

-- Step 2: add UNIQUE(sensorid) if absent. sensorid is TEXT, so a prefix length
-- is required for the index; 64 chars comfortably covers a 20-digit FNV id.
SET @has_uk := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = 'mydb'
      AND table_name   = 'sensorconfig'
      AND index_name   = 'uk_sensorid'
);
SET @ddl := IF(@has_uk = 0,
    'ALTER TABLE sensorconfig ADD UNIQUE KEY uk_sensorid (sensorid(64))',
    'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
