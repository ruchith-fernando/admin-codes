<?php
// nocache.php – include this at the very top of every PHP file (before HTML)

// Prevent caching by browser and proxies
// header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
// header("Cache-Control: post-check=0, pre-check=0", false);
// header("Pragma: no-cache");
// header("Expires: 0");

// // Extra: some proxies (like nginx) respect this
// if (!headers_sent()) {
//     header("X-Accel-Expires: 0");
// }
?>
