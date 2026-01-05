<?php
// run-page-group-update.php
include 'connections/connection.php'; // make sure this sets up $conn

$sql_file = 'update_page_groups_v2.sql';

if (!file_exists($sql_file)) {
    die("SQL file not found.");
}

$queries = file_get_contents($sql_file);
$statements = explode(";", $queries); // split on semicolon

$success = 0;
$errors = [];

foreach ($statements as $query) {
    $query = trim($query);
    if ($query === '') continue;

    if ($conn->query($query)) {
        $success++;
    } else {
        $errors[] = "Error with query: <code>$query</code><br>MySQL error: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Page Group Update Result</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card p-4 shadow">
      <h4 class="text-primary">Page Group Update</h4>
      <p><strong><?= $success ?></strong> queries executed successfully.</p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <h5>Errors:</h5>
          <?php foreach ($errors as $err): ?>
            <p><?= $err ?></p>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-success">All group updates completed successfully.</div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
