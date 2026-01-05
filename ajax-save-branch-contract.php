<?php
include 'connections/connection.php';

function clean($val, $conn) {
    return mysqli_real_escape_string($conn, trim(str_replace(',', '', $val)));
}

if ($_POST) {
    $id = $_POST['id'] ?? '';
    $action = $_POST['action'] ?? '';
    $fields = [
        'branch_number', 'branch_name', 'lease_agreement_number', 'contract_period',
        'start_date', 'end_date', 'total_rent', 'increase_of_rent',
        'advance_payment_key_money', 'monthly_rental_notes', 'floor_area',
        'repairs_by_cdb', 'deviations_within_contract'
    ];

    $values = [];
    foreach ($fields as $field) {
        $values[$field] = clean($_POST[$field] ?? '', $conn);
    }

    if ($action === 'update' && !empty($id)) {
        $update = "";
        foreach ($values as $key => $val) {
            $update .= "$key = '$val', ";
        }
        $update = rtrim($update, ', ');
        $sql = "UPDATE tbl_admin_branch_contracts SET $update WHERE id = $id";
    } else {
        $columns = implode(",", array_keys($values));
        $escaped_values = "'" . implode("','", $values) . "'";
        $sql = "INSERT INTO tbl_admin_branch_contracts ($columns) VALUES ($escaped_values)";
    }

    if (mysqli_query($conn, $sql)) {
        echo "✅ Data saved successfully.";
    } else {
        echo "❌ Error: " . mysqli_error($conn);
    }
}
?>