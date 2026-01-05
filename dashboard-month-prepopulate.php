<?php
require 'connections/connection.php';

// ✅ Define your dashboard categories
$categories = [
  'Security Charges',
  'Vehicle Maintenance',
  'Printing & Stationary',
  'Tea Service - Head Office',
  'Photocopy',
  'Courier',
  'Staff Transport',
  'Postage & Stamps',
  'Contracts',
  'Document Printing - Branches',
  'Electricity'
];

// ✅ Determine current financial year based on current date
$year = date('Y');
$month = date('n'); // 1 = Jan, 12 = Dec

if ($month >= 4) {
    // If April or later, financial year is current year to next year
    $start_year = $year;
    $end_year = $year + 1;
} else {
    // If Jan, Feb, Mar → belongs to previous financial year
    $start_year = $year - 1;
    $end_year = $year;
}

// ✅ Build the 12 financial year months
$months = [
  "April $start_year", "May $start_year", "June $start_year", "July $start_year",
  "August $start_year", "September $start_year", "October $start_year", "November $start_year",
  "December $start_year", "January $end_year", "February $end_year", "March $end_year"
];

$inserted_count = 0;

foreach ($categories as $category) {
  foreach ($months as $month_name) {
    // Check if the category/month already exists
    $check = mysqli_query($conn, "SELECT id FROM tbl_admin_dashboard_month_selection WHERE category='$category' AND month_name='$month_name' LIMIT 1");
    if ($check && mysqli_num_rows($check) === 0) {
      // Insert missing combination with default is_selected = 'no'
      $insert = mysqli_query($conn, "INSERT INTO tbl_admin_dashboard_month_selection (category, month_name, is_selected) VALUES ('$category', '$month_name', 'no')");
      if ($insert) $inserted_count++;
    }
  }
}

echo "<div style='padding:20px;font-family:monospace'>";
echo "<h3>✅ Dashboard Month Prepopulation Complete</h3>";
echo "<p>Financial Year: <strong>$start_year – $end_year</strong></p>";
echo "<p>New records added: <strong>$inserted_count</strong></p>";
echo "<p>If you see zero new records, everything was already populated.</p>";
echo "<hr><p>✅ You can now safely <strong>delete this file</strong>.</p>";
echo "</div>";
?>
