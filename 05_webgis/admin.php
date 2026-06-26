<?php
session_start();

if (!isset($_SESSION["login"])) {
    header("Location: login.html");
    exit();
}

$currentConfig = json_decode(
    file_get_contents("data/current_dataset.json"),
    true
);
$currentDataset = $currentConfig["active_dataset"];

$datasets = glob("uploads/*.geojson");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SITAKLIM - Admin Dashboard</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/admin.css" />
</head>
<body>

    <header class="topbar">
        <div>
            <h2>🌾 SITAKLIM</h2>
            <p>Portal administrator untuk pengelolaan dataset dan konfigurasi SITAKLIM</p>
        </div>

        <div class="user-info">
            <div class="user-details">
                <strong>Admin</strong>
                <span>System Administrator</span>
                <a href="php/logout.php" class="header-logout">Logout</a>
            </div>
            <div class="avatar">
                <?= strtoupper(substr("Admin", 0, 1)) ?>
            </div>
        </div>
    </header>


    <div class="admin-layout">
        <aside class="sidebar">

            <div class="sidebar-section">
                <h3>Upload Dataset GeoJSON</h3>
                <form action="php/upload.php" method="POST" enctype="multipart/form-data">
                    <input type="file" name="geojson" accept=".geojson" required />
                    <button type="submit" class="sidebar-btn">Upload Dataset</button>
                </form>
            </div>

             <div class="sidebar-section">
    <h3>Upload Dataset Excel</h3>

<p class="upload-note">
    Belum punya template?
</p>

<a href="php/download_template.php" class="download-template-link">
    📥 Download Template Excel
</a>

    <form action="php/upload_excel.php"
          method="POST"
          enctype="multipart/form-data">

        <input
            type="file"
            name="excel"
            accept=".xlsx,.xls"
            required
        />

        <button type="submit" class="sidebar-btn">
            Upload & Generate
        </button>

    </form>

</div>

            <div class="sidebar-section">
                <h3>Dataset Aktif</h3>
                <div class="active-dataset-box">
                    <div class="active-file">
                        📍 <?= htmlspecialchars($currentDataset) ?>
                    </div>
                </div>
            </div>

            <div class="sidebar-section">
                <h3>Daftar Dataset</h3>
                <p class="dataset-count"><?= count($datasets) ?> dataset tersedia</p>
                
                <ul class="dataset-list">
                    <?php foreach ($datasets as $file): ?>
                        <?php
                        $filename = basename($file);
                        $isActive = ($filename === $currentDataset);
                        ?>
                        
                        <li class="dataset-item <?= $isActive ? 'active' : '' ?>">
                            <div class="dataset-name">
                                📄 <?= htmlspecialchars($filename) ?>
                            </div>

                            <?php if ($isActive): ?>
                                <span class="active-badge">Aktif</span>
                            <?php else: ?>
                                <div class="dataset-actions">
                                    <form action="php/set_dataset.php" method="POST">
                                        <input type="hidden" name="dataset" value="<?= htmlspecialchars($filename) ?>" />
                                        <button type="submit" class="set-active-btn">Set Active</button>
                                    </form>

                                    <form action="php/delete_dataset.php" method="POST" onsubmit="return confirm('Hapus dataset:\n\n<?= htmlspecialchars($filename) ?>\n\nTindakan ini tidak dapat dibatalkan.');">
                                        <input type="hidden" name="dataset" value="<?= htmlspecialchars($filename) ?>" />
                                        <button type="submit" class="delete-btn">🗑️ Hapus</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </aside>


        <main class="map-wrapper">
            <div id="map"></div>

            <div id="info-panel">
                <div id="info-content">
                    <h3 style="margin-bottom:10px;">🌾 SITAKLIM</h3>
                    <p style="color:#666;">
                        Klik salah satu desa atau kelurahan pada peta untuk melihat rekomendasi komoditas tanaman berdasarkan kondisi iklim dan karakteristik lahan.
                    </p>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/app.js"></script>

</body>
</html>