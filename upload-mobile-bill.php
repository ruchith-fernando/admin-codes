<?php
// upload-mobile-bill.php
session_start();
include 'connections/connection.php';

$message = '';

// Helper: show PHP file upload error codes
function file_upload_error_message($error_code) {
    $errors = array(
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
    );
    return $errors[$error_code] ?? 'Unknown upload error.';
}

// Handle file upload (NO logic changes — only alert text/style adjusted)
if (isset($_POST['submit'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
        $filename = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($filename, "r")) !== FALSE) {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;

                // Skip empty rows
                if (count(array_filter($data)) == 0) continue;

                // Clean BOM from first cell
                if ($row == 1) {
                    $data[0] = preg_replace('/[\x{FEFF}\x{200B}\x{2060}]/u', '', $data[0]);
                    $data[0] = trim(mb_convert_encoding($data[0], 'UTF-8', 'UTF-8'));
                }

                // Assign values (unchanged)
                $MOBILE_NUMBER = mysqli_real_escape_string($conn, trim($data[0]));
                $PREVIOUS_DUE_AMOUNT = floatval(str_replace(',', '', $data[1]));
                $PAYMENTS = floatval(str_replace(',', '', $data[2]));
                $TOTAL_USAGE_CHARGES = floatval(str_replace(',', '', $data[3]));
                $IDD = floatval(str_replace(',', '', $data[4]));
                $ROAMING = floatval(str_replace(',', '', $data[5]));
                $VAS = floatval(str_replace(',', '', $data[6]));
                $DISCOUNTS = floatval(str_replace(',', '', $data[7]));
                $BALANCE_ADJUSTMENTS = floatval(str_replace(',', '', $data[8]));
                $COMMITMENT_CHARGES = floatval(str_replace(',', '', $data[9]));
                $LATE_PAYMENT_CHARGES = floatval(str_replace(',', '', $data[10]));
                $GOV_TAX = floatval(str_replace(',', '', $data[11]));
                $VAT = floatval(str_replace(',', '', $data[12]));
                $ADD_TO_BILL = floatval(str_replace(',', '', $data[13]));
                $CHARGES = floatval(str_replace(',', '', $data[14]));
                $TOTAL_AMOUNT_PAYABLE = floatval(str_replace(',', '', $data[15]));
                $Upload_date = mysqli_real_escape_string($conn, trim($data[16]));

                $sql = "INSERT INTO tbl_admin_mobile_bill_data (
                    MOBILE_Number,
                    PREVIOUS_DUE_AMOUNT,
                    PAYMENTS,
                    TOTAL_USAGE_CHARGES,
                    IDD,
                    ROAMING,
                    VALUE_ADDED_SERVICES,
                    DISCOUNTS_BILL_ADJUSTMENTS,
                    BALANCE_ADJUSTMENTS,
                    COMMITMENT_CHARGES,
                    LATE_PAYMENT_CHARGES,
                    GOVERNMENT_TAXES_AND_LEVIES,
                    VAT,
                    ADD_TO_BILL,
                    CHARGES_FOR_BILL_PERIOD,
                    TOTAL_AMOUNT_PAYABLE,
                    Update_date
                ) VALUES (
                    '$MOBILE_NUMBER',
                    $PREVIOUS_DUE_AMOUNT,
                    $PAYMENTS,
                    $TOTAL_USAGE_CHARGES,
                    $IDD,
                    $ROAMING,
                    $VAS,
                    $DISCOUNTS,
                    $BALANCE_ADJUSTMENTS,
                    $COMMITMENT_CHARGES,
                    $LATE_PAYMENT_CHARGES,
                    $GOV_TAX,
                    $VAT,
                    $ADD_TO_BILL,
                    $CHARGES,
                    $TOTAL_AMOUNT_PAYABLE,
                    '$Upload_date'
                )";

                if (!mysqli_query($conn, $sql)) {
                    echo "<div class='alert alert-danger fw-bold'>Row $row: " . htmlspecialchars(mysqli_error($conn)) . "</div>";
                }
            }
            fclose($handle);
            $message = "<div class='alert alert-success fw-bold'>✅ File uploaded and data imported successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger fw-bold'>❌ Error opening the uploaded file.</div>";
        }
    } else {
        $error_message = file_upload_error_message($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        $message = "<div class='alert alert-danger fw-bold'>❌ File upload error: " . htmlspecialchars($error_message) . "</div>";
    }
}
?>


  <!-- Layout styles (copied from your working CDMA Upload layout) -->
  <style>
    #globalLoader{position:fixed;inset:0;background:rgba(255,255,255,.9);display:none;align-items:center;justify-content:center;z-index:9999}
    .loader-inner.line-scale>div{height:72px;width:10.8px;margin:3.6px;display:inline-block;animation:scaleStretchDelay 1.2s infinite ease-in-out}
    .loader-inner.line-scale>div:nth-child(odd){background:#0070C0}.loader-inner.line-scale>div:nth-child(even){background:#E60028}
    .loader-inner.line-scale>div:nth-child(1){animation-delay:-1.2s}.loader-inner.line-scale>div:nth-child(2){animation-delay:-1.1s}
    .loader-inner.line-scale>div:nth-child(3){animation-delay:-1.0s}.loader-inner.line-scale>div:nth-child(4){animation-delay:-0.9s}
    .loader-inner.line-scale>div:nth-child(5){animation-delay:-0.8s}
    @keyframes scaleStretchDelay{0%,40%,100%{transform:scaleY(.4)}20%{transform:scaleY(1)}}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
    .content.font-size{padding:20px}.container-fluid{max-width:1100px;margin:0 auto}
    .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
    .card h5{margin:0 0 16px;color:#0d6efd}.mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem}
    .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
    .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
    .btn-success{background:#198754;color:#fff}.btn-success:disabled{opacity:.6;cursor:not-allowed}
    .text-danger{color:#dc3545}.text-success{color:#198754}.fw-bold{font-weight:700}
    .mt-2{margin-top:.5rem}.mt-4{margin-top:1.5rem}
    .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:12px;background:#fafafa}
    .center{display:flex;justify-content:center}
  </style>
</head>
<body class="bg-light">

<div id="globalLoader">
  <div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div>
</div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary"><h5>Upload Dialog Bill PDF</h5>

      <?php if (!empty($message)): ?>
        <div id="uploadResult" class="result-block">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
          <label for="csv_file" class="form-label">Select CSV File</label>
          <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
          <div class="mt-2" style="font-size:.9rem;color:#555">
            Choose the monthly CSV export. Month is taken from your CSV’s <b>Update_date</b> column.
          </div>
        </div>

        <button type="submit" name="submit" class="btn btn-success">Upload &amp; Process</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
