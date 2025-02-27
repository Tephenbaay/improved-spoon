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

$message = "";
$selectedSheet = isset($_GET['sheet']) ? $_GET['sheet'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $targetDir = "uploads/";
    $existingFiles = glob($targetDir . "*.{xls,xlsx,csv}", GLOB_BRACE);
    foreach ($existingFiles as $existingFile) {
        unlink($existingFile);
    }
    $fileName = basename($_FILES["excel_file"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    $allowedTypes = ["xls", "xlsx", "csv"];
    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES["excel_file"]["tmp_name"], $targetFilePath)) {
            $conn->query("DELETE FROM uploaded_files");

            $stmt = $conn->prepare("INSERT INTO uploaded_files (file_name) VALUES (?)");
            $stmt->bind_param("s", $fileName);
            if ($stmt->execute()) {
                $message = "File uploaded and replaced successfully!";
                $_SESSION["last_uploaded_file"] = $fileName;
            } else {
                $message = "Error saving file to database.";
            }
            $stmt->close();
        } else {
            $message = "Error uploading file.";
        }
    } else {
        $message = "Invalid file format. Only Excel files are allowed.";
    }
}

$uploadedFiles = glob("uploads/*.{xls,xlsx,csv}", GLOB_BRACE);
$latestFile = !empty($uploadedFiles) ? end($uploadedFiles) : null;
$worksheet = null;
$sheetNames = [];

