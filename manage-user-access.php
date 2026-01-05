<?php
// manage-user-access.php
include 'connections/connection.php';

// Fetch all users from tbl_admin_users
$users = [];
$user_result = $conn->query("SELECT id, username, name FROM tbl_admin_users ORDER BY username");
while ($row = $user_result->fetch_assoc()) {
    $users[] = $row;
}

// Fetch all available pages from tbl_admin_pages (with group names)
$pages = [];
$page_result = $conn->query("SELECT page_name, description, group_name FROM tbl_admin_pages ORDER BY group_name, page_name ASC");
while ($row = $page_result->fetch_assoc()) {
    $pages[] = $row;
}

$selected_username = '';
$selected_user_id = '';
$user_access = [];

// On POST (update access)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selected_user_id = $_POST['user_id'];
    $selected_username = $_POST['username'];
    $selected_pages = $_POST['pages'] ?? [];

    // Clear old access
    $stmt_del = $conn->prepare("DELETE FROM tbl_admin_user_page_access WHERE username = ?");
    $stmt_del->bind_param("s", $selected_username);
    $stmt_del->execute();
    $stmt_del->close();

    // Insert new access entries
    $stmt_ins = $conn->prepare("INSERT INTO tbl_admin_user_page_access (hris, username, page_name) VALUES (?, ?, ?)");
    foreach ($selected_pages as $page) {
        $stmt_ins->bind_param("sss", $selected_user_id, $selected_username, $page);
        $stmt_ins->execute();
    }
    $stmt_ins->close();

    echo "<script>alert('Access updated for $selected_username');</script>";
}

// On GET with user_id selection 
if (isset($_GET['user_id']) && $_GET['user_id'] != '') {
    $selected_user_id = $_GET['user_id'];

    // Get the username for display and access check
    $stmt_name = $conn->prepare("SELECT username FROM tbl_admin_users WHERE id = ?");
    $stmt_name->bind_param("i", $selected_user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($selected_username);
    $stmt_name->fetch();
    $stmt_name->close();

    // Load current access
    $stmt_acc = $conn->prepare("SELECT page_name FROM tbl_admin_user_page_access WHERE hris = ?");
    $stmt_acc->bind_param("s", $selected_user_id);
    $stmt_acc->execute();
    $result_acc = $stmt_acc->get_result();
    while ($row = $result_acc->fetch_assoc()) {
        $user_access[] = $row['page_name'];
    }
    $stmt_acc->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage User Page Access</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
</head>
<body class="bg-light">
<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Assign Page Access to Users</h4>

      <form method="GET" class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="user_id" class="form-label">Select User</label>
          <select name="user_id" id="user_id" class="form-select" required onchange="this.form.submit()">
            <option value="">-- Select User --</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>" <?= ($selected_user_id == $u['id']) ? 'selected' : '' ?>>
                <?= $u['username'] ?> - <?= $u['name'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>

      <?php if ($selected_user_id): ?>
        <form method="POST">
          <input type="hidden" name="user_id" value="<?= htmlspecialchars($selected_user_id) ?>">
          <input type="hidden" name="username" value="<?= htmlspecialchars($selected_username) ?>">

          <div class="mb-3">
            <label class="form-label">User: </label>
            <input type="text" class="form-control" value="<?= $selected_username ?> (ID: <?= $selected_user_id ?>)" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">Accessible Pages</label>
            <?php 
            $current_group = '';
              foreach ($pages as $p):
                if ($p['group_name'] !== $current_group):
                  $current_group = $p['group_name'];
                  echo "<h5 class='mt-4'>" . htmlspecialchars($current_group ?: 'Ungrouped') . "</h5>";
                endif;
              ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="pages[]" value="<?= $p['page_name'] ?>" 
                    id="page_<?= md5($p['page_name']) ?>" 
                    <?= in_array($p['page_name'], $user_access) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="page_<?= md5($p['page_name']) ?>">
                    <?= $p['description'] ? $p['description'] . " (" . $p['page_name'] . ")" : $p['page_name'] ?>
                  </label>
                </div>
              <?php endforeach; ?>

          </div>

          <button type="submit" class="btn btn-success">Update Access</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
