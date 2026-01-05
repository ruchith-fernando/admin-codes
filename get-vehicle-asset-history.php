<!-- get-vehicle-asset-history.php -->
<?php
include 'connections/connection.php';

$file_ref = $conn->real_escape_string($_GET['file_ref']);

$sql = "SELECT * FROM tbl_vehicle_assignment_log 
        WHERE file_ref = '$file_ref' 
        ORDER BY changed_at DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo '<ul class="list-group small">';
    while ($row = $result->fetch_assoc()) {
        echo '<li class="list-group-item">';
        echo "<strong>Date:</strong> " . htmlspecialchars($row['changed_at']) . "<br>";
        echo "<strong>Old User:</strong> " . htmlspecialchars($row['old_assigned_user']) . " (" . htmlspecialchars($row['old_hris']) . ")<br>";
        echo "<strong>New User:</strong> " . htmlspecialchars($row['new_assigned_user']) . " (" . htmlspecialchars($row['new_hris']) . ")<br>";
        echo "<strong>Division:</strong> " . htmlspecialchars($row['old_division']) . " → " . htmlspecialchars($row['new_division']) . "<br>";
        echo "<strong>Reason:</strong> " . nl2br(htmlspecialchars($row['reason'])) . "<br>";
        echo "<strong>Changed By:</strong> " . htmlspecialchars($row['changed_by']) . " (" . htmlspecialchars($row['change_method']) . ")";
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<p class="text-muted">No previous assignment history found.</p>';
}
?>
