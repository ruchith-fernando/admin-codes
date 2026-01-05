<?php

session_start();

include 'connections/connection.php';



// --- Capture HRIS

$hris = mysqli_real_escape_string($conn, $_POST['hris'] ?? '');

$row = null;



$employee_name = "";

$nic_no = "";

$designation = "";

$location = "";

$company_hierarchy = "";

$department_route = "";



if (!empty($hris)) {

    // Step 1: Get employee details

    $emp_query = "SELECT name_of_employee, nic_no, designation, location, company_hierarchy 

                  FROM tbl_admin_employee_details 

                  WHERE hris = '$hris' AND status = 'Active'";

    $emp_result = mysqli_query($conn, $emp_query);

    if ($emp_result && mysqli_num_rows($emp_result) > 0) {

        $emp_row = mysqli_fetch_assoc($emp_result);

        $employee_name = $emp_row['name_of_employee'];

        $nic_no = $emp_row['nic_no'];

        $designation = $emp_row['designation'];

        $location = $emp_row['location'];

        $company_hierarchy = $emp_row['company_hierarchy'];

    }



    // Step 2: Get department_route

    $dept_query = "SELECT department_route FROM tbl_admin_company_hierarchy 

                   WHERE company_hierarchy = '$company_hierarchy' LIMIT 1";

    $dept_result = mysqli_query($conn, $dept_query);



    if ($dept_result && mysqli_num_rows($dept_result) > 0) {

        $dept_row = mysqli_fetch_assoc($dept_result);

        $department_route = $dept_row['department_route'];

    }



    // Step 3: Get latest SIM/Mobile request

    $query = "SELECT * FROM tbl_admin_sim_request 

              WHERE hris = '$hris' 

              ORDER BY id DESC LIMIT 1";



    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {

        $row = mysqli_fetch_assoc($result);

    }

}



function getStepStatus($value) {

    if (!$value || $value == '') return 'pending';

    if (stripos($value, 'REJECTED') !== false) return 'rejected';

    return 'completed';

}



// Decide labels based on department_route

$recommendation_label = "Recommendation";

$approval_label = "Approval";



