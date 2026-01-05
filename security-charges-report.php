<?php
include 'connections/connection.php';

// Fetch all branches with numeric sorting
$branches_query = "SELECT branch_id, branch_name FROM tbl_admin_branch_information ORDER BY CAST(branch_id AS UNSIGNED) ASC";
$branches_result = mysqli_query($conn, $branches_query);
$branches = [];
while ($row = mysqli_fetch_assoc($branches_result)) {
    $branches[] = $row;
}

// Define months and correctly set years for the financial year 2025/2026
$months = [
    "April" => 2025, "May" => 2025, "June" => 2025, "July" => 2025, "August" => 2025, "September" => 2025,
    "October" => 2025, "November" => 2025, "December" => 2025, 
    "January" => 2026, "February" => 2026, "March" => 2026
];

// Fetch all security cost data for the financial year 2025/2026
$security_costs = [];
$cost_query = "SELECT branch_id, payment_month, shift_total_cost, no_of_shifts FROM tbl_admin_security_cost";
$cost_result = mysqli_query($conn, $cost_query);
while ($row = mysqli_fetch_assoc($cost_result)) {
    $security_costs[$row['branch_id']][$row['payment_month']] = [
        'shift_total_cost' => $row['shift_total_cost'],
        'no_of_shifts' => $row['no_of_shifts']
    ];
}

// Initialize cumulative total array
$cumulative_totals = array_fill_keys(array_keys($months), 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Cost Report (Financial Year 2025/2026)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .report-container {
            width: 100%;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .table-container {
            overflow-x: auto;
            max-height: 600px; /* Limits height and enables scrolling */
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            table-layout: auto;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            white-space: nowrap;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .sticky-col {
            position: sticky;
            left: 0;
            background-color: white;
            z-index: 2;
            border-right: 2px solid #ddd;
        }
        .sticky-header {
            position: sticky;
            top: 0;
            background-color: #007bff;
            z-index: 3;
        }
        .text-left {
            text-align: left !important;
        }
        .cumulative-row {
            font-weight: bold;
            background-color: #ffeb3b; /* Yellow background for visibility */
        }
    </style>
</head>
<body>

<div class="report-container">
    <h2 class="text-center mb-4">Security Cost Report (Financial Year 2025/2026)</h2>
    
    <div class="table-container">
        <table class="table table-bordered">
            <thead>
                <tr class="sticky-header">
                    <th class="sticky-col">Branch ID</th>
                    <th class="sticky-col">Branch Name</th>
                    <th class="sticky-col">No of Shifts</th>
                    <?php foreach ($months as $month => $year): ?>
                        <th><?php echo "$month $year"; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $branch): ?>
                    <tr>
                        <td class="sticky-col"><?php echo $branch['branch_id']; ?></td>
                        <td class="sticky-col text-left"><?php echo $branch['branch_name']; ?></td>
                        
                        <?php 
                            // Get the first record for number of shifts
                            $first_record = reset($security_costs[$branch['branch_id']]);
                            $no_of_shifts = isset($first_record['no_of_shifts']) ? $first_record['no_of_shifts'] : 0;
                        ?>
                        <td class="sticky-col"><?php echo $no_of_shifts; ?></td>
                        
                        <?php foreach ($months as $month => $year): ?>
                            <?php
                            $payment_month = "$month $year";
                            if (isset($security_costs[$branch['branch_id']][$payment_month])) {
                                $cost = $security_costs[$branch['branch_id']][$payment_month]['shift_total_cost'];
                                $formatted_cost = number_format($cost, 2);
                                
                                // Add to cumulative total
                                $cumulative_totals[$month] += $cost;
                            } else {
                                $cost = 0;
                                $formatted_cost = "-";
                            }
                            ?>
                            <td><?php echo $formatted_cost; ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Cumulative Total Row -->
                <tr class="cumulative-row">
                    <td class="sticky-col" colspan="2">Total</td>
                    <td class="sticky-col">-</td>
                    <?php foreach ($months as $month => $year): ?>
                        <td><?php echo number_format($cumulative_totals[$month], 2); ?></td>
                    <?php endforeach; ?>
                </tr>

            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
