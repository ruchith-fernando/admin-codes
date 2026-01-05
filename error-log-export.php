<?php
// error-log-export.php

require_once 'connections/connection.php';

$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date   = isset($_GET['to_date'])   ? trim($_GET['to_date'])   : '';
$error_type= isset($_GET['error_type'])? trim($_GET['error_type']): '';
$file_like = isset($_GET['file_like']) ? trim($_GET['file_like']) : '';
$q         = isset($_GET['q'])         ? trim($_GET['q'])         : '';

$where = " WHERE 1=1 ";
if ($from_date !== '') {
  $from = mysqli_real_escape_string($conn, $from_date . " 00:00:00");
  $where .= " AND created_at >= '$from' ";
}
if ($to_date !== '') {
  $to = mysqli_real_escape_string($conn, $to_date . " 23:59:59");
  $where .= " AND created_at <= '$to' ";
}
if ($error_type !== '') {
  $et = mysqli_real_escape_string($conn, $error_type);
  $where .= " AND error_type = '$et' ";
}
if ($file_like !== '') {
  $fl = mysqli_real_escape_string($conn, $file_like);
  $where .= " AND file LIKE '%$fl%' ";
}
if ($q !== '') {
  $qq = mysqli_real_escape_string($conn, $q);
  $where .= " AND (
      error_message LIKE '%$qq%' OR
      user_info LIKE '%$qq%' OR
      ip_address LIKE '%$qq%' OR
      ip_source LIKE '%$qq%' OR
      file LIKE '%$qq%'
    ) ";
}

$sql = "SELECT id, created_at, error_type, file, line, user_info, ip_address, ip_source, ip_chain, error_message
        FROM tbl_admin_errors
        $where
        ORDER BY created_at DESC, id DESC
        LIMIT 50000"; // hard cap

$filename = "error_log_export_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
$fh = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($fh, "\xEF\xBB\xBF");

// Header
fputcsv($fh, ['ID','Created At','Error Type','File','Line','User Info','IP Address','IP Source','IP Chain','Error Message']);

$rs = mysqli_query($conn, $sql);
if ($rs) {
  while($r = mysqli_fetch_assoc($rs)) {
    fputcsv($fh, [
      $r['id'],
      $r['created_at'],
      $r['error_type'],
      $r['file'],
      $r['line'],
      $r['user_info'],
      $r['ip_address'],
      $r['ip_source'],
      $r['ip_chain'],
      $r['error_message'],
    ]);
  }
}

fclose($fh);
exit;
