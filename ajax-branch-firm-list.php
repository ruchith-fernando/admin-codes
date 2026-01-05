<?php
// ajax-branch-firm-list.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$firm_id = intval($_POST['firm_id'] ?? 0);

if ($firm_id > 0) {
    // Filter by specific firm (not currently used by JS, but kept for flexibility)
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.branch_code,
            m.branch_name,
            f.firm_name
        FROM tbl_admin_branch_firm_map m
        LEFT JOIN tbl_admin_security_firms f 
            ON m.firm_id = f.id
        WHERE m.firm_id = ? 
          AND m.active = 'yes'
        ORDER BY CAST(m.branch_code AS UNSIGNED), m.branch_code
    ");
    $stmt->bind_param("i", $firm_id);
} else {
    // All active mappings for all firms
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.branch_code,
            m.branch_name,
            f.firm_name
        FROM tbl_admin_branch_firm_map m
        LEFT JOIN tbl_admin_security_firms f 
            ON m.firm_id = f.id
        WHERE m.active = 'yes'
        ORDER BY f.firm_name, CAST(m.branch_code AS UNSIGNED), m.branch_code
    ");
}

$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode(['success' => true, 'rows' => $rows]);
