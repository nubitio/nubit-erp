# ADR-013 — UTC Storage / Lima Display Contract

## Status

Accepted

## Date

2026-03-05

---

## Context

**Project domain:** Peruvian e-invoicing SaaS (efact). All business operations — sales,
purchases, tax documents submitted to SUNAT — are anchored to the Peruvian calendar day as
perceived in **America/Lima (UTC−5, no DST)**.

**Infrastructure facts:**
- Database: MariaDB with `DATETIME` columns. `DATETIME` has **no timezone metadata**; the
  value stored is exactly the bytes written by the application.
- PHP process timezone (both `php.dev.ini` and `php.prod.ini`): `date.timezone = "UTC"`.
- Docker services (`docker-compose.yml`): `TZ=UTC` on the PHP/web container **and** the
  MariaDB container.
- No daylight-saving-time transitions exist for America/Lima — the UTC offset is a fixed −5h
  year-round. This simplifies conversion but does not eliminate the obligation to convert.

**The bug class this ADR prevents:**

When a developer writes `new DateTimeImmutable()` without an explicit timezone, PHP uses the
process timezone (UTC). That UTC "now" is then stored as-is in a `DATETIME` column — which is
correct. However, a developer who later filters by a Lima calendar date using
`DATE(column) = '2026-01-15'` compares a **UTC datetime** against a **Lima date string**
without conversion. Between 00:00 and 05:00 Lima time, the UTC day is already the *next* day,
so sales, invoices, and SUNAT daily summaries are silently wrong. This class of bug was
observed in `DashboardRepository` before the UTC-range refactor (see commit history) and in
ad-hoc report queries across the codebase.

A second bug class arises in `TimestampableTrait`: `new DateTime()` (no timezone argument)
resolves to UTC under the process timezone, but code that later calls `->format(...)` without
re-attaching a timezone produces a UTC string that is displayed as Lima time in the UI, showing
a 5-hour offset to users.

**Why not store Lima local time?**
Storing wall-clock Lima time in `DATETIME` columns would work *now* but would break
catastrophically if the server TZ ever changed, if a MariaDB TIMESTAMP column was accidentally
used (auto-converts on server TZ change; 2038 limit), or if a developer outside Peru ran the
application in a different timezone context.

**Why not use MariaDB `TIMESTAMP`?**
`TIMESTAMP` columns perform implicit UTC↔server-TZ conversion on read/write. With `TZ=UTC` on
the server this is a no-op today, but it ties correctness to a server configuration variable.
It also has a hard upper limit of 2038-01-19. `DATETIME` columns with explicit UTC storage are
safer and more portable.

---

## Decision

**All datetime values are stored in UTC. The application layer is solely responsible for
converting between UTC and America/Lima when interpreting user-facing calendar dates.**

This means:
1. Every `DATETIME` value written to the database represents UTC time.
2. No conversion is ever performed implicitly by the database engine or the OS.
3. Any query that compares a `DATETIME` column against a Lima calendar date **must** apply
   `CONVERT_TZ(column, 'UTC', 'America/Lima')` before extracting the date component.
4. Any display of a datetime to the end user **must** render it in `America/Lima`.

---

## Rules

### Database

- All `DATETIME` columns store **UTC** values. Column comments should note `-- UTC`.
- **Do not use `TIMESTAMP` columns** for application data.
  - MariaDB `TIMESTAMP` auto-converts on `@@global.time_zone` change.
  - `TIMESTAMP` has a 2038-01-19 upper bound.
  - `DATETIME` with explicit UTC storage is the safe alternative.
- The application layer is responsible for **all** Lima↔UTC conversions.
- `CONVERT_TZ()` requires the MariaDB timezone tables to be populated. Confirm this is present
  in production with `SELECT CONVERT_TZ('2026-01-01 00:00:00','UTC','America/Lima');` → should
  return `2025-12-31 19:00:00`.

### PHP Backend

| Pattern | Status | Notes |
|---|---|---|
| `new DateTimeImmutable('now', new DateTimeZone('UTC'))` | ✅ | Canonical UTC "now" |
| `new DateTimeImmutable('now', new DateTimeZone('America/Lima'))` | ✅ | Lima "now" for calendar logic |
| `DateHelper::now()` | ✅ (after fix) | Must return UTC explicitly |
| `DateHelper::todayLima()` / `DateHelper::nowLima()` | ✅ (after fix) | Only for user-facing calendar |
| `new DateTimeImmutable()` without TZ argument | ❌ FORBIDDEN | Depends on process TZ — fragile |
| `new DateTime()` without TZ argument | ❌ FORBIDDEN | Same issue; also mutable |
| `date()`, `strtotime()`, `mktime()` for business dates | ❌ FORBIDDEN | TZ-dependent |

