<?php

session_start();

/* ==========================================================================
   01. DESTROY USER SESSION
   ========================================================================== */

// Menghapus semua data session yang tersimpan di server
session_destroy();


/* ==========================================================================
   02. REDIRECT TO LOGIN PAGE
   ========================================================================== */

// Mengembalikan user ke halaman login setelah session dihancurkan
header("Location: ../login.php");
exit();