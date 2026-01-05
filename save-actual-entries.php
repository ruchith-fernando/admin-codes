<?php
// save-actual-entries.php
require_once 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("INSERT INTO tbl_admin_actual_security (branch_code, branch, month_applicable, actual_shifts, total_amount, provision) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($_POST['branch_code'] as $i => $code) {
        $branch = $_POST['branch'][$i];
        $month = $_POST['month_applicable'];
        $shifts = (int) $_POST['actual_shifts'][$i];
        $amount = (float) str_replace(',', '', $_POST['actual_amount'][$i]);
        $provision = $_POST['provision'][$i] ?? 'no';

        if (empty($code)) continue;
        if ($shifts < 1) continue;

        $stmt->bind_param("sssids", $code, $branch, $month, $shifts, $amount, $provision);
        $stmt->execute();
    }
    header("Location: security-old.php?month=" . urlencode($_POST['month_applicable']));
    exit;
}
?>
