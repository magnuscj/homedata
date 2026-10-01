# Database Improvements Analysis — `mydb`

> Status: **analysis only, nothing changed.** Prepared for a planning agent to turn into an implementation plan.
> Date: 2026-08-30
> Source of truth inspected: live DB in pod `eds-deployment-7bb85c46c6-rbpq7` + repo `homedata`.

## 1. Context / System Overview

`mydb` is the datastore for a home-automation sensor system. It is written and read by **three separate codebases**, and — importantly — **the schema is defined in application code, not by migrations**. The C++ collector issues `CREATE TABLE ... IF` (effectively) on every run, so it is the de-facto schema owner.

| Layer | Files | Role |
|---|---|---|
| C++ collector (`eds`) | `edssensors/edsServerHandler.cc`, `edssensors/edsServerHandler.h` | **Owns the schema.** Creates `table<YYYYMM>` and `sensorconfig` on the fly, then INSERTs readings. Source of the `TEXT` / `float(23,3)` / `ON UPDATE` definitions. |
| PHP visualization | `visualize/homeFunctions.php` (query layer, ~15 DB functions) + ~12 report/graph pages, `sensorcfg.php` | Reads readings via dynamically-built `UNION` across monthly tables; edits `sensorconfig`. |
| Python pods | `scripts/getElectricityPrices.py`, `container/*pod/*.py` | Insert additional data rows. |

### Monthly-table naming is duplicated
The table name `table<YYYYMM>` is constructed independently in **at least 5 places**:
- `edssensors/edsServerHandler.cc` — `tbName + date`
- `visualize/homeFunctions.php` — `buildMonthlyUnion()` and `date("Ym")` in `getCurr()`, `getLatestTime()`, `getCurrByName()`
- `scripts/getElectricityPrices.py` — `table{year}{month}`
- `container/backup.sh` — `TABLE="table$(date +%Y%m)"`

## 2. Current Schema (as observed live)

