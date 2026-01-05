<?php
// ajax-save-postage.php
include 'connections/connection.php';

header('Content-Type: application/json');

function cleanNumber($val) {
    return floatval(str_replace(',', '', $val));
}

function generateSerialNumber() {
    $prefix = "POST-" . date("Ymd-His") . "-";
    $random = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    return $prefix . $random;
}

$response = ['success' => false, 'message' => 'Unknown error'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entry_date = $_POST['entry_date'] ?? '';
    $department = $_POST['department'] ?? '';
    $colombo = cleanNumber($_POST['where_to_colombo'] ?? '0');
    $outstation = cleanNumber($_POST['where_to_outstation'] ?? '0');
    $open_balance = cleanNumber($_POST['open_balance'] ?? '0');
    $end_balance = cleanNumber($_POST['end_balance'] ?? '0');
    $stamp_values = $_POST['stamp_value'] ?? [];
    $stamp_quantities = $_POST['stamp_quantity'] ?? [];
    $total = $colombo + $outstation;
    $serial_number = generateSerialNumber();

    if (!$entry_date || !$department) {
        $response['message'] = "Please fill in all required fields.";
    } elseif (!is_numeric($colombo) || !is_numeric($outstation) || !is_numeric($end_balance)) {
        $response['message'] = "Colombo, Outstation, and End Balance must be valid numbers.";
    } elseif ($end_balance < 0) {
        $response['message'] = "End balance cannot be negative.";
    } elseif (empty($stamp_values) || !is_array($stamp_values)) {
        $response['message'] = "Please enter at least one valid stamp entry.";
    } else {
        $validStampFound = false;
        foreach ($stamp_values as $index => $value) {
            $val = cleanNumber($value);
            $qty = (int)($stamp_quantities[$index] ?? 0);
            if (is_numeric($val) && $qty > 0) {
                $validStampFound = true;
                break;
            }
        }

        if (!$validStampFound) {
            $response['message'] = "At least one valid stamp row with value and quantity is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO tbl_admin_actual_postage_stamps (entry_date, department, where_to_colombo, where_to_outstation, total, open_balance, end_balance, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdddds", $entry_date, $department, $colombo, $outstation, $total, $open_balance, $end_balance, $serial_number);

            if ($stmt->execute()) {
                $postage_id = $stmt->insert_id;
                $stmt2 = $conn->prepare("INSERT INTO tbl_admin_postage_stamps_breakdown (postage_id, stamp_value, quantity) VALUES (?, ?, ?)");

                foreach ($stamp_values as $index => $value) {
                    $val = cleanNumber($value);
                    $qty = (int)($stamp_quantities[$index] ?? 0);
                    if (is_numeric($val) && $qty > 0) {
                        $stmt2->bind_param("idi", $postage_id, $val, $qty);
                        $stmt2->execute();
                    }
                }

                $response['success'] = true;
                $response['message'] = "Postage record saved successfully.";
            } else {
                $response['message'] = "Failed to save record: {$conn->error}";
            }
        }
    }
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
