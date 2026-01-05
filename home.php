<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 mt-2" id="sidebarArea">
            <?php include 'includes/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 mt-2" id="contentArea">
            <?php 
              include 'dashboard.php';
             ?>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.nav-link').on('click', function(e) {
        e.preventDefault();
        let page = $(this).data('page');
        $('#contentArea').load('load-admin.php?page=' + page);
    });
});
</script>
</body>
</html>
