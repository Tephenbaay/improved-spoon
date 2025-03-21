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
 "owwa" => 0, "sc" => 0, "pwd" => 0, "indigent" => 0, "pensioners" => 0,"non-nhip" => 0,"total_admission" => 0, 
 "nhip_discharges" => 0, "non_nhip_discharges" => 0, "non_nhip_lohs" => 0]);

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
        
        if (in_array("JANUARY", $sheetNames)) {
            $monthlySheet = $spreadsheet->getSheetByName("JANUARY");
            foreach ($monthlySheet->getRowIterator(3) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                if (count($rowData) < 13) continue;

                $admissionDate = isset($rowData[2]) && is_numeric($rowData[2]) 
                    ? Date::excelToDateTimeObject($rowData[2])->format("j") 
                    : null;
                
                $memberCategory = $rowData[12] ?? "";

                if ($admissionDate && is_numeric($admissionDate) && $admissionDate >= 1 && $admissionDate <= 31) {
                    if (stripos($memberCategory, "Formal-Government") !== false) {
                        $summaryData[$admissionDate]["govt"]++;
                    } elseif (stripos($memberCategory, "Formal-Private") !== false) {
                        $summaryData[$admissionDate]["private"]++;
                    } elseif (stripos($memberCategory, "Self earning Individual") !== false || stripos($memberCategory, "Informal Sector") !== false
                    || stripos($memberCategory, "Indirect Contributor") !== false) {
                        $summaryData[$admissionDate]["self_employed"]++;
                    } elseif (stripos($memberCategory, "ofw") !== false) {
                        $summaryData[$admissionDate]["ofw"]++;
                    } elseif (stripos($memberCategory, "migrant worker") !== false) {
                        $summaryData[$admissionDate]["owwa"]++;
                    } elseif (stripos($memberCategory, "senior citizen") !== false || stripos($memberCategory, "lifetime member") !== false) {
                        $summaryData[$admissionDate]["sc"]++;
                    } elseif (stripos($memberCategory, "pwd") !== false) {
                        $summaryData[$admissionDate]["pwd"]++;
                    } elseif (stripos($memberCategory, "indigent") !== false || stripos($memberCategory, "4PS/MCCT") !== false
                    || stripos($memberCategory, "SPONSORED- POS FINANCIALLY INCAPABLE") !== false) {
                        $summaryData[$admissionDate]["indigent"]++;
                    } elseif (stripos($memberCategory, "pensioners") !== false) {
                        $summaryData[$admissionDate]["pensioners"]++;
                    }   elseif (stripos($memberCategory, "non-nhip") !== false) {
                        $summaryData[$admissionDate]["non-nhip"]++;
                    }elseif (stripos($memberCategory, "non_nhip_lohs") !== false) {
                        $summaryData[$admissionDate]["non_nhip_lohs"]++;
                    }
                }
            }
        }

        if (in_array("admission (JAN)", $sheetNames)) {
            $admissionSheet = $spreadsheet->getSheetByName("admission (JAN)");
            foreach ($admissionSheet->getRowIterator(6) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);
        
                foreach ($cellIterator as $cell) {
                    if ($cell->getColumn() == 'H') {
                        $cellValue = $cell->getValue();
                        $admissionDate = is_numeric($cellValue)
                            ? Date::excelToDateTimeObject($cellValue)->format("j")
                            : (strtotime($cellValue) ? date("j", strtotime($cellValue)) : null);
        
                        if ($admissionDate && is_numeric($admissionDate) && $admissionDate >= 1 && $admissionDate <= 31) {
                            $summaryData[$admissionDate]["total_admission"]++;
                        }
                    }
                }
            }
        }    

        if (in_array(trim("DISCHARGE(BILLING)"), array_map('trim', $sheetNames))) {
            $dischargeSheet = $spreadsheet->getSheetByName(trim("DISCHARGE(BILLING)"));
            foreach ($dischargeSheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);
        
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[$cell->getColumn()] = $cell->getValue();

                if (empty(array_filter($rowData))) continue; // Skip empty rows
                if (!isset($rowData['A']) || count($rowData) < 10) continue; // Ensure valid data
        
                $admitDate = isset($rowData['C']) && is_numeric($rowData['C'])
                    ? Date::excelToDateTimeObject($rowData['C'])
                    : (strtotime($rowData['C']) ? new DateTime($rowData['C']) : null);
        
                $admitTime = isset($rowData['D']) && strtotime($rowData['D'])
                    ? strtotime($rowData['D'])
                    : null;
        
                $dischargeDate = isset($rowData['E']) && is_numeric($rowData['E'])
                    ? Date::excelToDateTimeObject($rowData['E'])
                    : (strtotime($rowData['E']) ? new DateTime($rowData['E']) : null);
        
                $dischargeTime = isset($rowData['F']) && strtotime($rowData['F'])
                    ? strtotime($rowData['F'])
                    : null;
        
                $membershipType = $rowData['G'] ?? "";  // Column T corresponds to Membership Type
        
                if ($admitDate && $dischargeDate) {
                    $admitTimestamp = $admitDate->getTimestamp();
                    if ($admitTime) {
                        $admitTimestamp += $admitTime;
                    }
        
                    $dischargeTimestamp = $dischargeDate->getTimestamp();
                    if ($dischargeTime) {
                        $dischargeTimestamp += $dischargeTime;
                    }
        
                    $dischargeDay = (date("Y-m-d", $admitTimestamp) == date("Y-m-d", $dischargeTimestamp))
                        ? $dischargeDate->format("j")
                        : date("j", strtotime("+1 day", $admitTimestamp));
        
                    if (!isset($summaryData[$dischargeDay])) {
                        $summaryData[$dischargeDay] = [
                            "non_nhip_discharges" => 0,
                            "nhip_discharges" => 0
                        ];
                    }
        
                    $membershipType = trim($membershipType);
        
                    if (preg_match('/\bNON\s?PHIC\b/i', $membershipType)) {
                        $summaryData[$dischargeDay]["non_nhip_discharges"]++;
                    } else {
                        $summaryData[$dischargeDay]["nhip_discharges"]++;
                    }
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
            <thead class="table-header">
                <tr>
                    <th colspan="1" style="color: white; background-color:black; width:50px;">1</th> 
                    <th colspan="2" style="color: white; background-color:black;">2</th> 
                    <th colspan="5" style="color: white; background-color:black;">3</th> 
                    <th colspan="1" style="color: white; background-color:black;">4</th> 
                    <th colspan="1" style="color: white; background-color:black;">5</th> 
                    <th colspan="2" style="color: white; background-color:black;">6</th> 
                    <th colspan="1" style="color: white; background-color:black;">7</th> 
                    <th colspan="2" style="color: white; background-color:black;">8</th> 
                    <th colspan="2" style="color: white; background-color:black;">9</th> 
                </tr>
                <tr>
                    <th rowspan="2" style="color: black; background-color:white;" id="date">DATE</th>
                    <th colspan="2">EMPLOYED</th>
                    <th colspan="5">INDIVIDUAL PAYING</th>
                    <th rowspan="2">INDIGENT</th>
                    <th rowspan="2">PENSIONERS</th>
                    <th rowspan="2" style="color: white; background-color:black;">NHIP</th>
                    <th rowspan="2" style="color: white; background-color:black;">NON-NHIP</th>
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
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["pensioners"] : 0; ?></td>
                            <td style="background-color: black; color:white;"><?php echo isset($summaryData[$i]) ? 
                                $summaryData[$i]["govt"] + $summaryData[$i]["private"] + $summaryData[$i]["self_employed"] +
                                $summaryData[$i]["ofw"] + $summaryData[$i]["owwa"] + $summaryData[$i]["sc"] +
                                $summaryData[$i]["pwd"] + $summaryData[$i]["indigent"] + $summaryData[$i]["pensioners"] : 0;
                            ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["non-nhip"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["total_admission"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["nhip_discharges"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["non_nhip_discharges"] : 0; ?></td>
                            <td><?php echo isset($summaryData[$i]) ? 
                                $summaryData[$i]["govt"] + $summaryData[$i]["private"] + $summaryData[$i]["self_employed"] +
                                $summaryData[$i]["ofw"] + $summaryData[$i]["owwa"] + $summaryData[$i]["sc"] +
                                $summaryData[$i]["pwd"] + $summaryData[$i]["indigent"] + $summaryData[$i]["pensioners"] : 0;
                            ?></td>
                            <td><?php echo isset($summaryData[$i]) ? $summaryData[$i]["non_nhip_lohs"] : 0; ?></td>
                            <?php for ($j = 1; $j <= 0; $j++): ?>
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
                    <th><?php echo array_sum(array_column($summaryData, "pensioners")); ?></th>
                    <th style="background-color: black; color:white;"><?php echo array_sum(array_map(function($data) {
                        return $data["govt"] + $data["private"] + $data["self_employed"] + 
                            $data["ofw"] + $data["owwa"] + $data["sc"] + 
                            $data["pwd"] + $data["indigent"] + $data["pensioners"];
                    }, $summaryData)); ?></th>
                    <th><?php echo array_sum(array_column($summaryData, "non-nhip")); ?></th>
                    <th><?php echo array_sum(array_column($summaryData, "total_admission")); ?></th>
                    <th><?php echo array_sum(array_column($summaryData, "nhip_discharges")); ?></th>
                    <th><?php echo array_sum(array_column($summaryData, "non_nhip_discharges")); ?></th>
                    <th><?php echo array_sum(array_map(function($data) {
                        return $data["govt"] + $data["private"] + $data["self_employed"] + 
                            $data["ofw"] + $data["owwa"] + $data["sc"] + 
                            $data["pwd"] + $data["indigent"] + $data["pensioners"];
                    }, $summaryData)); ?></th>
                    <th><?php echo array_sum(array_column($summaryData, "non_nhip_lohs")); ?></th>
                </tr>
                <tr>
                    <th></th>
                    <th colspan="10" style="background-color: black; color:white;"> <?php echo array_sum(array_map(function($data) {
                        return $data["govt"] + $data["private"] + $data["self_employed"] + 
                            $data["ofw"] + $data["owwa"] + $data["sc"] + 
                            $data["pwd"] + $data["indigent"] + $data["pensioners"];
                    }, $summaryData)); ?></th>
                    <th style="background-color: black; color:white;"><?php echo array_sum(array_column($summaryData, "non-nhip")); ?></th>
                    <th style="background-color: black; color:white;"><?php echo array_sum(array_column($summaryData, "total_admission")); ?></th>
                    <th colspan="2" style="background-color: black; color:white;"><?php echo array_sum(array_map(function($data) {
                        return $data["nhip_discharges"] + $data["non_nhip_discharges"];
                    }, $summaryData));?></th>
                    <th colspan="2" style="background-color: black; color:white;"><?php echo array_sum(array_map(function($data) {
                        return $data["govt"] + $data["private"] + $data["self_employed"] + 
                            $data["ofw"] + $data["owwa"] + $data["sc"] + 
                            $data["pwd"] + $data["indigent"] + $data["pensioners"] + $data["non-nhip"] + $data["non_nhip_lohs"];
                    }, $summaryData)); ?></th>
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
            totalRow.innerHTML = `<td colspan="100%" style="background-color:rgb(0, 0, 0); color: white; font-weight: bold; text-align: center;">TOTAL</td>`;
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
            <link rel="stylesheet" href="css/mmhr.css">
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