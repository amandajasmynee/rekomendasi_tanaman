<?php

session_start();

/* ==========================================================================
   01. SESSION SECURITY CHECK
   ========================================================================== */

if (!isset($_SESSION["login"])) {
    die("Akses ditolak");
}


/* ==========================================================================
   02. CAPTURE DATASET INPUT
   ========================================================================== */

// Mengambil nama file dataset GeoJSON yang dipilih dari form POST
$dataset = $_POST["dataset"] ?? "";


/* ==========================================================================
   03. UPDATE CURRENT CONFIGURATION (JSON)
   ========================================================================== */

$config = [
    "active_dataset" => $dataset
];

// Menulis ulang konfigurasi dataset aktif ke file current_dataset.json
file_put_contents(
    "../data/current_dataset.json",
    json_encode($config, JSON_PRETTY_PRINT)
);


/* ==========================================================================
   04. FINAL REDIRECT
   ========================================================================== */

// Mengembalikan admin ke halaman dashboard setelah dataset berhasil diubah
header("Location: ../admin.php");
exit();