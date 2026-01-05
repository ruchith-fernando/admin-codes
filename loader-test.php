<?php
// loader-test.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>50+ CDB Loader Showcase</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/loaders.css/0.1.2/loaders.min.css" />
  <style>
    body, html {
      margin: 0;
      padding: 0;
      height: 100%;
      font-family: 'Segoe UI', sans-serif;
    }
    .loader-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(to right, #e30613, #004d99);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      flex-direction: column;
      color: #ffffff;
      transition: opacity 0.5s ease;
    }
    .hidden {
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.5s ease;
    }
    .grid-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 10px;
    }
    .btn-style {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .loader-box > div {
      transform: scale(2);
      color: #ffffff !important;
    }
  </style>
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2 class="mb-4">50+ CDB Branded Loader Showcase</h2>
  <div class="grid-container">
    <?php
      for ($i = 1; $i <= 50; $i++) {
        echo "<button class='btn btn-outline-primary btn-style' onclick=\"showLoader('loader$i')\">$i. Loader Style $i</button>";
      }
    ?>
  </div>
</div>
<div class="loader-overlay hidden" id="loader" onclick="hideLoader()">
  <div id="loaderContent" class="loader-box"></div>
</div>
<script>
  const loaderMap = {
    'loader1': '<div class="loader-inner ball-pulse"><div></div><div></div><div></div></div>',
    'loader2': '<div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div>',
    'loader3': '<div class="loader-inner ball-clip-rotate"><div></div></div>',
    'loader4': '<div class="loader-inner square-spin"><div></div></div>',
    'loader5': '<div class="loader-inner ball-clip-rotate-pulse"><div></div><div></div></div>',
    'loader6': '<div class="loader-inner ball-beat"><div></div><div></div><div></div></div>',
    'loader7': '<div class="loader-inner line-scale-pulse-out"><div></div><div></div><div></div><div></div><div></div></div>',
    'loader8': '<div class="loader-inner pacman"><div></div><div></div><div></div><div></div><div></div></div>',
    'loader9': '<div class="loader-inner ball-grid-pulse"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>',
    'loader10': '<div class="loader-inner ball-spin-fade-loader"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>',
  };
  for (let i = 11; i <= 50; i++) {
    loaderMap[`loader${i}`] = loaderMap[`loader${(i - 1) % 10 + 1}`];
  }

  function hideLoader() {
    document.getElementById('loader').classList.add('hidden');
    document.getElementById('loaderContent').innerHTML = '';
  }

  function showLoader(type) {
    const loader = document.getElementById('loader');
    const content = document.getElementById('loaderContent');
    loader.classList.remove('hidden');
    content.innerHTML = loaderMap[type] || '<h1>Loading...</h1>';
  }
</script>
</body>
</html>
