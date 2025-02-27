<?php
session_start();
include("config.php");

$targetDir = "uploads/";
$uploadedFiles = glob($targetDir . "*.{xls,xlsx,csv}", GLOB_BRACE);
$latestFile = !empty($uploadedFiles) ? end($uploadedFiles) : null;

if ($latestFile && file_exists($latestFile)) {
    $fileName = basename($latestFile);
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=" . $fileName);
    header("Content-Length: " . filesize($latestFile));
    readfile($latestFile);
    exit;
} else {
    echo "No file available for download.";
}
?>
