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

$uploadedFiles = glob("uploads/*.{xls,xlsx,csv}", GLOB_BRACE);
$latestFile = !empty($uploadedFiles) ? end($uploadedFiles) : null;
$summaryData = array_fill(1, 31, ["govt" => 0, "private" => 0, "self_employed" => 0, "ofw" => 0,
 "owwa" => 0, "sc" => 0, "pwd" => 0, "indigent" => 0]);

$selectedSheet = isset($_GET['sheet']) ? $_GET['sheet'] : null;
$sheetNames = [];

if ($latestFile) {
    try {
        $spreadsheet = IOFactory::load($latestFile);
        $sheetNames = $spreadsheet->getSheetNames();
        if (!$selectedSheet || !in_array($selectedSheet, $sheetNames)) {
            $selectedSheet = $sheetNames[0] ?? null; 
        }
        $worksheet = $selectedSheet ? $spreadsheet->getSheetByName($selectedSheet) : null;

        if ($worksheet) {
            foreach ($worksheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                if (count($rowData) < 13) continue;

                $admissionDate = isset($rowData[2]) ? Date::excelToDateTimeObject($rowData[2])->format("j") : null;
                $memberCategory = $rowData[12] ?? "";

                if ($admissionDate && is_numeric($admissionDate) && $admissionDate >= 1 && $admissionDate <= 31) {
                    if (stripos($memberCategory, "Formal-Gov") !== false) {
                        $summaryData[$admissionDate]["govt"]++;
                    } elseif (stripos($memberCategory, "Formal-Private") !== false) {
                        $summaryData[$admissionDate]["private"]++;
                    } elseif (stripos($memberCategory, "Self earning Individual") !== false) {
                        $summaryData[$admissionDate]["self_employed"]++;
                    } elseif (stripos($memberCategory, "senior citizen") !== false) {
                        $summaryData[$admissionDate]["sc"]++;
                    }elseif (stripos($memberCategory, "pwd") !== false) {
                        $summaryData[$admissionDate]["pwd"]++;
                    }elseif (stripos($memberCategory, "indigent") !== false) {
                        $summaryData[$admissionDate]["indigent"]++;
                    }
                }
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
    <title>MMHR Summary</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/mmhr.css">
    <link rel="icon" href="templates/download-removebg-preview.png">
</head>
<body>
    <div class="container-fluid d-flex p-0">
        <div class="sidebar">
            <h2 class="text-center">MMHR</h2>
            <input type="text" id="searchInput" class="form-control mt-3" placeholder="Search...">
            <a href="dashboard.php" class="btn btn-primary w-100 mt-3">Dashboard</a>
            <a href="summary.php" class="btn btn-primary w-100 mt-3">Summary</a>
            <button id="print-button" class ="btn btn-warning w-100 mt-3">Print MMHR Summary</button>
        </div>

    <div class="main-content w-100">
        <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <a class="navbar-brand d-flex align-items-center" href="#">
                        <img src="templates/download-removebg-preview.png" class="logo" alt="Logo"> 
                        <span class="ms-2">BicutanMed</span>
                    </a>
                    <?php if ($latestFile): ?>
                        <form method="GET" class="mb-3 mt-2">
                        <label for="sheetSelect" class="text-white">Select Sheet:</label>
                        <select name="sheet" id="sheetSelect" class="form-select d-inline-block w-auto ms-2" onchange="this.form.submit()">
                        <?php foreach ($sheetNames as $sheet): ?>
                            <option value="<?php echo $sheet; ?>" <?php echo ($sheet === $selectedSheet ? 'selected' : ''); ?>>
                        <?php echo $sheet; ?>
                            </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
                    <div class="ms-auto">
                        <span class="navbar-text me-3">Welcome, <?php echo $_SESSION["username"]; ?>!</span>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </div>
                </div>
            </nav>
        <div class="container mt-4">
        <h2 class="text-center mb-3">MMHR Summary Table</h2>
        <div class="table-responsive">
            <table class="table table-bordered" id="data-table">
            <thead>
                <tr>
                    <th colspan="1">1</th> 
                    <th colspan="2">2</th> 
                    <th colspan="5">3</th> 
                    <th colspan="1">4</th> 
                    <th colspan="1">5</th> 
                    <th colspan="2">6</th> 
                    <th colspan="1">7</th> 
                    <th colspan="2">8</th> 
                    <th colspan="2">9</th> 
                </tr>
                <tr>
                    <th rowspan="2">DATE</th>
                    <th colspan="2">EMPLOYED</th>
                    <th colspan="5">INDIVIDUAL PAYING</th>
                    <th rowspan="2">INDIGENT</th>
                    <th rowspan="2">PENSIONERS</th>
                    <th rowspan="2">NHIP</th>
                    <th rowspan="2">NON-NHIP</th>
                    <th rowspan="2">TOTAL ADMISSION</th>
                    <th colspan="2">TOTAL DISCHARGES</th>
                    <th colspan="2">ACCUMULATED PATIENTS LOHS</th>
                </tr>
                <tr>
                    <th>GOV'T</th>
                    <th>PRIVATE</th>
                    <th>SELF EMPLOYED</th>
                    <th>OFW</th>
                    <th>OWWA</th>
                    <th>SC</th>
                    <th>PWD</th>
                    <th>NHIP</th>
                    <th>NON-NHIP</th>
                    <th>NHIP</th>
                    <th>NON-NHIP</th>
                </tr>
            </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 31; $i++): ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["govt"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["private"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["self_employed"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["ofw"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["owwa"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["sc"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["pwd"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["indigent"] : 0; ?></td>
                            <?php for ($j = 1; $j <= 8; $j++): ?>
                                <td></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
        <tr id="total-row">
            <th>Total</th>
            <th><?php echo array_sum(array_column($summaryData, "govt")); ?></th> 
            <th><?php echo array_sum(array_column($summaryData, "private")); ?></th>
            <th><?php echo array_sum(array_column($summaryData, "self_employed")); ?></th>
            <th><?php echo array_sum(array_column($summaryData, "ofw")); ?></th>
            <th><?php echo array_sum(array_column($summaryData, "owwa")); ?></th>
            <th><?php echo array_sum(array_column($summaryData, "sc")); ?></th>
            <th><?php echo array_sum(array_column($summaryData, "pwd")); ?></th>
            <th><?php echo array_sum(array_column($summaryData, "indigent")); ?></th>
            <th>0</th>
            <th>0</th>
            <th>0</th>
            <th>0</th>
            <th>0</th>
            <th>0</th>
            <th>0</th>
            <th>0</th>
        </tr>
    </tfoot>
            </table>
        </div>
    </div>
    </div>
    </div>
<script>
    document.querySelectorAll("th").forEach(th => {
    if (th.textContent.trim() === "GOV'T" || th.textContent.trim() === "PRIVATE"
        || th.textContent.trim() === "SELF EMPLOYED" || th.textContent.trim() === "OFW"
        || th.textContent.trim() === "OWWA" || th.textContent.trim() === "SC" 
        || th.textContent.trim() === "PWD") {
        th.style.backgroundColor = "green";
        th.style.color = "black";
    }
});
    document.querySelectorAll("th").forEach(th => {
    if (th.textContent.trim() === "1" || th.textContent.trim() === "2"
        || th.textContent.trim() === "3" || th.textContent.trim() === "4"
        || th.textContent.trim() === "5" || th.textContent.trim() === "6" 
        || th.textContent.trim() === "7" || th.textContent.trim() === "8"
        || th.textContent.trim() === "9") {
        th.style.backgroundColor = "black";
        th.style.color = "white";
    }
});
    document.querySelectorAll("th").forEach(th => {
    if (th.textContent.trim() === "EMPLOYED" || th.textContent.trim() === "INDIVIDUAL PAYING"
        || th.textContent.trim() === "INDIGENT" || th.textContent.trim() === "PENSIONERS"
        || th.textContent.trim() === "TOTAL ADMISSION" || th.textContent.trim() === "TOTAL DISCHARGES" 
        || th.textContent.trim() === "ACCUMULATED PATIENTS LOHS" || th.textContent.trim() === "NHIP & NON-NHIP") {
        th.style.backgroundColor = "yellow";
        th.style.color = "black";
    }
});
    document.addEventListener("DOMContentLoaded", function () {
        let headers = document.querySelectorAll("thead tr:nth-child(3) th"); 

        headers[7].style.backgroundColor = "orange"; 
        headers[8].style.backgroundColor = "orange"; 

        headers[9].style.backgroundColor = "blue"; 
        headers[10].style.backgroundColor = "blue"; 

        headers[7].style.color = "black"; 
        headers[8].style.color = "black"; 
        headers[9].style.color = "black";
        headers[10].style.color = "black";
        headers[11].style.color = "black";
        headers[12].style.color = "black";
});
    document.getElementById("print-button").addEventListener("click", function () {
        let originalTable = document.getElementById("data-table"); 
        let clonedTable = originalTable.cloneNode(true); 
        let clonedRows = clonedTable.getElementsByTagName("tr");
        let totalRow = clonedTable.querySelector("#total-row"); 

    if (clonedRows.length === 31) {
        if (!totalRow) {
            totalRow = document.createElement("tr");
            totalRow.id = "total-row";
            totalRow.innerHTML = `<td colspan="100%" style="background-color: #007bff; color: white; font-weight: bold; text-align: center;">TOTAL</td>`;
            clonedTable.appendChild(totalRow);
        }
    } else if (totalRow) {
        totalRow.remove();
    }

    let printWindow = window.open('', '', 'width=1200,height=800');

    printWindow.document.write(`
        <html>
        <head>
            <title>MMHR Summary</title>
            <style>
                @media print {
                    @page { size: landscape; }
                    body { font-family: Arial, sans-serif; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid black; padding: 8px; text-align: left; }

                    th {
                        background-color: inherit !important; 
                        color: inherit !important; 
                    }

                    th:contains("GOV'T"), 
                    th:contains("PRIVATE"), 
                    th:contains("SELF EMPLOYED"), 
                    th:contains("OFW"), 
                    th:contains("OWWA"), 
                    th:contains("SC"), 
                    th:contains("PWD") {
                        background-color: green !important;
                        color: black !important;
                    }

                    th:contains("1"), 
                    th:contains("2"), 
                    th:contains("3"), 
                    th:contains("4"), 
                    th:contains("5"), 
                    th:contains("6"), 
                    th:contains("7"), 
                    th:contains("8"), 
                    th:contains("9") {
                        background-color: black !important;
                        color: white !important;
                    }

                    th:contains("EMPLOYED"), 
                    th:contains("INDIVIDUAL PAYING"), 
                    th:contains("INDIGENT"), 
                    th:contains("PENSIONERS"), 
                    th:contains("TOTAL ADMISSION"), 
                    th:contains("TOTAL DISCHARGES"), 
                    th:contains("ACCUMULATED PATIENTS LOHS"), 
                    th:contains("NHIP & NON-NHIP") {
                        background-color: yellow !important;
                        color: black !important;
                    }

                    thead tr:nth-child(3) th:nth-child(10), 
                    thead tr:nth-child(3) th:nth-child(11) {
                        background-color: orange !important;
                        color: black !important;
                    }
                    thead tr:nth-child(3) th:nth-child(12), 
                    thead tr:nth-child(3) th:nth-child(13) {
                        background-color: blue !important;
                        color: black !important;
                    }

                    #total-row {
                        background-color: #007bff !important;
                        color: white !important;
                        font-weight: bold;
                        text-align: center;
                        position: sticky;
                        bottom: 0;
                    }
                }
            </style>
        </head>
        <body>
            <h2>MMHR Summary Report</h2>
            ${clonedTable.outerHTML}
            <script>
                window.onload = function() { window.print(); window.close(); };
            <\/script>
        </body>
        </html>
    `);

    printWindow.document.close();
});
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