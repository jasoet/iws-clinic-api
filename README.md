# iws clinic API

**Repo:** <https://github.com/jasoet/iws-clinic-api> (public, langsung `git clone` saja)

Repo ini adalah template untuk web API kecil yang **read-only**. API ini
dijalankan di sisi klinik, supaya iws bisa mengambil data medical check-up
(MCU) lalu meneruskannya ke rumah sakit.

```
database klinik  →  API ini (dijalankan klinik)  →  iws  →  rumah sakit (RSA UGM)
```

**Bagian Anda:** menulis SQL untuk membaca tabel-tabel di database klinik.
Sisanya sudah disiapkan: autentikasi dengan API key, paging, pengecekan
tiap baris, format JSON, dan pesan error. Yang perlu diubah hanya file-file di
`app/Queries/`.

**Bagian iws:** iws memanggil API ini setiap beberapa menit, mengubah datanya
ke FHIR R4, lalu mengirimkannya ke rumah sakit. Anda tidak perlu paham FHIR,
aturan LOINC, atau cara kerja iws di dalamnya.

Query di template ini sudah berisi **query milik GMC** sebagai contoh nyata.
Silakan ganti dengan query klinik Anda.

Isi:

1. [Cara iws memakai API ini](#1-cara-iws-memakai-api-ini)
2. [Mulai cepat](#2-mulai-cepat)
3. [Aturan main](#3-aturan-main)
4. [Endpoint](#4-endpoint)
5. [Memetakan nilai](#5-memetakan-nilai)
6. [Cek dan test](#6-cek-dan-test)
7. [Go-live](#7-go-live)
8. [Tanya jawab](#8-tanya-jawab)

Kontrak lengkapnya ada di [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1). Panduan
ini menjelaskan hal yang sama dengan bahasa sehari-hari. Versi PDF-nya bisa
dibuat dengan `task guide:pdf`.

---

## 1. Cara iws memakai API ini

- **Hanya membaca.** iws tidak pernah mengirim data ke klinik.
- **Diminta per rentang waktu.** Setiap request berbunyi kira-kira "kirim
  semua data untuk kunjungan yang masuk sejak `from` sampai sebelum `to`".
  Semua jenis data memakai **waktu masuk kunjungan**, termasuk hasil lab yang
  baru keluar beberapa hari kemudian.
- **Sengaja dibaca ulang.** Setiap kali jalan, iws membaca ulang 7 hari ke
  belakang, jadi hasil lab yang telat keluar tetap terambil. iws mengenali data
  yang sudah pernah diterima dari **key**-nya. Karena itu satu data harus
  punya **key yang sama selamanya**.
- **Hanya MCU.** Kirim kunjungan medical check-up beserta data turunannya,
  tidak lebih. SQL Anda yang menentukan kunjungan mana yang termasuk MCU (di
  GMC: kunjungan ke poli bernama "Medical Check Up").
- **Kunjungan batal.** Kunjungan yang batal tetap dikirim lewat `visits`
  dengan `status: "cancelled"`, supaya rumah sakit tahu kunjungan itu batal.
  Pasien, hasil pemeriksaan, dan diagnosisnya jangan ikut dikirim di endpoint
  lain.
- **Kunjungan terhapus.** Sesekali iws bertanya "kunjungan-kunjungan ini masih
  ada?" (endpoint presence), untuk tahu mana yang sudah dihapus.

## 2. Mulai cepat

Yang dibutuhkan: PHP 8.4 ke atas dengan driver database Anda (`pdo_pgsql`,
`pdo_mysql`, atau `pdo_sqlsrv`) dan Composer. [Task](https://taskfile.dev)
boleh dipakai, boleh tidak; semua perintah `task` di bawah ada di
`Taskfile.yaml` kalau mau diketik manual.

```sh
git clone https://github.com/jasoet/iws-clinic-api.git clinic-api
cd clinic-api
task install          # composer install, sekalian membuat .env
```

**Coba dulu dengan data contoh GMC** (butuh Docker atau Podman):

```sh
task up
curl -H "Authorization: Bearer change-me-local" http://localhost:8080/api/v1/health
# {"status":"ok","database":"ok","version":"1.0.0","contract":"v1"}
curl -H "Authorization: Bearer change-me-local" \
  "http://localhost:8080/api/v1/visits?from=2026-11-01T00:00:00%2B07:00&to=2026-12-01T00:00:00%2B07:00"
task down
```

**Lalu arahkan ke database klinik.** Ubah `.env`:

```sh
DB_CONNECTION=pgsql        # pgsql, mysql, mariadb, atau sqlsrv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=simrs
DB_USERNAME=iws_reader     # user yang hanya bisa SELECT
DB_PASSWORD=...
DB_TIMEZONE=Asia/Jakarta   # zona waktu yang dipakai database saat menyimpan jam
IWS_API_KEYS=...           # lihat bagian "Go-live"
```

Tulis ulang ketujuh file di `app/Queries/` sesuai tabel Anda (bagian 4),
lalu jalankan:

```sh
task check -- --from=2026-11-01 --to=2026-11-08   # cek SQL Anda terhadap data asli
task serve                                        # jalankan API di http://localhost:8080
```

## 3. Aturan main

| Aturan | Artinya |
|---|---|
| **Key** | Setiap request membawa `Authorization: Bearer <key>`. Tanpa key atau key salah, jawabannya `401`. |
| **HTTPS** | Wajib di production. |
| **Waktu** | Dikirim dalam format RFC 3339 lengkap dengan offset: `2026-11-25T08:30:00+07:00`. Template yang mengurus ini berdasarkan `DB_TIMEZONE`. |
| **Tanggal** | `YYYY-MM-DD` (tanggal lahir). Ini juga sudah diurus. |
| **Kosong** | Nilai kosong dikirim sebagai `null`. Template mengubah `''` jadi `null` dan membuang spasi di ujung. |
| **Paging** | Maksimal 500 baris per halaman (iws bisa minta sampai 1000). Paging diurus template. SQL Anda **jangan** pakai `ORDER BY` atau `LIMIT`. |
| **Lebar window** | Paling lebar 31 hari per request (`IWS_MAX_WINDOW_DAYS`). Lebih dari itu dijawab `422`. |
| **Error** | Selalu JSON dengan format `application/problem+json`, misalnya `{"type":"about:blank","title":"Bad Request","status":400,"detail":"'from' is required…"}`. Query yang gagal dijawab `500` tanpa SQL atau data di dalamnya; error lengkapnya masuk ke log Anda. |
| **Baris bermasalah** | Baris yang melanggar kontrak (misalnya status yang tidak dikenal) tidak masuk ke `data`, tapi dicatat di `skipped` beserta alasannya. Baris lain di halaman itu tetap terkirim. |

**Satu halaman respons bentuknya seperti ini:**

```json
{
  "data": [ { …satu data… }, … ],
  "next_cursor": "eyJ0Ijoid…",
  "window": { "from": "2026-11-01T00:00:00+07:00", "to": "2026-12-01T00:00:00+07:00" },
  "skipped": [ { "key": "123", "reason": "status 'x' is not one of in-progress, finished, cancelled" } ]
}
```

iws akan request lagi dengan `cursor=<next_cursor>` sampai `next_cursor`
bernilai `null`.

## 4. Endpoint

Semua endpoint ada di bawah `/api/v1`. Endpoint list menerima `from`, `to`
(wajib), `limit`, dan `cursor`.

| Endpoint | Isinya | File query |
|---|---|---|
| `GET /health` | API dan database hidup atau tidak | — |
| `GET /patients` | pasien yang punya kunjungan MCU tidak batal di window itu | `PatientQuery.php` |
| `GET /visits` | semua kunjungan MCU di window itu, **termasuk yang batal** | `VisitQuery.php` |
| `GET /practitioners` | dokter yang menangani kunjungan (yang tidak batal) tersebut | `PractitionerQuery.php` |
| `GET /locations` | poli/klinik tempat kunjungan tersebut | `LocationQuery.php` |
| `GET /observations` | tanda vital dan hasil lab/pemeriksaan dari kunjungan tersebut | `ObservationQuery.php` |
| `GET /conditions` | diagnosis ICD-10 dari kunjungan tersebut | `ConditionQuery.php` |
| `POST /visits/presence` | key kunjungan mana yang masih ada | `PresenceQuery.php` |

### Cara menulis query

Setiap file di `app/Queries/` mengembalikan satu `SELECT`:

- **Beri nama kolom persis seperti di tabel-tabel di bawah** (`AS visit_key`,
  `AS name`, …). Nama-nama itu adalah kontraknya. Kolom tambahan diabaikan.
- Pakai **`:from` dan `:to`** untuk window, terhadap waktu masuk kunjungan:
  `WHERE k.tanggal_masuk >= :from AND k.tanggal_masuk < :to`. Masing-masing
  boleh dipakai berkali-kali.
- Satu baris untuk satu data. Pasien, dokter, dan lokasi bisa muncul di
  banyak kunjungan, jadi pakai `GROUP BY` (lihat file-file GMC).
- Tanpa `ORDER BY` dan `LIMIT`.
- Key berupa text. Kalau kolomnya angka atau UUID, cast dulu (`k.id::text` di
  PostgreSQL, `CAST(k.id AS CHAR)` di MySQL, `CAST(k.id AS VARCHAR(50))` di
  SQL Server).
- Komentar bertanda **✎** di file-file GMC menunjukkan bagian yang khusus
  untuk GMC.

Kalau ada nilai yang perlu dirapikan di PHP (memecah satu field, mencari ke
tabel lain), override `transform(array $row): array` di class query tersebut.
Sebagian besar klinik tidak akan butuh ini.

Field bertanda ● wajib diisi. Selebihnya boleh `null`.

### `GET /patients`

| Field | | Keterangan |
|---|---|---|
| `patient_key` | ● | nomor rekam medis (no_rm). Tidak boleh berubah. |
| `nik` | | NIK |
| `name` | ● | |
| `gender` | | `male`, `female`, `other`, atau `unknown` |
| `birth_date` | | |
| `address` | | |
| `phone` | | |

```json
{ "patient_key": "RM-0001", "nik": "9900000000000101", "name": "Pasien Satu", "gender": "female",
  "birth_date": "1990-01-01", "address": "Jl. Contoh 1", "phone": null }
```

### `GET /visits`

| Field | | Keterangan |
|---|---|---|
| `visit_key` | ● | |
| `patient_key` | ● | sama dengan `patients.patient_key` |
| `practitioner_key` | | sama dengan `practitioners.practitioner_key` |
| `location_key` | | sama dengan `locations.location_key` |
| `class` | ● | `ambulatory`, `emergency`, atau `inpatient` |
| `status` | ● | `in-progress`, `finished`, atau `cancelled` |
| `admitted_at` | ● | |
| `discharged_at` | | |
| `diagnosis_keys` | | `condition_key` milik kunjungan ini, dipisah koma di SQL (`string_agg`, `GROUP_CONCAT`, `STRING_AGG`). API mengirimnya sebagai list. |

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

Satu baris untuk satu nilai yang diukur: satu per tanda vital, satu per
parameter lab.

| Field | | Keterangan |
|---|---|---|
| `observation_key` | ● | tidak boleh kosong, dan harus unik di antara tanda vital **dan** hasil lab. Kalau keduanya dari dua tabel yang id-nya bisa bentrok, beri prefix di salah satunya (`'lab:' \|\| id`). |
| `visit_key` | ● | |
| `patient_key` | ● | |
| `category` | ● | `vital-signs`, `laboratory`, `exam`, `procedure`, atau `social-history` |
| `name` | ● | nama pemeriksaan seperti yang ditulis di klinik ("Glukosa Puasa") |
| `loinc` | | kode LOINC, kalau ada |
| `local_code` | | kode internal klinik. **Untuk tanda vital yang tidak punya kode LOINC, isinya harus salah satu dari:** `heart_rate`, `respiratory_rate`, `body_temperature`, `systolic_bp`, `diastolic_bp`, `spo2` |
| `value` | ● | dalam bentuk text: `"5.4"`, `"+"`, `">50"`, `"Normal"` |
| `unit` | | |
| `reference_range` | | dalam bentuk text: `"70-100"` |
| `flag` | | `N` normal, `H` tinggi, `L` rendah, `A` abnormal |
| `effective_at` | ● | waktu pengukuran atau pengambilan sampel |
| `issued_at` | | waktu hasil keluar (kalau kosong, diisi `effective_at`) |

**Tanda vital:** `unit`, `reference_range`, dan `flag` boleh dibiarkan
`NULL`. iws yang mengisi satuan standarnya dan menentukan tinggi/rendahnya.
Klinik tidak perlu menyediakan batas-batas klinis.

**Kode:** kalau hasil lab tidak punya kode LOINC, kirim saja `local_code`
(atau namanya). iws akan menampilkan kode yang belum dikenal ke staf klinik
untuk dikonfirmasi sekali. Data tidak pernah ditahan gara-gara kodenya belum
ada.

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
| `icd10` | ● | diagnosis tanpa kode ICD-10 jangan dikirim |
| `name` | | |
| `recorded_at` | ● | |

### `POST /visits/presence`

iws mengirim sampai 500 key:

```json
{ "visit_keys": ["40000000-0000-0000-0000-000000000003", "40000000-0000-0000-0000-000000000099"] }
```

dan setiap key muncul tepat sekali di jawabannya:

```json
{ "present": ["40000000-0000-0000-0000-000000000003"], "absent": ["40000000-0000-0000-0000-000000000099"] }
```

`PresenceQuery` memakai `:visit_keys` (template yang memecahnya jadi list) dan
mengembalikan kolom `visit_key`. **Tanpa window, tanpa filter status, tanpa
filter MCU.** Kunjungan batal tetap dianggap ada; yang dianggap tidak ada
hanya kunjungan yang sudah dihapus.

```sql
SELECT k.kunjungan_id::text AS visit_key FROM kunjungan k WHERE k.kunjungan_id::text IN (:visit_keys)
```

## 5. Memetakan nilai

Database klinik punya istilahnya sendiri, sedangkan kontrak memakai kode
bahasa Inggris yang sudah baku. Terjemahkan dengan `CASE` di SQL. Contoh
pemetaan dari GMC:

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

`ELSE <nilai aslinya>` di akhir itu disengaja: nilai yang terlewat dipetakan
akan muncul apa adanya di `task check`, jadi kelihatan apa yang masih perlu
ditambahkan.

## 6. Cek dan test

**`task check`** (`php artisan iws:check`) menjalankan semua query terhadap
database asli untuk satu window, lalu memberi tahu apa yang salah:

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

Yang dilaporkan: kolom yang kurang, nilai di luar daftar, tanggal yang tidak
valid, key duplikat, key yang kosong, dan query yang error (lengkap dengan
pesan error dari database Anda). Kolom tambahan hanya diberi peringatan.
Tanpa `--from`/`--to`, yang dicek adalah 7 hari terakhir; `--type=visits`
untuk mengecek satu jenis saja. **Jalankan di rentang seminggu yang ramai
sebelum bilang ke iws bahwa API sudah siap.** Kalau ada masalah, exit code-nya
bukan nol, jadi bisa dipasang di CI Anda sendiri.

**`task test`** menjalankan test bawaan template (tidak perlu database). Test
ini menguji bagian yang tetap dan akan tetap lolos apa pun SQL yang Anda
tulis. `task test:gmc` menguji query contoh GMC terhadap PostgreSQL sementara.
Setelah query diganti dengan milik Anda, test itu tidak relevan lagi; folder
`tests/Gmc` boleh dihapus.

## 7. Go-live

1. **User database yang read-only.** Beri API ini user yang hanya bisa
   `SELECT` ke tabel-tabel yang dibaca query Anda.

   ```sql
   -- PostgreSQL
   CREATE ROLE iws_reader LOGIN PASSWORD '…';
   GRANT USAGE ON SCHEMA public TO iws_reader;
   GRANT SELECT ON pasien, kunjungan, poli, dokter, vital_sign, pemeriksaan_lab, hasil_lab, diagnosa TO iws_reader;

   -- MySQL / MariaDB
   CREATE USER 'iws_reader'@'%' IDENTIFIED BY '…';
   GRANT SELECT ON simrs.* TO 'iws_reader'@'%';
   ```

2. **HTTPS.** Pasang API di belakang web server atau load balancer yang punya
   sertifikat. Kalau ada proxy di depannya, isi `TRUSTED_PROXIES` dengan
   alamat proxy itu, supaya IP allow-list melihat IP asli iws.

3. **Key untuk iws.** Buat key acak yang panjang:

   ```sh
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

   Simpan di `IWS_API_KEYS`. Kirim **base URL lewat email**, dan **key lewat
   jalur aman yang terpisah** (jangan pernah dalam satu pesan).
   Untuk ganti key nanti, isi dua key sekaligus (`IWS_API_KEYS=baru,lama`),
   berikan yang baru ke iws, lalu hapus yang lama.

4. **Opsional: batasi IP.** Isi `IWS_ALLOWED_IPS` dengan IP iws. Minta
   daftarnya ke tim iws.

5. **Jalankan.**
   - Sebagai container: `docker build -t clinic-api .` (tambahkan
     `--build-arg WITH_SQLSRV=1` untuk SQL Server), lalu jalankan dengan nilai
     `.env` sebagai environment variable di port 8080.
   - Di server PHP: deploy seperti aplikasi Laravel biasa (php-fpm + nginx
     atau Apache, document root `public/`), dengan `APP_ENV=production` dan
     `APP_DEBUG=false`. Jalankan `php artisan config:cache` setiap kali `.env`
     diubah.

6. **Jalankan `task check` sekali lagi** di server, baru kirim URL-nya ke iws.

## 8. Tanya jawab

**Hasil lab berubah setelah window kunjungannya lewat. Apakah iws tahu?**
Ya, selama masih dalam 7 hari, karena iws selalu membaca ulang sejauh itu.
Untuk koreksi yang lebih lama, kabari tim iws supaya periode itu dibaca ulang.

**Key apa yang sebaiknya dipakai?** Primary key tabel Anda sendiri, dalam
bentuk text. Jangan nomor urut baris atau apa pun yang bisa berubah. Untuk
pasien, pakai nomor rekam medis.

**Database kami menyimpan jam tanpa zona waktu.** Isi `DB_TIMEZONE` dengan
zona waktu klinik saat mencatat (biasanya `Asia/Jakarta`). Template akan
membacanya di zona itu dan mengirim offset yang benar.

**Database kami MySQL / SQL Server.** Bisa. Tulis SQL sesuai dialeknya;
template yang menyesuaikan paging-nya. Cast key ke text, dan pakai
`GROUP_CONCAT` / `STRING_AGG` untuk `diagnosis_keys`.

**Bisakah iws minta data setahun sekaligus?** Tidak, maksimal 31 hari per
request. iws sendiri yang memecah periode yang lebih panjang.

**Kalau ada baris yang salah?** Baris itu dilewati dan dicatat di `skipped`
beserta alasannya, dan peringatannya masuk ke log Anda. Baris lain tetap
terkirim. Hal yang sama berlaku untuk baris yang membuat `transform()` error.
Baris yang key-nya `NULL` dihitung di `skipped` pada halaman pertama.
`task check` menampilkan alasan yang sama.

**Kolom key kami bertipe `char(n)` dan berisi spasi di belakang.** Tidak
masalah, API membuang spasi itu, baik di list maupun di presence.

**SQL yang tidak bisa dibaca template.** Template mengenali string `'…'`
biasa, nama `"…"`, komentar `--` dan `/* */`, serta cast `::` PostgreSQL.
Yang belum dikenali: string PostgreSQL `$$…$$` atau `E'…'`, escape backslash
MySQL (`'it\'s'`), dan operator JSON `?` PostgreSQL; tulis dengan cara lain.
Di SQL Server, query Anda dibungkus sebagai subquery, jadi tidak bisa diawali
`WITH` (pindahkan CTE-nya ke subquery) dan nama kolomnya harus unik. Semua
kasus ini akan gagal dengan jelas di `task check`.

**Apakah iws pernah menulis ke database kami?** Tidak. API ini tidak punya
endpoint untuk menulis, dan user database-nya seharusnya hanya bisa `SELECT`.
