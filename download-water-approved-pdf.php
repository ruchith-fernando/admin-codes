<?php
// download-water-approved-pdf.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$hris = $_SESSION['hris'] ?? 'N/A';
$name = $_SESSION['name'] ?? 'Unknown';
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if (!isset($_GET['file'])) {
    die("No file specified.");
}

$file = basename($_GET['file']); 
$path = __DIR__ . "/exports/" . $file;

if (!file_exists($path)) {
    userlog("❌ DOWNLOAD FAILED — File Missing: $file | User: $name ($hris) | IP: $ip");
    die("File does not exist.");
}

// Log successful download
userlog("⬇ DOWNLOAD — File: $file | User: $name ($hris) | IP: $ip");

// Send the file
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
?>
