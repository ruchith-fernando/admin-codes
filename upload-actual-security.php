<?php include 'connections/connection.php'; ?>
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Upload Actual Security CSV</h5>

            <div id="uploadMessage"></div>

            <form id="securityCsvForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="csv_file" class="form-label">Select CSV File</label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload File</button>
            </form>

            <div class="mt-4">
                <h6>Expected Columns:</h6>
                <ul>
                    <li><code>branch_code</code></li>
                    <li><code>Branch</code></li>
                    <li><code>no of Shift</code></li>
                    <li><code>Total Amount - Remove the 1000 Seperator</code></li>
                    <li><code>Month</code> (e.g., April 2025)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('securityCsvForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = document.getElementById('securityCsvForm');
    const formData = new FormData(form);

    fetch('process-actual-security-upload.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('uploadMessage').innerHTML = `<div class="alert alert-${data.status}">${data.message}</div>`;
        form.reset();
    })
    .catch(err => {
        document.getElementById('uploadMessage').innerHTML = `<div class="alert alert-danger">Upload failed. Please try again.</div>`;
    });
});
</script>

<?php include 'footer.php'; ?>
