<?php
// change_password.php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.php');
    exit;
}

include 'connections/connection.php'; // $conn

$username = $_SESSION['username'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current user details
    $stmt = $conn->prepare("SELECT password FROM tbl_admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        // Fetch last 5 passwords
        $historyStmt = $conn->prepare("SELECT password FROM tbl_admin_user_password_history WHERE username = ? ORDER BY changed_at DESC LIMIT 5");
        $historyStmt->bind_param("s", $username);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();
        $oldPasswords = [];
        while ($row = $historyResult->fetch_assoc()) {
            $oldPasswords[] = $row['password'];
        }

        // Check if new password matches any of the last 5
        foreach ($oldPasswords as $oldHashedPassword) {
            if (password_verify($new_password, $oldHashedPassword)) {
                $error = "You cannot reuse your last 5 passwords.";
                break;
            }
        }

        if (empty($error)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update users table
            $update = $conn->prepare("UPDATE tbl_admin_users SET password = ?, password_changed_at = NOW() WHERE username = ?");
            $update->bind_param("ss", $hashed_password, $username);

            if ($update->execute()) {
                // Insert into password history
                $historyInsert = $conn->prepare("INSERT INTO tbl_admin_user_password_history (username, password) VALUES (?, ?)");
                $historyInsert->bind_param("ss", $username, $hashed_password);
                $historyInsert->execute();

                $success = "Password changed successfully.";
                   // Destroy current session for security
                session_destroy();

                // Redirect to login page
                header('Location: index.php?password_changed=1');
                exit;
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Change Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card {
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }
    .btn-primary {
      background: linear-gradient(135deg, #667eea, #764ba2);
      border: none;
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, #5a67d8, #6b46c1);
    }
    .input-group-text {
      cursor: pointer;
    }
  </style>
</head>
<body>

<div class="container font-size">
  <div class="col-md-6">
    <div class="card">
      <h3 class="text-center mb-4">Change Password</h3>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php endif; ?>

      <?php if (isset($_GET['expired'])): ?>
        <div class="alert alert-warning">
          Your password has expired. Please set a new password.
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label class="form-label">Current Password</label>
          <div class="input-group">
            <input type="password" name="current_password" class="form-control" required id="currentPassword">
            <span class="input-group-text" onclick="togglePassword('currentPassword')">&#128065;</span>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">New Password</label>
          <div class="input-group">
            <input type="password" name="new_password" class="form-control" required id="newPassword">
            <span class="input-group-text" onclick="togglePassword('newPassword')">&#128065;</span>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirm New Password</label>
          <div class="input-group">
            <input type="password" name="confirm_password" class="form-control" required id="confirmPassword">
            <span class="input-group-text" onclick="togglePassword('confirmPassword')">&#128065;</span>
          </div>
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-primary">Change Password</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  if (field.type === "password") {
    field.type = "text";
  } else {
    field.type = "password";
  }
}
</script>

</body>
</html>
