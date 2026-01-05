<?php
// water-vendor-map.php
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
            <h5 class="mb-4 text-primary">Water Vendors — Map Vendor to Water Type</h5>

            <!-- 🔔 Inline alert area -->
            <div id="waterVendorMapAlertPlaceholder"></div>

            <div id="waterVendorMapContent">
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
    $('#waterVendorMapContent').load('ajax-water-vendor-map-body.php');
});
</script>
