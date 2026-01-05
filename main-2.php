<?php
// main.php
session_start();
// If you need DB or auth, include here (optional):
// require_once 'connections/connection.php';
date_default_timezone_set('Asia/Colombo');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Main</title>

  <!-- Bootstrap / Icons -->
  

  <style>
    :root { --sidebar-width: 260px; }
    html, body { height: 100%; }
    body { min-height: 100vh; background: #f7f8fb; }

    /* Sidebar */
    #sidebar {
      width: var(--sidebar-width);
      overflow-y: auto;
    }

    /* Content wrapper shifts to the right of sidebar (desktop) */
    #contentWrap { margin-left: var(--sidebar-width); }

    /* Mobile: sidebar slides in/out */
    @media (max-width: 991.98px) {
      #sidebar {
        position: fixed;
        transform: translateX(-100%);
        transition: transform .25s ease;
        z-index: 1040;
        top: 0; bottom: 0; left: 0;
      }
      #sidebar.show { transform: translateX(0); }
      #contentWrap { margin-left: 0; }
    }

    /* Active link highlight (works if side-menu links are simple <a>) */
    #sideMenu a.active {
      background: rgba(13,110,253,.08) !important;
      font-weight: 600;
      color: #0d6efd !important;
    }

    /* Loader */
    #loader {
      display: flex; align-items: center; justify-content: center; gap: .75rem;
      padding: 2rem; color: #6c757d;
    }
  </style>
  <?php
// main.php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit;
}

?>
<!-- <link rel="icon" href="data:,"> -->
<!-- <link rel="icon" type="image/png" href="images/cdb-favicon.png"> -->
<!-- <link rel="icon" type="image/png" sizes="32x32" href="https://www.cdb.lk/assets/images/fav/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="https://www.cdb.lk/assets/images/fav/favicon-16x16.png">
<!DOCTYPE html>
<html lang="en"> -->
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
</head>
<body>

  <!-- Top bar -->
  <header class="border-bottom bg-white sticky-top">
    <div class="container-fluid py-2 d-flex align-items-center justify-content-between">
      <button class="btn btn-outline-primary d-lg-none" id="sidebarToggle" aria-label="Toggle menu">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="fw-semibold">Admin Portal</div>
      <div></div>
    </div>
  </header>

  <div id="app">
    <!-- LEFT SIDEBAR -->
    <nav id="sidebar" class="bg-light border-end position-fixed pt-3">
      <div class="px-3 mb-2 d-none d-lg-block">
        <span class="text-muted small">Navigation</span>
      </div>
      <div id="sideMenu" class="list-group list-group-flush">
        <?php
          // IMPORTANT:
          // side-menu.php should output simple <a href="page.php" class="list-group-item list-group-item-action">...</a>
          // Any link you DON'T want loaded via AJAX should have class="no-ajax" or rel="external" or a target attribute.
          include 'side-menu.php';
        ?>
      </div>
    </nav>

    <!-- RIGHT CONTENT AREA -->
    <main id="contentWrap">
      <div class="container-fluid py-3">
        <div id="contentArea" class="content font-size">
          <!-- Initial loader -->
          <div id="loader">
            <div class="spinner-border" role="status" aria-hidden="true"></div>
            <span>Loading…</span>
          </div>
        </div>
      </div>
    </main>
  </div>


  <script>
    (function() {
      const content  = $("#contentArea");
      const sidebar  = $("#sidebar");
      const menu     = $("#sideMenu");
      const defaultPage = "dashboard.php"; // change default landing page if needed

      const loaderHtml = `
        <div id="loader">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <span>Loading…</span>
        </div>`;

      function setActive(href) {
        const clean = (href || "").split("?")[0].split("#")[0];
        menu.find("a").removeClass("active");
        menu.find("a").each(function() {
          const aHref = ($(this).attr("href") || "").split("?")[0].split("#")[0];
          if (aHref === clean) $(this).addClass("active");
        });
      }

      function loadPage(page, push = true) {
        content.html(loaderHtml).load(page, function(response, status, xhr) {
          if (status === "error") {
            const msg = xhr && xhr.status ? (xhr.status + " " + xhr.statusText) : "Unknown error";
            content.html(`
              <div class="alert alert-danger shadow-sm">
                <div class="fw-semibold mb-1">Failed to load <code>${page}</code></div>
                <div>${msg}</div>
              </div>
            `);
          } else {
            setActive(page);
          }
        });

        if (push) {
          const url = new URL(window.location);
          url.searchParams.set("page", page);
          history.pushState({ page }, "", url);
        }

        // Close sidebar on mobile after navigation
        sidebar.removeClass("show");
      }

      // Intercept sidebar link clicks for AJAX load
      $(document).on("click", "#sideMenu a", function(e) {
        const href = $(this).attr("href");
        // Allow normal nav for external/disabled links
        if (!href || href === "#" || $(this).hasClass("no-ajax") || $(this).attr("rel") === "external" || $(this).attr("target")) {
          return; // let browser handle it
        }
        e.preventDefault();
        loadPage(href, true);
      });

      // Handle back/forward buttons
      window.addEventListener("popstate", function() {
        const p = (new URL(window.location)).searchParams.get("page") || defaultPage;
        loadPage(p, false);
      });

      // Mobile sidebar toggle
      $("#sidebarToggle").on("click", function() {
        sidebar.toggleClass("show");
      });

      // Initial page load
      $(function() {
        const initial = (new URL(window.location)).searchParams.get("page") || defaultPage;
        loadPage(initial, false);
      });
    })();
  </script>
  <script src="vehicle-approval.js?v=5"></script>
<script src="page-loader.js"></script>
</body>
</html>
