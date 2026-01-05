<?php
// submit-electricity-entry.php
require_once 'connections/connection.php';
session_start();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_initial'])) {

    // Require month
    $month = isset($_POST['month_applicable']) ? trim($_POST['month_applicable']) : '';
    if ($month === '') {
        echo '<div class="alert alert-danger">Please select Month Applicable.</div>';
        exit;
    }

    $stmt = $conn->prepare("REPLACE INTO tbl_admin_actual_electricity 
        (branch_code, branch, month_applicable, actual_units, total_amount, account_no, bank_paid_to, bill_from_date, bill_to_date, number_of_days) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $entries_saved = false;

    foreach ($_POST['branch_code'] as $i => $code) {
        $code           = trim($code);
        $branch         = trim($_POST['branch'][$i] ?? '');
        $units          = trim($_POST['actual_units'][$i] ?? '');
        $amount         = trim($_POST['actual_amount'][$i] ?? '');
        $account_no     = trim($_POST['account_no'][$i] ?? '');
        $bank_paid_to   = trim($_POST['bank_paid_to'][$i] ?? '');
        $bill_from_date = trim($_POST['bill_from_date'][$i] ?? '');
        $bill_to_date   = trim($_POST['bill_to_date'][$i] ?? '');
        $number_of_days = trim($_POST['number_of_days'][$i] ?? '');

        // consider row "attempted" if at least 2 fields typed
        $filled_fields = array_filter([$code, $branch, $units, $amount, $bill_from_date, $bill_to_date]);

        if (count($filled_fields) >= 2) {
            $missing = [];
            if (!$code)           $missing[] = "Branch Code";
            if (!$branch)         $missing[] = "Branch Name";
            if (!$units)          $missing[] = "Units";
            if (!$amount)         $missing[] = "Total Amount";
            if (!$bill_from_date) $missing[] = "Bill From Date";
            if (!$bill_to_date)   $missing[] = "Bill To Date";

            if (count($missing)) {
                $errors[] = "Row " . ($i + 1) . ": Missing - " . implode(', ', $missing);
                continue;
            }

            $stmt->bind_param(
                "ssssssssss",
                $code, $branch, $month, $units, $amount, $account_no, $bank_paid_to,
                $bill_from_date, $bill_to_date, $number_of_days
            );
            $stmt->execute();
            $entries_saved = true;
        }
    }

    if ($entries_saved && empty($errors)) {
        echo '<div class="alert alert-success">Records saved successfully!</div>';
    } else {
        echo '<div class="alert alert-warning">Some entries had issues. Please correct them below.</div>';
        if (!empty($errors)) {
            echo '<div class="alert alert-danger"><ul>';
            foreach ($errors as $err) {
                echo '<li>' . htmlspecialchars($err) . '</li>';
            }
            echo '</ul></div>';
        }
    }
} else {
    echo '<div class="alert alert-danger">Invalid request.</div>';
}
