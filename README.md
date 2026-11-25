# iws clinic API

This is a template for a small, **read-only** web API that your clinic runs so
iws can collect your medical check-up (MCU) results and pass them on to the
hospital.

```
your database  →  this API (you run it)  →  iws  →  hospital (RSA UGM)
```

**What you do:** write the SQL that reads your own tables. Everything else is
already built: sign-in with a key, paging, checking each row, the JSON format
and error messages. You edit the files in `app/Queries/` and nothing else.

**What iws does:** it calls this API every few minutes, turns the records into
FHIR R4 and delivers them. You don't need to know FHIR, LOINC rules or how iws
works inside.

The template ships with **GMC's queries** already filled in, as a worked
example. Replace them with yours.

Contents:

1. [How iws uses the API](#1-how-iws-uses-the-api)
2. [Quick start](#2-quick-start)
3. [The rules](#3-the-rules)
4. [The endpoints](#4-the-endpoints)
5. [Mapping your values](#5-mapping-your-values)
6. [Check and test](#6-check-and-test)
7. [Going live](#7-going-live)
8. [Questions](#8-questions)

The exact contract is in [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1). This
guide says the same thing in plain words.

---

## 1. How iws uses the API

- **It only reads.** iws never sends data to you.
- **It asks by time window.** Each call says "give me everything for visits
  admitted from `from` up to (not including) `to`". Every record type uses the
  **visit's admission time**, even results that came out days later.
- **It asks again on purpose.** Each run re-reads the last 7 days, so a result
  that came out late is still picked up. iws recognises records it already has
  by their **key**. So a record must keep the **same key forever**.
- **Only MCU.** Return medical check-up visits and what belongs to them,
  nothing else. Your SQL decides what counts as MCU (at GMC it's visits to the
  poli named "Medical Check Up").
- **Cancelled visits.** Return a cancelled visit from `visits` with
  `status: "cancelled"`, so the hospital learns it was cancelled. Leave its
  patient, results and diagnoses out of the other lists.
- **Deleted visits.** Now and then iws asks "do you still have these visits?"
  (the presence endpoint), to notice visits that were deleted.

## 2. Quick start

You need PHP 8.4 or newer with the driver for your database (`pdo_pgsql`,
`pdo_mysql` or `pdo_sqlsrv`), and Composer. [Task](https://taskfile.dev) is
optional: every `task` command below is listed in `Taskfile.yaml` if you'd
rather type it out.

```sh
git clone <this repo> clinic-api && cd clinic-api
task install          # composer install, creates .env
```

**Try it first with GMC's example data** (needs Docker or Podman):

```sh
task up
curl -H "Authorization: Bearer change-me-local" http://localhost:8080/api/v1/health
# {"status":"ok","database":"ok","version":"1.0.0","contract":"v1"}
curl -H "Authorization: Bearer change-me-local" \
  "http://localhost:8080/api/v1/visits?from=2026-11-01T00:00:00%2B07:00&to=2026-12-01T00:00:00%2B07:00"
task down
```

**Then point it at your database.** Edit `.env`:

```sh
DB_CONNECTION=pgsql        # pgsql, mysql, mariadb or sqlsrv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=simrs
DB_USERNAME=iws_reader     # a login that can only SELECT
DB_PASSWORD=...
DB_TIMEZONE=Asia/Jakarta   # the zone your database writes times in
IWS_API_KEYS=...           # see "Going live"
```

Rewrite the seven files in `app/Queries/` for your tables (section 4), then:

```sh
task check -- --from=2026-11-01 --to=2026-11-08   # checks your SQL against your data
task serve                                        # runs the API on http://localhost:8080
```

## 3. The rules

| Rule | What it means |
|---|---|
| **Key** | Every call sends `Authorization: Bearer <key>`. A missing or wrong key gets `401`. |
| **HTTPS** | Always, in production. |
| **Times** | Returned as RFC 3339 with the offset: `2026-11-25T08:30:00+07:00`. The template does this for you from `DB_TIMEZONE`. |
| **Dates** | `YYYY-MM-DD` (birth date). Also done for you. |
| **Empty** | An empty value is `null`. The template turns `''` into `null` and trims spaces. |
| **Paging** | Up to 500 rows per page (iws may ask up to 1000). The template does the paging. Your SQL must **not** have `ORDER BY` or `LIMIT`. |
| **Window size** | At most 31 days per call (`IWS_MAX_WINDOW_DAYS`). Wider gets `422`. |
| **Errors** | Always JSON in the `application/problem+json` format, e.g. `{"type":"about:blank","title":"Bad Request","status":400,"detail":"'from' is required…"}`. A failed query returns `500` with no SQL or data in it; the full error goes to your log. |
| **Bad rows** | A row that breaks the contract (say, a status the template doesn't know) is left out of `data` and listed under `skipped` with the reason. The rest of the page still arrives. |

**A page looks like this:**

```json
{
  "data": [ { …one record… }, … ],
  "next_cursor": "eyJ0Ijoid…",
  "window": { "from": "2026-11-01T00:00:00+07:00", "to": "2026-12-01T00:00:00+07:00" },
  "skipped": [ { "key": "123", "reason": "status 'x' is not one of in-progress, finished, cancelled" } ]
}
```

iws calls again with `cursor=<next_cursor>` until `next_cursor` is `null`.

## 4. The endpoints

All under `/api/v1`. The list endpoints take `from`, `to` (required), `limit`
and `cursor`.

| Endpoint | Returns | Query file |
|---|---|---|
| `GET /health` | is the API and database up | — |
| `GET /patients` | patients with a non-cancelled MCU visit in the window | `PatientQuery.php` |
| `GET /visits` | every MCU visit in the window, **including cancelled** | `VisitQuery.php` |
| `GET /practitioners` | doctors who attended those (non-cancelled) visits | `PractitionerQuery.php` |
| `GET /locations` | the poli/clinic of those visits | `LocationQuery.php` |
| `GET /observations` | vital signs and lab/exam results of those visits | `ObservationQuery.php` |
| `GET /conditions` | ICD-10 diagnoses of those visits | `ConditionQuery.php` |
| `POST /visits/presence` | which visit keys you still have | `PresenceQuery.php` |

### How to write a query

Each file in `app/Queries/` returns one SQL `SELECT`:

- **Name the columns exactly as the tables below** (`AS visit_key`, `AS name`, …).
  Those names are the contract. Extra columns are ignored.
- Use **`:from` and `:to`** for the window, on the visit's admission time:
  `WHERE k.tanggal_masuk >= :from AND k.tanggal_masuk < :to`. You can use each
  one as often as you need.
- Return **one row per record**. For patients, doctors and locations, which
  repeat across visits, use `GROUP BY` (see GMC's files).
- No `ORDER BY` or `LIMIT`.
- Keys are text. Cast numbers or UUIDs if you need to (`k.id::text` in
  PostgreSQL, `CAST(k.id AS CHAR)` in MySQL, `CAST(k.id AS VARCHAR(50))` in SQL Server).
- The comments marked **✎** in GMC's files show what's specific to GMC.

If you need to fix up a value in PHP (split a field, look something up),
override `transform(array $row): array` in that query class. Most clinics
won't need it.

Fields marked ● are required. The rest may be `null`.

### `GET /patients`

| Field | | Notes |
|---|---|---|
| `patient_key` | ● | your medical record number (no_rm). Never changes. |
| `nik` | | national identity number |
| `name` | ● | |
| `gender` | | `male`, `female`, `other` or `unknown` |
| `birth_date` | | |
| `address` | | |
| `phone` | | |

```json
{ "patient_key": "RM-0001", "nik": "9900000000000101", "name": "Pasien Satu", "gender": "female",
  "birth_date": "1990-01-01", "address": "Jl. Contoh 1", "phone": null }
```

### `GET /visits`

| Field | | Notes |
|---|---|---|
| `visit_key` | ● | |
| `patient_key` | ● | same value as `patients.patient_key` |
| `practitioner_key` | | same value as `practitioners.practitioner_key` |
| `location_key` | | same value as `locations.location_key` |
| `class` | ● | `ambulatory`, `emergency` or `inpatient` |
| `status` | ● | `in-progress`, `finished` or `cancelled` |
| `admitted_at` | ● | |
| `discharged_at` | | |
| `diagnosis_keys` | | the visit's `condition_key`s, comma-separated in SQL (`string_agg`, `GROUP_CONCAT`, `STRING_AGG`). The API sends a list. |

```json
{ "visit_key": "40000000-0000-0000-0000-000000000001", "patient_key": "RM-0001",
  "practitioner_key": "20000000-0000-0000-0000-000000000001", "location_key": "30000000-0000-0000-0000-000000000001",
  "class": "ambulatory", "status": "finished",
  "admitted_at": "2026-11-10T08:00:00+07:00", "discharged_at": "2026-11-10T12:00:00+07:00",
  "diagnosis_keys": ["80000000-0000-0000-0000-000000000001"] }
```

### `GET /practitioners`

| Field | | |
|---|---|---|
| `practitioner_key` | ● | |
| `nik` | | |
| `name` | ● | |

### `GET /locations`

| Field | | |
|---|---|---|
| `location_key` | ● | |
| `name` | ● | |

### `GET /observations`

One row per measured value: one per vital sign, one per lab parameter.

| Field | | Notes |
|---|---|---|
| `observation_key` | ● | unique across vital signs **and** results. If they come from two tables whose ids can clash, prefix one (`'lab:' \|\| id`). |
| `visit_key` | ● | |
| `patient_key` | ● | |
| `category` | ● | `vital-signs`, `laboratory`, `exam`, `procedure` or `social-history` |
| `name` | ● | the test's name as you write it ("Glukosa Puasa") |
| `loinc` | | the LOINC code, if you have it |
| `local_code` | | your own code. **For vital signs it must be one of:** `heart_rate`, `respiratory_rate`, `body_temperature`, `systolic_bp`, `diastolic_bp`, `spo2` |
| `value` | ● | as text: `"5.4"`, `"+"`, `">50"`, `"Normal"` |
| `unit` | | |
| `reference_range` | | as text: `"70-100"` |
| `flag` | | `N` normal, `H` high, `L` low, `A` abnormal |
| `effective_at` | ● | when it was measured or the sample was taken |
| `issued_at` | | when the result came out (defaults to `effective_at`) |

**Vital signs:** you can leave `unit`, `reference_range` and `flag` as `NULL`.
iws fills in the standard unit and works out high/low itself. You never need to
supply clinical limits.

**Codes:** if a result has no LOINC code, send your `local_code` (or just the
name). iws shows unknown codes to the clinic staff to confirm once; records are
never held back because of a missing code.

```json
{ "observation_key": "lab:70000000-0000-0000-0000-000000000001", "visit_key": "40000000-0000-0000-0000-000000000001",
  "patient_key": "RM-0001", "category": "laboratory", "name": "Glukosa Puasa", "loinc": "1558-6", "local_code": "GDP",
  "value": "130", "unit": "mg/dL", "reference_range": "70-100", "flag": "H",
  "effective_at": "2026-11-10T08:30:00+07:00", "issued_at": "2026-11-10T10:00:00+07:00" }
```

### `GET /conditions`

| Field | | |
|---|---|---|
| `condition_key` | ● | |
| `visit_key` | ● | |
| `patient_key` | ● | |
| `icd10` | ● | leave out diagnoses with no ICD-10 code |
| `name` | | |
| `recorded_at` | ● | |

### `POST /visits/presence`

iws sends up to 500 keys:

```json
{ "visit_keys": ["40000000-0000-0000-0000-000000000003", "40000000-0000-0000-0000-000000000099"] }
```

and gets back every key once:

```json
{ "present": ["40000000-0000-0000-0000-000000000003"], "absent": ["40000000-0000-0000-0000-000000000099"] }
```

Your `PresenceQuery` uses `:visit_keys` (the template expands it into a list)
and returns a `visit_key` column. **No window, no status filter, no MCU
filter.** A cancelled visit is still present; only a deleted one is absent.

```sql
SELECT k.kunjungan_id::text AS visit_key FROM kunjungan k WHERE k.kunjungan_id::text IN (:visit_keys)
```

## 5. Mapping your values

Your database uses its own words; the contract uses fixed English codes.
Translate them with `CASE` in your SQL. GMC's mappings, as an example:

```sql
CASE p.jenis_kelamin WHEN 'L' THEN 'male' WHEN 'P' THEN 'female' ELSE 'unknown' END AS gender,

CASE k.tipe WHEN 'rawat_jalan' THEN 'ambulatory' WHEN 'igd' THEN 'emergency'
            WHEN 'rawat_inap' THEN 'inpatient' ELSE k.tipe END AS class,

CASE k.status WHEN 'aktif' THEN 'in-progress' WHEN 'selesai' THEN 'finished'
              WHEN 'batal' THEN 'cancelled' ELSE k.status END AS status,

CASE v.tipe WHEN 'nadi' THEN 'heart_rate' WHEN 'pernapasan' THEN 'respiratory_rate'
            WHEN 'suhu_tubuh' THEN 'body_temperature' WHEN 'tekanan_darah_sistolik' THEN 'systolic_bp'
            WHEN 'tekanan_darah_diastolik' THEN 'diastolic_bp' WHEN 'spo2' THEN 'spo2'
            ELSE v.tipe END AS local_code,

CASE UPPER(TRIM(hl.flag)) WHEN 'TINGGI' THEN 'H' WHEN 'RENDAH' THEN 'L' WHEN 'NORMAL' THEN 'N' … END AS flag
```

Ending with `ELSE <the original value>` is deliberate: a value you didn't map
shows up in `task check` with its real text, so you can see what to add.

## 6. Check and test

**`task check`** (`php artisan iws:check`) runs every query against your real
database for a window and tells you what's wrong:

```
$ task check -- --from=2026-11-01 --to=2026-12-01
Window 2026-11-01T00:00:00+07:00 → 2026-12-01T00:00:00+07:00 (Asia/Jakarta)
patients: 3 rows, OK
visits: 4 rows, OK
practitioners: 2 rows, OK
locations: 1 rows, OK
observations: 9 rows, OK
conditions: 1 rows, OK
presence: 4 of 4 visit keys found, OK
All good. Tell iws your API is ready.
```

It reports missing columns, values outside the lists, bad dates, duplicate
keys and query errors (with your database's own error message), and warns
about extra columns. Without `--from`/`--to` it checks the last 7 days;
`--type=visits` checks one type. **Run it over a busy week before you tell iws
you're ready.** It exits non-zero when something is wrong, so you can use it in
your own CI.

**`task test`** runs the template's own tests (no database needed). They test
the fixed parts and keep passing whatever SQL you write. `task test:gmc` tests
GMC's example queries against a throwaway PostgreSQL. Once you've replaced the
queries with yours, that one no longer applies; delete `tests/Gmc` if you like.

## 7. Going live

1. **A read-only database login.** Give the API a user that can only `SELECT`
   the tables your queries read.

   ```sql
   -- PostgreSQL
   CREATE ROLE iws_reader LOGIN PASSWORD '…';
   GRANT USAGE ON SCHEMA public TO iws_reader;
   GRANT SELECT ON pasien, kunjungan, poli, dokter, vital_sign, pemeriksaan_lab, hasil_lab, diagnosa TO iws_reader;

   -- MySQL / MariaDB
   CREATE USER 'iws_reader'@'%' IDENTIFIED BY '…';
   GRANT SELECT ON simrs.* TO 'iws_reader'@'%';
   ```

2. **HTTPS.** Put the API behind your web server or load balancer with a
   certificate. If it sits behind a proxy, put the proxy's address in
   `TRUSTED_PROXIES` so the IP allow-list sees iws's real address.

3. **A key for iws.** Generate a long random key:

   ```sh
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

   Put it in `IWS_API_KEYS`. Send the **base URL by email** and the **key
   through a separate secure channel** (never both in one message).
   To change the key later, set both keys (`IWS_API_KEYS=new,old`), give iws
   the new one, then remove the old one.

4. **Optional: limit by IP.** Put iws's addresses in `IWS_ALLOWED_IPS`. Ask iws
   for them.

5. **Run it.**
   - As a container: `docker build -t clinic-api .` (add
     `--build-arg WITH_SQLSRV=1` for SQL Server) and run it with your `.env`
     values as environment variables on port 8080.
   - On a PHP server: deploy it like any Laravel app (php-fpm + nginx or
     Apache, document root `public/`), with `APP_ENV=production` and
     `APP_DEBUG=false`. Run `php artisan config:cache` after changing `.env`.

6. **Run `task check` once more** on the server, then send iws the URL.

## 8. Questions

**A result changes after the visit window has passed. Does iws see it?**
Yes, for 7 days: iws re-reads that far back each run. For corrections older
than that, tell iws and they can re-read a period.

**Which key should I use?** Your table's own primary key, as text. Never a row
number or anything that can change. For patients use the medical record number.

**Our database stores times without a time zone.** Set `DB_TIMEZONE` to the
zone the clinic writes them in (usually `Asia/Jakarta`). The template reads
them in that zone and sends the right offset.

**Our database is MySQL / SQL Server.** That works. Write the SQL in your
dialect; the template adapts the paging. Cast keys to text and use
`GROUP_CONCAT` / `STRING_AGG` for `diagnosis_keys`.

**Can iws ask for a whole year at once?** No, 31 days at most per call. iws
splits longer periods itself.

**What if a row is wrong?** It's skipped and listed in `skipped` with the
reason, and a warning goes to your log. The others still arrive. `task check`
shows the same reasons.

**Does iws ever write to our database?** No. The API has no endpoint that
writes, and its database login should only be able to `SELECT`.
