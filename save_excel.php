<?php
session_start();
include("config.php"); // Ensure database connection is included
require "vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["excelData"])) {
        echo "No data received!";
        exit;
    }

    $excelData = json_decode($_POST["excelData"], true);

    $query = "SELECT file_name FROM uploaded_files ORDER BY upload_date DESC LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fileName = $row["file_name"];
        $filePath = "uploads/" . $fileName;

        if (!file_exists($filePath)) {
            echo "Error: Uploaded file not found!";
            exit;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($excelData as $rowIndex => $row) {
                foreach ($row as $colIndex => $cellValue) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
                    $sheet->setCellValue($colLetter . ($rowIndex + 1), $cellValue);
                }
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath);
            echo "Changes saved successfully!";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "No uploaded files found!";
    }
}
?>