**Specific rules:**

- `DateHelper::now()` — **must** pass `new DateTimeZone('UTC')`. The canonical implementation:
  ```php
  public static function now(): DateTimeImmutable
  {
      return new DateTimeImmutable('now', new DateTimeZone('UTC'));
  }
  ```
- `DateHelper::nowLima()` / `DateHelper::todayLima()` — **must** pass
  `new DateTimeZone('America/Lima')`. Use only to derive Lima calendar dates for display or
  for constructing UTC range parameters for SQL queries.
- `TimestampableTrait::updateTimestampsOnPersist()` and `updateTimestampsOnUpdate()` —
  **must** use `new DateTimeImmutable('now', new DateTimeZone('UTC'))`, not bare
  `new DateTime()`.
- When building UTC boundaries from a Lima date (e.g. `2026-01-15`), construct the Lima
  midnight and add 1 day, then call `->setTimezone(new DateTimeZone('UTC'))` before passing to
  SQL.

### SQL Filtering

**Rule: Never compare a Lima calendar date against a raw UTC `DATETIME` column.**

| Pattern | Status |
|---|---|
| `DATE(CONVERT_TZ(col, 'UTC', 'America/Lima')) = :limaDate` | ✅ Correct (non-sargable, OK for small tables) |
| `col >= :dayStartUtc AND col < :dayEndUtc` (UTC boundaries) | ✅ Correct and **sargable** (preferred for indexed columns) |
| `DATE(col) = :limaDate` | ❌ FORBIDDEN — wrong when Lima time ≠ UTC date |
| `col BETWEEN :limaDate AND :limaDateEnd` with Lima strings | ❌ FORBIDDEN — misses UTC-offset records |

**Preferred pattern for indexed columns (sargable):**

```php
// In PHP — derive UTC boundaries from Lima day
$tz  = new DateTimeZone('America/Lima');
$utc = new DateTimeZone('UTC');

$dayStart = new DateTimeImmutable($limaDate . ' 00:00:00', $tz);
$dayEnd   = $dayStart->modify('+1 day');

$params = [
    'dayStartUtc' => $dayStart->setTimezone($utc)->format('Y-m-d H:i:s'),
    'dayEndUtc'   => $dayEnd->setTimezone($utc)->format('Y-m-d H:i:s'),
];
```

```sql
-- In SQL — uses the index on `issued_at`
WHERE issued_at >= :dayStartUtc
  AND issued_at < :dayEndUtc
```

**Fallback pattern for unindexed or small tables (non-sargable but readable):**

```sql
WHERE DATE(CONVERT_TZ(issued_at, 'UTC', 'America/Lima')) = :limaDate
```

**BETWEEN clauses** must always use UTC boundary datetimes derived from Lima day start/end,
never raw Lima strings.

**Reference implementation:** `src/Repository/DashboardRepository.php` —
`buildQueryContext()` computes `monthStartUtc`, `monthEndUtc`, `todayStartUtc`, `todayEndUtc`
and queries use direct UTC range filters (`issued_at >= :monthStartUtc`), keeping indexes
effective. The `CONVERT_TZ` form is used only for display formatting in `SELECT` projections
(e.g. `DATE_FORMAT(CONVERT_TZ(issued_at, 'UTC', 'America/Lima'), '%H:%i')`).

### Frontend

| Pattern | Status |
|---|---|
| UTC ISO-8601 with `Z` suffix sent to API: `2026-01-15T23:00:00Z` | ✅ |
| `luxon` `DateTime.fromJSDate(d, {zone: 'America/Lima'}).toUTC().toISO()` | ✅ |
| `Intl.DateTimeFormat('es-PE', {timeZone: 'America/Lima'})` for display | ✅ |
| `luxon.Settings.defaultZone = 'America/Lima'` at app startup | ✅ |
| `getTimezoneOffset()` arithmetic for TZ conversion | ❌ FORBIDDEN — browser-TZ-dependent |
| `timeZone: 'UTC'` in display formatters | ❌ FORBIDDEN — shows UTC time to Lima users |
| `new Date(dateString).toLocaleDateString()` without `timeZone` option | ❌ FORBIDDEN — uses browser locale |

