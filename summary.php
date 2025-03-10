<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

include("config.php");
require "vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

function convertExcelTime($excelTime) {
    if (!is_numeric($excelTime)) return "00:00:00";
    $secondsInDay = 86400;
    $totalSeconds = round($excelTime * $secondsInDay);
    return gmdate("H:i:s", $totalSeconds);
}

$selectedSheet = $_GET['sheet'] ?? null;
$uploadedFiles = glob("uploads/*.{xls,xlsx,csv}", GLOB_BRACE);
$latestFile = !empty($uploadedFiles) ? end($uploadedFiles) : null;

$patientCensus = [];
$sheetNames = [];

if ($latestFile) {
    try {
        $spreadsheet = IOFactory::load($latestFile);
        $sheetNames = $spreadsheet->getSheetNames();

        try {
            $spreadsheet = IOFactory::load($latestFile);
            $sheetNames = $spreadsheet->getSheetNames();
            if (!$selectedSheet || !in_array($selectedSheet, $sheetNames)) {
                $selectedSheet = $sheetNames[0] ?? null; 
            }
            $worksheet = $selectedSheet ? $spreadsheet->getSheetByName($selectedSheet) : null;
        } catch (Exception $e) {
            $message = "Error loading Excel file: " . $e->getMessage();
        }

        if (in_array($selectedSheet, $sheetNames)) {
            $worksheet = $spreadsheet->getSheetByName($selectedSheet);
            if ($worksheet) {
                $highestRow = $worksheet->getHighestRow();

                for ($row = 3; $row <= $highestRow; $row++) {
                    $patientName = trim($worksheet->getCell("A" . $row)->getValue());
                    $philhealthAmount = trim($worksheet->getCell("F" . $row)->getValue()); 
                    $membershipType = strtolower(trim($worksheet->getCell("T" . $row)->getValue())); 
                    
                    $dateAdmitted = $worksheet->getCell("K" . $row)->getValue(); 
                    $timeAdmitted = trim($worksheet->getCell("L" . $row)->getValue());
                    $dateDischarged = $worksheet->getCell("M" . $row)->getValue();
                    $timeDischarged = trim($worksheet->getCell("N" . $row)->getValue());
                
                    if (!$patientName || !$dateAdmitted) continue;

                    if (is_numeric($dateAdmitted)) {
                        $dateAdmitted = Date::excelToDateTimeObject($dateAdmitted)->format("Y-m-d");
                    }
                    if ($dateDischarged && is_numeric($dateDischarged)) {
                        $dateDischarged = Date::excelToDateTimeObject($dateDischarged)->format("Y-m-d");
                    }
                
                    $timeAdmitted = convertExcelTime($timeAdmitted);
                    $timeDischarged = convertExcelTime($timeDischarged);
                
                    $admitTimestamp = strtotime("$dateAdmitted $timeAdmitted");
                    $dischargeTimestamp = ($dateDischarged) ? strtotime("$dateDischarged $timeDischarged") : null;

                    $isNHIP = (is_numeric($philhealthAmount) && floatval($philhealthAmount) > 0);
                    $isNNHIP = ($membershipType === "non phic");

                    if (!$dischargeTimestamp || $dischargeTimestamp < $admitTimestamp) {
                        $dischargeTimestamp = $admitTimestamp;
                    }

                    $startDay = strtotime(date("Y-m-d", strtotime("+1 day", $admitTimestamp)) . " 00:00:00");
                    $endDay = strtotime(date("Y-m-d", $dischargeTimestamp) . " 00:00:00");

                    $daysCounted = [];

                    while ($startDay <= $endDay) {
                        $dayNumber = (int)date("j", $startDay);
                        if (!in_array($dayNumber, $daysCounted)) {
                            $daysCounted[] = $dayNumber;
                        }
                        $startDay = strtotime("+1 day", $startDay);
                    }

                    foreach ($daysCounted as $index => $dayNumber) {
                        if (!isset($patientCensus[$index + 1])) {
                            $patientCensus[$index + 1] = [
                                "No" => $index + 1, 
                                "NHIP" => 0, 
                                "NNHIP" => 0, 
                                "Total" => 0
                            ];
                        }

                        if ($isNHIP) {
                            $patientCensus[$index + 1]["NHIP"] += 1;
                        } elseif ($isNNHIP) {
                            $patientCensus[$index + 1]["NNHIP"] += 1;
                        }

                        $patientCensus[$index + 1]["Total"] = $patientCensus[$index + 1]["NHIP"] + $patientCensus[$index + 1]["NNHIP"];
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
        .content-wrapper {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .table-container {
            flex: 1;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ddd;
        }

        .table-container {
            width: 60%; 
            height: 400px; 
            overflow-y: auto; 
            border: 1px solid #ccc;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container thead {
            position: sticky;
            top: 0;
            background: #ffffff;
            z-index: 2;
        }

        .graph-container {
            width: 40%; 
            height: 400px; 
            padding-left: 20px;
        }

        .graph-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .table-container th {
            background-color:rgb(110, 134, 160);
            color: white;
            text-align: center;
        }

        .chart-container {
            width: 50%;
            min-width: 400px;
            text-align: center;
        }

        #searchInput {
            margin-bottom: 10px;
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
            <a href="mmhr.php" class="btn btn-primary w-100 mt-3">MMHR</a>
        </div>

        <div class="main-content w-100">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <a class="navbar-brand d-flex align-items-center" href="#">
                        <img src="templates/download-removebg-preview.png" class="logo" alt="Logo"> 
                        <span class="ms-2">BicutanMed</span>
                    </a>
                    <?php if (!empty($latestFile) && isset($worksheet)): ?>
                        <form method="GET" class="mb-3 mt-2">
                            <label for="sheetSelect" class="text-white">Select Sheet:</label>
                            <select name="sheet" id="sheetSelect" class="form-select d-inline-block w-auto ms-2" onchange="this.form.submit()">
                                <?php foreach ($sheetNames as $sheet): ?>
                                    <option value="<?php echo $sheet; ?>" <?php echo $sheet === $selectedSheet ? 'selected' : ''; ?>>
                                        <?php echo $sheet; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-success ms-2 mt-2 mb-3">Back to Dashboard</a>
                    <div class="ms-auto">
                        <span class="navbar-text me-3">Welcome, <?php echo $_SESSION["username"]; ?>!</span>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </div>
                </div>
            </nav>

            <div class="container mt-3">
    <?php if ($selectedSheet): ?>
        <h3>Summary for Sheet: <?php echo htmlspecialchars($selectedSheet); ?></h3>
        <div class="d-flex">
            <div class="table-container">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>NHIP (PhilHealth)</th>
                            <th>NNHIP (Non-PhilHealth)</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                        $totalNHIP = 0;
                        $totalNNHIP = 0;
                        $totalOverall = 0;

                    foreach ($patientCensus as $row): 
                        $totalNHIP += $row["NHIP"];
                        $totalNNHIP += $row["NNHIP"];
                        $totalOverall += $row["Total"];
                    ?>
                        <tr>
                            <td><?= $row["No"]; ?></td>
                            <td><?= $row["NHIP"]; ?></td>
                            <td><?= $row["NNHIP"]; ?></td>
                            <td><?= $row["Total"]; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4" class="text-center fw-bold">*** NOTHING FOLLOWS ***</td>
                    </tr>

                    <tr class="fw-bold">
                        <td>Total</td>
                        <td><?= $totalNHIP; ?></td>
                        <td><?= $totalNNHIP; ?></td>
                        <td><?= $totalOverall; ?></td>
                    </tr>
                </tbody>

                </table>
            </div>
            <div class="graph-container">
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
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
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
                    let rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(value) > -1);
                });
            });
        });
    </script>
</body>
</html>
