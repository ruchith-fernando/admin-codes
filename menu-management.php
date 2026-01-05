<?php
session_start();
include 'connections/connection.php';

$statusMsg = "";

// Handle Add Menu submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_key = trim($_POST['menu_key']);
    $menu_label = trim($_POST['menu_label']);
    $menu_group = trim($_POST['menu_group']);
    $menu_file = trim($_POST['menu_file']);
    $menu_order = intval($_POST['menu_order']);

    if ($menu_key && $menu_label && $menu_group && $menu_file) {
        // Check duplicate
        $check = mysqli_query($conn, "SELECT id FROM tbl_admin_menu_keys WHERE menu_key='$menu_key'");
        if (mysqli_num_rows($check) > 0) {
            $statusMsg = '<div class="alert alert-danger">Error: Menu Key already exists.</div>';
        } else {
            $insert = mysqli_query($conn, "INSERT INTO tbl_admin_menu_keys(menu_key, menu_label, menu_group, menu_file, menu_order) 
                VALUES('$menu_key', '$menu_label', '$menu_group', '$menu_file', '$menu_order')");
            if ($insert) {
                $statusMsg = '<div class="alert alert-success">Menu Item added successfully.</div>';
            } else {
                $statusMsg = '<div class="alert alert-danger">Error saving menu item.</div>';
            }
        }
    } else {
        $statusMsg = '<div class="alert alert-warning">All fields are required.</div>';
    }
}
?>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4 mt-4">
            <h5 class="mb-4 text-primary">Menu Management</h5>

            <?php echo $statusMsg; ?>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="menu_key" class="form-label">Menu Key (Unique)</label>
                        <input type="text" class="form-control" id="menu_key" name="menu_key" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="menu_label" class="form-label">Menu Label (Display Text)</label>
                        <input type="text" class="form-control" id="menu_label" name="menu_label" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="menu_group" class="form-label">Menu Group</label>
                        <input type="text" class="form-control" id="menu_group" name="menu_group" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="menu_file" class="form-label">Menu File Path (e.g., security-report.php)</label>
                        <input type="text" class="form-control" id="menu_file" name="menu_file" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="menu_order" class="form-label">Menu Order</label>
                        <input type="number" class="form-control" id="menu_order" name="menu_order" value="0">
                    </div>
                </div>

                <button type="submit" class="btn btn-success">Add Menu Item</button>
            </form>

            <hr class="my-4">

            <h6 class="text-secondary mb-3">Existing Menu Items</h6>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Menu Key</th>
                        <th>Label</th>
                        <th>Group</th>
                        <th>File</th>
                        <th>Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = mysqli_query($conn, "SELECT * FROM tbl_admin_menu_keys ORDER BY menu_group, menu_order, menu_label");
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['menu_key']}</td>
                            <td>{$row['menu_label']}</td>
                            <td>{$row['menu_group']}</td>
                            <td>{$row['menu_file']}</td>
                            <td>{$row['menu_order']}</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
