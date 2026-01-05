<?php
// export-dashboard-adjusted.php
// Streams an HTML table to Excel (.xls)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$filename = isset($_POST['filename']) && $_POST['filename'] !== ''
  ? preg_replace('/[^\w\-.]/', '_', $_POST['filename'])
  : 'dashboard-adjusted.xls';

$tableHtml = isset($_POST['table_html']) ? $_POST['table_html'] : '<html><body><p>No data.</p></body></html>';

// Excel-friendly headers
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output the HTML (Excel will render it as a worksheet)
echo $tableHtml;
