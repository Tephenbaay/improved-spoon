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

            for ($row = 2; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellCoordinate = Coordinate::stringFromColumnIndex($col) . $row;
                    $cellValue = $worksheet->getCell($cellCoordinate)->getValue();
                    
                    if (is_numeric($cellValue)) {
                        $columnTotals[$col] += $cellValue;
                    }
                }
            }

            foreach ($columnTotals as $col => $total) {
                $columnAverages[$col] = $highestRow > 1 ? $total / ($highestRow - 1) : 0;
            }

            $summaryData = [
                "totals" => $columnTotals,
                "averages" => $columnAverages,
            ];
        }
    } catch (Exception $e) {
        die("Error loading Excel file: " . $e->getMessage());
    }
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="summary.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, ["Column", "Total", "Average"]);

if (!empty($summaryData)) {
    foreach ($summaryData["totals"] as $colIndex => $total) {
        fputcsv($output, [
            Coordinate::stringFromColumnIndex($colIndex),
            number_format($total, 2),
            number_format($summaryData["averages"][$colIndex], 2)
        ]);
    }
}

fclose($output);
exit;
?>
