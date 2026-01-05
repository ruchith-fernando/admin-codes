<?php
require_once 'connections/connection.php';

if (!isset($_POST['id'])) {
    echo "<div class='alert alert-danger'>Invalid request.</div>";
    exit;
}

$id = intval($_POST['id']);

$query = "SELECT m.*, v.vehicle_number, v.vehicle_type 
          FROM tbl_admin_vehicle_maintenance m
          JOIN tbl_admin_vehicle v ON m.vehicle_number = v.vehicle_number
          WHERE m.id = $id";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    ?>
    <div>
        <p><strong>Vehicle No:</strong> <?= htmlspecialchars($row['vehicle_number']) ?></p>
        <p><strong>Vehicle Type:</strong> <?= htmlspecialchars($row['vehicle_type']) ?></p>
        <p><strong>Maintenance Type:</strong> <?= htmlspecialchars($row['maintenance_type']) ?></p>
        <p><strong>Purchase Date:</strong> <?= htmlspecialchars($row['purchase_date']) ?></p>
        <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($row['description'])) ?></p>
    </div>
    <?php
} else {
    echo "<div class='alert alert-warning'>Record not found.</div>";
}
?>
