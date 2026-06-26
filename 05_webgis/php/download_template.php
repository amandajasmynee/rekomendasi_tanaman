<?php
session_start();

if (!isset($_SESSION["login"])) {
    header("Location: ../login.html");
    exit();
}

require "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/* ===========================
   BACA DATASET AKTIF
=========================== */

$config = json_decode(
    file_get_contents("../data/current_dataset.json"),
    true
);

if (!$config || empty($config["active_dataset"])) {
    die("Dataset aktif tidak ditemukan.");
}

$geojsonPath = "../uploads/" . $config["active_dataset"];

if (!file_exists($geojsonPath)) {
    die("GeoJSON aktif tidak ditemukan.");
}

$geojson = json_decode(file_get_contents($geojsonPath), true);

if (!$geojson) {
    die("GeoJSON tidak dapat dibaca.");
}

/* ===========================
   AMBIL DATA DESA
=========================== */

$data = [];

foreach ($geojson["features"] as $feature) {

    $props = $feature["properties"];

    $data[] = [
    "kabupaten" => $props["WADMKK"] ?? "",
    "kecamatan" => $props["WADMKC"] ?? "",
    "desa"      => $props["WADMKD"] ?? ""
];

}

/* ===========================
   SORT
=========================== */

$data = array_filter($data, function($item){
    return
    $item["kabupaten"] !== "" &&
    $item["kecamatan"] !== "" &&
    $item["desa"] !== "";
});

usort($data,function($a,$b){

    if($a["kabupaten"] != $b["kabupaten"]){
        return strcmp($a["kabupaten"],$b["kabupaten"]);
    }

    if($a["kecamatan"] != $b["kecamatan"]){
        return strcmp($a["kecamatan"],$b["kecamatan"]);
    }

    return strcmp($a["desa"],$b["desa"]);

});

/* ===========================
   BUAT EXCEL
=========================== */

$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle("Template");

/* Header */

$sheet->fromArray([
    [
    "kota_kabupaten",
    "kecamatan",
    "nama_desa",
    "curah_hujan",
    "suhu",
    "elevasi"
]
]);

/* Style Header */

$sheet->getStyle("A1:F1")->getFont()->setBold(true);

$sheet->getStyle("A1:F1")->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("A1:F1")->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()
      ->setARGB("FF4CAF50");

$sheet->getStyle("A1:F1")->getFont()->getColor()->setARGB("FFFFFFFF");

/* Isi */

$row = 2;

foreach ($data as $item) {

    $sheet->setCellValue("A$row",$item["kabupaten"]);
$sheet->setCellValue("B$row",$item["kecamatan"]);
$sheet->setCellValue("C$row",$item["desa"]);

    $row++;

}

/* Auto Width */

foreach (range('A','E') as $col){

    $sheet->getColumnDimension($col)->setAutoSize(true);

}

/* Freeze Header */

$sheet->freezePane("A2");

/* Download */

$filename = "template_dataset_excel_" . date("Ymd") . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);

$writer->save('php://output');

exit();