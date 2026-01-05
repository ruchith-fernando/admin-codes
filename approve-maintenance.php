<?php
require_once 'connections/connection.php';
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $approver = 'super-admin'; // replace with session user if available
    $timestamp = date('Y-m-d H:i:s');
    $paymentAdviceNo = 'PADV' . date('YmdHis') . $id;

    // Update approval status
    $stmt = $conn->prepare("UPDATE tbl_admin_vehicle_maintenance SET is_approved = 1, approved_by = ?, approved_on = ?, payment_advice_no = ? WHERE id = ?");
    $stmt->bind_param("sssi", $approver, $timestamp, $paymentAdviceNo, $id);
    if ($stmt->execute()) {
        // Fetch details to generate PDF
        $result = $conn->query("SELECT * FROM tbl_admin_vehicle_maintenance WHERE id = $id");
        $row = $result->fetch_assoc();

        ob_start();
        ?>
        <h2>Payment Advice</h2>
        <p><strong>Advice No:</strong> <?= $paymentAdviceNo ?></p>
        <p><strong>Vehicle:</strong> <?= htmlspecialchars($row['vehicle_number']) ?></p>
        <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
        <p><strong>Amount:</strong> Rs. <?= number_format($row['price'], 2) ?></p>
        <p><strong>Date:</strong> <?= date('d-m-Y', strtotime($row['date'])) ?></p>
        <p><strong>Approved by:</strong> <?= $approver ?> on <?= $timestamp ?></p>
        <?php
        $html = ob_get_clean();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $pdfFile = 'uploads/payment-advice/' . $paymentAdviceNo . '.pdf';
        file_put_contents($pdfFile, $pdfOutput);

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'DB update failed']);
    }
}
?>
