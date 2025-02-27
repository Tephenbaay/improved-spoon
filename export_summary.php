<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

include("config.php");
require "vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$selectedSheet = isset($_GET['sheet']) ? $_GET['sheet'] : null;
$uploadedFiles = glob("uploads/*.{xls,xlsx,csv}", GLOB_BRACE);
$latestFile = !empty($uploadedFiles) ? end($uploadedFiles) : null;
$summaryData = [];

if ($latestFile && $selectedSheet) {
    try {
        $spreadsheet = IOFactory::load($latestFile);
        $worksheet = $spreadsheet->getSheetByName($selectedSheet);

        if ($worksheet) {
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            $columnTotals = array_fill(1, $highestColumnIndex, 0);
            $columnAverages = array_fill(1, $highestColumnIndex, 0);
            $columnCounts = array_fill(1, $highestColumnIndex, 0);

            for ($row = 2; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellCoordinate = Coordinate::stringFromColumnIndex($col) . $row;
                    $cellValue = $worksheet->getCell($cellCoordinate)->getValue();
                    
                    if (!empty($cellValue)) {
                        $columnCounts[$col]++;
                    }
                    
                    if (is_numeric($cellValue)) {
                        $columnTotals[$col] += $cellValue;
                    }
                }
            }

            foreach ($columnTotals as $col => $total) {
                $columnAverages[$col] = ($columnCounts[$col] > 0) ? $total / $columnCounts[$col] : 0;
            }

            $summaryData = [
                "totals" => $columnTotals,
                "averages" => $columnAverages,
                "counts" => $columnCounts,
            ];
        }
    } catch (Exception $e) {
        die("Error loading Excel file: " . $e->getMessage());
    }
}

// **Step 1: Create Bar Graph**
$imagePath = "summary_chart.png";

$dataPoints = array_values($summaryData["totals"]);
$labels = array_map(fn($col) => Coordinate::stringFromColumnIndex($col), array_keys($summaryData["totals"]));

$width = 800;
$height = 400;

$image = imagecreate($width, $height);
$background = imagecolorallocate($image, 255, 255, 255);
$barColor = imagecolorallocate($image, 50, 150, 250);
$textColor = imagecolorallocate($image, 0, 0, 0);

$barWidth = 50;
$padding = 20;
$xStart = 50;
$yBase = $height - 50;

$maxValue = max($dataPoints);
$scale = ($maxValue > 0) ? ($yBase - 50) / $maxValue : 1;

foreach ($dataPoints as $i => $value) {
    $x1 = $xStart + ($i * ($barWidth + $padding));
    $y1 = $yBase - ($value * $scale);
    $x2 = $x1 + $barWidth;
    $y2 = $yBase;

    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $barColor);
    imagestring($image, 3, $x1 + 10, $yBase + 5, $labels[$i], $textColor);
}

imagestring($image, 4, 350, 10, "Column Totals Bar Graph", $textColor);
imagepng($image, $imagePath);
imagedestroy($image);

// **Step 2: Create Excel File & Embed Image**
$excel = new Spreadsheet();
$sheet = $excel->getActiveSheet();
$sheet->setTitle("Summary");

// Add headers
$sheet->setCellValue("A1", "Column");
$sheet->setCellValue("B1", "Total");
$sheet->setCellValue("C1", "Average");
$sheet->setCellValue("D1", "Filled Cells");

$row = 2;
foreach ($summaryData["totals"] as $colIndex => $total) {
    $sheet->setCellValue("A{$row}", Coordinate::stringFromColumnIndex($colIndex));
    $sheet->setCellValue("B{$row}", number_format($total, 2));
    $sheet->setCellValue("C{$row}", number_format($summaryData["averages"][$colIndex], 2));
    $sheet->setCellValue("D{$row}", $summaryData["counts"][$colIndex]);
    $row++;
}

// Insert Image in Excel
$drawing = new Drawing();
$drawing->setName("Summary Chart");
$drawing->setDescription("Bar Graph of Column Totals");
$drawing->setPath($imagePath);
$drawing->setHeight(300);
$drawing->setCoordinates("F2");
$drawing->setWorksheet($sheet);

// **Step 3: Export as Excel File**
$excelFile = "summary.xlsx";
$writer = new Xlsx($excel);
$writer->save($excelFile);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="summary.xlsx"');
readfile($excelFile);
unlink($excelFile);
unlink($imagePath);
exit;
?>
