<?php
// File: water-branch-water-map.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

if (empty($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

$errors  = [];
$success = '';
$data = [
    'branch_code'       => '',
    'water_type_id'     => '',
    'vendor_id'         => '',
    'no_of_machines'    => '',
    'account_number'    => '',
    'rate'              => '',
    'monthly_charge'    => '',
    'bottle_rate'       => '',
    'cooler_rental_rate'=> '',
    'sscl_percentage'   => '',
    'vat_percentage'    => '',
    'rate_profile_id'   => '',
];

$branches     = [];
$waterTypes   = [];
$waterVendors = [];
$rateProfiles = [];

if ($res = $conn->query("SELECT branch_code, branch_name FROM tbl_admin_branches WHERE is_active = 1 ORDER BY branch_name")) {
    $branches = $res->fetch_all(MYSQLI_ASSOC);
}
if ($res = $conn->query("SELECT water_type_id, water_type_name, water_type_code FROM tbl_admin_water_types WHERE is_active = 1 ORDER BY water_type_name")) {
    $waterTypes = $res->fetch_all(MYSQLI_ASSOC);
}
if ($res = $conn->query("SELECT vendor_id, vendor_name FROM tbl_admin_water_vendors WHERE is_active = 1 ORDER BY vendor_name")) {
    $waterVendors = $res->fetch_all(MYSQLI_ASSOC);
}
if ($res = $conn->query("SELECT rate_profile_id, profile_name FROM tbl_admin_water_rate_profiles WHERE is_active = 1 ORDER BY profile_name")) {
    $rateProfiles = $res->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $v) {
        $data[$k] = trim($_POST[$k] ?? '');
    }

    $data['water_type_id']   = (int)($data['water_type_id'] ?: 0);
    $data['vendor_id']       = $data['vendor_id']       !== '' ? (int)$data['vendor_id'] : null;
    $data['rate_profile_id'] = $data['rate_profile_id'] !== '' ? (int)$data['rate_profile_id'] : null;

    if ($data['branch_code'] === '') $errors[] = 'Branch is required.';
    if (!$data['water_type_id'])     $errors[] = 'Water type is required.';

    if (!$errors) {
        $sql = "
            INSERT INTO tbl_admin_branch_water
                (branch_code, branch_name, water_type_id, vendor_id,
                 no_of_machines, account_number, rate, monthly_charge,
                 bottle_rate, cooler_rental_rate, sscl_percentage,
                 vat_percentage, rate_profile_id)
            VALUES (
                ?, 
                (SELECT branch_name FROM tbl_admin_branches WHERE branch_code = ? LIMIT 1),
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                vendor_id          = VALUES(vendor_id),
                no_of_machines     = VALUES(no_of_machines),
                account_number     = VALUES(account_number),
                rate               = VALUES(rate),
                monthly_charge     = VALUES(monthly_charge),
                bottle_rate        = VALUES(bottle_rate),
                cooler_rental_rate = VALUES(cooler_rental_rate),
                sscl_percentage    = VALUES(sscl_percentage),
                vat_percentage     = VALUES(vat_percentage),
                rate_profile_id    = VALUES(rate_profile_id)
        ";

        if ($stmt = $conn->prepare($sql)) {
            $no_of_machines = $data['no_of_machines']    !== '' ? (int)$data['no_of_machines']    : null;
            $rate           = $data['rate']              !== '' ? (float)$data['rate']            : null;
            $monthly_charge = $data['monthly_charge']    !== '' ? (float)$data['monthly_charge']  : null;
            $bottle_rate    = $data['bottle_rate']       !== '' ? (float)$data['bottle_rate']     : null;
            $cooler_rental  = $data['cooler_rental_rate']!== '' ? (float)$data['cooler_rental_rate'] : null;
            $sscl           = $data['sscl_percentage']   !== '' ? (float)$data['sscl_percentage'] : null;
            $vat            = $data['vat_percentage']    !== '' ? (float)$data['vat_percentage']  : null;

            $stmt->bind_param(
                "ssiiisddddddi",
                $data['branch_code'],
                $data['branch_code'],
                $data['water_type_id'],
                $data['vendor_id'],
                $no_of_machines,
                $data['account_number'],
                $rate,
                $monthly_charge,
                $bottle_rate,
                $cooler_rental,
                $sscl,
                $vat,
                $data['rate_profile_id']
            );
            if ($stmt->execute()) {
                $success = 'Branch water configuration saved.';
                userlog('Branch water configuration saved', $data);
            } else {
                $errors[] = 'Database error while saving configuration.';
                userlog('Branch water configuration failed', ['error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            $errors[] = 'Failed to prepare statement.';
            userlog('Branch water configuration prepare failed', ['error' => $conn->error]);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Branch Water Configuration</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
</head>
<body>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">Branch Water Configuration — Add / Edit</h5>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Branch <span class="text-danger">*</span></label>
            <select name="branch_code" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach ($branches as $b): ?>
                <option value="<?= $b['branch_code'] ?>"
                    <?= $data['branch_code']===$b['branch_code']?'selected':'' ?>>
                  <?= htmlspecialchars($b['branch_name']) ?> (<?= htmlspecialchars($b['branch_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Water Type <span class="text-danger">*</span></label>
            <select name="water_type_id" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach ($waterTypes as $wt): ?>
                <option value="<?= $wt['water_type_id'] ?>"
                    <?= (int)$data['water_type_id']===(int)$wt['water_type_id']?'selected':'' ?>>
                  <?= htmlspecialchars($wt['water_type_name']) ?> (<?= htmlspecialchars($wt['water_type_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Vendor</label>
            <select name="vendor_id" class="form-select">
              <option value="">-- Select --</option>
              <?php foreach ($waterVendors as $v): ?>
                <option value="<?= $v['vendor_id'] ?>"
                    <?= (string)$data['vendor_id']===(string)$v['vendor_id']?'selected':'' ?>>
                  <?= htmlspecialchars($v['vendor_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">No. of Machines</label>
            <input type="number" name="no_of_machines" class="form-control"
                   value="<?= htmlspecialchars($data['no_of_machines']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Account Number</label>
            <input type="text" name="account_number" class="form-control"
                   value="<?= htmlspecialchars($data['account_number']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Rate</label>
            <input type="number" step="0.01" name="rate" class="form-control"
                   value="<?= htmlspecialchars($data['rate']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Monthly Charge</label>
            <input type="number" step="0.01" name="monthly_charge" class="form-control"
                   value="<?= htmlspecialchars($data['monthly_charge']) ?>">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Bottle Rate</label>
            <input type="number" step="0.01" name="bottle_rate" class="form-control"
                   value="<?= htmlspecialchars($data['bottle_rate']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Cooler Rental Rate</label>
            <input type="number" step="0.01" name="cooler_rental_rate" class="form-control"
                   value="<?= htmlspecialchars($data['cooler_rental_rate']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">SSCL %</label>
            <input type="number" step="0.01" name="sscl_percentage" class="form-control"
                   value="<?= htmlspecialchars($data['sscl_percentage']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">VAT %</label>
            <input type="number" step="0.01" name="vat_percentage" class="form-control"
                   value="<?= htmlspecialchars($data['vat_percentage']) ?>">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Rate Profile</label>
            <select name="rate_profile_id" class="form-select">
              <option value="">-- Select --</option>
              <?php foreach ($rateProfiles as $rp): ?>
                <option value="<?= $rp['rate_profile_id'] ?>"
                    <?= (string)$data['rate_profile_id']===(string)$rp['rate_profile_id']?'selected':'' ?>>
                  <?= htmlspecialchars($rp['profile_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-primary">Save Configuration</button>
        </div>
      </form>

    </div>
  </div>
</div>

</body>
</html>
