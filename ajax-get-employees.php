<?php
include 'connections/connection.php';

$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$start = ($page - 1) * $limit;

$searchSql = '';
if ($query !== '') {
  $q = "%$query%";
  $searchSql = "WHERE hris LIKE ? OR epf_no LIKE ? OR name_of_employee LIKE ? OR nic_no LIKE ? OR designation LIKE ?";
}

$sql = "SELECT * FROM tbl_admin_employee_details $searchSql ORDER BY name_of_employee ASC LIMIT $start, $limit";
$stmt = $conn->prepare($sql);

if ($searchSql !== '') {
  $stmt->bind_param("sssss", $q, $q, $q, $q, $q);
}

$stmt->execute();
$result = $stmt->get_result();

$data = '';
$data .= '<table class="table table-bordered table-hover">';
$data .= '<thead><tr>
  <th>HRIS</th>
  <th>Name</th>
  <th>NIC</th>
  <th>EPF</th>
  <th>Designation</th>
  <th>Location</th>
  <th>Action</th>
</tr></thead><tbody>';

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $data .= '<tr>
      <td>' . $row['hris'] . '</td>
      <td>' . $row['name_of_employee'] . '</td>
      <td>' . $row['nic_no'] . '</td>
      <td>' . $row['epf_no'] . '</td>
      <td>' . $row['designation'] . '</td>
      <td>' . $row['location'] . '</td>
      <td><button class="btn btn-sm btn-primary viewBtn" data-id="' . $row['id'] . '">View</button></td>
    </tr>';
  }
} else {
  $data .= '<tr><td colspan="7" class="text-center">No records found.</td></tr>';
}
$data .= '</tbody></table>';

// Pagination
$countSql = "SELECT COUNT(*) AS total FROM tbl_admin_employee_details " . ($searchSql ? $searchSql : '');
$countStmt = $conn->prepare($countSql);
if ($searchSql !== '') {
  $countStmt->bind_param("sssss", $q, $q, $q, $q, $q);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$pages = ceil($total / $limit);

$data .= '<div id="pagination"><nav><ul class="pagination">';
for ($i = 1; $i <= $pages; $i++) {
  $active = ($i == $page) ? 'active' : '';
  $data .= "<li class='page-item $active'><a class='page-link' href='#' data-page='$i'>$i</a></li>";
}
$data .= '</ul></nav></div>';

echo $data;
?>
