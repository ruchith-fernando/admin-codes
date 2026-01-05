<?php
session_start();
if (!isset($_SESSION['name'])) {
    header("Location: index.php?redirect=stock-in.php");
    exit();
}
?>
<h2>✅ You are in STOCK-IN</h2>
