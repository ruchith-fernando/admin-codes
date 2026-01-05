<?php
// ajax-get-menu-keys.php
include 'connections/connection.php';

// Get search term
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

// Build query
$where = "";
if (!empty($search)) {
  $where = "WHERE menu_key LIKE '%$search%' 
            OR menu_label LIKE '%$search%' 
            OR menu_group LIKE '%$search%'";
}

// Fetch all records (no pagination)
$query = "SELECT * FROM tbl_admin_menu_keys $where ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Build table
$output = '<table class="table table-bordered table-sm align-middle">
<thead class="table-light">
<tr>
  <th style="width:60px;">ID</th>
  <th>Menu Key</th>
  <th>Label</th>
  <th>Group</th>
  <th style="width:80px;">Color</th>
</tr>
</thead>
<tbody>';

if (mysqli_num_rows($result) > 0) {
  while ($row = mysqli_fetch_assoc($result)) {
    $colorBlock = $row['color'] 
      ? "<div style='width:25px;height:25px;border-radius:4px;background:{$row['color']};
                border:1px solid #ccc;display:flex;align-items:center;justify-content:center;margin:auto;'></div>"
      : "<span class='text-muted small'>—</span>";

    $output .= "<tr>
                  <td>{$row['id']}</td>
                  <td>{$row['menu_key']}</td>
                  <td>{$row['menu_label']}</td>
                  <td>{$row['menu_group']}</td>
                  <td class='text-center align-middle'>$colorBlock</td>
                </tr>";
  }
} else {
  $output .= '<tr><td colspan="5" class="text-center text-muted">No records found</td></tr>';
}

$output .= '</tbody></table>';

echo json_encode(['table' => $output]);
?>
