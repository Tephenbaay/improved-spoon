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
for ($day = 1; $day <= 31; $day++) {
    $patientCensus[$day] = [
        "No" => $day, 
        "Day" => 0, 
        "NHIP" => 0, 
        "NNHIP" => 0 
    ];
}

$sheetNames = [];

if ($latestFile) {
    try {
        $spreadsheet = IOFactory::load($latestFile);
        $sheetNames = $spreadsheet->getSheetNames();

        if (in_array($selectedSheet, $sheetNames)) {
            $worksheet = $spreadsheet->getSheetByName($selectedSheet);
            if ($worksheet) {
                $highestRow = $worksheet->getHighestRow();

                for ($row = 3; $row <= $highestRow; $row++) { 
                    $patientName = trim($worksheet->getCell("A" . $row)->getValue());
                    $dateAdmitted = $worksheet->getCell("K" . $row)->getValue();
                    $timeAdmitted = trim($worksheet->getCell("L" . $row)->getValue());
                    $dateDischarged = $worksheet->getCell("M" . $row)->getValue();
                    $timeDischarged = trim($worksheet->getCell("N" . $row)->getValue());
                    $philhealthStatus = strtolower(trim($worksheet->getCell("F" . $row)->getValue()));

                    if (!$patientName || !$dateAdmitted || !$timeAdmitted) continue;

                    if (is_numeric($dateAdmitted)) {
                        // echo "🔍 Raw Excel Date for $patientName: $dateAdmitted\n";  // Debugging
                        $dateAdmitted = Date::excelToDateTimeObject($dateAdmitted)->format("Y-m-d");
                    }else {
                        // echo "❌ Invalid or Non-Numeric DateAdmitted for $patientName: $dateAdmitted\n";  // Debugging
                    }
                    if ($dateDischarged && is_numeric($dateDischarged)) {
                        $dateDischarged = Date::excelToDateTimeObject($dateDischarged)->format("Y-m-d");
                    }                                    

                    $timeAdmitted = convertExcelTime($timeAdmitted);
                    $timeDischarged = convertExcelTime($timeDischarged);

                    $admitTimestamp = strtotime("$dateAdmitted $timeAdmitted");
                    $dischargeTimestamp = ($dateDischarged) ? strtotime("$dateDischarged $timeDischarged") : null;

                    $isNHIP = (strcasecmp($philhealthStatus, "NHIP") == 0);

                    // echo "<pre>";
                        // echo "Patient: $patientName is counted on " . date("Y-m-d", $admitTimestamp) ."\n";
                        // echo "Admitted: " . date("Y-m-d H:i:s", $admitTimestamp) . "\n";
                        // echo "Discharged: " . ($dischargeTimestamp ? date("Y-m-d H:i:s", $dischargeTimestamp) : "Still Admitted") . "\n";
                        // echo "Counted under: " . ($isNHIP ? "NHIP" : "Non-NHIP") . "\n";
                        // echo "--------------------\n";
                    // echo "</pre>";
                    
                    $originalDateAdmitted = $dateAdmitted;  

                    for ($day = 1; $day <= 31; $day++) {
                        $dateAdmitted = $originalDateAdmitted;  
                    
                        if (!$dateAdmitted || trim($dateAdmitted) === "") {
                            // echo "⚠️ Skipping $patientName due to missing DateAdmitted!\n";
                            continue;
                        }
                    
                        // Convert if it's numeric (Excel timestamp)
                        if (is_numeric($dateAdmitted)) {
                            $dateAdmitted = Date::excelToDateTimeObject($dateAdmitted)->format("Y-m-d");
                        } else {
                            $dateAdmitted = trim($dateAdmitted);
                        }

                        $dateFormats = ["Y-m-d", "m/d/Y", "Y/m/d", "d-m-Y"];
                        $dateObj = false;
                        foreach ($dateFormats as $format) {
                            $dateObj = DateTime::createFromFormat($format, $dateAdmitted);
                            if ($dateObj) break;
                        }
                    
                        if (!$dateObj) {
                            // echo "⚠️ Error: Invalid date format for $patientName! Skipping...\n";
                            continue;
                        }

                        $dateObj->modify("+".($day - 1)." days");
                        $currentDate = $dateObj->format("Y-m-d");
                    
                        $dayStart = strtotime("$currentDate 00:00:00");
                        $dayEnd = strtotime("$currentDate 23:59:59");
                    
                        if ($admitTimestamp <= $dayEnd && (!$dischargeTimestamp || $dischargeTimestamp >= $dayStart)) {
                            if (!isset($patientCensus[$day])) {
                                $patientCensus[$day] = ["Day" => 0, "NHIP" => 0, "NNHIP" => 0];
                            }
                    
                            $patientCensus[$day]["Day"] += 1;
                    
                            if ($isNHIP) {
                                $patientCensus[$day]["NHIP"] += 1;
                            } else {
                                $patientCensus[$day]["NNHIP"] += 1;
                            }
                    
                            // echo "<pre>";
                            // echo "Day: $day | Date: $currentDate\n";
                            // echo "Patient: $patientName | NHIP: " . ($isNHIP ? "YES" : "NO") . "\n";
                            // echo "Current Day Count: " . $patientCensus[$day]["Day"] . "\n";
                            // echo "NHIP Count: " . $patientCensus[$day]["NHIP"] . "\n";
                            // echo "NNHIP Count: " . $patientCensus[$day]["NNHIP"] . "\n";
                            // echo "----------------------------------\n";
                            // echo "</pre>";
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
                                    <td><?php echo htmlspecialchars($counts["Day"]); ?></td>
                                    <td><?php echo htmlspecialchars($counts["NHIP"]); ?></td>
                                    <td><?php echo htmlspecialchars($counts["NNHIP"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <h4>Graphs Overview</h4>
                    <canvas id="summaryChart"></canvas>

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
</body>
</html>
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