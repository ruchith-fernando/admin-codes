<?php
require_once 'connections/connection.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$page_like = $_GET['page_like'] ?? '';
$q         = $_GET['q'] ?? '';

$where = " WHERE 1=1 ";
if ($from_date) $where .= " AND created_at >= '".$conn->real_escape_string($from_date)." 00:00:00' ";
if ($to_date)   $where .= " AND created_at <= '".$conn->real_escape_string($to_date)." 23:59:59' ";
if ($page_like) $where .= " AND page LIKE '%".$conn->real_escape_string($page_like)."%' ";
if ($q) {
  $qq = $conn->real_escape_string($q);
  $where .= " AND (
    log_uid LIKE '%$qq%' OR
    user LIKE '%$qq%' OR
    hris LIKE '%$qq%' OR
    ip_address LIKE '%$qq%' OR
    ip_source LIKE '%$qq%' OR
    action LIKE '%$qq%' OR
    page LIKE '%$qq%'
  ) ";
}

// ✅ Combine live + archive logs, include log_uid
$sql = "
  SELECT log_uid, id, user, hris, action, page, ip_address, ip_source, user_agent, created_at, 'live' AS source
  FROM tbl_admin_user_logs $where
  UNION ALL
  SELECT log_uid, id, user, hris, action, page, ip_address, ip_source, user_agent, created_at, 'archive' AS source
  FROM tbl_admin_user_logs_archive $where
  ORDER BY created_at DESC
  LIMIT 50000
";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=\"user_log_export_'.date('Ymd_His').'.csv\"');

// Open output stream
$fh = fopen('php://output', 'w');

// UTF-8 BOM (for Excel)
fwrite($fh, "\xEF\xBB\xBF");

// CSV header
fputcsv($fh, [
  'Log UID',
  'ID',
  'User',
  'HRIS',
  'Action',
  'Page',
  'IP Address',
  'IP Source',
  'User Agent',
  'Created At',
  'Source'
]);

// Execute and stream results
$res = $conn->query($sql);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    fputcsv($fh, [
      $r['log_uid'],
      $r['id'],
      $r['user'],
      $r['hris'],
      $r['action'],
      $r['page'],
      $r['ip_address'],
      $r['ip_source'],
      $r['user_agent'],
      $r['created_at'],
      $r['source']
    ]);
  }
}

fclose($fh);
exit;
?>
