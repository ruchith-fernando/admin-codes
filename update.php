<?php
require_once 'connections/connection.php';

echo "<pre>";

// 1️⃣ Find the highest log number across BOTH archive and live
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
$start = (int)($rowMax['max_num'] ?? 0);

echo "Last global log number found (archive + live): $start\n";

// 2️⃣ Select only live logs that have no log_uid yet
$sqlSelect = "SELECT id FROM tbl_admin_user_logs WHERE log_uid IS NULL OR log_uid = '' ORDER BY id ASC";
$res = $conn->query($sqlSelect);

if (!$res || $res->num_rows === 0) {
    echo "✅ No missing log_uid values found in live table.\n";
    exit;
}

$count = 0;
$conn->begin_transaction();

try {
    while ($row = $res->fetch_assoc()) {
        $start++;
        $count++;
        $log_uid = sprintf('USER-LOG-%07d', $start);
        $stmt = $conn->prepare("UPDATE tbl_admin_user_logs SET log_uid = ? WHERE id = ?");
        $stmt->bind_param("si", $log_uid, $row['id']);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo "✅ Updated {$count} live rows.\n";
    echo "➡️  First new UID: USER-LOG-" . sprintf('%07d', $start - $count + 1) . "\n";
    echo "➡️  Last new UID:  USER-LOG-" . sprintf('%07d', $start) . "\n";

    // 3️⃣ Reset AUTO_INCREMENT to continue properly for future inserts
    $next = $start + 1;
    $conn->query("ALTER TABLE tbl_admin_user_logs AUTO_INCREMENT = $next");
    echo "🔧 AUTO_INCREMENT reset to start from ID $next.\n";
}
catch (Throwable $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
