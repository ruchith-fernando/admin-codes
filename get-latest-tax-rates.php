<!-- get-latest-tax-rates.php -->

<?php
include 'connections/connection.php';

$sql = "SELECT sscl_percentage, vat_percentage FROM tbl_admin_vat_sscl_rates 
        ORDER BY effective_date DESC LIMIT 1";
$result = $conn->query($sql);

$response = ['sscl' => 0, 'vat' => 0];

if ($result && $row = $result->fetch_assoc()) {
    $response['sscl'] = $row['sscl_percentage'];
    $response['vat'] = $row['vat_percentage'];
}

header('Content-Type: application/json');
echo json_encode($response);
