<?php
session_start();
include("config.php");
require "vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_POST["upload"])) {
    $targetDir = "uploads/";
    $fileName = basename($_FILES["excel_file"]["name"]);
    $targetFilePath = $targetDir . $fileName;

    try {
        if (file_exists($targetFilePath)) {

            $spreadsheet = IOFactory::load($targetFilePath);
            $sheet = $spreadsheet->getActiveSheet();

            $uploadedSpreadsheet = IOFactory::load($_FILES["excel_file"]["tmp_name"]);
            $uploadedSheet = $uploadedSpreadsheet->getActiveSheet();

            $changesMade = false; 

            foreach ($uploadedSheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $cellCoord = $cell->getCoordinate();
                    $existingValue = $sheet->getCell($cellCoord)->getValue();
                    $newValue = $cell->getValue();

                    if ($newValue !== null && $newValue !== $existingValue) {
                        $sheet->setCellValue($cellCoord, $newValue);
                        $changesMade = true;
                    }
                }
            }

            if ($changesMade) {
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($targetFilePath);
                echo "File updated successfully!";
            } else {
                echo "No changes detected in the uploaded file.";
            }
        } else {

            if (move_uploaded_file($_FILES["excel_file"]["tmp_name"], $targetFilePath)) {
                echo "File uploaded successfully!";
            } else {
                echo "Error uploading file.";
                exit;
            }
        }

        $_SESSION["last_uploaded_file"] = $fileName;
        $stmt = $conn->prepare("INSERT INTO uploaded_files (file_name, upload_date) VALUES (?, NOW()) 
                                ON DUPLICATE KEY UPDATE upload_date=NOW()");
        $stmt->bind_param("s", $fileName);
        $stmt->execute();
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
