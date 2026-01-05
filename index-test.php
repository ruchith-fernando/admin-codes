<?php
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'connections/connection.php';

    $username = $_POST['username'];
    $password = $_POST['password'];
    $redirect = $_POST['redirect'] ?? 'main.php';

    // Simulated user (replace with your DB logic)
    if ($username === 'admin' && $password === '123') {
        $_SESSION['name'] = 'Admin User';

        // Force redirect to main.php?page=...
        if (!str_contains($redirect, 'main.php')) {
            $redirect = 'main.php?page=' . urlencode($redirect);
        }
        header("Location: $redirect");
        exit;
    } else {
        $error = "Invalid login";
    }
}
?>

<form method="POST">
  <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? ''); ?>">
  <label>Username</label>
  <input type="text" name="username" required><br>
  <label>Password</label>
  <input type="password" name="password" required><br>
  <button type="submit">Login</button>
  <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>
</form>
