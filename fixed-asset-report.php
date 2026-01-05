<?php
include 'connections/connection.php';

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch data from the database, sorting file_ref numerically
$sql = "SELECT file_ref, registration_date, veh_no, vehicle_type, make, model, yom, cr_available, 
               book_owner, division, asset_condition, assigned_user, hris, nic, tp_no, agreement, new_comments 
        FROM tbl_admin_fixed_assets 
        ORDER BY CAST(file_ref AS UNSIGNED) ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixed Assets Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            margin: 0;
            padding: 20px;
        }

        h2 {
            font-size: 24px;
            color: #333;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: left;
        }

        .search-container {
            margin: 20px 0;
            text-align: left;
        }

        input[type="text"] {
            padding: 8px 15px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus {
            border-color: #007bff;
        }

        th, td {
            padding: 10px;
            text-align: left;
            font-size: 12px;
            color: #555;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f0f0f0;
            color: #333;
            font-weight: 600;
        }

        .data-row:nth-child(even) td {
            background-color: #f1f1f1;
        }

        .total-row {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .total-row td {
            background-color: #e1e1e1;
        }

        /* Adjust column widths */
        table {
            width: 100%;
            min-width: 1500px; /* Ensures table spreads horizontally */
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Edit Button Style */
        .edit-btn {
            display: inline-block;
            padding: 8px 20px;
            font-size: 14px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        .edit-btn:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .edit-btn:active {
            background-color: #004085;
        }

        .veh-no {
            width: 100px; /* Increased width of the vehicle number column */
            min-width: 100px; /* Ensure minimum width */
        }

        .agreement {
            width: 100px; /* Make agreement column smaller */
        }
    </style>
</head>
<body>
    <h2>Fixed Assets Report</h2>

    <div class="search-container">
        <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Search for assets...">
    </div>

    <table id="assetsTable">
        <thead>
            <tr>
                <th>File Ref</th>
                <th class="veh-no">Vehicle No</th>
                <th>Registration Date</th>
                <th>Vehicle Type</th>
                <th>Make</th>
                <th>Model</th>
                <th>YOM</th>
                <th>CR Available</th>
                <th>Book Owner</th>
                <th>Division</th>
                <th>Condition</th>
                <th>Assigned User</th>
                <th>HRIS</th>
                <th>NIC</th>
                <th>TP No</th>
                <th class="agreement">Agreement</th>
                <th>New Comments</th>
                <!-- <th>Edit Record</th> -->
            </tr>
        </thead>
        <tbody>
        <?php
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr class='data-row' onclick=\"redirectToEdit('{$row['file_ref']}')\">
                    <td>{$row['file_ref']}</td>
                    <td class='veh-no'>{$row['veh_no']}</td>
                    <td>{$row['registration_date']}</td>
                    <td>{$row['vehicle_type']}</td>
                    <td>{$row['make']}</td>
                    <td>{$row['model']}</td>
                    <td>{$row['yom']}</td>
                    <td>{$row['cr_available']}</td>
                    <td>{$row['book_owner']}</td>
                    <td>{$row['division']}</td>
                    <td>{$row['asset_condition']}</td>
                    <td>{$row['assigned_user']}</td>
                    <td>{$row['hris']}</td>
                    <td>{$row['nic']}</td>
                    <td>{$row['tp_no']}</td>
                    <td class='agreement'>{$row['agreement']}</td>
                    <td>{$row['new_comments']}</td>
                    </tr>";
                    }
                    } else {
                        echo "<tr><td colspan='17'>No records found</td></tr>";
                        }
                        ?>
        </tbody>
    </table>
                        
    <!-- <td><a href=\"edit-record.php?file_ref={$row['file_ref']}\" class=\"edit-btn\">Edit</a></td> -->
    <script>
        function searchTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("assetsTable");
            tr = table.getElementsByTagName("tr");

            for (i = 1; i < tr.length; i++) {
                td = tr[i].getElementsByTagName("td");
                let found = false;
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
        }

        // Redirect to the edit page when any row is clicked
        function redirectToEdit(fileRef) {
            window.location.href = 'edit-record.php?file_ref=' + fileRef;
        }
    </script>

</body>
</html>
<?php
$conn->close();
?>
