<?php
// special-notes-view.php (UPDATED)
session_start();
if (!isset($_SESSION['hris']) || empty($_SESSION['hris'])) { echo '<div class="alert alert-warning">Not logged in.</div>'; exit; }
$me    = $_SESSION['hris'];

require_once 'connections/connection.php';
mysqli_set_charset($conn, 'utf8');

$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$scope = isset($_GET['scope']) ? trim($_GET['scope']) : 'inbox';
if ($id <= 0) { echo '<div class="alert alert-danger">Invalid note.</div>'; exit; }

$meEsc = mysqli_real_escape_string($conn, $me);
$idEsc = (int)$id;

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$row = null;
$isAuthorView = false;

if ($scope === 'sent') {
  // Author's own message
  $sql = "SELECT m.id, m.category, m.record_key, m.sr_number, m.comment, m.commented_at, 
                 m.origin_page, m.origin_url,
                 m.sender_hris, u.name AS sender_name
          FROM tbl_admin_remarks m
          LEFT JOIN tbl_admin_users u ON m.sender_hris = u.hris
          WHERE m.id = $idEsc AND m.hris_id = '$meEsc'
          LIMIT 1";
  $res = mysqli_query($conn, $sql);
  $row = mysqli_fetch_assoc($res);
  if (!$row) { echo '<div class="alert alert-info">Note not found.</div>'; exit; }
  $isAuthorView = true;
} else {
  // Inbox message (I am a recipient)
  $sql = "SELECT m.id, m.category, m.record_key, m.sr_number, m.comment, m.commented_at, 
                 m.origin_page, m.origin_url,
                 r.is_read, r.read_at, m.sender_hris, u.name AS sender_name
          FROM tbl_admin_remarks m
          JOIN tbl_admin_remarks_recipients r ON r.remark_id = m.id
          LEFT JOIN tbl_admin_users u ON m.sender_hris = u.hris
          WHERE m.id = $idEsc AND r.recipient_hris = '$meEsc'
          LIMIT 1";
  $res = mysqli_query($conn, $sql);
  $row = mysqli_fetch_assoc($res);
  if (!$row) { echo '<div class="alert alert-info">Note not found.</div>'; exit; }

  // Mark as read if unread
  if (($row['is_read'] ?? 'no') !== 'yes') {
    mysqli_query($conn, "UPDATE tbl_admin_remarks_recipients
                         SET is_read='yes', read_at=NOW()
                         WHERE remark_id=$idEsc AND recipient_hris='$meEsc'");
  }
}

// ============== Compute Report Line # (Security Charges) ==============
$reportLineNo = null;
$reportMonth  = $row['record_key'] ?? ''; // e.g. 'June 2025'
if (!empty($reportMonth) && ($row['origin_page'] === 'security-cost-report.php' || $row['category'] === 'Security Charges')) {
  $dt = DateTime::createFromFormat('F Y', $reportMonth);
  if ($dt) {
    $year  = (int)$dt->format('Y');
    $mon   = (int)$dt->format('n');
    $fyStartYear = ($mon >= 4) ? $year : ($year - 1);
    $start = new DateTime("$fyStartYear-04-01");
    $end   = new DateTime(($fyStartYear + 1) . "-03-01");

    $fyMonths = [];
    $tmp = clone $start;
    while ($tmp <= $end) {
      $fyMonths[] = $tmp->format('F Y');
      $tmp->modify('+1 month');
    }

    if ($fyMonths) {
      $esc = array_map(fn($m)=>"'".mysqli_real_escape_string($conn, $m)."'", $fyMonths);
      $inList = implode(',', $esc);
      $q = "SELECT month_applicable, SUM(total_amount) AS s
            FROM tbl_admin_actual_security
            WHERE month_applicable IN ($inList)
            GROUP BY month_applicable";
      $rs = mysqli_query($conn, $q);
      $sumMap = [];
      if ($rs) {
        while ($r = mysqli_fetch_assoc($rs)) {
          $sumMap[$r['month_applicable']] = (float)($r['s'] ?? 0);
        }
      }
      $displayed = [];
      foreach ($fyMonths as $m) {
        if (($sumMap[$m] ?? 0) > 0) $displayed[] = $m;
      }
      $ix = array_search($reportMonth, $displayed, true);
      if ($ix !== false) $reportLineNo = $ix + 1; // 1-based
    }
  }
}

// If author view, fetch recipients + read state + names
$recips = [];
if ($isAuthorView) {
  $qr = mysqli_query($conn, "SELECT r.recipient_hris, r.is_read, r.read_at, u.name AS recipient_name
                             FROM tbl_admin_remarks_recipients r
                             LEFT JOIN tbl_admin_users u ON r.recipient_hris = u.hris
                             WHERE r.remark_id=$idEsc
                             ORDER BY r.recipient_hris");
  while ($r = mysqli_fetch_assoc($qr)) { $recips[] = $r; }
}
?>
<div>
  <div class="row g-2">
    <div class="col-sm-6"><strong>Date:</strong> <?php echo e($row['commented_at']); ?></div>
    <div class="col-sm-6"><strong>Category:</strong> <?php echo e($row['category']); ?></div>

    <div class="col-sm-6"><strong>Month:</strong> <?php echo e($reportMonth ?: '-'); ?></div>
    <div class="col-sm-6"><strong>Record Key:</strong> <?php echo e($row['record_key']); ?></div>
    
  </div>
  <hr>
  <p class="mb-0"><strong>Comment:</strong></p>
  <div class="mb-3"><?php echo nl2br(e($row['comment'])); ?></div>

  <?php if ($isAuthorView): ?>
    <div class="mb-3">
      <strong>Recipients:</strong>
      <?php if (empty($recips)): ?>
        <div class="text-muted">None (saved for your reference).</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Recipient</th><th>Status</th><th>Read At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recips as $rc): ?>
                <tr>
                  <td><?php echo e(($rc['recipient_name'] ?: 'Unknown') . ' (' . $rc['recipient_hris'] . ')'); ?></td>
                  <td>
                    <?php echo ($rc['is_read'] === 'yes')
                      ? '<span class="badge bg-secondary">Read</span>'
                      : '<span class="badge bg-warning text-dark">Unread</span>'; ?>
                  </td>
                  <td><?php echo e($rc['read_at'] ?: '-'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="mb-3">
      <strong>From:</strong> <?php echo e(($row['sender_name'] ?: 'Unknown') . ' (' . ($row['sender_hris'] ?: '-') . ')'); ?>
    </div>
  <?php endif; ?>

  <button class="btn btn-primary" id="goToReport"
          data-origin="<?php echo e($row['origin_page'] ?: 'dashboard.php'); ?>"
          data-url="<?php echo e($row['origin_url'] ?: ''); ?>"
          data-category="<?php echo e($row['category']); ?>"
          data-record="<?php echo e($row['record_key']); ?>">
    Go to that record / report
  </button>
</div>

<script>
// Go to the linked report/record from Special Notes
$(document).off('click', '#goToReport').on('click', '#goToReport', function(){
  const origin    = $(this).data('origin') || 'dashboard.php';
  const originUrl = $(this).data('url') || '';          // exact deeplink if saved
  const category  = $(this).data('category') || '';
  const record    = $(this).data('record') || '';

  // Router fallback only if originUrl missing
  const router = {
    'Security Charges': 'security-cost-report.php',
    'Tea Service - Head Office': 'tea-budget-vs-actual.php',
    'Printing & Stationary': 'budget-vs-actual-stationary.php',
    'Electricity Charges': 'electricity-overview.php',
    'Photocopy': 'photocopy-budget-report.php',
    'Courier': 'courier-cost-report.php',
    'Vehicle Maintenance': 'vehicle-budget-vs-actual.php',
    'Postage & Stamps': 'postage-budget-vs-actual.php',
    'Telephone Bills': 'telephone-budget-vs-actual.php',
    'Newspaper': '#',
    'Water': '#'
  };

  // 1) Close the Special Notes modal cleanly
  const $modal = $('#specialNoteModal');
  try {
    const inst = bootstrap.Modal.getInstance($modal[0]) || bootstrap.Modal.getOrCreateInstance($modal[0]);
    inst.hide();
  } catch(e) {}

  setTimeout(function(){
    // 2) Hard-clean any leftover UI classes/backdrops
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css({ overflow: '', 'padding-right': '' });

    // 3) Decide target URL: prefer originUrl; else route by category; else fall back to origin
    let target = originUrl || router[category] || origin || 'dashboard.php';
    if (!target || target === '#') {
      target = origin || 'dashboard.php';
    }

    // Always try to pass the record as a query param to help the page filter/highlight
    const hasQuery = target.indexOf('?') !== -1;
    const withQ = record ? (target + (hasQuery ? '&' : '?') + 'q=' + encodeURIComponent(record)) : target;

    // 4) Load the page, then try to auto-open the same record’s remark modal
    $('#contentArea').html('<div class="text-center p-4">Loading...</div>');
    $.get(withQ, function(res){
      $('#contentArea').html(res);

      // Give the destination page a tick to bind handlers, then try to open the correct remark
      setTimeout(function(){
        // Primary selector used across your reports
        let $btn = $('.open-remarks[data-category="'+category+'"][data-record="'+record+'"]');

        // Fallback: if pages use only data-record
        if (!$btn.length && record) {
          $btn = $('.open-remarks[data-record="'+record+'"]');
        }

        if ($btn.length) {
          $btn.trigger('click');
        } else {
          // As a last resort, just scroll to top
          $('html, body').animate({ scrollTop: 0 }, 200);
        }
      }, 300);
    });
  }, 150);
});
</script>
