<?php
// vendor-master.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
?>

<div class="content font-size">
    <div class="container-fluid">
        <div class="card p-4 shadow-sm">
            <h5 class="mb-4 text-primary">Water Vendor Master</h5>

            <!-- 🔔 Inline alert area -->
            <div id="vendorAlertPlaceholder"></div>

            <div id="waterVendorContent">
                <div class="text-center">
                    <div class="spinner-border text-primary"></div>
                    <div>Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#waterVendorContent').load('ajax-vendor-body.php');
});
</script>
