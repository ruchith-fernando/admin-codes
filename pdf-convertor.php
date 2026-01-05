<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PDF to Word/Excel Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container">
        <h2 class="mb-4">Upload PDF to Convert</h2>
        <form action="convert.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Choose PDF</label>
                <input type="file" name="pdf_file" accept="application/pdf" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Output Format</label>
                <select name="output_format" class="form-select" required>
                    <option value="excel">Excel (.xlsx)</option>
                    <option value="word">Word (.docx)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Convert</button>
        </form>
    </div>
</body>
</html>
