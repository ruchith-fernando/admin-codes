<!DOCTYPE html>
<html>
<head>
    <title>Upload Branch CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 shadow">
        <h4 class="mb-3">Upload Branch CSV File</h4>
        <form action="process-branch-csv.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Select CSV File</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-success">Upload</button>
        </form>
    </div>
</div>
</body>
</html>