```sql
CREATE TABLE `table202608` (          -- one table per month, ~77k rows, 4.52 MB, NO indexes beyond PK
  `id` int NOT NULL AUTO_INCREMENT,
  `sensorid` text,
  `data` float(23,3) DEFAULT NULL,
  `curr_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `sensorconfig` (         -- 64 rows
  `id` int NOT NULL AUTO_INCREMENT,
  `sensorid` text NOT NULL,
  `sensorname` text NOT NULL,
  `color` text NOT NULL,
  `visible` text NOT NULL,
  `type` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Observed data characteristics
- `table202608`: 49 distinct sensorids, sensorid length 16–20 digits, `data` range −2166 → ~233,709,376 with 3 decimals.
- **2,400 rows have `sensorid = 'electricityprice'`** (non-numeric — inserted by `getElectricityPrices.py`).
- `sensorconfig.visible` holds inconsistent casing: `'True'` and `'false'`.
- `sensorconfig.type` values: `temp`, `power`, `bar`, `moisture`, `Wind`, `soilmoist`, `default`, `rain`.

## 3. Proposed Improvements & Required Work

Ordered safe → risky.

### 3.1 Add index on `(sensorid, curr_timestamp)` — HIGHEST VALUE, LOWEST RISK
Every query filters by sensor and/or time; currently full-scans (zero index space beyond PK).
- **Code:** add the index to the C++ `CREATE TABLE` in `storeServerData()` so **future** monthly tables get it automatically.
- **Data:** `ALTER TABLE ... ADD INDEX` per existing monthly table (loop over `SHOW TABLES LIKE 'table20%'`).
- **App logic:** none.

### 3.2 `sensorid` `TEXT` → `VARCHAR(32)` (NOT `BIGINT`) — enabler for indexing
- **BLOCKER for BIGINT:** 2,400 live rows have `sensorid='electricityprice'`. A `TEXT→BIGINT` conversion would fail / corrupt these. Going `BIGINT` additionally requires reworking the `electricityprice` sentinel in `getElectricityPrices.py` and any consumer filtering on that literal.
- **Recommendation:** `VARCHAR(32)`. Captures ~95% of the indexing benefit, tolerates the `electricityprice` sentinel, and needs **zero application-logic changes** (all code already treats sensorid as a string: PHP binds `"s"`, C++/Python quote it).
- **Code:** `edsServerHandler.cc` (2 CREATE statements), seed files `container/sensorconfig.sql`, `dump.sql`.
- **Data:** `ALTER` each monthly table + `sensorconfig`.

### 3.3 `data float(23,3)` → `DECIMAL(15,3)` or `DOUBLE`
`FLOAT(M,D)` syntax is deprecated in MySQL 8.0.
- **Code:** `edsServerHandler.cc` CREATE only.
- **App logic:** none (PHP casts `(double)` everywhere; Python passes strings). Low risk.
- **Choice note:** `DECIMAL(15,3)` if 3-decimal exactness matters; `DOUBLE` if floating point is acceptable.

### 3.4 Drop `ON UPDATE CURRENT_TIMESTAMP` on `curr_timestamp`
For immutable sensor readings this is wrong — any UPDATE silently rewrites the historical timestamp.
- **Code:** `edsServerHandler.cc` CREATE only.
- **Data:** `ALTER` existing tables.
- Python inserter passes an explicit `curr_timestamp`, so unaffected.

### 3.5 `sensorconfig` column types — MOST APP-VISIBLE CHANGE
Convert `visible` → `TINYINT(1)`/BOOLEAN, `type`/`color`/`sensorname` → `VARCHAR`, add `UNIQUE KEY (sensorid)`.
- **Data cleanup first:** normalize `visible` `'True'`/`'false'` → `1`/`0`.
- **App logic that MUST change:**
  - `sensorcfg.php` — `visible` edited via free-text `<input>`, bound as `"s"`; needs checkbox/select.
  - `visualize/homeFunctions.php::onlyPowerType()` — compares `strcasecmp($visible, "true")`; breaks if `visible` becomes `1`/`0`.
  - `edsServerHandler.cc::writeSensorConfiguration()` — inserts literal `'false'`; must insert `0`.
- Highest regression risk (touches all three layers).

### 3.6 (Optional, later) Native partitioning instead of monthly tables
Replace per-month tables with `PARTITION BY RANGE` on the timestamp.
- **Large job:** rewrite `buildMonthlyUnion()` + the table-name construction in all 5 locations + `backup.sh`/`restore.sh`.
- Defer unless the monthly-table sprawl is actively causing pain.

## 4. Cross-Cutting Issues Noticed (out of scope but worth flagging)

- **SQL injection in the C++ collector.** `storeServerData()` and `writeSensorConfiguration()` build INSERTs by string-concatenating parsed XML values. Should use prepared statements (`mysql_stmt_*`).
- **Query building in PHP.** `buildMonthlyUnion()` interpolates (escaped) values into the query string rather than binding; most other PHP functions correctly use prepared statements.
- **Hardcoded DB credentials in cleartext** (`dbuser` / `<redacted>`) in `sensorcfg.php`, `scripts/getElectricityPrices.py`, and others. Move to env/secret if touching this code.
- **Pre-existing review doc:** `visualize/homeFunctions_review.md` already documents related bugs:
  - `SHOW TABLES LIKE '...'` uses substring/wildcard matching (a table name with `%`/`_` gives false positives) — should use `= '...'`.
  - `buildMonthlyUnion` multi-year loop bug: `$mcont` iterates `$frommonth..$tomonth` without resetting per year, producing wrong table names for multi-year ranges.

## 5. Suggested Sequencing

1. **Schema/DDL-only batch (no app-logic change):** 3.1 index + 3.2 VARCHAR(32) + 3.3 data type + 3.4 drop ON UPDATE. Update the C++ `CREATE TABLE` statements + a backfill loop script over existing tables.
2. **`sensorconfig` normalization (3.5):** coordinated edits to `sensorcfg.php`, `homeFunctions.php`, C++ writer + data-cleanup step.
3. **Later:** partitioning (3.6) + SQL-injection / credentials hardening.

## 6. Open Decisions for the Planner
- `sensorid`: commit to `VARCHAR(32)` (recommended) or full `BIGINT` (requires reworking the `electricityprice` sentinel)?
- `data`: `DECIMAL(15,3)` vs `DOUBLE`?
- Migration mechanism: introduce a real migration tool/scripts, or keep code-driven `CREATE TABLE` and add an idempotent backfill script for existing monthly tables?
- Address the SQL-injection and hardcoded-credentials issues in the same effort, or track separately?

## 7. Verification Notes
- Findings 2–3 confirmed by querying the live DB (`SHOW CREATE TABLE`, `information_schema.TABLES`, distinct-value and `NOT REGEXP '^[0-9]+$'` checks).
- Code findings confirmed by reading: `edssensors/edsServerHandler.cc`, `visualize/homeFunctions.php`, `sensorcfg.php`, `scripts/getElectricityPrices.py`, `container/sensorconfig.sql`, `dump.sql`.
- Not exhaustively read: every `container/*pod/*.py` inserter and each individual `visualize/*.php` report page (they call into `homeFunctions.php`).
