<?php
// update-dashboard-selection.php
require 'connections/connection.php';
header('Content-Type: application/json');

// Start session only if not already active (prevents "session already active" notice)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['hris'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in.'
    ]);
    exit;
}

$user_id = mysqli_real_escape_string($conn, $_SESSION['hris']);

/**
 * Accept either standard form POST (data[]=...) or raw JSON like:
 * { "data": [ { "category": "...", "month": "..." }, ... ] }
 */
$data = null;

if (isset($_POST['data'])) {
    $data = $_POST['data'];
} else {
    // Try JSON body
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['data'])) {
            $data = $decoded['data'];
        }
    }
}

if (!is_array($data) || count($data) === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No data received or invalid format'
    ]);
    exit;
}

// ✅ Step 1: Reset selections by category for this user
$categories = array_unique(array_map(function($row) {
    return isset($row['category']) ? (string)$row['category'] : '';
}, $data));
$categories = array_filter($categories, fn($c) => $c !== '');

foreach ($categories as $category) {
    $category_esc = mysqli_real_escape_string($conn, $category);
    $reset_query = "
        UPDATE tbl_admin_dashboard_month_selection
        SET is_selected='no'
        WHERE category='$category_esc' AND user_id='$user_id'
    ";
    if (!$conn->query($reset_query)) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to reset selections for category: ' . htmlspecialchars($category)
        ]);
        exit;
    }
}

// ✅ Step 2: Insert/Update selected months for this user
foreach ($data as $item) {
    // Skip rows missing required fields
    if (!isset($item['month'], $item['category'])) {
        continue;
    }

    $month    = mysqli_real_escape_string($conn, (string)$item['month']);
    $category = mysqli_real_escape_string($conn, (string)$item['category']);

    $check_sql = "
        SELECT id
        FROM tbl_admin_dashboard_month_selection
        WHERE category='$category' AND month_name='$month' AND user_id='$user_id'
        LIMIT 1
    ";
    $check = $conn->query($check_sql);

    if ($check && $check->num_rows > 0) {
        $update_sql = "
            UPDATE tbl_admin_dashboard_month_selection
            SET is_selected='yes'
            WHERE category='$category' AND month_name='$month' AND user_id='$user_id'
        ";
        if (!$conn->query($update_sql)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Failed to update month: ' . htmlspecialchars($month)
            ]);
            exit;
        }
    } else {
        $insert_sql = "
            INSERT INTO tbl_admin_dashboard_month_selection (user_id, category, month_name, is_selected)
            VALUES ('$user_id', '$category', '$month', 'yes')
        ";
        if (!$conn->query($insert_sql)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Failed to insert month: ' . htmlspecialchars($month)
            ]);
            exit;
        }
    }
}

// ✅ Final Success Response
echo json_encode([
    'status'  => 'success',
    'message' => 'Dashboard selection updated'
]);
exit;
