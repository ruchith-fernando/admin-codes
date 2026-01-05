<?php
session_start();
include "connections/connection.php";

if (!isset($_SESSION['name']) || !in_array($_SESSION['user_level'], ['manager', 'super-admin'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Secure Document Viewer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <script>
        function openSecurePrint(filePath, documentNumber) {
            const printWindow = window.open(`secure-print-viewer.php?file=${encodeURIComponent(filePath)}&doc=${encodeURIComponent(documentNumber)}`, '_blank');
        }
    </script>
</head>
<body class="bg-light">

<div class="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Secure Documents</h5>
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Document Number</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM tbl_admin_secure_documents");
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>{$row['document_number']}</td>
                            <td>{$row['description']}</td>
                            <td>
                                <button class='btn btn-primary' onclick=\"openSecurePrint('{$row['file_path']}', '{$row['document_number']}')\">Print</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
