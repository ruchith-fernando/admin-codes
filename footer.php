<!-- footer.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Loader Animation</title>
  <style>
    /* Fullscreen loader container */
    #globalLoader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(255, 255, 255, 0.9);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    /* Bar animation */
    .loader-inner.line-scale > div {
      height: 72px;
      width: 10.8px;
      margin: 3.6px;
      display: inline-block;
      animation: scaleStretchDelay 1.2s infinite ease-in-out;
    }

    /* CDB colors: Blue and Red */
    .loader-inner.line-scale > div:nth-child(odd) {
      background-color: #0070C0; /* CDB Blue */
    }

    .loader-inner.line-scale > div:nth-child(even) {
      background-color: #E60028; /* CDB Red */
    }

    /* Animation delay for each bar */
    .loader-inner.line-scale > div:nth-child(1) { animation-delay: -1.2s; }
    .loader-inner.line-scale > div:nth-child(2) { animation-delay: -1.1s; }
    .loader-inner.line-scale > div:nth-child(3) { animation-delay: -1.0s; }
    .loader-inner.line-scale > div:nth-child(4) { animation-delay: -0.9s; }
    .loader-inner.line-scale > div:nth-child(5) { animation-delay: -0.8s; }

    /* Keyframes for the stretching effect */
    @keyframes scaleStretchDelay {
      0%, 40%, 100% { transform: scaleY(0.4); }
      20% { transform: scaleY(1.0); }
    }
  </style>
</head>
<body>

  <!-- Loader HTML -->
  <!-- <div id="globalLoader">
    <div class="loader-inner line-scale">
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
    </div>
  </div> -->

</body>
</html>
