<?php
/* /pages/avatar-approvals.php — DROP-IN (loads inside main.php SPA)
   - Returns HTML normally
   - Returns JSON ONLY when JS interceptor sets ajax=1
   - NO hidden ajax fields in the forms (fixes the “raw JSON page” bug)
*/

/* ---------- SPA guard: if opened directly (non-AJAX GET), bounce into main.php ---------- */
$__DOCROOT = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$__FILEFS  = str_replace('\\','/', __FILE__);
$SELF_PATH = substr($__FILEFS, strlen($__DOCROOT));                 // e.g. /pages/avatar-approvals.php
if ($SELF_PATH === '' || $SELF_PATH[0] !== '/') $SELF_PATH = '/pages/avatar-approvals.php';

$isAjaxReq = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjaxReq && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $qs = $_SERVER['QUERY_STRING'] ? ('?'.$_SERVER['QUERY_STRING']) : '';
  $target = $SELF_PATH . $qs;
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><script>(function(){var p=' . json_encode($target) . ';'
     . 'if (window.opener && typeof window.opener.loadPage==="function"){window.opener.loadPage(p,{force:true});window.close();return;}'
     . 'if (window.parent && typeof window.parent.loadPage==="function"){window.parent.loadPage(p,{force:true});return;}'
     . 'location.replace("/pages/main.php#"+encodeURIComponent(p));})();</script>';
  exit;
}

/* ---------- app bootstrap ---------- */
session_start();
if (empty($_SESSION['hris'])) { echo '<div class="alert alert-warning m-3">Please log in.</div>'; exit; }

require_once __DIR__ . '/connections/connection.php';
if (!isset($conn) || !($conn instanceof mysqli)) { if (isset($con) && $con instanceof mysqli) { $conn = $con; } }
if (!($conn instanceof mysqli)) { echo '<div class="alert alert-danger m-3">Database unavailable.</div>'; exit; }
mysqli_set_charset($conn, 'utf8mb4');

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function flash_set($k,$m){ $_SESSION['__flash']=['k'=>$k,'m'=>$m]; }
function flash_get(){ $f=$_SESSION['__flash']??null; unset($_SESSION['__flash']); return $f; }

/* ---------- config ---------- */
$APPROVER_WHITELIST = ['01006428']; // add more HRIS ids here
$me  = $_SESSION['hris'] ?? '';
$isApprover = in_array($me, $APPROVER_WHITELIST, true) ||
              in_array(strtolower($_SESSION['user_level'] ?? ''), ['admin','super-admin'], true);

/* ---------- POST: Approve / Reject ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $wantsJson = (isset($_POST['ajax']) && $_POST['ajax'] == '1');

  $respond = function(bool $ok, string $msg, string $kind = 'info') use ($wantsJson, $SELF_PATH){
    if ($wantsJson) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>$ok?1:0, 'message'=>$msg, 'kind'=>$kind], JSON_UNESCAPED_UNICODE);
      exit;
    }
    flash_set($ok ? $kind : 'danger', $msg);
    $qs = !empty($_GET) ? ('?'.http_build_query($_GET)) : '';
    header('Location: '.$SELF_PATH.$qs);
    exit;
  };

  $action = trim($_POST['action'] ?? '');
  $target = trim($_POST['hris_id'] ?? '');
  $reason = trim($_POST['reason'] ?? '');

  if (!$isApprover)                          $respond(false,'You do not have permission.','danger');
  if (!in_array($action,['approve','reject'],true) || $target==='') $respond(false,'Bad request.','danger');
  if ($action==='reject' && $reason==='')    $respond(false,'Rejection reason is required.','warning');

  $esc_t = mysqli_real_escape_string($conn,$target);
  $esc_m = mysqli_real_escape_string($conn,$me);

  $res = mysqli_query($conn,"SELECT pending_path, pending_mime FROM tbl_admin_user_profile WHERE hris_id='$esc_t' AND pending_path IS NOT NULL LIMIT 1");
  if (!$res || mysqli_num_rows($res)===0)    $respond(false,'No pending record for that HRIS.','warning');
  $row = mysqli_fetch_assoc($res);
  $pending = $row['pending_path'] ?? '';

  if ($action==='approve') {
    $q = "UPDATE tbl_admin_user_profile SET
            avatar_path = pending_path,
            avatar_mime = pending_mime,
            pending_path = NULL,
            pending_mime = NULL,
            status = 'approved',
            reviewed_by = '$esc_m',
            reviewed_at = NOW(),
            rejection_reason = NULL,
            updated_at = NOW()
          WHERE hris_id='$esc_t' AND pending_path IS NOT NULL
          LIMIT 1";
    if (!mysqli_query($conn,$q))             $respond(false,'DB error: '.mysqli_error($conn),'danger');
    if (mysqli_affected_rows($conn) < 1)     $respond(false,'Nothing to update.','warning');
    $respond(true,"Approved $target.",'success');
  }

  if ($action==='reject') {
    // safe (best-effort) file delete under typical avatar folders
    if ($pending && !preg_match('~^https?://~i', $pending)) {
      $candidates = [];
      if ($pending[0] === '/') {
        $candidates[] = realpath($_SERVER['DOCUMENT_ROOT'] . $pending);
        $candidates[] = realpath(__DIR__ . $pending);
      } else {
        $candidates[] = realpath(__DIR__ . '/' . $pending);
        $candidates[] = realpath($_SERVER['DOCUMENT_ROOT'] . '/' . $pending);
      }
      $allowedDirs = array_filter([
        realpath(__DIR__ . '/uploads/avatars'),
        realpath($_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars'),
        realpath($_SERVER['DOCUMENT_ROOT'] . '/pages/uploads/avatars'),
      ]);
      foreach ($candidates as $abs) {
        if (!$abs || !is_file($abs)) continue;
        foreach ($allowedDirs as $allow) {
          if ($allow && strncmp($abs, $allow, strlen($allow)) === 0) { @unlink($abs); break 2; }
        }
      }
    }

    $esc_reason = mysqli_real_escape_string($conn,$reason);
    $q = "UPDATE tbl_admin_user_profile SET
            pending_path = NULL,
            pending_mime = NULL,
            status = 'rejected',
            reviewed_by = '$esc_m',
            reviewed_at = NOW(),
            rejection_reason = '$esc_reason',
            updated_at = NOW()
          WHERE hris_id='$esc_t' AND pending_path IS NOT NULL
          LIMIT 1";
    if (!mysqli_query($conn,$q))             $respond(false,'DB error: '.mysqli_error($conn),'danger');
    if (mysqli_affected_rows($conn) < 1)     $respond(false,'Nothing to update.','warning');
    $respond(true,"Rejected $target.",'success');
  }
}

/* ---------- GET: List / Search / Paging ---------- */
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where = "p.status='pending' AND p.pending_path IS NOT NULL";
if ($q !== '') {
  $qs = mysqli_real_escape_string($conn,$q);
  $where .= " AND (u.name LIKE '%$qs%' OR u.hris LIKE '%$qs%' OR u.branch_name LIKE '%$qs%' OR u.designation LIKE '%$qs%')";
}

$resC  = mysqli_query($conn,"SELECT COUNT(*) AS c
                             FROM tbl_admin_user_profile p
                             JOIN tbl_admin_users u ON u.hris = p.hris_id
                             WHERE $where");
$total = ($resC && ($rc = mysqli_fetch_assoc($resC))) ? (int)$rc['c'] : 0;
$pages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT u.name, u.hris, u.branch_name, u.designation,
               p.pending_path, p.submitted_at, p.submitted_by
        FROM tbl_admin_user_profile p
        JOIN tbl_admin_users u ON u.hris = p.hris_id
        WHERE $where
        ORDER BY p.submitted_at DESC, u.name ASC
        LIMIT $offset, $perPage";
$res  = mysqli_query($conn,$sql);
$rows = [];
if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

$flash = flash_get();
?>
<div id="content-root">
  <div id="collapseAvatarApprovals" class="content font-size">
    <div class="container-fluid">
      <div class="card shadow bg-white rounded p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
          <h5 class="text-primary mb-0">
            <i class="fas fa-user-check me-2"></i>Profile Photo Approvals
          </h5>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="dashboard.php" data-load="dashboard.php">← Back</a>
          </div>
        </div>

        <div id="appr-alert">
          <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['k']) ?>"><?= e($flash['m']) ?></div>
          <?php endif; ?>
        </div>

        <!-- SEARCH -->
        <form class="row g-2 mb-3" method="get" id="appr-search" action="<?= e($SELF_PATH) ?>">
          <div class="col-sm-6 col-md-5 col-lg-4">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search name / HRIS / department / designation">
          </div>
          <div class="col-auto">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i> Search</button>
          </div>
          <?php if ($q !== ''): ?>
          <div class="col-auto">
            <a class="btn btn-outline-secondary" href="<?= e($SELF_PATH) ?>" data-load="<?= e($SELF_PATH) ?>">Clear</a>
          </div>
          <?php endif; ?>
        </form>

        <?php if ($total === 0): ?>
          <div class="alert alert-info mb-0">No pending profile photos to approve.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle table-hover">
              <thead class="table-light">
                <tr>
                  <th style="width:80px;">Photo</th>
                  <th>Name</th>
                  <th>HRIS</th>
                  <th>Department</th>
                  <th>Designation</th>
                  <th>Submitted</th>
                  <th class="text-end" style="width:260px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td>
                      <?php if (!empty($r['pending_path'])): ?>
                        <span style="display:inline-block;width:56px;height:56px;border-radius:10px;overflow:hidden;background:#f0f0f0;">
                          <img src="<?= e($r['pending_path']) ?>?v=<?= time() ?>" alt="Pending"
                               style="width:56px!important;height:56px!important;object-fit:cover!important;display:block;border-radius:10px;">
                        </span>
                      <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><?= e($r['name']) ?></td>
                    <td><code><?= e($r['hris']) ?></code></td>
                    <td><?= e($r['branch_name'] ?: '-') ?></td>
                    <td><?= e($r['designation'] ?: '-') ?></td>
                    <td class="small">
                      <?= e($r['submitted_at'] ?: '-') ?><br>
                      <span class="text-muted">by <?= e($r['submitted_by'] ?: $r['hris']) ?></span>
                    </td>
                    <td class="text-end">
                      <?php if ($isApprover): ?>
                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                          <!-- Approve (NO ajax hidden input here) -->
                          <form method="post" action="<?= e($SELF_PATH) ?>" class="m-0 apprForm">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="hris_id" value="<?= e($r['hris']) ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                              <i class="fas fa-check me-1"></i>Approve
                            </button>
                          </form>

                          <!-- Reject (NO ajax hidden input here) -->
                          <form method="post" action="<?= e($SELF_PATH) ?>" class="m-0 d-flex align-items-start gap-2 apprForm">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="hris_id" value="<?= e($r['hris']) ?>">
                            <textarea name="reason" rows="2" class="form-control form-control-sm"
                                      placeholder="Reason (required)" style="width:220px;"></textarea>
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                              <i class="fas fa-times me-1"></i>Reject
                            </button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span class="text-muted">No permission</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-end">
              <?php
                $base = $SELF_PATH . '?';
                if ($q !== '') { $base .= 'q='.urlencode($q).'&'; }
                $prev = max(1, $page-1);
                $next = min($pages, $page+1);
              ?>
              <li class="page-item <?= $page<=1?'disabled':''; ?>">
                <a class="page-link" href="<?= $base.'page='.$prev; ?>" data-load="<?= $base.'page='.$prev; ?>">Previous</a>
              </li>
              <li class="page-item active"><span class="page-link"><?= $page ?></span></li>
              <li class="page-item <?= $page>=$pages?'disabled':''; ?>">
                <a class="page-link" href="<?= $base.'page='.$next; ?>" data-load="<?= $base.'page='.$next; ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Intercept Approve/Reject only when SPA loader exists
  document.addEventListener('submit', function(ev){
    const f = ev.target.closest('form.apprForm');
    if (!f) return;

    // If SPA loader is not present, let the form submit normally
    if (typeof window.loadPage !== 'function') return;

    ev.preventDefault();

    const fd = new FormData(f);
    fd.set('ajax','1'); // tell PHP to respond with JSON (ONLY for intercepted requests)

    const btn = f.querySelector('button[type="submit"]');
    const old = btn ? btn.textContent : '';
    if (btn){
      btn.disabled = true;
      btn.textContent = (fd.get('action') === 'approve') ? 'Approving…' : 'Rejecting…';
    }

    fetch(f.action || <?= json_encode($SELF_PATH) ?>, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(r => r.json()).then(data => {
      // Show bootstrap alert
      const box = document.getElementById('appr-alert');
      if (box){
        const kind = data && data.kind ? data.kind : (data && data.ok ? 'success' : 'danger');
        const msg  = data && data.message ? data.message : 'Operation finished.';
        box.innerHTML = '<div class="alert alert-'+kind+'">'+msg+'</div>';
      }
      // reload list inside SPA
      const url = <?= json_encode($SELF_PATH) ?> + (location.search || '');
      window.loadPage(url, {force:true});
    }).catch(() => {
      const box = document.getElementById('appr-alert');
      if (box) box.innerHTML = '<div class="alert alert-danger">Network error.</div>';
      if (btn){ btn.disabled = false; btn.textContent = old; }
    });
  });

  // Intercept search to stay in SPA
  const sf = document.getElementById('appr-search');
  if (sf && typeof window.loadPage === 'function'){
    sf.addEventListener('submit', function(e){
      e.preventDefault();
      const q = (sf.querySelector('[name="q"]').value || '').trim();
      const url = <?= json_encode($SELF_PATH) ?> + (q ? ('?q=' + encodeURIComponent(q)) : '');
      window.loadPage(url, {force:true});
    });
  }
})();
</script>
