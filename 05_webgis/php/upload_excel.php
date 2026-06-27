<?php

error_reporting(E_ALL & ~E_WARNING);

session_start();

/* ==========================================================================
   01. SESSION SECURITY CHECK
   ========================================================================== */

if (!isset($_SESSION["login"])) {
    header("Location: ../login.html");
    exit();
}

/* ==========================================================================
   02. FILE UPLOAD VALIDATION
   ========================================================================== */

if (!isset($_FILES["excel"]) || $_FILES["excel"]["error"] !== UPLOAD_ERR_OK) {
    $code = $_FILES["excel"]["error"] ?? -1;
    die("Upload gagal (kode error: $code). Pastikan ukuran file tidak melebihi batas server.");
}

$file     = $_FILES["excel"];
// basename() mencegah path traversal
$origName = basename($file["name"]);
$fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($fileExt, ["xlsx", "xls"], true)) {
    header("Location: ../admin.php?error=format");
    exit();
}

// Simpan dengan nama yang di-sanitize (hanya alfanumerik, strip, titik)
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
$excelDir = __DIR__ . "/../excel/";

if (!is_dir($excelDir)) {
    mkdir($excelDir, 0755, true);
}

$destination = $excelDir . $safeName;

if (!move_uploaded_file($file["tmp_name"], $destination)) {
    header("Location: ../admin.php?error=upload");
    exit();
}

/* ==========================================================================
   03. BACA FILE EXCEL DENGAN PHPSPREADSHEET
   ========================================================================== */

require_once __DIR__ . "/../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load($destination);
} catch (\Exception $e) {
    header("Location: ../admin.php?error=readexcel");
    exit();
}

$sheet   = $spreadsheet->getActiveSheet();
$rawData = $sheet->toArray(null, true, true, false);
// toArray(null, true, true, false):
//   param 1 null   = nilai default null (bukan string kosong)
//   param 2 true   = hitung formula
//   param 3 true   = format nilai (misalnya tanggal tidak jadi serial number)
//   param 4 false  = baris/kolom berbasis 0

if (empty($rawData)) {
    die("File Excel kosong.");
}

// Baris pertama = header, normalisasi ke lowercase + trim
$headerRow = array_shift($rawData);
$headers   = array_map(fn($h) => strtolower(trim((string)$h)), $headerRow);

$required = [
    "kota_kabupaten",
    "kecamatan",
    "nama_desa",
    "curah_hujan",
    "suhu",
    "elevasi"
];

foreach ($required as $col) {
    if (!in_array($col, $headers, true)) {
        header("Location: ../admin.php?error=template");
        exit();
    }
}

$idxKab       = array_search("kota_kabupaten", $headers, true);
$idxKecamatan = array_search("kecamatan", $headers, true);
$idxDesa      = array_search("nama_desa", $headers, true);
$idxCH        = array_search("curah_hujan", $headers, true);
$idxSuhu      = array_search("suhu", $headers, true);
$idxElev      = array_search("elevasi", $headers, true);

/* ==========================================================================
   04. PARSING BARIS EXCEL → ARRAY [nama_desa_normalized => data]
   Normalisasi nama: uppercase, strip spasi berlebih
   ========================================================================== */

/**
 * Normalisasi nama desa untuk perbandingan yang toleran:
 * - uppercase
 * - trim + collapse whitespace
 * - hapus karakter non-alfanumerik kecuali spasi (mengakomodasi tanda hubung dll)
 */
