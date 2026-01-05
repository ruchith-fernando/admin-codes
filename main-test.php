<?php
session_start();
if (!isset($_SESSION['name'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Main</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<h1>Welcome to Main</h1>

<ul>
  <li><a href="dashboard.php">Dashboard</a></li>
  <li><a href="stock-in.php">Stock In</a></li>
</ul>

<div id="contentArea">Loading...</div>

<script>
function loadPage(href) {
  console.log("Loading:", href);
  $('#contentArea').html("Loading " + href + "...");
  $.get(href, function(data) {
    $('#contentArea').html(data);
  }).fail(function() {
    $('#contentArea').html('<div style="color:red;">Failed to load ' + href + '</div>');
  });
}

const params = new URLSearchParams(window.location.search);
const page = params.get('page') || 'dashboard.php';
loadPage(page);

// Also load pages when links are clicked
$('a').on('click', function(e) {
  e.preventDefault();
  const href = $(this).attr('href');
  loadPage(href);
});
</script>
</body>
</html>
