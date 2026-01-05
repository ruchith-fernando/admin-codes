<?php
// <!-- ajax-login-user.php -->
define('SKIP_SESSION_CHECK', true);

session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

error_reporting(E_ALL);

include 'connections/connection.php';

// Optional include for user action log
require_once 'includes/userlog.php';

// Response helper
function respond($status, $message, $extra = []) {
    $response = array_merge(['status' => $status, 'message' => $message], $extra);
    echo json_encode($response);
    exit;
}

// Get data
$hris     = trim($_POST['hris'] ?? '');
$password = $_POST['password'] ?? '';
$redirect = trim($_POST['redirect'] ?? '') ?: 'main.php';

if (!$hris || !$password) respond("error", "Please fill in both HRIS and Password.");
if (!preg_match('/^\d{8}$/', $hris)) respond("error", "HRIS must be exactly 8 digits.");
if (!isset($conn) || !$conn) respond("error", "Database connection error.");

// Check recent login attempts
$stmt = $conn->prepare("
    SELECT COUNT(*) AS attempts 
    FROM tbl_admin_login_attempts 
    WHERE hris = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)
");
if (!$stmt) respond("error", "Login attempt check failed: " . $conn->error);
$stmt->bind_param("s", $hris);
$stmt->execute();
$attempts = $stmt->get_result()->fetch_assoc()['attempts'] ?? 0;

if ($attempts >= 5) {
    respond("error", "Too many failed login attempts. Please try again after 15 minutes.");
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM tbl_admin_users WHERE hris = ?");
if (!$stmt) respond("error", "User fetch failed: " . $conn->error);
$stmt->bind_param("s", $hris);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {

    // Store session values
    $_SESSION['loggedin'] = true;
    $_SESSION['user'] = $user;

    $_SESSION['id'] = $user['id'];
    $_SESSION['hris'] = $user['hris'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['user_level'] = $user['user_level'];
    $_SESSION['category'] = $user['category'];
    $_SESSION['designation'] = $user['designation'];
    $_SESSION['title'] = $user['title'];
    $_SESSION['company_hierarchy'] = $user['company_hierarchy'];
    $_SESSION['location'] = $user['location'];
    $_SESSION['category_auto'] = $user['category_auto'];
    $_SESSION['branch_code'] = $user['branch_code'];
    $_SESSION['branch_name'] = $user['branch_name'];

    // Clear failed attempts on successful login
    $clear = $conn->prepare("DELETE FROM tbl_admin_login_attempts WHERE hris = ?");
    if ($clear) {
        $clear->bind_param("s", $hris);
        $clear->execute();
    }

    // Log successful login
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $msg = sprintf(
            "✅ Successful login | HRIS: %s | User: %s | IP: %s | Browser: %s",
            $user['hris'], $user['name'], $ip, substr($browser, 0, 120)
        );
        userlog($msg);
    } catch (Throwable $e) {
        // Silent fail
    }

    // Password expiry check (90 days)
    if (!empty($user['password_changed_at'])) {
        $now = new DateTime();
        $changed = new DateTime($user['password_changed_at']);
        $minutesSinceChange = ($now->getTimestamp() - $changed->getTimestamp()) / 60;

        if ($minutesSinceChange >= 259200) { // 90 days
            respond("success", "Password expired", [
                "redirect" => "change-password.php?expired=1"
            ]);
        }
    }

    // Handle redirect
    if (!str_contains($redirect, 'main.php')) {
        $redirect = 'main.php?page=' . urlencode($redirect);
    }

    respond("success", "Login successful", ["redirect" => $redirect]);

} else {
    // Log failed login attempt
    $fail = $conn->prepare("INSERT INTO tbl_admin_login_attempts (hris) VALUES (?)");
    if ($fail) {
        $fail->bind_param("s", $hris);
        $fail->execute();
    }

    // Log failed login
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $msg = sprintf("❌ Failed login attempt | HRIS: %s | IP: %s", $hris, $ip);
        userlog($msg);
    } catch (Throwable $e) {
        // Silent fail
    }

    respond("error", "Invalid HRIS or password.");
}
?>
