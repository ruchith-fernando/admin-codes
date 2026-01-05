<?php
// invoice-summary-update.php
include 'connections/connection.php';

$invoice_id = intval($_GET['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
    die("❌ Missing or invalid invoice_id");
}

/**
 * Backfill from tbl_admin_mobile_issues (only where connection_status = 'Connected')
 * Will update hris_no, employee_name, nic_no, voice_data
 * but only if those fields are currently NULL or empty in the summary table
 */
$sql_update_mobile = "
    UPDATE tbl_admin_hr_report_dialog_summary s
    JOIN tbl_admin_dialog_invoices inv 
      ON inv.id = s.invoice_id
    JOIN tbl_admin_mobile_issues mi
      ON mi.mobile_no = s.mobile_number
     AND mi.connection_status = 'Connected'
    SET 
      s.hris_no = CASE WHEN s.hris_no IS NULL OR s.hris_no = '' THEN mi.hris_no ELSE s.hris_no END,
      s.employee_name = CASE WHEN s.employee_name IS NULL OR s.employee_name = '' THEN mi.name_of_employee ELSE s.employee_name END,
      s.nic_no = CASE WHEN s.nic_no IS NULL OR s.nic_no = '' THEN mi.nic_no ELSE s.nic_no END,
      s.voice_data = CASE WHEN s.voice_data IS NULL OR s.voice_data = '' THEN mi.voice_data ELSE s.voice_data END
    WHERE s.invoice_id = ?
      AND (s.hris_no IS NULL OR s.hris_no = '' OR s.employee_name IS NULL OR s.employee_name = '');
";

$stmt = $conn->prepare($sql_update_mobile);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$rows = $stmt->affected_rows;
$stmt->close();

echo "✅ Summary enrichment completed for invoice_id {$invoice_id}. Rows updated: {$rows}";
$conn->close();
?>
