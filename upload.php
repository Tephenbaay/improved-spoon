<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Excel File</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
    <h2 class="mb-4">Upload an Excel File</h2>
    <form action="process_upload.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <input type="file" name="excel_file" class="form-control" accept=".xls,.xlsx,.csv" required>
        </div>
        <button type="submit" name="upload" class="btn btn-primary">Upload</button>
    </form>
</body>
<script>
    document.querySelector("form").addEventListener("submit", function(event) {
        let fileInput = document.querySelector("input[type='file']");
        let fileName = fileInput.files[0].name;

        if (sessionStorage.getItem("lastUploaded") === fileName) {
            event.preventDefault();
            alert("This file is already uploaded. Please edit the existing file.");
        } else {
            sessionStorage.setItem("lastUploaded", fileName);
        }
    });
</script>

</html>
