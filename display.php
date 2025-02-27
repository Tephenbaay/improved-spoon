<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_GET['file'])) {
    die("No file specified.");
}

$filePath = urldecode($_GET['file']);

if (!file_exists($filePath)) {
    die("File not found.");
}

$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray(null, true, true, true);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Data</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
    <h2 class="mb-4">Uploaded Excel Data</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <?php foreach ($data[1] as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 2; $i <= count($data); $i++): ?>
                <tr>
                    <?php foreach ($data[$i] as $cell): ?>
                        <td><?php echo htmlspecialchars($cell); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    <a href="upload.php" class="btn btn-secondary">Upload Another File</a>
</body>
</html>
