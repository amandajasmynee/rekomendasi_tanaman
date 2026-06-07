<?php

session_start();

/* ==========================================================================
   01. CAPTURE & SANITIZE USER INPUT
   ========================================================================== */

$username = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");


/* ==========================================================================
   02. CREDENTIALS VALIDATION (HARDCODED AUTH)
   ========================================================================== */

if ($username === "admin" && $password === "admin123") {
    // Set session status login berhasil
    $_SESSION["login"] = true;

    // Alihkan langsung ke halaman dashboard admin
    header("Location: ../admin.php");
    exit();
}


/* ==========================================================================
   03. AUTHENTICATION FAILED HANDLING
   ========================================================================== */

// Set session flag untuk memicu alert error di login.php
$_SESSION["login_error"] = true;

// Kembalikan ke halaman login
header("Location: ../login.php");
exit();