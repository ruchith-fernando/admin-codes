<?php
// archive-user-logs-ajax.php
session_start();
require_once 'connections/connection.php';
header('Content-Type: application/json');

try {
    // ✅ Restrict access — only HRIS 01006428 can run this
    if (!isset($_SESSION['hris']) || $_SESSION['hris'] !== '01006428') {
        throw new Exception('Unauthorized access.');
    }

    $months = 6; // Default threshold for old logs
    $conn->begin_transaction();

    // ✅ Step 1: Auto-repair duplicate or missing log_uid entries
    // 1a. Find duplicates and reassign them unique numbers
    $repair_sql = "
        WITH all_logs AS (
            SELECT log_uid, id
            FROM tbl_admin_user_logs
            UNION ALL
            SELECT log_uid, id
            FROM tbl_admin_user_logs_archive
        )
        SELECT log_uid, COUNT(*) AS cnt
        FROM all_logs
        GROUP BY log_uid
        HAVING cnt > 1;
    ";
    $duplicates = $conn->query($repair_sql);

    if ($duplicates && $duplicates->num_rows > 0) {
        // Find current maximum sequence first
        $sqlMax = "
            SELECT MAX(num) AS max_num FROM (
                SELECT MAX(CAST(SUBSTRING_INDEX(log_uid, '-', -1) AS UNSIGNED)) AS num
                FROM tbl_admin_user_logs_archive
                UNION ALL
                SELECT MAX(CAST(SUBSTRING_INDEX(log_uid, '-', -1) AS UNSIGNED)) AS num
                FROM tbl_admin_user_logs
            ) AS combined
        ";
        $resMax = $conn->query($sqlMax);
        $rowMax = $resMax ? $resMax->fetch_assoc() : ['max_num' => 0];
        $seq = (int)($rowMax['max_num'] ?? 0);

        while ($dup = $duplicates->fetch_assoc()) {
            $dup_uid = $conn->real_escape_string($dup['log_uid']);
            $res_dupes = $conn->query("
                SELECT id FROM (
                    SELECT id FROM tbl_admin_user_logs WHERE log_uid = '$dup_uid'
                    UNION ALL
                    SELECT id FROM tbl_admin_user_logs_archive WHERE log_uid = '$dup_uid'
                ) AS dups
                ORDER BY id ASC
            ");
            $skip_first = true;
            while ($row = $res_dupes->fetch_assoc()) {
                if ($skip_first) { // Keep first occurrence
                    $skip_first = false;
                    continue;
                }
                $seq++;
                $new_uid = 'USER-LOG-' . sprintf('%07d', $seq);
                // Try to update in both tables (whichever contains this ID)
                $conn->query("UPDATE tbl_admin_user_logs SET log_uid = '$new_uid' WHERE id = {$row['id']}");
                $conn->query("UPDATE tbl_admin_user_logs_archive SET log_uid = '$new_uid' WHERE id = {$row['id']}");
            }
        }
    }

    // ✅ Step 2: Find global max log number AFTER repair (before archiving)
    $sqlMax = "
        SELECT MAX(num) AS max_num FROM (
            SELECT MAX(CAST(SUBSTRING_INDEX(log_uid, '-', -1) AS UNSIGNED)) AS num
            FROM tbl_admin_user_logs_archive
            UNION ALL
            SELECT MAX(CAST(SUBSTRING_INDEX(log_uid, '-', -1) AS UNSIGNED)) AS num
            FROM tbl_admin_user_logs
        ) AS combined
    ";
    $resMax = $conn->query($sqlMax);
    $rowMax = $resMax ? $resMax->fetch_assoc() : ['max_num' => 0];
    $last_num = (int)($rowMax['max_num'] ?? 0);
    $next_num = $last_num + 1;

    // ✅ Step 3: Build WHERE condition
    $where = "WHERE created_at < DATE_SUB(NOW(), INTERVAL $months MONTH)";
    if ($_SESSION['hris'] === '01006428') {
        $where = ''; // full archive for authorized HRIS
    }

    // ✅ Step 4: Move logs to archive (include log_uid)
    $insert = "
        INSERT INTO tbl_admin_user_logs_archive 
        (user, hris, log_uid, action, page, ip_address, ip_source, ip_chain, user_agent, created_at)
        SELECT user, hris, log_uid, action, page, ip_address, ip_source, ip_chain, user_agent, created_at
        FROM tbl_admin_user_logs
        $where
    ";
    if (!$conn->query($insert)) {
        throw new Exception('Failed to insert archive records: ' . $conn->error);
    }
    $moved = $conn->affected_rows;

    // ✅ Step 5: Delete moved rows
    $delete = "DELETE FROM tbl_admin_user_logs $where";
    if (!$conn->query($delete)) {
        throw new Exception('Failed to delete old logs: ' . $conn->error);
    }

    $conn->commit();

    // ✅ Step 6: Reset AUTO_INCREMENT to next global number
    $conn->query("ALTER TABLE tbl_admin_user_logs AUTO_INCREMENT = $next_num");

    // ✅ Step 7: Log this archive operation with a proper log_uid
    $user = $conn->real_escape_string($_SESSION['hris']);
    $ip   = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
    $ua   = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');

    $log_uid = 'USER-LOG-' . sprintf('%07d', $next_num);
    $action  = "Archived $moved logs (manual archive)";

    $log_sql = "
        INSERT INTO tbl_admin_user_logs 
        (user, hris, log_uid, action, page, ip_address, user_agent)
        VALUES ('$user','$user','$log_uid','$action','user-log-report.php','$ip','$ua')
    ";
    if (!$conn->query($log_sql)) {
        throw new Exception('Failed to insert archive log entry: ' . $conn->error);
    }

    echo json_encode([
        'status'   => 'success',
        'message'  => "✅ Archived $moved logs successfully. Numbering continues from $log_uid.",
        'log_uid'  => $log_uid,
        'archived' => $moved
    ]);
}
catch (Throwable $e) {
    if (isset($conn) && $conn->errno) {
        $conn->rollback();
    }
    echo json_encode([
        'status'  => 'error',
        'message' => '❌ ' . $e->getMessage()
    ]);
}
?>
