<?php
include 'connections/connection.php';

$user = $_GET['searchUser'] ?? '';
$from = $_GET['dateFrom'] ?? '';
$to = $_GET['dateTo'] ?? '';

$sql = "SELECT * FROM tbl_admin_user_logs WHERE 1";

if (!empty($user)) {
    $sql .= " AND user LIKE '%" . $conn->real_escape_string($user) . "%'";
}

if (!empty($from)) {
    $sql .= " AND timestamp >= '" . $conn->real_escape_string($from) . " 00:00:00'";
}

if (!empty($to)) {
    $sql .= " AND timestamp <= '" . $conn->real_escape_string($to) . " 23:59:59'";
}

$sql .= " ORDER BY timestamp DESC LIMIT 200";

$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo "<div class='alert alert-warning'>No logs found for the selected filters.</div>";
    exit;
}

echo "<div class='table-responsive'>";
echo "<table class='table table-bordered table-striped table-sm'>";
echo "<thead class='table-light'>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Action</th>
            <th>Page</th>
            <th>IP</th>
            <th>Timestamp</th>
        </tr>
      </thead><tbody>";

$count = 1;
while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$count}</td>
            <td>" . htmlspecialchars($row['user']) . "</td>
            <td>" . nl2br(htmlspecialchars($row['action'])) . "</td>
            <td>" . htmlspecialchars($row['page']) . "</td>
            <td>" . htmlspecialchars($row['ip_address']) . "</td>
            <td>" . $row['timestamp'] . "</td>
          </tr>";
    $count++;
}

echo "</tbody></table></div>";
?>
