<?php
// request-audit-export.php
require_once 'connections/connection.php';

$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date   = isset($_GET['to_date'])   ? trim($_GET['to_date'])   : '';
$method    = isset($_GET['method'])    ? trim($_GET['method'])    : '';
$page_like = isset($_GET['page_like']) ? trim($_GET['page_like']) : '';
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
if ($method !== '') {
  $m = mysqli_real_escape_string($conn, $method);
  $where .= " AND method = '$m' ";
}
if ($page_like !== '') {
  $pl = mysqli_real_escape_string($conn, $page_like);
  $where .= " AND page_name LIKE '%$pl%' ";
}
if ($q !== '') {
  $qq = mysqli_real_escape_string($conn, $q);
  $where .= " AND (
      username LIKE '%$qq%' OR
      hris LIKE '%$qq%' OR
      ip_address LIKE '%$qq%' OR
      request_uri LIKE '%$qq%' OR
      user_agent LIKE '%$qq%' OR
      referer LIKE '%$qq%' OR
      page_name LIKE '%$qq%'
    ) ";
}

$sql = "SELECT id, created_at, method, page_name, request_uri, username, hris, ip_address, ip_source, xff_chain, user_agent, referer
        FROM tbl_admin_request_audit
        $where
        ORDER BY created_at DESC, id DESC
        LIMIT 50000";

$filename = "request_audit_export_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
$fh = fopen('php://output', 'w');
fwrite($fh, "\xEF\xBB\xBF");

fputcsv($fh, ['ID','Created At','Method','Page','Request URI','Username','HRIS','IP Address','IP Source','XFF Chain','User Agent','Referer']);

$rs = mysqli_query($conn, $sql);
if ($rs) {
  while($r = mysqli_fetch_assoc($rs)) {
    fputcsv($fh, [
      $r['id'],
      $r['created_at'],
      $r['method'],
      $r['page_name'],
      $r['request_uri'],
      $r['username'],
      $r['hris'],
      $r['ip_address'],
      $r['ip_source'],
      $r['xff_chain'],
      $r['user_agent'],
      $r['referer'],
    ]);
  }
}
fclose($fh);
exit;
