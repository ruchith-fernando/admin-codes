<?php
// main.php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Main Page</title>

  <!-- External CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/css/bootstrap-datepicker.min.css" rel="stylesheet">
  <link href="styles.css" rel="stylesheet">

  <!-- External JS -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- <script src="js/shared-js.js?=v2"></script> -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/js/bootstrap-datepicker.min.js"></script>

  <style>
    body {
      background-color: #f8f9fa;
    }
    .sidebar {
      min-width: 280px;
      background-color: #343a40;
      color: white;
      padding: 10px;
      min-height: 100vh;
    }
    .sidebar a {
      color: #ccc;
      text-decoration: none;
      display: block;
      padding: 8px;
    }
    .sidebar a:hover {
      color: white;
      background-color: #d2d8dfff;
    }
    .btn-primary {
      background: linear-gradient(135deg, #667eea, #764ba2);
      border: none;
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, #5a67d8, #6b46c1);
    }
    @media (max-width: 991.98px) {
      .sidebar {
        position: absolute;
        top: 56px;
        left: 0;
        width: 280px;
        z-index: 1050;
        background-color: #343a40;
      }
    }
    .table {
      font-size: 0.875rem;
    }
    th, td {
      white-space: nowrap;
      padding: 0.5rem 1rem;
    }
  </style>
</head>

<body class="bg-light">
  <!-- Top Navbar -->
  <nav class="navbar navbar-light bg-white border-bottom d-lg-none px-3">
    <button id="burgerToggle" class="btn btn-outline-dark">
      <i class="fas fa-bars"></i>
    </button>
    <span class="navbar-text text-dark ms-2">Menu</span>
  </nav>

  <div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar d-none d-lg-block" id="sidebarMenu">
      <?php include 'side-menu.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div id="contentArea" class="container-fluid p-3">
      <div class="text-center text-muted mt-5">
        <h4>Welcome</h4>
        <p>Select a menu item to load content here.</p>
      </div>
    </div>
  </div>
<?php 
  // include 'shared-modals.php'
?>
  <script>
  $(document).ready(function () {
    // Toggle sidebar on small screens
    $('#burgerToggle').on('click', function () {
      $('#sidebarMenu').toggleClass('d-none d-block');
    });

    // Load page dynamically into contentArea
    function loadPage(href, push = true) {
      $('#contentArea').html(`
        <div class="text-center mt-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="mt-3">Loading...</p>
        </div>
      `);

      $.ajax({
        url: href,
        method: 'GET',
        success: function (data) {
          $('#contentArea').html(data);
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
          tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
          });

          if (typeof runDashboardChart === 'function') setTimeout(runDashboardChart, 150);
          initPageFeatures();

          // Push state without altering the visible URL
          if (push) {
            history.pushState({ page: href }, '', 'main.php');
          }
        },
        error: function () {
          $('#contentArea').html('<div class="alert alert-danger mt-3">Error loading page.</div>');
        }
      });
    }

    // Sidebar link click handling
    $('.sidebar').on('click', 'a[href]', function (e) {
      const href = $(this).attr('href');
      if (href && href !== '#' && !href.includes('logout')) {
        e.preventDefault();
        loadPage(href);
      }
    });

    // Init features after AJAX load
    function initPageFeatures() {
      if ($('#item_code').length && $.fn.select2) {
        $('#item_code').select2({
          placeholder: 'Search item code or description',
          ajax: {
            url: 'fetch-items.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
              return { term: params.term };
            },
            processResults: function (data) {
              return { results: data.results };
            },
            cache: true
          },
          minimumInputLength: 1,
          width: '100%'
        });
      }

      if ($('#received_date').length && $.fn.datepicker) {
        $('#received_date').datepicker({
          format: 'yyyy-mm-dd',
          endDate: new Date(),
          autoclose: true,
          todayHighlight: true
        }).datepicker('setDate', new Date());
      }
    }

    // Handle browser back/forward
    window.onpopstate = function (event) {
      if (event.state && event.state.page) {
        loadPage(event.state.page, false);
      }
    };

    // Initial page load (default to dashboard.php)
    loadPage('dashboard.php', false);
  });
</script>
<script src="vehicle-approval.js?v=5"></script>
</body>
</html>
