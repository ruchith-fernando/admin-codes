<?php
// water-branch-map.php
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
            <h5 class="mb-4 text-primary">Branch – Water Types Mapping</h5>

            <!-- 🔔 Inline alert area -->
            <div id="branchWaterAlertPlaceholder"></div>

            <div id="branchWaterContent">
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
    $('#branchWaterContent').load('ajax-water-branch-map-body.php');
});
</script>
