<?php
include 'connections/connection.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM tbl_admin_sim_request WHERE status = 'Initiated'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<div class='font-size' style='overflow-x: auto;'>"; // font size and scroll container
    echo "<table class='table table-striped table-bordered table-hover align-middle w-100'>"; // force full width
    echo "<thead class='table-dark'>
        <tr>
            <th class='p-3' style='min-width: 100px;'>Type</th>
            <th class='p-3' style='min-width: 300px;'>Name</th>
            <th class='p-3'>HRIS</th>
            <th class='p-3'>NIC</th>
            <th class='p-3' style='min-width: 220px;'>Designation</th>
            <th class='p-3'>Branch/Division</th>
            <th class='p-3' style='min-width: 220px;'>Employee Category</th>
            <th class='p-3' style='min-width: 100px;'>Voice / Data</th>
            <th class='p-3' style='min-width: 220px;'>Voice Package</th>
            <th class='p-3' style='min-width: 100px;'>Data Package</th>
            <th class='p-3' style='min-width: 50px;'>Other</th>
            <th class='p-3'>Action</th>
        </tr>
        </thead><tbody>";

        while($row = $result->fetch_assoc()) {
            echo "<tr>
                <td class='p-3'>{$row['request_type']}</td>
                <td class='p-3'>{$row['name']}</td>
                <td class='p-3'>{$row['hris']}</td>
                <td class='p-3'>{$row['nic']}</td>
                <td class='p-3'>{$row['designation']}</td>
                <td class='p-3'>{$row['branch_division']}</td>
                <td class='p-3'>{$row['employee_category']}</td>
                <td class='p-3'>{$row['voice_data']}</td>
                <td class='p-3'>{$row['voice_package']}</td>
                <td class='p-3'>{$row['data_package']}</td>
                <td class='p-3'>{$row['other_amount']}</td>
                <td class='p-3'>
                    <div class='d-flex gap-2'>
                        <button class='btn btn-success btn-sm' onclick='approveReject({$row['id']}, \"approved\")'>Approve</button>
                        <button class='btn btn-danger btn-sm' onclick='approveReject({$row['id']}, \"rejected\")'>Reject</button>
                    </div>
                </td>
            </tr>";
        }        

    echo "</tbody></table>";
    echo "</div>";
} else {
    echo "<div class='alert alert-info'>No pending requests.</div>";
}

$conn->close();
?>
