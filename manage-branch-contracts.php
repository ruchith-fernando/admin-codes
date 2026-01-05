<!-- manage-branch-contracts.php -->

<?php

include 'connections/connection.php';



$editing = false;

$message = "";



// Handle form submission

if (isset($_POST['submit'])) {

    $id = $_POST['id'] ?? '';

    $fields = [

        'branch_number', 'branch_name', 'lease_agreement_number', 'contract_period',

        'start_date', 'end_date', 'total_rent', 'increase_of_rent',

        'advance_payment_key_money', 'monthly_rental_notes', 'floor_area',

        'repairs_by_cdb', 'deviations_within_contract'

    ];



    $errors = [];



    $required = ['branch_number', 'branch_name', 'lease_agreement_number', 'start_date', 'end_date'];

    foreach ($required as $field) {

        if (empty(trim($_POST[$field] ?? ''))) {

            $errors[] = "$field is required.";

        }

    }



    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {

        $start = strtotime($_POST['start_date']);

        $end = strtotime($_POST['end_date']);

        if ($start > $end) {

            $errors[] = "End Date must be after or equal to Start Date.";

        }

    }



    if (!empty($_POST['advance_payment_key_money']) && !preg_match('/^[0-9,]+$/', $_POST['advance_payment_key_money'])) {

        $errors[] = "Advance Payment / Key Money must be numeric.";

    }



    if (!empty($_POST['total_rent']) && !preg_match('/^[0-9,]+$/', $_POST['total_rent'])) {

        $errors[] = "Total Rent must be numeric.";

    }



    if (count($errors) === 0) {

        $values = [];

        foreach ($fields as $field) {

            $raw = $_POST[$field] ?? '';

            if (in_array($field, ['advance_payment_key_money', 'total_rent'])) {

                $raw = str_replace(',', '', $raw);

            }

            $values[$field] = mysqli_real_escape_string($conn, trim($raw));

        }



        if (!empty($id)) {

            $update = "";

            foreach ($values as $key => $val) {

                $update .= "$key = '$val', ";

            }

            $update = rtrim($update, ', ');

            $sql = "UPDATE tbl_admin_branch_contracts SET $update WHERE id = $id";

            $editing = true;

        } else {

            $columns = implode(",", array_keys($values));

            $escaped_values = "'" . implode("','", $values) . "'";

            $sql = "INSERT INTO tbl_admin_branch_contracts ($columns) VALUES ($escaped_values)";

        }



        if (mysqli_query($conn, $sql)) {

            $message = "<div class='alert alert-success'>✅ Data saved successfully.</div>";

        } else {

            $message = "<div class='alert alert-danger'>❌ Error: " . mysqli_error($conn) . "</div>";

        }

    } else {

        $message = "<div class='alert alert-danger'><ul>";

        foreach ($errors as $err) {

            $message .= "<li>" . htmlspecialchars($err) . "</li>";

        }

        $message .= "</ul></div>";

    }

}



// Load data if editing

$edit_data = [];

if (isset($_GET['edit'])) {

    $id = intval($_GET['edit']);

    $result = mysqli_query($conn, "SELECT * FROM tbl_admin_branch_contracts WHERE id = $id LIMIT 1");

    if ($row = mysqli_fetch_assoc($result)) {

        $edit_data = $row;

        $editing = true;

    }

}

?>



<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <title>Manage Branch Contracts</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="styles.css">

</head>

<body class="bg-light">

<div class="sidebar" id="sidebar">

    <?php include 'side-menu.php'; ?>

</div>

<div class="content font-size" id="contentArea">

    <div class="container-fluid">

        <div class="card shadow bg-white rounded p-4">



            <h5 class="mb-4 text-primary"><?= $editing ? "Edit Branch Contract" : "Add New Branch Contract" ?></h5>

            <?php if ($message) echo $message; ?>



            <div class="card shadow-sm">

                <div class="card-body">

                    <form method="POST">

                        <?php if ($editing): ?>

                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">

                        <?php endif; ?>



                        <div class="row">

                            <?php

                            $fields = [

                                'branch_number' => 'Branch Number',

                                'branch_name' => 'Branch Name',

                                'lease_agreement_number' => 'Lease Agreement Number',

                                'contract_period' => 'Contract Period',

                                'start_date' => 'Start Date',

                                'end_date' => 'End Date',

                                'total_rent' => 'Total Rent',

                                'increase_of_rent' => 'Increase of Rent',

                                'advance_payment_key_money' => 'Advance Payment / Key Money',

                                'monthly_rental_notes' => 'Monthly Rental - Notes',

                                'floor_area' => 'Floor Area',

                                'repairs_by_cdb' => 'Repairs by CDB',

                                'deviations_within_contract' => 'Deviations within the Contract'

                            ];



                            foreach ($fields as $key => $label) {

                                $value = $edit_data[$key] ?? '';

                                $fieldHtml = '';



                                if (in_array($key, ['monthly_rental_notes', 'repairs_by_cdb', 'deviations_within_contract'])) {

                                    $fieldHtml = "<textarea name='$key' class='form-control' rows='2'>" . htmlspecialchars($value) . "</textarea>";

                                } elseif (in_array($key, ['start_date', 'end_date'])) {

                                    $fieldHtml = "<input type='date' name='$key' class='form-control' value='" . htmlspecialchars($value) . "'>";

                                } elseif ($key === 'advance_payment_key_money') {

                                    $fieldHtml = "<input type='text' name='$key' class='form-control thousand-format' value='" . htmlspecialchars($value) . "'>";

                                } else {

                                    $fieldHtml = "<input type='text' name='$key' class='form-control' value='" . htmlspecialchars($value) . "'>";

                                }



                                echo "

                                    <div class='col-md-6 mb-3'>

                                        <label class='form-label'>$label</label>

                                        $fieldHtml

                                    </div>

                                ";

                            }

                            ?>

                        </div>



                        <div class="d-grid">

                            <button type="submit" name="submit" class="btn btn-success">

                                <?= $editing ? "Update" : "Save" ?>

                            </button>

                        </div>



                        <?php if ($editing): ?>

                            <div class="mt-3 text-center">

                                <a href="manage-branch-contracts.php" class="btn btn-secondary">Cancel Edit</a>

                            </div>

                        <?php endif; ?>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>



<script>

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.thousand-format').forEach(function (el) {

        el.addEventListener('input', function () {

            let value = this.value.replace(/,/g, '');

            if (!isNaN(value) && value !== '') {

                this.value = Number(value).toLocaleString('en-US');

            }

        });

    });

});

</script>

</body>

</html>