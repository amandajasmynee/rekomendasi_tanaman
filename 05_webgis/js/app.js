// ─── Inisialisasi peta ───────────────────────────────────────────
const map = L.map("map");

// Basemap OpenStreetMap
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution:
    '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 18,
}).addTo(map);

// ─── Daftar tanaman & label ──────────────────────────────────────
const TANAMAN = {
  padi_mean: "Padi",
  jagung_mean: "Jagung",
  cabai_mean: "Cabai",
  tomat_mean: "Tomat",
  kentang_mean: "Kentang",
};

const EMOJI = {
  Padi: "🌾",
  Jagung: "🌽",
  Cabai: "🌶️",
  Tomat: "🍅",
  Kentang: "🥔",
};

// ─── Fungsi warna polygon ────────────────────────────────────────
function getColor(score) {
  if (score >= 0.7) return "#1a9641";
  if (score >= 0.5) return "#a6d96a";
  if (score >= 0.3) return "#fdae61";
  return "#d7191c";
}

// ─── Ranking tanaman ─────────────────────────────────────────────
function getRanking(props) {
  const scores = Object.entries(TANAMAN).map(([field, nama]) => ({
    nama: nama,
    skor: parseFloat(props[field]) || 0,
  }));

  scores.sort((a, b) => b.skor - a.skor);

  return scores;
}

// ─── Panel info ──────────────────────────────────────────────────
function tampilkanInfo(feature) {
  const props = feature.properties;

  const namaDesa =
    props.NAME_4 ||
    props.WADMKD ||
    props.NAMOBJ ||
    "Desa/Kelurahan";

  const ranking = getRanking(props);
  const top3 = ranking.slice(0, 3);

  const html = `
    <h2>📍 ${namaDesa}</h2>

    ${top3
      .map(
        (item, i) => `
        <div class="rank-item">
          <div class="rank-badge rank-${i + 1}">
            ${i + 1}
          </div>

          <div class="rank-name">
            ${EMOJI[item.nama]} ${item.nama}
          </div>

          <div class="rank-score">
            ${item.skor.toFixed(3)}
          </div>
        </div>
      `
      )
      .join("")}

    <p style="font-size:11px; color:#777; margin-top:8px">
      Metode: SAW (Simple Additive Weighting)
    </p>
  `;

  document.getElementById("info-content").innerHTML = html;
}

// ─── Load GeoJSON ────────────────────────────────────────────────
let selectedLayer = null;

fetch("data/malang_raya_desa_rank.geojson")
  .then((res) => res.json())

  .then((data) => {

    const geojsonLayer = L.geoJSON(data, {

      // ─── Style default polygon ────────────────────────────────
      style: function (feature) {

        const ranking = getRanking(feature.properties);
        const topSkor = ranking[0].skor;

        return {
          fillColor: getColor(topSkor),
          weight: 0,
          fillOpacity: 0.12,
        };
      },

      // ─── Event tiap polygon ───────────────────────────────────
      onEachFeature: function (feature, layer) {

        const namaDesa =
          feature.properties.NAME_4 ||
          feature.properties.WADMKD ||
          feature.properties.NAMOBJ ||
          "Desa/Kelurahan";

        // Tooltip hover
        layer.bindTooltip(namaDesa, {
          permanent: false,
          direction: "top",
          className: "custom-tooltip",
        });

        // ─── Klik polygon ───────────────────────────────────────
        layer.on("click", function () {

          // Reset layer sebelumnya
          if (selectedLayer) {

            selectedLayer.setStyle({
              weight: 0,
              fillOpacity: 0.12,
            });
          }

          // Highlight layer aktif
          layer.setStyle({
            weight: 1.8,
            color: "#ffffff",
            fillOpacity: 0.45,
          });

          selectedLayer = layer;

          // Tampilkan panel info
          tampilkanInfo(feature);
        });

        // ─── Hover masuk ────────────────────────────────────────
        layer.on("mouseover", function () {

          if (layer !== selectedLayer) {

            layer.setStyle({
              weight: 1.2,
              color: "#ffffff",
              fillOpacity: 0.30,
            });
          }
        });

        // ─── Hover keluar ───────────────────────────────────────
        layer.on("mouseout", function () {

          if (layer !== selectedLayer) {

            layer.setStyle({
              weight: 0,
              fillOpacity: 0.12,
            });
          }
        });
      },
    });

    // Tambahkan layer ke map
    geojsonLayer.addTo(map);

    // Auto zoom ke area data
    map.fitBounds(geojsonLayer.getBounds());

  })

  .catch((err) => {

    console.error("Gagal load GeoJSON:", err);

    document.getElementById("info-content").innerHTML =
      '<p style="color:red">⚠️ Gagal memuat data GeoJSON.</p>';
  });