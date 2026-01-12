<?php
require_once 'connections/connection.php';
require_once 'includes/helpers.php';
date_default_timezone_set('Asia/Colombo');

$rows = [];
$rs = $conn->query("
  SELECT id, mobile_no, hris_no, name_of_employee, voice_data, connection_status
  FROM tbl_admin_mobile_issues
  WHERE issue_status='Pending'
  ORDER BY id DESC
  LIMIT 500
");
if ($rs) {
  while ($r = $rs->fetch_assoc()) $rows[] = $r;
}
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Mobile Allocation — Pending Approvals</h5>

      <?php if (!count($rows)): ?>
        <?= bs_alert('success', '✅ No pending records.') ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>Issue ID</th>
                <th>Mobile</th>
                <th>HRIS</th>
                <th>Owner</th>
                <th>Voice/Data</th>
                <th>Conn</th>
                <th style="width:120px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><b><?= esc($r['id']) ?></b></td>
                  <td><?= esc($r['mobile_no']) ?></td>
                  <td><?= esc($r['hris_no']) ?></td>
                  <td><?= esc($r['name_of_employee']) ?></td>
                  <td><?= esc($r['voice_data']) ?></td>
                  <td><?= esc($r['connection_status']) ?></td>
                  <td>
                    <a class="btn btn-sm btn-primary"
                       href="mobile-allocation-complete.php?issue_id=<?= (int)$r['id'] ?>">
                      Open
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
