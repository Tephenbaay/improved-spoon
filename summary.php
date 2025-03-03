<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

include("config.php");
require "vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;

$selectedSheet = isset($_GET['sheet']) ? $_GET['sheet'] : null;
$uploadedFiles = glob("uploads/*.{xls,xlsx,csv}", GLOB_BRACE);
$latestFile = !empty($uploadedFiles) ? end($uploadedFiles) : null;
$patientCensus = array_fill(1, 31, ["Day" => 0, "NHIP" => 0, "NNHIP" => 0]);
$sheetNames = [];

if ($latestFile) {
    try {
        $spreadsheet = IOFactory::load($latestFile);
        $sheetNames = $spreadsheet->getSheetNames();

        if (in_array($selectedSheet, $sheetNames)) {
            $worksheet = $spreadsheet->getSheetByName($selectedSheet);
            if ($worksheet) {
                $highestRow = $worksheet->getHighestRow();

                for ($row = 2; $row <= $highestRow; $row++) { // Skip header row
                    $patientName = $worksheet->getCell("A" . $row)->getValue();
                    $dateAdmitted = $worksheet->getCell("B" . $row)->getValue();
                    $dateDischarged = $worksheet->getCell("C" . $row)->getValue();
                    $philhealthStatus = trim($worksheet->getCell("D" . $row)->getValue());

                    if (!$patientName || !$dateAdmitted) continue;

                    $admitTimestamp = strtotime($dateAdmitted);
                    $dischargeTimestamp = $dateDischarged ? strtotime($dateDischarged) : null;
                    
                    // Loop through each day of the current month
                    for ($day = 1; $day <= 31; $day++) {
                        $currentDate = date("Y-m-") . str_pad($day, 2, "0", STR_PAD_LEFT);
                        $midnightTimestamp = strtotime($currentDate . " 00:00:00");

                        // Check if the patient was present at the 12 AM cutoff
                        if ($admitTimestamp < $midnightTimestamp && (!$dischargeTimestamp || $dischargeTimestamp > $midnightTimestamp)) {
                            $patientCensus[$day]["Day"]++;
                            if (!empty($philhealthStatus)) {
                                $patientCensus[$day]["NHIP"]++;
                            } else {
                                $patientCensus[$day]["NNHIP"]++;
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $message = "Error processing the Excel file: " . $e->getMessage();
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
                    <div class="row">
                        <div class="col-md-6">
                            <h3>31-Day Patient Census for <?= date("F Y"); ?></h3>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Day</th>
                                        <th>NHIP (PhilHealth)</th>
                                        <th>NNHIP (Non-PhilHealth)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patientCensus as $day => $counts): ?>
                                        <tr>
                                            <td><?= $day; ?></td>
                                            <td><?= $counts["Day"]; ?></td>
                                            <td><?= $counts["NHIP"]; ?></td>
                                            <td><?= $counts["NNHIP"]; ?></td>
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

                            const labels = <?php echo json_encode(array_keys($patientCensus)); ?>;
                            const nhipData = <?php echo json_encode(array_column($patientCensus, "NHIP")); ?>;
                            const nnhipData = <?php echo json_encode(array_column($patientCensus, "NNHIP")); ?>;

                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [
                                        { label: 'NHIP', data: nhipData, backgroundColor: 'blue' },
                                        { label: 'NNHIP', data: nnhipData, backgroundColor: 'red' }
                                    ]
                                }
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
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