-- =============================================================================
-- PostgreSQL SIMRS schema (read by the pgsimrs adapter; used by the gmc source)
-- Fake hospital database for testing the IWS adapter pattern
-- =============================================================================

-- Core entities -----------------------------------------------------------------

CREATE TABLE pasien (
    pasien_id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    no_rm           VARCHAR(20)  NOT NULL UNIQUE,
    nik             VARCHAR(16)  NOT NULL UNIQUE,
    ihs_number      VARCHAR(50),
    nama            VARCHAR(200) NOT NULL,
    jenis_kelamin   VARCHAR(1)   NOT NULL CHECK (jenis_kelamin IN ('L', 'P')),
    tanggal_lahir   DATE         NOT NULL,
    alamat          TEXT,
    no_telepon      VARCHAR(20),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE dokter (
    dokter_id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nik             VARCHAR(16)  NOT NULL UNIQUE,
    ihs_number      VARCHAR(50),
    nama            VARCHAR(200) NOT NULL,
    spesialisasi    VARCHAR(100),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE poli (
    poli_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama            VARCHAR(100) NOT NULL,
    tipe            VARCHAR(20)  NOT NULL CHECK (tipe IN ('rawat_jalan', 'igd', 'rawat_inap')),
    ihs_location_id VARCHAR(50),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE kamar (
    kamar_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    poli_id         UUID         NOT NULL REFERENCES poli(poli_id),
    nama_kamar      VARCHAR(100) NOT NULL,
    kelas           VARCHAR(20),
    nomor_bed       VARCHAR(20),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Visits -----------------------------------------------------------------------

CREATE TABLE kunjungan (
    kunjungan_id    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pasien_id       UUID         NOT NULL REFERENCES pasien(pasien_id),
    dokter_id       UUID         NOT NULL REFERENCES dokter(dokter_id),
    poli_id         UUID         NOT NULL REFERENCES poli(poli_id),
    tipe            VARCHAR(20)  NOT NULL CHECK (tipe IN ('rawat_jalan', 'igd', 'rawat_inap')),
    tanggal_masuk   TIMESTAMPTZ  NOT NULL,
    tanggal_keluar  TIMESTAMPTZ,
    status          VARCHAR(20)  NOT NULL DEFAULT 'aktif' CHECK (status IN ('aktif', 'selesai', 'batal')),
    kamar_id        UUID         REFERENCES kamar(kamar_id),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Clinical data ----------------------------------------------------------------

CREATE TABLE vital_sign (
    vital_sign_id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    tipe                VARCHAR(50)  NOT NULL,
    nilai               VARCHAR(50)  NOT NULL,
    satuan              VARCHAR(20),
    waktu_pengukuran    TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE diagnosa (
    diagnosa_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    kode_icd10          VARCHAR(10)  NOT NULL,
    nama_diagnosa       VARCHAR(300) NOT NULL,
    tipe_diagnosa       VARCHAR(20)  NOT NULL CHECK (tipe_diagnosa IN ('primer', 'sekunder')),
    waktu_diagnosa      TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE catatan_klinis (
    catatan_id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    subjektif           TEXT,
    objektif            TEXT,
    analisis            TEXT,
    perencanaan         TEXT,
    waktu_pencatatan    TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE kuesioner (
    kuesioner_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    tipe_kuesioner      VARCHAR(50)  NOT NULL,
    jawaban             JSONB        NOT NULL DEFAULT '{}',
    waktu_pengisian     TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Medications ------------------------------------------------------------------

CREATE TABLE obat (
    obat_id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kode_kfa            VARCHAR(50),
    nama                VARCHAR(300) NOT NULL,
    bentuk_sediaan      VARCHAR(100),
    kekuatan            VARCHAR(100),
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE resep (
    resep_id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    dokter_id           UUID         NOT NULL REFERENCES dokter(dokter_id),
    waktu_resep         TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE resep_detail (
    resep_detail_id     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    resep_id            UUID         NOT NULL REFERENCES resep(resep_id),
    obat_id             UUID         NOT NULL REFERENCES obat(obat_id),
    dosis               VARCHAR(100),
    frekuensi           VARCHAR(100),
    jumlah              INTEGER      NOT NULL DEFAULT 0,
    aturan_pakai        TEXT,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE dispensasi (
    dispensasi_id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    resep_detail_id     UUID         NOT NULL REFERENCES resep_detail(resep_detail_id),
    waktu_dispensasi    TIMESTAMPTZ  NOT NULL,
    jumlah_diserahkan   INTEGER      NOT NULL DEFAULT 0,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Lab --------------------------------------------------------------------------

CREATE TABLE pemeriksaan_lab (
    pemeriksaan_lab_id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id                UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    kode_loinc                  VARCHAR(20),
    nama_pemeriksaan            VARCHAR(300) NOT NULL,
    waktu_permintaan            TIMESTAMPTZ  NOT NULL,
    waktu_pengambilan_spesimen  TIMESTAMPTZ,
    created_at                  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE spesimen (
    spesimen_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pemeriksaan_lab_id  UUID         NOT NULL REFERENCES pemeriksaan_lab(pemeriksaan_lab_id),
    tipe_spesimen       VARCHAR(100) NOT NULL,
    kode_spesimen       VARCHAR(50),
    waktu_pengambilan   TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE hasil_lab (
    hasil_lab_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pemeriksaan_lab_id  UUID         NOT NULL REFERENCES pemeriksaan_lab(pemeriksaan_lab_id),
    parameter           VARCHAR(200) NOT NULL,
    -- The result's own code. kode_loinc is set when the laboratory codes the
    -- parameter in LOINC; kode_lokal is the laboratory's own code otherwise (e.g.
    -- a body-composition device index LOINC has no code for). Both empty means
    -- the result is known only by its parameter name.
    kode_loinc          VARCHAR(20),
    kode_lokal          VARCHAR(50),
    -- What kind of result this is, which is the FHIR Observation.category it
    -- publishes under: a laboratory assay, a physical-exam finding, a procedure
    -- measurement (ECG intervals) or a history/lifestyle answer.
    kategori            VARCHAR(20)  NOT NULL DEFAULT 'laboratory'
                        CHECK (kategori IN ('laboratory', 'exam', 'procedure', 'vital-signs', 'social-history')),
    nilai               VARCHAR(100),
    satuan              VARCHAR(50),
    nilai_normal        VARCHAR(100),
    flag                VARCHAR(20),
    waktu_hasil         TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Radiology --------------------------------------------------------------------

CREATE TABLE pemeriksaan_radiologi (
    pemeriksaan_rad_id  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    kode_loinc          VARCHAR(20),
    nama_pemeriksaan    VARCHAR(300) NOT NULL,
    temuan              TEXT,
    kesimpulan          TEXT,
    waktu_pemeriksaan   TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Procedures -------------------------------------------------------------------

CREATE TABLE tindakan (
    tindakan_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    kode_icd9cm         VARCHAR(10),
    nama_tindakan       VARCHAR(300) NOT NULL,
    waktu_tindakan      TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Other clinical ---------------------------------------------------------------

CREATE TABLE alergi (
    alergi_id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pasien_id           UUID         NOT NULL REFERENCES pasien(pasien_id),
    jenis               VARCHAR(100) NOT NULL,
    deskripsi           TEXT,
    keparahan           VARCHAR(50),
    waktu_pencatatan    TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE imunisasi (
    imunisasi_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    nama_vaksin         VARCHAR(200) NOT NULL,
    kode_vaksin         VARCHAR(50),
    tanggal_imunisasi   TIMESTAMPTZ  NOT NULL,
    dosis               VARCHAR(50),
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- IGD-specific -----------------------------------------------------------------

CREATE TABLE triage_igd (
    triage_id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    kategori_esi        VARCHAR(10)  NOT NULL,
    nilai_triage        VARCHAR(50),
    waktu_triage        TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE skala_nyeri (
    nyeri_id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    skala               INTEGER      NOT NULL CHECK (skala BETWEEN 0 AND 10),
    kualitas            VARCHAR(100),
    lokasi              VARCHAR(100),
    waktu_pencatatan    TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE riwayat_medis (
    riwayat_id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    riwayat             TEXT         NOT NULL,
    waktu_pencatatan    TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Inpatient-specific -----------------------------------------------------------

CREATE TABLE prognosis (
    prognosis_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    kode_prognosis      VARCHAR(20),
    deskripsi           TEXT,
    kode_diagnosa       VARCHAR(10),
    nama_diagnosa       VARCHAR(300),
    waktu_pencatatan    TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE riwayat_gangguan (
    gangguan_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kunjungan_id        UUID         NOT NULL REFERENCES kunjungan(kunjungan_id),
    keluhan             TEXT,
    riwayat_gangguan    TEXT,
    waktu_pencatatan    TIMESTAMPTZ  NOT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Indexes for common query patterns -------------------------------------------

CREATE INDEX idx_pasien_nik ON pasien(nik);
CREATE INDEX idx_pasien_no_rm ON pasien(no_rm);
CREATE INDEX idx_pasien_ihs_number ON pasien(ihs_number);

CREATE INDEX idx_dokter_nik ON dokter(nik);
CREATE INDEX idx_dokter_ihs_number ON dokter(ihs_number);

CREATE INDEX idx_kunjungan_pasien_id ON kunjungan(pasien_id);
CREATE INDEX idx_kunjungan_dokter_id ON kunjungan(dokter_id);
CREATE INDEX idx_kunjungan_poli_id ON kunjungan(poli_id);
CREATE INDEX idx_kunjungan_tanggal_masuk ON kunjungan(tanggal_masuk);
CREATE INDEX idx_kunjungan_tipe ON kunjungan(tipe);
CREATE INDEX idx_kunjungan_status ON kunjungan(status);

CREATE INDEX idx_vital_sign_kunjungan_id ON vital_sign(kunjungan_id);
CREATE INDEX idx_diagnosa_kunjungan_id ON diagnosa(kunjungan_id);
CREATE INDEX idx_catatan_klinis_kunjungan_id ON catatan_klinis(kunjungan_id);
CREATE INDEX idx_kuesioner_kunjungan_id ON kuesioner(kunjungan_id);

CREATE INDEX idx_resep_kunjungan_id ON resep(kunjungan_id);
CREATE INDEX idx_resep_detail_resep_id ON resep_detail(resep_id);
CREATE INDEX idx_dispensasi_resep_detail_id ON dispensasi(resep_detail_id);

CREATE INDEX idx_pemeriksaan_lab_kunjungan_id ON pemeriksaan_lab(kunjungan_id);
CREATE INDEX idx_spesimen_pemeriksaan_lab_id ON spesimen(pemeriksaan_lab_id);
CREATE INDEX idx_hasil_lab_pemeriksaan_lab_id ON hasil_lab(pemeriksaan_lab_id);

CREATE INDEX idx_pemeriksaan_radiologi_kunjungan_id ON pemeriksaan_radiologi(kunjungan_id);
CREATE INDEX idx_tindakan_kunjungan_id ON tindakan(kunjungan_id);
CREATE INDEX idx_alergi_pasien_id ON alergi(pasien_id);
CREATE INDEX idx_imunisasi_kunjungan_id ON imunisasi(kunjungan_id);

CREATE INDEX idx_triage_igd_kunjungan_id ON triage_igd(kunjungan_id);
CREATE INDEX idx_skala_nyeri_kunjungan_id ON skala_nyeri(kunjungan_id);
CREATE INDEX idx_riwayat_medis_kunjungan_id ON riwayat_medis(kunjungan_id);

CREATE INDEX idx_prognosis_kunjungan_id ON prognosis(kunjungan_id);
CREATE INDEX idx_riwayat_gangguan_kunjungan_id ON riwayat_gangguan(kunjungan_id);
