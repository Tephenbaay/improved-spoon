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
$sheetNames = [];

if ($latestFile) {
    try {
        $spreadsheet = IOFactory::load($latestFile);
        $sheetNames = $spreadsheet->getSheetNames(); // Get all sheet names

        if ($selectedSheet && in_array($selectedSheet, $sheetNames)) {
            $worksheet = $spreadsheet->getSheetByName($selectedSheet);

            if ($worksheet) {
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

                $columnTotals = array_fill(1, $highestColumnIndex, 0);
                $columnAverages = array_fill(1, $highestColumnIndex, 0);

                for ($row = 2; $row <= $highestRow; $row++) { // Assuming row 1 is headers
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
        }
    } catch (Exception $e) {
        $message = "Error loading Excel file: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Summary</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="templates/download-removebg-preview.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        html, body {
            height: 100vh;
            margin: 0;
            overflow: hidden; 
        }
        .main-content {
            height: 100vh; 
            overflow-y: auto; 
            padding: 80px;
        }
        .chart-container {
            width: 100%;
            max-width: 800px;
            margin: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid d-flex p-0">
        <div class="sidebar">
            <h2 class="text-center">Summary</h2>
            <input type="text" id="searchInput" class="form-control mt-3" placeholder="Search...">
            <?php if ($selectedSheet && $latestFile): ?>
                <a href="export_summary.php?sheet=<?php echo urlencode($selectedSheet); ?>" class="btn btn-warning w-100 mt-3">Export Summary</a>
            <?php endif; ?>
        </div>

        <div class="main-content w-100">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="#">
                        <img src="templates/download-removebg-preview.png" class="logo" alt="Logo"> 
                        <span class="ms-2">BicutanMed</span>
                    </a>
                    <?php if ($latestFile): ?>
                        <?php if ($latestFile && $worksheet): ?>
                        <form method="GET" class="mb-3">
                            <label for="sheetSelect">Select Sheet:</label>
                            <select name="sheet" id="sheetSelect" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($sheetNames as $sheet): ?>
                                    <option value="<?php echo $sheet; ?>" <?php echo $sheet === $selectedSheet ? 'selected' : ''; ?>>
                                        <?php echo $sheet; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </select>
            </form>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-success ms-2 mt-2">Back to Dashboard</a>
                    <div class="ms-auto">
                        <span class="navbar-text me-3">Welcome, <?php echo $_SESSION["username"]; ?>!</span>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </div>
                </div>
            </nav>

            <div class="container mt-3">
    <?php if ($selectedSheet): ?>
        <h3>Summary for Sheet: <?php echo htmlspecialchars($selectedSheet); ?></h3>
        <?php if (!empty($summaryData)): ?>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered" id="excelTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Column</th>
                                <th>Total</th>
                                <th>Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryData["totals"] as $colIndex => $total): ?>
                                <tr>
                                    <td><?php echo Coordinate::stringFromColumnIndex($colIndex); ?></td>
                                    <td><?php echo number_format($total, 2); ?></td>
                                    <td><?php echo number_format($summaryData["averages"][$colIndex], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="col-md-6">
                    <h4>Graphs Overview</h4>
                    <canvas id="summaryChart"></canvas>
                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const ctx = document.getElementById('summaryChart').getContext('2d');

                    const labels = <?php echo json_encode(array_map(fn($colIndex) => Coordinate::stringFromColumnIndex($colIndex), array_keys($summaryData["totals"]))); ?>;
                    const totals = <?php echo json_encode(array_values($summaryData["totals"])); ?>;

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Total',
                                data: totals,
                                backgroundColor: 'rgba(80, 200, 120, 1)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                });
            </script>
        <?php else: ?>
            <p>No numerical data available to summarize.</p>
        <?php endif; ?>
    <?php else: ?>
        <p>Please select a sheet to view the summary.</p>
    <?php endif; ?>
</div>

        </div>
    </div>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $("#searchInput").on("keyup", function() {
        let value = $(this).val().toLowerCase().trim();
        
        $("#excelTable tbody tr").each(function() {
            let rowText = $(this).find("td").text().toLowerCase().trim();
            $(this).toggle(rowText.indexOf(value) > -1);
        });
    });
});
</script>
</html>