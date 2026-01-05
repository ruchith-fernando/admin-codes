<?php
// seed-cdma-details.php — run once, then delete or keep for future refreshes.
ini_set('display_errors','1'); error_reporting(E_ALL);
session_start();
require_once 'connections/connection.php';

mysqli_set_charset($conn, 'utf8mb4');

// 1) Ensure table exists
$ddl = <<<SQL
CREATE TABLE IF NOT EXISTS `tbl_admin_cdma_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscription_number` VARCHAR(20) NOT NULL,
  `allocated_to` VARCHAR(255) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_subscription_number` (`subscription_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!mysqli_query($conn, $ddl)) {
  http_response_code(500);
  exit("Failed to create table: " . mysqli_error($conn));
}

// 2) Your data (paste exactly as provided: "<number><tab or spaces><label>")
$raw = <<<TXT
117561004	Call Center - Number Shuffling SIP Trunk
117561005	Call Center - Number Shuffling SIP Trunk
117561006	Call Center - Number Shuffling SIP Trunk
117561007	Call Center - Number Shuffling SIP Trunk
117561008	Call Center - Number Shuffling SIP Trunk
117561009	Call Center - Number Shuffling SIP Trunk
117561091	Call Center - Number Shuffling SIP Trunk
117561092	Call Center - Number Shuffling SIP Trunk
117561093	Call Center - Number Shuffling SIP Trunk
117561094	Call Center - Number Shuffling SIP Trunk
117561095	Call Center - Number Shuffling SIP Trunk
117561096	Call Center - Number Shuffling SIP Trunk
117561097	Call Center - Number Shuffling SIP Trunk
117561098	Call Center - Number Shuffling SIP Trunk
117561099	Call Center - Number Shuffling SIP Trunk
117873425	Call Center - Number Shuffling SIP Trunk
117873426	Call Center - Number Shuffling SIP Trunk
117873427	Call Center - Number Shuffling SIP Trunk
117873428	Call Center - Number Shuffling SIP Trunk
117873429	Call Center - Number Shuffling SIP Trunk
117873430	Call Center - Number Shuffling SIP Trunk
117873431	Call Center - Number Shuffling SIP Trunk
117873432	Call Center - Number Shuffling SIP Trunk
117873433	Call Center - Number Shuffling SIP Trunk
117873434	Call Center - Number Shuffling SIP Trunk
117873435	Call Center - Number Shuffling SIP Trunk
117873436	Call Center - Number Shuffling SIP Trunk
117873437	Call Center - Number Shuffling SIP Trunk
117873438	Call Center - Number Shuffling SIP Trunk
117873439	Call Center - Number Shuffling SIP Trunk

TXT;

// 3) Prepare insert ... on duplicate key update
$sql = "INSERT INTO `tbl_admin_cdma_details`
        (`subscription_number`, `allocated_to`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `allocated_to` = VALUES(`allocated_to`)";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
  http_response_code(500);
  exit("Prepare failed: " . mysqli_error($conn));
}

$ins = $upd = $skipped = 0;

// 4) Parse and insert
$lines = preg_split('/\R/u', $raw);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') { continue; }
    // split into 2 parts: number + label (label can contain spaces)
    $parts = preg_split('/\s+/', $line, 2);
    if (count($parts) < 2) { $skipped++; continue; }
    $num   = trim($parts[0]);
    $label = trim($parts[1]);
    if ($num === '' || $label === '') { $skipped++; continue; }

    mysqli_stmt_bind_param($stmt, 'ss', $num, $label);
    if (!mysqli_stmt_execute($stmt)) {
        // you can log errors here if needed
        $skipped++;
        continue;
    }
    // affected_rows: 1 = insert, 2 = update (because of ON DUPLICATE KEY)
    $aff = mysqli_stmt_affected_rows($stmt);
    if ($aff === 1) $ins++;
    elseif ($aff === 2) $upd++;
    else $skipped++;
}

mysqli_stmt_close($stmt);

// 5) Done
header('Content-Type: text/plain; charset=UTF-8');
echo "CDMA details seeded.\nInserted: {$ins}\nUpdated: {$upd}\nSkipped: {$skipped}\n";