**Rules:**

1. **Serialisation to API** — Always send UTC ISO-8601 strings with a `Z` suffix. Use `luxon`:
   ```ts
   import { DateTime } from 'luxon';
   const utcIso = DateTime.fromJSDate(jsDate, { zone: 'America/Lima' }).toUTC().toISO();
   // → "2026-01-15T23:00:00.000Z"
   ```
2. **Display to user** — Always render in `America/Lima`. Use `Intl.DateTimeFormat` or luxon:
   ```ts
   new Intl.DateTimeFormat('es-PE', {
     timeZone: 'America/Lima',
     day: '2-digit', month: '2-digit', year: 'numeric',
     hour: '2-digit', minute: '2-digit',
   }).format(new Date(utcIsoString));
   ```
3. **App startup** — Set luxon default zone once in `app.tsx` / entry point:
   ```ts
   import { Settings } from 'luxon';
   Settings.defaultZone = 'America/Lima';
   ```
4. **Never** use `Date.prototype.getTimezoneOffset()` for manual offset arithmetic — the result
   depends on the browser's local timezone, which is unpredictable in a SaaS context where
   users may be on machines set to other timezones.

### Environment / Config

| File | Required setting |
|---|---|
| `docker/php/php.dev.ini` | `date.timezone = "UTC"` ✅ already set |
| `docker/php/php.prod.ini` | `date.timezone = "UTC"` ✅ already set |
| `docker-compose.yml` — web service | `TZ: UTC` ✅ already set |
| `docker-compose.yml` — database service | `TZ: UTC` ✅ already set |
| `docker-compose.prod.yml` | Must mirror dev: `TZ: UTC` on both services |

Any new container (worker, queue consumer, cron) **must** also set `TZ=UTC`.

---

## Consequences

### Positive

- **Predictable audit trail** — `created_at`, `updated_at`, `deleted_at` are unambiguous UTC
  timestamps regardless of which server, region, or developer environment wrote them.
- **Correct SUNAT daily summaries** — Tax documents and sales totals group correctly by Lima
  calendar day, satisfying SUNAT's daily close requirements.
- **Correct reports** — "Sales today", "invoices this month", and all dashboard metrics reflect
  the Lima business day, not a UTC-day artefact.
- **Index-friendly queries** — The preferred UTC-range pattern keeps `WHERE issued_at >= ? AND
  issued_at < ?` sargable, allowing the database to use covering indexes rather than full
  scans.
- **No silent drift** — The rule is binary and checkable: every `new DateTimeImmutable()` call
  either has an explicit TZ argument or it violates the rule. Code review and static analysis
  (e.g. a custom PHPStan rule) can enforce this mechanically.

### Negative

- **More verbose PHP code** — Every datetime construction requires an explicit `DateTimeZone`
  argument. Developers unfamiliar with the convention will need onboarding.
- **Two-step calendar queries** — Filtering on a Lima date always requires deriving UTC
  boundaries in PHP *or* using `CONVERT_TZ` in SQL. This is non-obvious and must be enforced
  by convention and code review.
- **`CONVERT_TZ` dependency** — The MySQL/MariaDB timezone tables must be populated. This is
  standard in production installations but must be verified in new environments.

---

## Examples

### ✅ Correct — PHP

**`DateHelper` — explicit UTC "now" and Lima helpers:**

```php
// CORRECT: explicit timezone on every construction
public static function now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

public static function nowLima(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
}

public static function todayLima(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('America/Lima'))->setTime(0, 0);
}
```

**`TimestampableTrait` — UTC timestamps on persist/update:**

```php
#[ORM\PrePersist]
public function updateTimestampsOnPersist(): void
{
    // CORRECT: explicit UTC — not bare new DateTime()
    $this->createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

#[ORM\PreUpdate]
public function updateTimestampsOnUpdate(): void
{
    $this->updatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
}
```

**Query with UTC range filter (sargable, from `DashboardRepository`):**

