<?php

namespace App\Queries;

/**
 * GET /api/v1/observations: vital signs and lab/exam results of non-cancelled
 * MCU visits admitted in [:from, :to). One row per measured value.
 *
 * GMC keeps them in two tables, so this is two SELECTs joined by UNION ALL.
 * observation_key must be unique across both: vital signs use their own id,
 * results are prefixed "lab:".
 *
 * ✎ This is GMC's query. Change names to yours, keep the column aliases.
 */
class ObservationQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
-- Vital signs. local_code must be one of the six names below; iws maps them to
-- LOINC. unit, reference_range and flag may stay NULL: iws fills them in.
SELECT
    v.vital_sign_id::text AS observation_key,
    k.kunjungan_id::text  AS visit_key,
    p.no_rm               AS patient_key,
    'vital-signs'         AS category,
    v.tipe                AS name,
    NULL                  AS loinc,
    CASE v.tipe                                   -- ✎ your vital sign names
        WHEN 'nadi'                    THEN 'heart_rate'
        WHEN 'pernapasan'              THEN 'respiratory_rate'
        WHEN 'suhu_tubuh'              THEN 'body_temperature'
        WHEN 'tekanan_darah_sistolik'  THEN 'systolic_bp'
        WHEN 'tekanan_darah_diastolik' THEN 'diastolic_bp'
        WHEN 'spo2'                    THEN 'spo2'
        ELSE v.tipe
    END                   AS local_code,
    v.nilai               AS value,
    v.satuan              AS unit,
    NULL                  AS reference_range,
    NULL                  AS flag,
    v.waktu_pengukuran    AS effective_at,
    v.waktu_pengukuran    AS issued_at
FROM vital_sign v
    INNER JOIN kunjungan k ON k.kunjungan_id = v.kunjungan_id
    INNER JOIN pasien p ON p.pasien_id = k.pasien_id
    -- ✎ which polis count as medical check-up at your clinic
    INNER JOIN poli mcu ON mcu.poli_id = k.poli_id AND mcu.nama IN ('Medical Check Up')
WHERE k.tanggal_masuk >= :from
  AND k.tanggal_masuk <  :to
  AND k.status <> 'batal'

UNION ALL

-- Lab and exam results.
SELECT
    'lab:' || hl.hasil_lab_id::text AS observation_key,
    k.kunjungan_id::text  AS visit_key,
    p.no_rm               AS patient_key,
    hl.kategori           AS category,           -- laboratory / exam / procedure / vital-signs / social-history
    hl.parameter          AS name,
    hl.kode_loinc         AS loinc,
    hl.kode_lokal         AS local_code,
    hl.nilai              AS value,
    hl.satuan             AS unit,
    hl.nilai_normal       AS reference_range,
    CASE UPPER(TRIM(hl.flag))                    -- ✎ map your flags to N / H / L / A
        WHEN 'H' THEN 'H' WHEN 'HIGH' THEN 'H' WHEN 'TINGGI' THEN 'H'
        WHEN 'L' THEN 'L' WHEN 'LOW'  THEN 'L' WHEN 'RENDAH' THEN 'L'
        WHEN 'A' THEN 'A' WHEN 'ABNORMAL' THEN 'A'
        WHEN 'N' THEN 'N' WHEN 'NORMAL' THEN 'N'
        ELSE NULL
    END                   AS flag,
    COALESCE(pl.waktu_pengambilan_spesimen, pl.waktu_permintaan) AS effective_at,
    hl.waktu_hasil        AS issued_at
FROM hasil_lab hl
    INNER JOIN pemeriksaan_lab pl ON pl.pemeriksaan_lab_id = hl.pemeriksaan_lab_id
    INNER JOIN kunjungan k ON k.kunjungan_id = pl.kunjungan_id
    INNER JOIN pasien p ON p.pasien_id = k.pasien_id
    -- ✎ which polis count as medical check-up at your clinic
    INNER JOIN poli mcu ON mcu.poli_id = k.poli_id AND mcu.nama IN ('Medical Check Up')
WHERE k.tanggal_masuk >= :from
  AND k.tanggal_masuk <  :to
  AND k.status <> 'batal'
  AND NULLIF(TRIM(hl.nilai), '') IS NOT NULL      -- a result with no value is not sent
SQL;
    }
}
