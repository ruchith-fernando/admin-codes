<?php
include 'connections/connection.php';

// Use the same hashed password for all users ("Test@123")
$hashed_password = '$2y$10$DwWJpAeP7qjhuMCg0X2z..UIvEAhJ/Yjvfx61TDlqPSQgX53KMyoy';

// Users data: name, username, user_level, category
$users = [
    // Requestors
    ['Requestor 1', 'requestor1', 'requestor', 'Marketing'],
    ['Requestor 2', 'requestor2', 'requestor', 'Branch Operation'],
    ['Requestor 3', 'requestor3', 'requestor', 'Operation'],

    // Recommenders
    ['Marketing Recommender', 'recomm_marketing', 'asm_rm', 'Marketing'],
    ['Branch Recommender', 'recomm_branch', 'cluster_leader', 'Branch Operation'],
    ['Ops Recommender', 'recomm_ops', 'division_head', 'Operation'],

    // Approvers
    ['Marketing Approver 1', 'approver_mkt1', 'agm_sales', 'Marketing'],
    ['Marketing Approver 2', 'approver_mkt2', 'dgm_sales', 'Marketing'],
    ['Branch Approver', 'approver_branch', 'head_branch_ops', 'Branch Operation'],
    ['Ops Approver 1', 'approver_ops1', 'agm_operations', 'Operation'],
    ['Ops Approver 2', 'approver_ops2', 'dgm_operations', 'Operation'],

    // Acceptor
    ['Main Acceptor', 'acceptor_main', 'acceptor', 'All'],

    // Issuer
    ['Main Issuer', 'issuer_main', 'issuer', 'All'],

    // Admin
    ['Super Admin', 'admin_main', 'admin', 'All']
];

$success = 0;
$fail = 0;

foreach ($users as $user) {
    [$name, $username, $user_level, $category] = $user;

    // Check if the username already exists to avoid duplicates
    $check = $conn->prepare("SELECT id FROM tbl_admin_users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<div style='color:orange;'>Skipped (already exists): $username</div>";
        $check->close();
        continue;
    }
    $check->close();

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO tbl_admin_users (name, username, password, user_level, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $username, $hashed_password, $user_level, $category);

    if ($stmt->execute()) {
        echo "<div style='color:green;'>✅ User $username inserted successfully.</div>";
        $success++;
    } else {
        echo "<div style='color:red;'>❌ Failed to insert $username: " . $stmt->error . "</div>";
        $fail++;
    }

    $stmt->close();
}

echo "<hr><strong>$success users added. $fail failed.</strong>";
?>
