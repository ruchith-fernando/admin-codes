<?php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';

function esc($v){return htmlspecialchars($v ?? '', ENT_QUOTES,'UTF-8');}
function format_amount($v){return is_numeric($v)?'Rs. '.number_format((float)$v,2,'.',','):'-';}

// Get CSV filename from query string
$file = $_GET['file'] ?? '';
$path = __DIR__ . '/exports/' . basename($file);

if (!file_exists($path)) {
  echo "<div class='alert alert-danger m-5'>❌ File not found or has expired.</div>";
  exit;
}

$rows = array_map('str_getcsv', file($path));
$header = array_shift($rows);
?>

<div class="content font-size">
  <div class="container-fluid mt-4">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-success mb-0">✅ Approved Water Records Summary</h5>
        <div>
          <a href="exports/<?= esc($file) ?>" class="btn btn-outline-primary btn-sm" download>⬇️ Download CSV</a>
          <a href="water-pending-approvals.php" class="btn btn-outline-secondary btn-sm ms-2">← Back</a>
        </div>
      </div>

      <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle mb-0">
          <thead class="table-success">
            <tr>
              <?php foreach ($header as $h): ?>
                <th><?= esc($h) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php 
              $total = 0;
              foreach ($rows as $r): 
                $total += (float)($r[3] ?? 0);
            ?>
              <tr>
                <?php foreach ($r as $i => $cell): ?>
                  <td class="<?= $i === 3 ? 'text-end' : '' ?>">
                    <?= $i === 3 ? format_amount($cell) : esc($cell) ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold bg-light">
              <td colspan="3" class="text-end">Total:</td>
              <td class="text-end"><?= format_amount($total) ?></td>
              <td colspan="<?= count($header) - 4 ?>"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
