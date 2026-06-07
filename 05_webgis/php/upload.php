<?php

session_start();

/* ==========================================================================
   01. SESSION & FILE EXISTENCE SECURITY CHECK
   ========================================================================= */

if (!isset($_SESSION["login"])) {
    die("Akses ditolak");
}

if (!isset($_FILES["geojson"])) {
    die("File tidak ditemukan");
}


/* ==========================================================================
   02. EXTENSION VALIDATION
   ========================================================================= */

// basename() mengamankan nama file dari serangan Path Traversal
$filename = basename($_FILES["geojson"]["name"]);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if ($extension !== "geojson") {
    die("File harus berformat .geojson");
}


/* ==========================================================================
   03. GEOJSON STRUCTURE VALIDATION (ANTI-CORRUPT CHECK)
   ========================================================================= */

$content = file_get_contents($_FILES["geojson"]["tmp_name"]);
json_decode($content);

// Memastikan isi file benar-benar berformat JSON yang valid (tidak rusak/kosong)
if (json_last_error() !== JSON_ERROR_NONE) {
    die("File GeoJSON tidak valid");
}


/* ==========================================================================
   04. EXECUTE FILE UPLOAD
   ========================================================================= */

move_uploaded_file(
    $_FILES["geojson"]["tmp_name"],
    "../uploads/" . $filename
);


/* ==========================================================================
   05. FINAL REDIRECT
   ========================================================================= */

header("Location: ../admin.php");
exit();