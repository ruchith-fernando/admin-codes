<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Admin Shell</title>
  <meta charset="UTF-8" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body class="d-flex">
  <div class="sidebar" id="sidebar">
    <?php include 'includes/side-bar.php'; ?>
  </div>
  <div class="content" id="contentArea">
    <!-- AJAX-loaded content -->
  </div>

  <script>
    $(document).ready(function() {
      function loadPage(page) {
        $('#contentArea').load('pages/' + page + '.php');
      }

      $('.ajax-link').click(function(e) {
        e.preventDefault();
        let page = $(this).data('page');
        loadPage(page);
      });

      // Load dashboard by default
      loadPage('dashboard');
    });
  </script>
</body>
</html>
