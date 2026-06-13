/* ==========================================================================
   01. MAP INITIALIZATION & BASEMAP
   ========================================================================== */

const map = L.map("map", {
    preferCanvas: true,
    minZoom: 9,
    maxZoom: 18,
    maxBoundsViscosity: 1.0
});

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18
}).addTo(map);

/* ==========================================================================
   02. DATA DICTIONARIES (PLANTS & EMOJIS)
   ========================================================================== */

const TANAMAN = {
    padi_mean: "Padi",
    jagung_mean: "Jagung",
    cabai_mean: "Cabai",
    tomat_mean: "Tomat",
    kentang_mean: "Kentang",
    wortel_mean: "Wortel",
    terong_mean: "Terong"
};

const EMOJI = {
    Padi: "🌾",
    Jagung: "🌽",
    Cabai: "🌶️",
    Tomat: "🍅",
    Kentang: "🥔",
    Wortel: "🥕",
    Terong: "🍆"
};

/* ==========================================================================
   03. UTILITY FUNCTIONS (COLORS & SPATIAL RANKING)
   ========================================================================== */

/**
 * Menentukan warna polygon berdasarkan nilai skor fuzzy
 */
function getColor(score) {
    if (score >= 0.7) return "#1a9641";
    if (score >= 0.5) return "#a6d96a";
    if (score >= 0.3) return "#fdae61";
    return "#d7191c";
}

/**
 * Mengolah properti GeoJSON untuk mengurutkan skor kesesuaian lahan tanaman
 */
function getRanking(props) {
    const scores = Object.entries(TANAMAN).map(([field, nama]) => ({
        nama: nama,
        skor: parseFloat(props[field]) || 0
    }));

    scores.sort((a, b) => b.skor - a.skor);
    return scores;
}

/* ==========================================================================
   04. UI COMPONENTS (INFO PANEL DISPLAY)
   ========================================================================== */

/**
 * Merender konten informasi spasial desa ke elemen HTML sidebar/info panel
 */
function tampilkanInfo(feature) {
    const props = feature.properties;

    const desa = props.WADMKD || props.NAME_4 || "";
    const kecamatan = props.WADMKC || props.NAME_3 || "";
    const kabupaten = props.WADMKK || props.NAME_2 || "";
    const namaDesa = [desa, kecamatan, kabupaten].filter(Boolean).join(", ");

    const ranking = getRanking(props);
    const top4 = ranking.slice(0, 4);

    const html = `
        <h2>📍 ${namaDesa}</h2>
        ${top4
            .map(
                (item, i) => `
                <div class="rank-item">
                    <div class="rank-badge rank-${i + 1}">
                        ${i + 1}
                    </div>
                    <div class="rank-name">
                        ${EMOJI[item.nama]} ${item.nama}
                    </div>
                </div>
            `
            )
            .join("")}
        <p style="font-size:11px; color:#777; margin-top:8px">
            * Skor berdasarkan rata-rata ketinggian, curah hujan, dan suhu untuk tahun 2021-2025
        </p>
    `;

    document.getElementById("info-content").innerHTML = html;
}

/* ==========================================================================
   05. DATA LOADING & GEOJSON PROCESSING
   ========================================================================== */

let selectedLayer = null;

// Mengambil konfigurasi dataset aktif saat ini
fetch("data/current_dataset.json")
    .then((res) => res.json())
    .then((config) => {
        return fetch("uploads/" + config.active_dataset);
    })
    .then((res) => res.json())
    .then((data) => {
        const geojsonLayer = L.geoJSON(data, {
            // Style default masing-masing polygon wilayah
            style: function (feature) {
                const ranking = getRanking(feature.properties);
                const topSkor = ranking[0].skor;

                return {
                    fillColor: getColor(topSkor),
                    fillOpacity: 0.28,
                    color: getColor(topSkor),
                    weight: 0.3,
                    opacity: 0.25
                };
            },

            // Interaksi event handler tiap fitur polygon
            onEachFeature: function (feature, layer) {
                const desa = feature.properties.WADMKD || feature.properties.NAME_4 || "";
                const kecamatan = feature.properties.WADMKC || feature.properties.NAME_3 || "";
                const kabupaten = feature.properties.WADMKK || feature.properties.NAME_2 || "";
                const namaDesa = [desa, kecamatan, kabupaten].filter(Boolean).join(", ");

                // Mengikat komponen Tooltip saat hover
                layer.bindTooltip(namaDesa, {
                    permanent: false,
                    direction: "top",
                    className: "custom-tooltip"
                });

                // Event ketika polygon diklik
                layer.on("click", function () {
                    if (selectedLayer) {
                        selectedLayer.setStyle({
                            fillOpacity: 0.28,
                            color: getColor(getRanking(selectedLayer.feature.properties)[0].skor),
                            weight: 0.3,
                            opacity: 0.25
                        });
                    }

                    layer.bringToFront();
                    layer.setStyle({
                        fillOpacity: 0.8,
                        color: "#166534",
                        weight: 2,
                        opacity: 1
                    });

                    selectedLayer = layer;
                    tampilkanInfo(feature);
                });

                // Event ketika kursor masuk (Hover In)
                layer.on("mouseover", function () {
                    if (layer !== selectedLayer) {
                        layer.setStyle({
                            fillOpacity: 0.38,
                            weight: 0.8,
                            opacity: 0.7
                        });
                    }
                });

                // Event ketika kursor keluar (Hover Out)
                layer.on("mouseout", function () {
                    if (layer !== selectedLayer) {
                        layer.setStyle({
                            fillOpacity: 0.28,
                            color: getColor(getRanking(feature.properties)[0].skor),
                            weight: 0.3,
                            opacity: 0.25
                        });
                    }
                });
            }
        });

        // Menambahkan data utama zonasi ke peta
        geojsonLayer.addTo(map);

        // Memuat Masking Wilayah Luar (Zonasi Fokus Malang Raya)
        fetch("data/mask_jatim_web.geojson")
            .then((res) => res.json())
            .then((maskData) => {
                L.geoJSON(maskData, {
                    style: {
                        fillColor: "#ffffff",
                        fillOpacity: 0.55,
                        color: "#ffffff",
                        weight: 0
                    },
                    interactive: false
                }).addTo(map);
            });

        // Autofokus dan mengunci bounds peta ke area Malang Raya
        map.fitBounds(geojsonLayer.getBounds());
        map.setMaxBounds(geojsonLayer.getBounds());
        map.setMinZoom(10);
    })
    .catch((err) => {
        console.error("Gagal load GeoJSON:", err);
        document.getElementById("info-content").innerHTML = '<p style="color:red">⚠️ Gagal memuat data GeoJSON.</p>';
    });