```php
$tz  = new DateTimeZone('America/Lima');
$utc = new DateTimeZone('UTC');

$todayStart = new DateTimeImmutable('now', $tz)->setTime(0, 0, 0);
$todayEnd   = $todayStart->modify('+1 day');

$params = [
    'branchId'     => $branchId,
    'todayStartUtc' => $todayStart->setTimezone($utc)->format('Y-m-d H:i:s'),
    'todayEndUtc'   => $todayEnd->setTimezone($utc)->format('Y-m-d H:i:s'),
];

// SQL — sargable, uses index on issued_at
$sql = "
    SELECT DATE_FORMAT(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima'), '%H:%i') AS issued_at_lima,
           s.grand_total
    FROM sales s
    WHERE s.branch_id = :branchId
      AND s.issued_at >= :todayStartUtc
      AND s.issued_at < :todayEndUtc
";
```

**Query with `CONVERT_TZ` in WHERE (non-sargable, for small/unindexed tables):**

```php
// SQL — readable, correct, but does not use index
$sql = "
    SELECT * FROM tax_document
    WHERE DATE(CONVERT_TZ(issue_date, 'UTC', 'America/Lima')) = :limaDate
";
```

---

### ✅ Correct — Frontend

**Serialise a JS `Date` to UTC ISO-8601 for the API:**

```ts
import { DateTime } from 'luxon';

function toApiUtc(d: Date): string {
  // Interpret the Date as a Lima wall-clock time, then convert to UTC ISO-8601
  return DateTime.fromJSDate(d, { zone: 'America/Lima' }).toUTC().toISO()!;
  // → "2026-01-15T23:00:00.000Z"  (Lima 18:00 → UTC 23:00)
}
```

**Display a UTC ISO string to a Lima user:**

```ts
function displayLima(utcIso: string): string {
  return new Intl.DateTimeFormat('es-PE', {
    timeZone: 'America/Lima',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(utcIso));
  // "15/01/2026, 06:00 p. m."  (UTC 23:00 → Lima 18:00)
}
```

**App startup (`assets/app.tsx`):**

```ts
import { Settings } from 'luxon';
Settings.defaultZone = 'America/Lima'; // all luxon DateTime.now() calls use Lima
```

---

### ❌ Wrong — do not do this

**PHP anti-patterns:**

```php
// ❌ FORBIDDEN — no explicit timezone; resolves to whatever the process TZ is
$now = new DateTimeImmutable();
$now = new DateTime();

// ❌ FORBIDDEN — TZ-dependent legacy functions
$today = date('Y-m-d');
$ts    = strtotime('now');
$ts    = mktime(0, 0, 0, 1, 15, 2026);

// ❌ FORBIDDEN — bare new DateTime() in TimestampableTrait
#[ORM\PrePersist]
public function updateTimestampsOnPersist(): void
{
    $this->createdAt = new DateTime(); // ← no TZ — fragile
}
```

**SQL anti-patterns:**

```sql
-- ❌ FORBIDDEN — DATE() of a UTC column compared to a Lima date string
--    At 00:30 Lima time (05:30 UTC), UTC date is already the next day.
WHERE DATE(issued_at) = '2026-01-15'

-- ❌ FORBIDDEN — BETWEEN with Lima strings on a UTC column
WHERE issued_at BETWEEN '2026-01-15 00:00:00' AND '2026-01-15 23:59:59'
-- (misses the first 5 hours of the Lima day stored as 2026-01-14 19:00:00 UTC)
```

**Frontend anti-patterns:**

```ts
// ❌ FORBIDDEN — getTimezoneOffset() depends on the browser's local TZ
const offsetMs = new Date().getTimezoneOffset() * 60 * 1000;
const limaTs   = Date.now() - offsetMs; // wrong if browser is not in Lima

// ❌ FORBIDDEN — timeZone:'UTC' for display shows UTC time, not Lima time
new Intl.DateTimeFormat('es-PE', { timeZone: 'UTC' }).format(new Date(utcIso));

// ❌ FORBIDDEN — no timeZone option; uses browser locale
new Date(utcIso).toLocaleDateString('es-PE');
```

---

## References

- `src/Core/Util/DateHelper.php` — UTC/Lima helper methods (to be updated per this ADR)
- `src/Core/Traits/TimestampableTrait.php` — Timestampable lifecycle hooks (to be updated)
- `src/Repository/DashboardRepository.php` — **Reference implementation** for UTC-range
  SQL queries and Lima-boundary computation
- `docker/php/php.dev.ini`, `docker/php/php.prod.ini` — `date.timezone = "UTC"` confirmed
- `docker-compose.yml` — `TZ: UTC` on web and database services confirmed
- SLICE-013 — UTC-first datetime consistency (parent slice)
