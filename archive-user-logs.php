<?php
// archive-user-logs.php
require_once 'connections/connection.php';

echo "<pre>";

$months = 6;

$conn->begin_transaction();

try {
    // ✅ Step 1: Move older logs to archive
    $insert = "
        INSERT INTO tbl_admin_user_logs_archive 
        (user, hris, action, page, ip_address, ip_source, ip_chain, user_agent, created_at)
        SELECT user, hris, action, page, ip_address, ip_source, ip_chain, user_agent, created_at
        FROM tbl_admin_user_logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL $months MONTH)
    ";
    $conn->query($insert);
    $moved = $conn->affected_rows;

    // ✅ Step 2: Delete archived logs from main table
    $delete = "
        DELETE FROM tbl_admin_user_logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL $months MONTH)
    ";
    $conn->query($delete);

    $conn->commit();

    // ✅ Step 3: Find highest log number across archive + live
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

    // ✅ Step 4: Reset AUTO_INCREMENT to next
    $conn->query("ALTER TABLE tbl_admin_user_logs AUTO_INCREMENT = $next_num");

    // ✅ Step 5: Log archive action itself (headless mode)
    $ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    $ua = 'System Auto Archive';
    $hris = 'SYSTEM';

    $log_sql = "
        INSERT INTO tbl_admin_user_logs 
        (user, hris, action, page, ip_address, user_agent)
        VALUES ('$hris','$hris','Auto-archived $moved logs older than $months months','archive-user-logs.php','$ip','$ua')
    ";
    $conn->query($log_sql);

    echo "✅ Archived $moved records successfully.\n";
    echo "🔧 AUTO_INCREMENT set to start from $next_num.\n";
}
catch (Throwable $e) {
    $conn->rollback();
    echo "❌ Archive failed: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