function normalizeName(string $name): string
{
    $name = mb_strtoupper(trim($name), "UTF-8");
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

$excelData = []; // [normalized_nama => ['curah_hujan'=>..., 'suhu'=>..., 'elevasi'=>...]]

foreach ($rawData as $row) {
    // Skip baris kosong (semua sel null/string kosong)
    if (array_filter($row, fn($v) => $v !== null && $v !== "") === []) {
        continue;
    }

    $kabupaten = trim((string)$row[$idxKab]);
    $kecamatan = trim((string)($row[$idxKecamatan] ?? ""));
    $namaDesa  = trim((string)($row[$idxDesa] ?? ""));

    if ($kecamatan === "" || $namaDesa === "") {
        continue;
    }

    $ch   = is_numeric($row[$idxCH]) ? (float)$row[$idxCH] : null;
    $suhu = is_numeric($row[$idxSuhu]) ? (float)$row[$idxSuhu] : null;
    $elev = is_numeric($row[$idxElev]) ? (float)$row[$idxElev] : null;

    if ($ch === null || $suhu === null || $elev === null) {
        continue;
    }

    $key = normalizeName($kabupaten) . "|" . normalizeName($kecamatan) . "|" . normalizeName($namaDesa);

    $excelData[$key] = [
        "kabupaten"   => $kabupaten,
        "kecamatan"   => $kecamatan,
        "nama_desa"   => $namaDesa,
        "curah_hujan" => $ch,
        "suhu"        => $suhu,
        "elevasi"     => $elev,
    ];
}

if (empty($excelData)) {
    die("Tidak ada data Excel yang valid. Pastikan kolom Kecamatan, Nama Desa, Curah Hujan, Suhu, dan Elevasi telah diisi.");
}

/* ==========================================================================
   05. RULE-BASED SCORING + WEIGHTED SUITABILITY
   ========================================================================== */

/**
 * Memberi skor 3/2/1 untuk satu nilai parameter.
 */
function score(float $val, float $idealMin, float $idealMax,
               float $tolMin, float $tolMax, string $dir): int
{
    if ($val >= $idealMin && $val <= $idealMax) {
        return 3;
    }
    if ($dir === "lower") {
        if ($val >= $tolMin && $val <= $tolMax) {
            return 2;
        }
    } else {
        if ($val > $idealMax && $val <= $tolMax) {
            return 2;
        }
    }
    return 1;
}

/**
 * Menghitung skor kesesuaian lahan untuk satu komoditas.
 */
function calculateCommodity(float $ch, float $suhu, float $elev, array $rules): float
{
    $sCH   = score($ch,   ...$rules["ch"]);
    $sSuhu = score($suhu, ...$rules["suhu"]);
    $sElev = score($elev, ...$rules["elev"]);

    // Bobot final: CH=0.35, Suhu=0.35, Elevasi=0.30 — tidak dinormalisasi
    return round(($sCH * 0.35) + ($sSuhu * 0.35) + ($sElev * 0.30), 4);
}

const RULES = [
    "padi" => [
        "ch"   => [1500, 2000, 1200, 1499, "lower"],
        "suhu" => [22.5, 26.5,   20, 22.49, "lower"],
        "elev" => [   0, 1500, 1500, 1800, "upper"],
    ],
    "jagung" => [
        "ch"   => [1020, 2400,  800, 1019, "lower"],
        "suhu" => [  23,   30,   20, 22.99, "lower"],
        "elev" => [   0, 1200, 1200, 1500, "upper"],
    ],
    "cabai" => [
        "ch"   => [ 600, 1200, 1200, 1500, "upper"],
        "suhu" => [  18,   27,   27,   30, "upper"],
        "elev" => [   0, 1400, 1400, 1700, "upper"],
    ],
    "tomat" => [
        "ch"   => [ 750, 1250, 1250, 1500, "upper"],
        "suhu" => [  20,   27,   27,   30, "upper"],
        "elev" => [   0, 1600, 1600, 1900, "upper"],
    ],
    "kentang" => [
        "ch"   => [1500, 5000, 1200, 1499, "lower"],
        "suhu" => [  15,   20,   20,   23, "upper"],
        "elev" => [1000, 2000,  700,  999, "lower"],
    ],
    "wortel" => [
        "ch"   => [1500, 2800, 1200, 1499, "lower"],
        "suhu" => [  16,   25,   25,   28, "upper"],
        "elev" => [ 700, 1500,  500,  699, "lower"],
    ],
    "terong" => [
        "ch"   => [1020, 2400,  800, 1019, "lower"],
        "suhu" => [  22,   30,   19, 21.99, "lower"],
        "elev" => [   0, 1000, 1000, 1300, "upper"],
    ],
];

/* ==========================================================================
   06. BACA CURRENT_DATASET.JSON → TENTUKAN GEOJSON AKTIF
   ========================================================================== */

$configPath = __DIR__ . "/../data/current_dataset.json";

if (!file_exists($configPath)) {
    die("File konfigurasi current_dataset.json tidak ditemukan di: $configPath");
}

$configJson = file_get_contents($configPath);
$config     = json_decode($configJson, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($config["active_dataset"])) {
    die("current_dataset.json tidak valid atau active_dataset kosong.");
}

$activeDataset = basename($config["active_dataset"]);
$geojsonPath   = __DIR__ . "/../uploads/" . $activeDataset;

if (!file_exists($geojsonPath)) {
    die("Dataset aktif tidak ditemukan: uploads/$activeDataset");
}

/* ==========================================================================
   07. BACA GEOJSON AKTIF
   ========================================================================== */

$geojsonRaw = file_get_contents($geojsonPath);
if ($geojsonRaw === false) {
    die("Gagal membaca file GeoJSON: $geojsonPath");
}

$geojson = json_decode($geojsonRaw, true);
unset($geojsonRaw); 

if (json_last_error() !== JSON_ERROR_NONE) {
    die("GeoJSON tidak valid: " . json_last_error_msg());
}

if (empty($geojson["features"])) {
    die("GeoJSON tidak memiliki fitur (features kosong).");
}

/* ==========================================================================
   08. COCOKKAN DESA → UPDATE PROPERTIES
   ========================================================================== */

$matchCount  = 0;
$noMatchDesa = [];

foreach ($geojson["features"] as &$feature) {
    $props = &$feature["properties"];
    $key   = normalizeName($props["WADMKK"]) . "|" . normalizeName($props["WADMKC"]) . "|" . normalizeName($props["WADMKD"]);

    if ($key === "||") {
        continue;
    }

    if (!isset($excelData[$key])) {
        $noMatchDesa[] = ($props["WADMKC"] ?? "") . " - " . ($props["WADMKD"] ?? "");
        continue;
    }

    $hit  = $excelData[$key];
    $ch   = $hit["curah_hujan"];
    $suhu = $hit["suhu"];
    $elev = $hit["elevasi"];

    // Update nama_desa dan 7 skor komoditas
    $props["nama_kabupaten"] = $hit["kabupaten"];
    $props["nama_kecamatan"] = $hit["kecamatan"];
    $props["nama_desa"]      = $hit["nama_desa"];
    $props["padi_mean"]      = calculateCommodity($ch, $suhu, $elev, RULES["padi"]);
    $props["jagung_mean"]    = calculateCommodity($ch, $suhu, $elev, RULES["jagung"]);
    $props["cabai_mean"]     = calculateCommodity($ch, $suhu, $elev, RULES["cabai"]);
    $props["tomat_mean"]     = calculateCommodity($ch, $suhu, $elev, RULES["tomat"]);
    $props["kentang_mean"]   = calculateCommodity($ch, $suhu, $elev, RULES["kentang"]);
    $props["wortel_mean"]    = calculateCommodity($ch, $suhu, $elev, RULES["wortel"]);
    $props["terong_mean"]    = calculateCommodity($ch, $suhu, $elev, RULES["terong"]);

    $matchCount++;
}
unset($feature, $props);

if ($matchCount === 0) {
    @unlink($destination);
    header("Location: ../admin.php?error=nodata");
    exit();
}

/* ==========================================================================
   09. SIMPAN GEOJSON BARU KE FOLDER uploads/
   ========================================================================== */

$uploadsDir     = __DIR__ . "/../uploads/";
$baseName       = pathinfo($safeName, PATHINFO_FILENAME);
$timestamp      = date("Ymd_His");
$newGeoJsonName = "dataset_excel_{$timestamp}.geojson";
$newGeoJsonPath = $uploadsDir . $newGeoJsonName;

$encoded = json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
unset($geojson);

if ($encoded === false) {
    die("Gagal mengenkode GeoJSON: " . json_last_error_msg());
}

if (file_put_contents($newGeoJsonPath, $encoded) === false) {
    die("Gagal menyimpan GeoJSON baru ke: $newGeoJsonPath");
}
unset($encoded);

/* ==========================================================================
   10. UPDATE CURRENT_DATASET.JSON → AKTIFKAN DATASET BARU
   ========================================================================== */

$newConfig = ["active_dataset" => $newGeoJsonName];
$written   = file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT));

if ($written === false) {
    die(
        "GeoJSON berhasil dibuat ($newGeoJsonName) tetapi gagal memperbarui " .
        "current_dataset.json. Aktifkan dataset secara manual dari halaman admin."
    );
}

/* ==========================================================================
   11. REDIRECT KE ADMIN.PHP
   ========================================================================== */

header("Location: ../admin.php");
exit();