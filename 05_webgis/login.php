<?php
session_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SITAKLIM - Admin Portal</title>

    <link rel="stylesheet" href="css/login.css" />
</head>
<body>

    <div class="login-page">
        <div class="login-card">
            
            <div class="login-header">
                <div class="logo">🌾</div>
                <span class="login-badge">ADMIN PORTAL</span>
                <h1>SITAKLIM</h1>
                <p>
                    Masuk untuk mengelola dataset GeoJSON dan menentukan dataset aktif yang digunakan pada
                    Sistem Rekomendasi Tanaman Berbasis Zonasi Ketinggian dan Iklim Lahan (SITAKLIM).
                </p>
            </div>

            <?php if (isset($_SESSION["login_error"])): ?>
                <div class="login-error">
                    Username atau password salah.
                </div>
                <?php unset($_SESSION["login_error"]); ?>
            <?php endif; ?>

            <form action="php/auth.php" method="POST">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Masukkan username" required />
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Masukkan password" required />
                        <span id="toggle-password">Show</span>
                    </div>
                </div>

                <button type="submit">Login</button>

                <div class="login-note">Portal ini hanya digunakan oleh administrator sistem.</div>
                <div class="back-link">
                    <a href="/">← Kembali ke Peta</a>
                </div>

            </form>

        </div>
    </div>

    <script>
        const passwordInput = document.getElementById("password");
        const toggle = document.getElementById("toggle-password");

        toggle.addEventListener("click", () => {
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggle.textContent = "Hide";
            } else {
                passwordInput.type = "password";
                toggle.textContent = "Show";
            }
        });
    </script>

</body>
</html>