<?php
session_start();
header('Content-Type: application/json');

require_once 'connections/connection.php';
require_once 'includes/userlog.php';

// Only allow POST + backup_type
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['backup_type'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$backupType = $_POST['backup_type'];
$date = date('Y-m-d_H-i-s');

$backupDir = "backup_$date";   // temp folder (SQL file goes here)
$zipDir    = "backups";        // final ZIP files go here
if (!file_exists($zipDir)) mkdir($zipDir, 0775, true);

// ZIP name
$zipFilename = match ($backupType) {
    'db'    => "db-backup-$date.zip",
    'files' => "files-backup-$date.zip",
    'root'  => "root-files-backup-$date.zip",
    default => "full-backup-$date.zip"
};

$zipFileFullPath = "$zipDir/$zipFilename";

// temp folder
if (!file_exists($backupDir)) mkdir($backupDir, 0775, true);

// DB creds (move to config/env if possible)
$dbHost = '198.37.102.12';
$dbUser = 'admincodebuild_db_user';
$dbPass = 'RJ??,B)1o0=0';
$dbName = 'admincodebuild_admin';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit;
}

// --- DB dump (for db/full) ---
if ($backupType === 'db' || $backupType === 'full') {

    $dbDumpFile = "$backupDir/database.sql";

    $sql_dump  = "-- Backup of $dbName on $date\n\n";
    $sql_dump .= "SET NAMES utf8mb4;\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // tables + views
    $result = $conn->query("SHOW FULL TABLES");
    while ($row = $result->fetch_row()) {
        $name = $row[0];                 // table/view name
        $type = strtoupper($row[1]);     // BASE TABLE / VIEW

        // create statement
        $createRes = ($type === 'VIEW')
            ? $conn->query("SHOW CREATE VIEW `$name`")
            : $conn->query("SHOW CREATE TABLE `$name`");

        if (!$createRes) {
            $sql_dump .= "-- Could not read CREATE for `$name`: ".$conn->error."\n\n";
            continue;
        }

        $createRow = $createRes->fetch_row();
        $sql_dump .= $createRow[1] . ";\n\n";

        // data only for real tables
        if ($type === 'BASE TABLE') {
            $rows = $conn->query("SELECT * FROM `$name`");
            if ($rows) {
                while ($r = $rows->fetch_assoc()) {
                    $vals = array_map(function($v) use ($conn) {
                        return isset($v) ? "'" . $conn->real_escape_string($v) . "'" : "NULL";
                    }, $r);
                    $sql_dump .= "INSERT INTO `$name` VALUES (" . implode(",", $vals) . ");\n";
                }
                $sql_dump .= "\n";
            }
        }
    }

    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($dbDumpFile, $sql_dump);
}

// --- ZIP setup ---
$zip = new ZipArchive();
if ($zip->open($zipFileFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create ZIP']);
    exit;
}

$rootPath = realpath(".");

// --- Files backup (for files/full) ---
if ($backupType === 'files' || $backupType === 'full') {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if ($file->isDir()) continue;

        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($rootPath) + 1);

        // skip backups + temp folders
        if (str_starts_with($relativePath, 'backup_') || str_starts_with($relativePath, 'backups')) {
            continue;
        }

        $zip->addFile($filePath, $relativePath);
    }
}

// add DB dump into ZIP
if (($backupType === 'db' || $backupType === 'full') && isset($dbDumpFile) && file_exists($dbDumpFile)) {
    $zip->addFile($dbDumpFile, "database.sql");
}

// --- Root files only (for root) ---
if ($backupType === 'root') {
    foreach (scandir($rootPath) as $file) {
        if ($file === '.' || $file === '..') continue;

        $fullPath = $rootPath . DIRECTORY_SEPARATOR . $file;
        if (is_file($fullPath)) {
            $zip->addFile($fullPath, $file);
        }
    }
}

$zip->close();

// cleanup temp SQL
if (isset($dbDumpFile) && file_exists($dbDumpFile)) unlink($dbDumpFile);
@rmdir($backupDir);

// log
$hris = $_SESSION['hris'] ?? 'UNKNOWN';
$username = $_SESSION['name'] ?? 'SYSTEM';

$backupTypeLabel = [
    "db"   => "Database Only",
    "files"=> "Files and Folders",
    "root" => "Root Files Only",
    "full" => "Full Backup"
][$backupType] ?? $backupType;

userlog("✅ $username ($hris) created $backupTypeLabel: $zipFilename");

echo json_encode(['status' => 'success', 'filename' => $zipFileFullPath]);
exit;
