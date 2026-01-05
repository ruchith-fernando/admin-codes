<?php
require_once 'connections/connection.php';
require_once 'dompdf/autoload.inc.php'; // ✅ DOMPDF

use Dompdf\Dompdf;
use Dompdf\Options;

// Validate
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Invalid Request');

$entry_id = intval($_POST['entry_id']);
$entry_type = $_POST['entry_type'];
$validTypes = ['maintenance', 'service', 'license'];
if (!in_array($entry_type, $validTypes)) exit('Invalid Type');

// Map tables
$tableMap = [
    'maintenance' => 'tbl_admin_vehicle_maintenance',
    'service'     => 'tbl_admin_vehicle_service',
    'license'     => 'tbl_admin_vehicle_licensing_insurance',
];
$table = $tableMap[$entry_type];

// Fetch record
$stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    exit("<div class='alert alert-danger'>Entry not found.</div>");
}

// Create Advice Number
$adviceNo = "PA-" . strtoupper(substr($entry_type, 0, 1)) . "-" . str_pad($entry_id, 6, '0', STR_PAD_LEFT);
$savePath = "../uploads/payment-advice/";
$pdfFile = $savePath . $adviceNo . ".pdf";
$downloadUrl = str_replace("../", "", $pdfFile);

// Generate PDF content
$billRows = '';
foreach ($data as $key => $value) {
    if (in_array($key, ['id', 'status', 'image_path'])) continue;
    $billRows .= "<tr><td style='padding:5px;border:1px solid #ccc'>" . ucfirst(str_replace('_', ' ', $key)) . "</td>
    <td style='padding:5px;border:1px solid #ccc'>" . htmlspecialchars($value) . "</td></tr>";
}
$billRows .= "<tr><td style='padding:5px;border:1px solid #ccc'>Advice Number</td><td style='padding:5px;border:1px solid #ccc'>{$adviceNo}</td></tr>";
$billRows .= "<tr><td style='padding:5px;border:1px solid #ccc'>Approved Date</td><td style='padding:5px;border:1px solid #ccc'>" . date("Y-m-d H:i:s") . "</td></tr>";

$html = "<h3>Payment Advice</h3><table style='width:100%; border-collapse:collapse'>{$billRows}</table>";

// Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

if (!is_dir($savePath)) mkdir($savePath, 0755, true);
file_put_contents($pdfFile, $dompdf->output());

// Update DB
$update = $conn->prepare("UPDATE $table SET status = 'approved', payment_advice_no = ? WHERE id = ?");
$update->bind_param("si", $adviceNo, $entry_id);
$update->execute();
$update->close();

// Success output
echo "<div class='alert alert-success'>
    ✅ Approved and Payment Advice Generated.<br>
    <strong>Advice No:</strong> {$adviceNo}<br>
    <a href='{$downloadUrl}' target='_blank' class='btn btn-sm btn-outline-primary mt-2'>Download Payment Advice</a>
</div>";