if ($latestFile) {
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="templates/download-removebg-preview.png">
    <style>
        .highlight-row { background-color: #f0e68c !important; }
    </style>
</head>
<body>
    <div class="container-fluid d-flex p-0">

        <div class="sidebar">
        <input type="text" id="searchInput" class="form-control mt-3" placeholder="Search...">
            <h6 class="text-left mt-3">Upload File</h6>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="file" name="excel_file" class="form-control mb-2" required>
                <button type="submit" class="btn btn-success w-100">Upload</button>
            </form>
            <h6 class = "mt-3">Latest Uploaded File: <?php echo basename($latestFile); ?></h6>
            <button id="addRow" class="btn btn-primary mb-2 mt-3">Add Row</button>
            <button id="addColumn" class="btn btn-primary mb-2">Add Column</button>
            <button id="saveChanges" class="btn btn-success w-100 mt-3">Save Changes</button>
            <?php if ($latestFile): ?>
                <a href="export.php" class="btn btn-warning w-100 mt-3">Export File</a>
            <?php endif; ?>
        </div>

        <div class="main-content w-100">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <a class="navbar-brand d-flex align-items-center" href="#">
                        <img src="templates/download-removebg-preview.png" class="logo" alt="Logo"> 
                        <span class="ms-auto">BicutanMed</span>
                    </a>
                    <?php if ($latestFile): ?>
                        <?php if ($latestFile && $worksheet): ?>
                        <form method="GET" class="mb-3">
                            <label for="sheetSelect" class="text-left">Select Sheet:</label>
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
        <a href="summary.php?sheet=<?php echo urlencode($selectedSheet); ?>" class="btn btn-success mt-2 ms-auto">View Summary</a> 
                    <div class="ms-auto">
                        <span class="navbar-text me-3">Welcome, <?php echo $_SESSION["username"]; ?>!</span>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </div>
                </div>
            </nav>

            <?php if (!empty($message)): ?>
                <div id="messageBox" class="alert alert-info mt-3"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($latestFile): ?>
                <div class="container mt-3">
                    <div class="table-responsive">
                        <table class="table table-bordered mt-3" id="excelTable">
                            <thead class="table-dark">
                                <tr id="tableHeader">
                                    <th>No.</th> 
                                        <?php
                                        $spreadsheet = IOFactory::load($latestFile);
                                        $sheetNames = $spreadsheet->getSheetNames();
                                        if (!$selectedSheet) {
                                            $selectedSheet = $sheetNames[0];
                                        }
                                        $worksheet = $spreadsheet->getSheetByName($selectedSheet);
                                        $highestColumn = $worksheet->getHighestColumn();
                                        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
                                        for ($i = 1; $i <= $highestColumnIndex; $i++) {
                                            echo "<th>" . Coordinate::stringFromColumnIndex($i) . "</th>";
                                        }
                                        ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $rows = $worksheet->toArray(null, true, true, true);
                                    $rowNumber = 1;
                                    foreach ($rows as $row) {
                                        echo "<tr class='table-row' data-row='$rowNumber'>";
                                        echo "<td class='row-header'>{$rowNumber}</td>"; // Added 'row-header' class
                                            for ($i = 1; $i <= $highestColumnIndex; $i++) {
                                                $colLetter = Coordinate::stringFromColumnIndex($i);
                                                echo "<td contenteditable='true'>" . ($row[$colLetter] ?? "") . "</td>"; 
                                            }
                                        echo "</tr>";
                                        $rowNumber++;
                                    }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
$(document).ready(function() {
    $("#searchInput").on("keyup", function() {
        let value = $(this).val().toLowerCase().trim();
        
        $("#excelTable tbody tr").each(function() {
            let rowText = $(this).find("td").text().toLowerCase().trim();
            $(this).toggle(rowText.indexOf(value) > -1);
        });
    });
});
$(document).ready(function () {
    $("#excelTable tbody").on("click", ".row-header", function () {
        $(this).parent().toggleClass("highlight-row");
    });

    $("#tableHeader th").on("click", function () {
        let columnIndex = $(this).index();
        let isHighlighted = $(this).hasClass("highlight-column");

        $("#tableHeader th").removeClass("highlight-column");
        $("#excelTable tbody tr td").removeClass("highlight-column");

        if (!isHighlighted) {
            $(this).addClass("highlight-column");
            $("#excelTable tbody tr").each(function () {
                $(this).children().eq(columnIndex).addClass("highlight-column");
            });
        }
    });
});
$("<style>")
    .prop("type", "text/css")
    .html(`
        .highlight-row { background-color: #f0e68c !important; } /* Light Yellow */
        .highlight-column { background-color: #add8e6 !important; } /* Light Blue */
    `)
    .appendTo("head");
$(document).ready(function() {
    $("#saveChanges").click(function() {
        let tableData = [];
        $("#excelTable tbody tr").each(function() {
            let rowData = [];
            $(this).find("td:not(:first)").each(function() {
                rowData.push($(this).text().trim());
            });
            tableData.push(rowData);
        });

        $.ajax({
            url: "save_excel.php",
            method: "POST",
            data: { excelData: JSON.stringify(tableData) },
            success: function(response) {
                alert(response);
                location.reload();
            },
            error: function() {
                alert("Failed to save changes.");
            }
        });
    });
});
setTimeout(function() {
        let messageBox = document.getElementById("messageBox");
        if (messageBox) {
            messageBox.style.transition = "opacity 0.5s";
            messageBox.style.opacity = "0"; 
            setTimeout(() => messageBox.remove(), 500); 
        }
    }, 4000); 

    $(document).ready(function() {
    $("#addRow").click(function() {
        let rowNumber = $("#excelTable tr").length + 1; 
        let newRow = `<tr><td class='row-number'>${rowNumber}</td>`; 
        $("#tableHeader th:not(:first)").each(function() { 
            let colLetter = $(this).text();
            newRow += `<td contenteditable='true' data-row='${rowNumber}' data-col='${colLetter}'></td>`;
        });
        newRow += "</tr>";
        $("#excelTable").append(newRow);
    });

    $("#addColumn").click(function() {
        let lastColumn = $("#tableHeader th:last").text();
        let nextColumn = String.fromCharCode(lastColumn.charCodeAt(0) + 1); 

        if (lastColumn === "Z") {
            alert("Currently, this script supports A-Z columns only. Extending beyond requires a better approach.");
            return;
        }
        $("#tableHeader").append(`<th>${nextColumn}</th>`); 
        $("#excelTable tr").each(function() {
            $(this).append(`<td contenteditable='true' data-col='${nextColumn}'></td>`);
        });
    });
});
</script>
</html>