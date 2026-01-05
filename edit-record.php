<?php
include 'connections/connection.php';

if (isset($_GET['file_ref'])) {
    $file_ref = $_GET['file_ref'];

    // Fetch the record from the database
    $sql = "SELECT * FROM tbl_admin_fixed_assets WHERE file_ref = '$file_ref'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        echo "Record not found.";
        exit;
    }
} else {
    echo "No record specified.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the form data
    $registration_date = $_POST['registration_date'];
    $vehicle_type = $_POST['vehicle_type'];
    $make = $_POST['make'];
    $model = $_POST['model'];
    $yom = $_POST['yom'];
    $cr_available = $_POST['cr_available'];
    $book_owner = $_POST['book_owner'];
    $division = $_POST['division'];
    $asset_condition = $_POST['asset_condition'];
    $assigned_user = $_POST['assigned_user'];
    $hris = $_POST['hris'];
    $nic = $_POST['nic'];
    $tp_no = $_POST['tp_no'];
    $agreement = $_POST['agreement'];
    $new_comments = $_POST['new_comments'];

    // Update the record in the database
    $update_sql = "UPDATE tbl_admin_fixed_assets SET 
        registration_date = '$registration_date',
        vehicle_type = '$vehicle_type',
        make = '$make',
        model = '$model',
        yom = '$yom',
        cr_available = '$cr_available',
        book_owner = '$book_owner',
        division = '$division',
        asset_condition = '$asset_condition',
        assigned_user = '$assigned_user',
        hris = '$hris',
        nic = '$nic',
        tp_no = '$tp_no',
        agreement = '$agreement',
        new_comments = '$new_comments'
        WHERE file_ref = '$file_ref'";

    if ($conn->query($update_sql) === TRUE) {
        echo "<script>
            alert('Record Updated Successfully');
            window.location.href = 'fixed-asset-report.php';
        </script>";
    } else {
        echo "Error updating record: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset Record</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
                font-family: 'Inter', sans-serif;
                font-size: 14px;
                background-color: #f4f7fa;
                margin: 0;
                padding: 20px;
            }

            h2 {
                font-size: 24px;
                color: #333;
                font-weight: bold;
                margin-bottom: 20px;
                text-align: center;
            }

            .form-container {
                max-width: 800px;
                margin: 0 auto;
                background-color: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            .form-group {
                margin-bottom: 15px;
                display: flex;
                align-items: center;
            }

            label {
                font-weight: 600;
                color: #333;
                width: 400px; /* Adjust label width */
            }

            input[type="text"], textarea {
                width: 100%;
                padding: 10px;
                font-size: 14px;
                border: 1px solid #ddd;
                border-radius: 5px;
                box-sizing: border-box;
                transition: border-color 0.3s;
                margin-left: 20px; /* Add margin to move the text boxes to the right */
            }

            input[type="text"]:focus, textarea:focus {
                border-color: #007bff;
                outline: none;
            }

            textarea {
                resize: vertical;
                min-height: 100px;
            }

            .submit-btn {
                display: inline-block;
                padding: 12px 30px;
                font-size: 16px;
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                transition: background-color 0.3s;
            }

            .submit-btn:hover {
                background-color: #0056b3;
            }

            .cancel-btn {
                display: inline-block;
                padding: 12px 30px;
                font-size: 16px;
                background-color: #f44336;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                transition: background-color 0.3s;
            }

            .cancel-btn:hover {
                background-color: #d32f2f;
            }

    </style>
</head>
<body>

    <h2>Edit Asset Record</h2>

    <div class="form-container">
        <form method="POST">
            <div class="form-group">
                <label for="registration_date">Registration Date:</label>
                <input type="text" name="registration_date" value="<?= $row['registration_date']; ?>">
            </div>

            <div class="form-group">
                <label for="vehicle_type">Vehicle Type:</label>
                <input type="text" name="vehicle_type" value="<?= $row['vehicle_type']; ?>">
            </div>

            <div class="form-group">
                <label for="make">Make:</label>
                <input type="text" name="make" value="<?= $row['make']; ?>">
            </div>

            <div class="form-group">
                <label for="model">Model:</label>
                <input type="text" name="model" value="<?= $row['model']; ?>">
            </div>

            <div class="form-group">
                <label for="yom">Year of Manufacture:</label>
                <input type="text" name="yom" value="<?= $row['yom']; ?>">
            </div>

            <div class="form-group">
                <label for="cr_available">CR Available:</label>
                <input type="text" name="cr_available" value="<?= $row['cr_available']; ?>">
            </div>

            <div class="form-group">
                <label for="book_owner">Book Owner:</label>
                <input type="text" name="book_owner" value="<?= $row['book_owner']; ?>">
            </div>

            <div class="form-group">
                <label for="division">Division:</label>
                <input type="text" name="division" value="<?= $row['division']; ?>">
            </div>

            <div class="form-group">
                <label for="asset_condition">Asset Condition:</label>
                <input type="text" name="asset_condition" value="<?= $row['asset_condition']; ?>">
            </div>

            <div class="form-group">
                <label for="assigned_user">Assigned User:</label>
                <input type="text" name="assigned_user" value="<?= $row['assigned_user']; ?>">
            </div>

            <div class="form-group">
                <label for="hris">HRIS:</label>
                <input type="text" name="hris" value="<?= $row['hris']; ?>">
            </div>

            <div class="form-group">
                <label for="nic">NIC:</label>
                <input type="text" name="nic" value="<?= $row['nic']; ?>">
            </div>

            <div class="form-group">
                <label for="tp_no">TP No:</label>
                <input type="text" name="tp_no" value="<?= $row['tp_no']; ?>">
            </div>

            <div class="form-group">
                <label for="agreement">Agreement:</label>
                <input type="text" name="agreement" value="<?= $row['agreement']; ?>">
            </div>

            <div class="form-group">
                <label for="new_comments">New Comments:</label>
                <textarea name="new_comments"><?= $row['new_comments']; ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="submit-btn">Update Record</button>
                <a href="fixed-asset-report.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>

</body>
</html>
