<?php

session_start();

/* ==========================================================================
   01. SESSION SECURITY CHECK
   ========================================================================== */

if (!isset($_SESSION["login"])) {
    die("Akses ditolak");
}


/* ==========================================================================
   02. SANITIZE INPUT & EXTENSION VALIDATION
   ========================================================================== */

// basename() digunakan untuk mencegah exploit Path Traversal (keamanan folder)
$dataset = basename($_POST["dataset"] ?? "");

if (pathinfo($dataset, PATHINFO_EXTENSION) !== "geojson") {
    die("File tidak valid");
}


/* ==========================================================================
   03. ACTIVE DATASET PROTECTION
   ========================================================================== */

$config = json_decode(
    file_get_contents("../data/current_dataset.json"),
    true
);

// Mencegah error WebGIS akibat dataset yang sedang dipakai malah terhapus
if ($dataset === $config["active_dataset"]) {
    header("Location: ../admin.php");
    exit();
}


/* ==========================================================================
   04. FILE EXECUTION & CLEANUP
   ========================================================================== */

$filePath = "../uploads/" . $dataset;

if (file_exists($filePath)) {
    unlink($filePath);
}


/* ==========================================================================
   05. FINAL REDIRECT
   ========================================================================== */

header("Location: ../admin.php");
exit();