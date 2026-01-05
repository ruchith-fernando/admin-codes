<?php
session_start();
header('Content-Type: application/json');
require_once 'connections/connection.php'; 
require_once 'includes/userlog.php';

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['backup_type'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$backupType = $_POST['backup_type'];
$date = date('Y-m-d_H-i-s');
$backupDir = "backup_$date";
$zipDir = "backups"; // Save zip in dedicated folder
if (!file_exists($zipDir)) mkdir($zipDir, 0775, true);

$zipFilename = match ($backupType) {
    'db' => "db-backup-$date.zip",
    'files' => "files-backup-$date.zip",
    'root' => "root-files-backup-$date.zip",
    default => "full-backup-$date.zip"
};

$zipFileFullPath = "$zipDir/$zipFilename";

// Create temp folder
if (!file_exists($backupDir)) mkdir($backupDir, 0775, true);

// DB credentials
$dbHost = '198.37.102.12';
$dbUser = 'admincodebuild_db_user';
$dbPass = 'RJ??,B)1o0=0';
$dbName = 'admincodebuild_admin';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit;
}

// Database dump if needed
if ($backupType === 'db' || $backupType === 'full') {
    $dbDumpFile = "$backupDir/database.sql";
    $sql_dump = "-- Backup of $dbName on $date\n\n";

    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) $tables[] = $row[0];

    foreach ($tables as $table) {
        $sql_dump .= "-- Table `$table`\n";
        $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc();
        $sql_dump .= $create['Create Table'] . ";\n\n";

        $rows = $conn->query("SELECT * FROM `$table`");
        while ($row = $rows->fetch_assoc()) {
            $vals = array_map(function($v) use ($conn) {
                return isset($v) ? "'" . $conn->real_escape_string($v) . "'" : "NULL";
            }, $row);
            $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(",", $vals) . ");\n";
        }
        $sql_dump .= "\n";
    }

    file_put_contents($dbDumpFile, $sql_dump);
}

$zip = new ZipArchive();
if ($zip->open($zipFileFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create ZIP']);
    exit;
}

$rootPath = realpath(".");

// Backup files if needed
if ($backupType === 'files' || $backupType === 'full') {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);

            if (str_starts_with($relativePath, 'backup_') || str_starts_with($relativePath, 'backups') || $relativePath === $zipFilename) {
                continue;
            }

            $zip->addFile($filePath, $relativePath);
        }
    }
}

// DB file into ZIP
if (($backupType === 'db' || $backupType === 'full') && file_exists($dbDumpFile)) {
    $zip->addFile($dbDumpFile, "database.sql");
}

// Root files only
if ($backupType === 'root') {
    foreach (scandir($rootPath) as $file) {
        if ($file === '.' || $file === '..') continue;
        $fullPath = $rootPath . DIRECTORY_SEPARATOR . $file;
        if (is_file($fullPath) && $file !== $zipFilename) {
            $zip->addFile($fullPath, $file);
        }
    }
}

$zip->close();

// Cleanup temporary dir
if (isset($dbDumpFile) && file_exists($dbDumpFile)) unlink($dbDumpFile);
@rmdir($backupDir);

// ✅ Log backup action
$hris = $_SESSION['hris'] ?? 'UNKNOWN';
$username = $_SESSION['name'] ?? 'SYSTEM';

$backupTypeLabel = [
    "db" => "Database Only",
    "files" => "Files and Folders",
    "root" => "Root Files Only",
    "full" => "Full Backup"
][$backupType] ?? $backupType;

userlog("✅ $username ($hris) created $backupTypeLabel: $zipFilename");

echo json_encode(['status' => 'success', 'filename' => $zipFileFullPath]);
exit;