if (stripos($department_route, "Operations") !== false) {

    $recommendation_label = "Recommendation by Divisional Head";

    $approval_label = "Approval by AGM/DGM";

} elseif (stripos($department_route, "Branch Operations") !== false) {

    $recommendation_label = "Recommendation by Cluster Leader / BOIC";

    $approval_label = "Approval by Head of Branch Operations";

} elseif (stripos($department_route, "Marketing") !== false) {

    $recommendation_label = "Recommendation by ASM / RM";

    $approval_label = "Approval by DGM or AGM Sales";

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Check Your Request Progress</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="styles.css">

    <style>

        .progress-tracker {

            position: relative;

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-top: 50px;

            margin-bottom: 30px;

        }



        /* .progress-tracker::before {

            content: '';

            position: absolute;

            top: 34%;

            transform: translateY(-50%);

            left: 7%;

            right: 7%;

            height: 6px;

            background-color: #ccc;

            z-index: 1;

        } */



        .step {

            position: relative;

            z-index: 2;

            text-align: center;

            flex: 1;

        }



        .circle {

            width: 81px; /* 60px * 1.35 to make it 35% bigger */

            height: 81px;

            border-radius: 50%;

            background-color: lightgray;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            font-size: 22px;

            margin: 0 auto;

            border: 3px solid #ccc;

        }



        .completed .circle {

            background-color: green;

            border-color: green;

        }



        .rejected .circle {

            background-color: red;

            border-color: red;

        }



        .pending .circle {

            background-color: lightgray;

            border-color: #ccc;

        }



        .label {

            margin-top: 8px;

            font-size: 14px;

            word-break: break-word;

        }



        .progress-tracker.completed::before {

            background-color: green;

        }



        .no-record {

            color: red;

            font-weight: bold;

            margin-top: 20px;

        }



        .progress-tracker {

            position: relative;

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-top: 50px;

            margin-bottom: 30px;

        }



        .progress-line {

            position: absolute;

            top: 34%;

            left: 0;

            right: 0;

            margin: 0; /* ✅ Avoids stretching to the edges */

            height: 6px;

            background-color: green;

            z-index: 1;

            transition: width 0.4s;

        }





        .progress-tracker::before {

            content: '';

            position: absolute;

            top: 34%;

            transform: translateY(-50%);

            left: 7%;

            right: 7%;

            height: 6px;

            background-color: #ccc;

            z-index: 0;

        }



    </style>

</head>

<body class="bg-light">

<div class="sidebar" id="sidebar">

    <?php include 'side-menu.php'; ?>

</div>



<div class="content font-size" id="contentArea">

    <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4">

    <h5 class="mb-4 text-primary">Check Your Request Progress</h5>



    <form method="post" class="mb-4">

        <div class="input-group" style="max-width: 400px;">

            <input type="text" name="hris" class="form-control" placeholder="Enter your HRIS" required value="<?php echo htmlspecialchars($hris); ?>">

            <button type="submit" class="btn btn-primary">Check Progress</button>

        </div>

    </form>



    <?php if (!empty($hris)) { ?>



        <?php if ($row) { ?>



            <h5>Name: <?php echo htmlspecialchars($employee_name); ?></h5>

            <p>NIC: <?php echo htmlspecialchars($nic_no); ?> | Designation: <?php echo htmlspecialchars($designation); ?> | Location: <?php echo htmlspecialchars($location); ?></p>

            <p>Request Type: <?php echo htmlspecialchars($row['request_type']); ?></p>



            <?php

            // Prepare the steps and statuses

            // Prepare the steps and statuses

        $steps = [

            ['label' => 'Submitted', 'status' => 'completed'],

            ['label' => $recommendation_label, 'status' => getStepStatus($row['recommended_by'])],

            ['label' => $approval_label, 'status' => getStepStatus($row['approved_by'])],

        ];



        // Stop adding more steps if rejected already

        $any_rejected = false;



        // Check if recommended rejected

        if (getStepStatus($row['recommended_by']) == 'rejected' || getStepStatus($row['approved_by']) == 'rejected') {

            $steps[] = ['label' => 'Rejected at Approval', 'status' => 'rejected'];

            $any_rejected = true;

        }



        // Accepted

        if (!$any_rejected) {

            if (stripos($row['accepted_by'], 'REJECTED') !== false) {

                $steps[] = ['label' => 'Rejected at Acceptance', 'status' => 'rejected'];

                $any_rejected = true;

            } else {

                $steps[] = ['label' => 'Accepted by Admin', 'status' => $row['accepted_by'] ? 'completed' : 'pending'];

            }

        }



        // Issued

        if (!$any_rejected) {

            if (stripos($row['issue_status'], 'REJECTED') !== false) {

                $steps[] = ['label' => 'Rejected at Issuing', 'status' => 'rejected'];

                $any_rejected = true;

            } else {

                $steps[] = ['label' => 'Issued by Admin', 'status' => ($row['issue_status'] == 'Issued') ? 'completed' : 'pending'];

            }

        }



        // Closed

        if (!$any_rejected) {

            $steps[] = ['label' => 'Closed by Admin', 'status' => ($row['close_status'] == 'Closed') ? 'completed' : 'pending'];

        }



            



            // Count completed steps

            $total_steps = count($steps);

            $completed_steps = 0;

            foreach ($steps as $s) {

                if ($s['status'] == 'completed') {

                    $completed_steps++;

                } else {

                    break; // Stop at first non-completed

                }

            }



            $progress_percent = 0;

            if ($total_steps > 1) {

                if ($completed_steps == $total_steps) {

                    // All steps completed – line should end at the last circle

                    $progress_percent = 100;

                } else {

                    // Line ends at the last completed step (not touching next circle)

                    $progress_percent = (($completed_steps - 1) / ($total_steps - 1)) * 100;

                }

            }





            ?>



            

            <div class="progress-tracker">



            <!-- Green line -->

            <div class="progress-line" style="width:<?php echo $progress_percent; ?>%;"></div>



            <?php foreach ($steps as $index => $step) { ?>

                <div class="step <?php echo $step['status']; ?>">

                    <div class="circle"><?php echo $index + 1; ?></div>

                    <div class="label"><?php echo $step['label']; ?></div>

                </div>

            <?php } ?>





            </div>



            <?php

            // Check for any rejections and show message

            $any_rejected = false;

            foreach ($steps as $s) {

                if ($s['status'] == 'rejected') {

                    $any_rejected = true;

                    break;

                }

            }

            if ($any_rejected) {

                echo '<div class="alert alert-danger">Your request was rejected at one or more stages.</div>';

            } elseif ($all_completed) {

                echo '<div class="alert alert-success">Your request has been fully processed and completed.</div>';

            } else {

                echo '<div class="alert alert-info">Your request is in progress.</div>';

            }

            ?>



        <?php } else { ?>

            <div class="no-record">No requests found for HRIS <?php echo htmlspecialchars($hris); ?>.</div>

        <?php } ?>



    <?php } ?>

</div>



</div>

</body>

</html>

