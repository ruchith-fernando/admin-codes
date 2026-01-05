<?php
// home-content.php (Quick Access removed, lightweight nav + per-user prefs with DB sync)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Colombo');

$name          = $_SESSION['name']        ?? 'Unknown';
$hris          = $_SESSION['hris']        ?? 'N/A';
$email         = $_SESSION['email']       ?? '-';
$mobile        = $_SESSION['mobile']      ?? '-';
$branch        = $_SESSION['branch_name'] ?? '-';
$user_level    = $_SESSION['user_level']  ?? '-';
$date          = date('Y-m-d');
$current_time  = date('h:i:s A');
$version       = 'In Development';

require_once 'connections/connection.php';

/* ==== Connection alias (in case your connection file exposes $con) ==== */
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($con) && $con instanceof mysqli) { $conn = $con; }
}



/* ── Designation lookup (exact HRIS first, then trimmed fallback) ────── */
$designation = '-';
if (isset($conn) && $conn instanceof mysqli) {
    $hrisRaw   = (string)$hris;
    $hrisEsc   = mysqli_real_escape_string($conn, $hrisRaw);

    $sql1 = "SELECT designation FROM tbl_admin_employee_details WHERE hris = '$hrisEsc' AND (status IS NULL OR status='Active') LIMIT 1";
    $res  = mysqli_query($conn, $sql1);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $designation = $row['designation'] ?? '-';
    } else {
        // Fallback to your previous “strip first two non-digit-prefixed chars” behavior
        $hrisDigits    = preg_replace('/\D/', '', (string)$hrisRaw);
        $lookupHris    = (strlen($hrisDigits) > 2) ? substr($hrisDigits, 2) : $hrisDigits;
        $lookupHrisEsc = mysqli_real_escape_string($conn, $lookupHris);
        $sql2 = "SELECT designation FROM tbl_admin_employee_details WHERE hris = '$lookupHrisEsc' AND (status IS NULL OR status='Active') LIMIT 1";
        $res2 = mysqli_query($conn, $sql2);
        if ($res2 && mysqli_num_rows($res2) > 0) {
            $row = mysqli_fetch_assoc($res2);
            $designation = $row['designation'] ?? '-';
        }
    }
}

/* ── Access helper ─────────────────────────────────────────────────── */
if (!function_exists('hasAccess')) {
    function hasAccess($conn, $hris_id, $menu_key){
        if (!($conn instanceof mysqli)) return false;
        $hris = mysqli_real_escape_string($conn, (string)$hris_id);
        $menu = mysqli_real_escape_string($conn, (string)$menu_key);
        $q = "
          SELECT 1
          FROM tbl_admin_user_page_access
          WHERE hris_id='$hris'
            AND menu_key='$menu'
            AND (
                 LOWER(is_allowed)='yes'
              OR is_allowed='1'
              OR is_allowed=1
              OR LOWER(is_allowed)='true'
            )
          LIMIT 1";
        $res = mysqli_query($conn, $q);
        return $res && mysqli_num_rows($res) > 0;
    }
}
$hris_id = $_SESSION['hris'] ?? '';

/* ── Define navigable modules ─ */
$navPagesAll = [
  ['key'=>'budget-dashboard','page'=>'dashboard.php','label'=>'Budget Performance','shortcut'=>'g d'],
  ['key'=>'graphs','page'=>'load-graphs.php','label'=>'Graphs','shortcut'=>'g g'],
  ['key'=>'security','page'=>'load-security.php','label'=>'Security Charges','shortcut'=>'g s'],
  ['key'=>'electricity','page'=>'load-electricity.php','label'=>'Electricity','shortcut'=>'g e'],
  ['key'=>'stationary','page'=>'monthly-stock-report.php','label'=>'Stationery','shortcut'=>'g t'],
  ['key'=>'vehicle','page'=>'load-vehicle.php','label'=>'Vehicle Service & Maintenance','shortcut'=>'g v'],
  ['key'=>'courier','page'=>'courier-budget-vs-actual.php','label'=>'Courier Services','shortcut'=>'g c'],
  ['key'=>'postage','page'=>'load-postage.php','label'=>'Postage & Stamps','shortcut'=>'g p'],
  ['key'=>'photocopy','page'=>'load-photocopy.php','label'=>'Photocopies','shortcut'=>'g o'],
  ['key'=>'telephone','page'=>'load-telephone.php','label'=>'Telecommunication','shortcut'=>'g t e'],
  ['key'=>'staff-transport','page'=>'load-staff-transport.php','label'=>'Staff Transport','shortcut'=>'g r'],
  ['key'=>'tea','page'=>'load-tea-service.php','label'=>'Tea Service (HO & Branches)','shortcut'=>'g h'],
  ['key'=>'water','page'=>'under-constuction.php','label'=>'Water','shortcut'=>null],
  ['key'=>'security-vpn','page'=>'under-constuction.php','label'=>'VPN (Security)','shortcut'=>null],
  ['key'=>'newspaper','page'=>'under-constuction.php','label'=>'Newspapers','shortcut'=>null],
  ['key'=>'dashboard-settings','page'=>'load-settings.php','label'=>'User & System Settings','shortcut'=>'g u'],
  ['key'=>'avatar-approvals','page'=>'avatar-approvals.php','label'=>'Avatar Approvals','shortcut'=>null],
];

/* ── Filter by access: admin/super-admin get all pages ─ */
$allowedPages = [];
$user_role = strtolower((string)($user_level ?? ''));
if (in_array($user_role, ['admin','super-admin'], true)) {
    $allowedPages = $navPagesAll;
} else {
    if (isset($conn) && $conn instanceof mysqli) {
        foreach ($navPagesAll as $p) {
          if (hasAccess($conn, $hris_id, $p['key'])) {
            $allowedPages[] = $p;
          }
        }
    } else {
        // If no DB, keep empty to be safe
        $allowedPages = [];
    }
}

/* ── Load saved user prefs from DB (for cross-device pins/recents/last) ─ */
$server_prefs = ['pinned'=>[], 'recents'=>[], 'last'=>''];
if (!empty($hris_id) && isset($conn) && $conn instanceof mysqli) {
    $hris_esc = mysqli_real_escape_string($conn, $hris_id);
    $q = "SELECT pinned_json, recents_json, last_page FROM tbl_admin_user_prefs WHERE hris_id='$hris_esc' LIMIT 1";
    if ($rs = mysqli_query($conn, $q)) {
        if (mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            $server_prefs['pinned']  = $row['pinned_json']  ? (json_decode($row['pinned_json'], true) ?: []) : [];
            $server_prefs['recents'] = $row['recents_json'] ? (json_decode($row['recents_json'], true) ?: []) : [];
            $server_prefs['last']    = $row['last_page'] ?? '';
        }
    }
}

/* ── Build initials for avatar ──────────────────────────────────────── */
// $initials = 'U';
// if (!empty($name) && $name !== 'Unknown') {
//     $parts = preg_split('/\s+/', trim($name));
//     $buf = '';
//     foreach ($parts as $p) { if ($p !== '') { $buf .= strtoupper(substr($p, 0, 1)); } if (strlen($buf) >= 2) break; }
//     if ($buf !== '') $initials = $buf;
// }

/* ── Avatar fetch ─────────────────────────────────────────────── */
$avatar_url = '';
if (isset($conn) && $conn instanceof mysqli && !empty($hris)) {
    $esc = mysqli_real_escape_string($conn, (string)$hris);
    $sqlAv = "SELECT avatar_path FROM tbl_admin_user_profile WHERE hris_id='$esc' LIMIT 1";
    if ($rsAv = mysqli_query($conn, $sqlAv)) {
        if (mysqli_num_rows($rsAv) > 0) {
            $rowAv = mysqli_fetch_assoc($rsAv);
            $path  = $rowAv['avatar_path'] ?? '';
            if ($path && file_exists(__DIR__ . '/' . ltrim($path, '/'))) {
                $avatar_url = $path;
            }
        }
    }
}

?>
<style>
/* =================== Dashboard Polish =================== */
:root{
  --card-radius: 14px;
  --tile-radius: 14px;
  --shadow-sm: 0 4px 10px rgba(0,0,0,.06);
  --shadow-md: 0 10px 24px rgba(0,0,0,.08);
  --brand-grad: linear-gradient(135deg,#0d6efd 0%, #6f42c1 100%);
}
.dashboard-hero{
  background: var(--brand-grad);
  color: #fff;
  border-top-left-radius: var(--card-radius);
  border-top-right-radius: var(--card-radius);
}
.btn-icon{
  width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;padding:0;
  background:#fff; border-color:#e9ecef !important;
}
.btn-icon i{font-size:1rem;}
.toolbar-nowrap{flex-wrap:nowrap;gap:.35rem;}
.btn-icon:hover{box-shadow: var(--shadow-sm);}
.table td,.table th{ white-space:normal; word-break:break-word; }
/* subtle progress bar for async loads */
#topLoader{
  position:fixed; left:0; top:0; height:3px; width:0; background:#0d6efd; z-index:9999; transition:width .25s ease;
}
/* tighten cards */
.card.shadow{ border-radius: var(--card-radius); }
.card .card-header{ border-top-left-radius: var(--card-radius); border-top-right-radius: var(--card-radius); }

/* ===== User Details (Profile Card) ===== */
.profile-card{
  border:1px solid #e9ecef;
  border-radius: var(--card-radius);
  overflow:hidden;
  background:#fff;
}
.profile-header{
  background:#f8f9fa;
  padding:16px;
  display:flex;
  align-items:center;
  gap:12px;
}
.profile-avatar{
  width:56px; height:56px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  background: var(--brand-grad);
  color:#fff; font-weight:700; font-size:1.1rem;
  box-shadow: var(--shadow-sm);
}
.profile-name{ font-weight:600; line-height:1.1; }
.profile-sub{ color:#6c757d; font-size:.9rem; }
.profile-body{ padding:14px 16px; }
.details-grid{ display:grid; grid-template-columns: 1fr; gap:10px 12px; }
@media (min-width: 576px){ .details-grid{ grid-template-columns: auto 1fr; } }
.detail-label{ color:#6c757d; font-size:.9rem; white-space:nowrap; }
.detail-value{ font-weight:500; }

/* Remove the top gap above the main dashboard card */
body, .content, .content > .container-fluid { margin-top: 0 !important; padding-top: 0 !important; }
.content > .container-fluid > .card:first-of-type,
.content > .container-fluid > .card.shadow.bg-white.rounded { margin-top: 0 !important; }
#contentArea, #content-wrapper { margin-top:0 !important; padding-top:0 !important; }

/* Special Notes circular badge */
.notif-badge{
  position:absolute; top:-6px; right:-6px; min-width:22px; height:22px; padding:0 6px; border-radius:999px;
  display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; line-height:1;
  background:#dc3545; color:#fff; box-shadow:0 0 0 2px #fff; pointer-events:none;
}
@keyframes badge-bounce { 0%{transform:scale(1)} 30%{transform:scale(1.3)} 60%{transform:scale(.9)} 100%{transform:scale(1)} }
.notif-badge.bump { animation: badge-bounce .4s ease; }

/* Command Palette */
#cmdPalette .modal-content{ border-radius:16px; box-shadow:var(--shadow-md); }
#cmdPaletteInput{ font-size:1.05rem; padding:.75rem 1rem; }
.cmd-list{ max-height:50vh; overflow:auto; margin-top:.5rem; }
.cmd-item{ padding:.5rem .75rem; border-radius:10px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; }
.cmd-item:hover, .cmd-item.active{ background:#f1f3f5; }
.cmd-item .cmd-meta{ color:#6c757d; font-size:.85rem; }
/* Base kbd style (outline button) */
.kbd {
  background: none !important;   /* no box */
  border: none !important;
  padding: 0 .2rem;
  font-size: .85rem;
  font-weight: 600;
  color: #0d6efd !important;     /* same blue as Bootstrap primary */
}

/* When the Command Palette button is hovered/active (blue background) */
#openPalette:hover .kbd,
#openPalette:focus .kbd,
#openPalette:active .kbd,
#openPalette.show .kbd {
  color: #ffffff !important;     /* flip to white */
}


/* Skeleton for #contentArea */
.skel{ animation: skel 1.2s ease-in-out infinite; background: linear-gradient(90deg, #f2f4f7 25%, #e6e9ed 37%, #f2f4f7 63%); background-size: 400% 100%; border-radius:8px; }
@keyframes skel{ 0%{background-position: 100% 50%} 100%{background-position: 0 50%} }
.skel-line{ height:14px; margin:10px 0; }
</style>

<div id="topLoader" hidden></div>

<div class="content font-size mt-2">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center p-3 dashboard-hero">
        <h5 class="mb-0 fw-semibold"></h5>
        <div class="d-flex align-items-center toolbar-nowrap">
          <button type="button" class="btn btn-icon" data-bs-toggle="tooltip" data-bs-placement="bottom" aria-label="Users">
            <i class="fas fa-users text-primary"></i>
          </button>

          <button type="button"
            class="btn btn-icon position-relative load-special-notes"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            data-bs-trigger="hover"
            aria-label="Special Notes"
            id="btnSpecialNotes">
            <i class="fas fa-sticky-note text-primary"></i>
            <span id="badgeSpecialNotes" class="notif-badge d-none">0</span>
          </button>


          <button type="button" class="btn btn-icon" id="btnCmdPalette" data-bs-toggle="tooltip"  aria-label="Command Palette">
            <i class="fas fa-search text-success"></i>
          </button>

          <form method="post" action="logout.php" class="m-0 p-0">
            <button type="submit" class="btn btn-icon" data-bs-toggle="tooltip" data-bs-placement="bottom"  aria-label="Logout">
              <i class="fas fa-sign-out-alt text-danger"></i>
            </button>
          </form>
        </div>
      </div>

      <div class="card-body">
        <div class="row g-4">
          <!-- Welcome/navigation helper -->
          <div class="col-lg-9">
            <div class="profile-card shadow-sm h-100">
              <div class="profile-header d-flex justify-content-between align-items-center">
                <div>
                  <i class="fas fa-info-circle text-primary me-2"></i>Welcome
                </div>
                <div class="d-flex align-items-center gap-2">
                  <button class="btn btn-sm btn-outline-secondary" id="continueBtn" style="display:none" data-load="">
                    Continue last page
                  </button>
                  <button class="btn btn-sm btn-outline-primary" id="openPalette">
                    <i class="fas fa-terminal me-1"></i> Command Palette <span class="ms-1 kbd">Ctrl</span>+<span class="kbd">K</span>
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <h6 class="mb-2">Pinned <small class="text-muted">(your favorites)</small></h6>
                  <div id="pinnedList" class="d-flex flex-wrap gap-2"></div>
                  <div id="noPins" class="text-muted">No pins yet. Open the Command Palette (<span class="kbd">Ctrl</span>+<span class="kbd">K</span>) and ⭐ a page.</div>
                </div>
                <hr>
                <div>
                  <h6 class="mb-2">Recent</h6>
                  <div id="recentList" class="d-flex flex-wrap gap-2"></div>
                  <div id="noRecent" class="text-muted">You haven’t opened any pages in this session.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- User Details -->
          <div class="col-lg-3">
            <div class="profile-card shadow-sm h-100">
              <div class="profile-header">
                <div class="profile-avatar" id="profileAvatar" data-bs-toggle="tooltip" title="Click to change avatar">
                  <?php if (!empty($avatar_url)): ?>
                    <img src="<?= htmlspecialchars($avatar_url) ?>?v=<?= time() ?>" alt="Avatar"
                        style="width:56px; height:56px; border-radius:50%; object-fit:cover;">
                  <?php else: ?>
                    <?= htmlspecialchars($initials) ?>
                  <?php endif; ?>
                  <span class="cam"><i class="fas fa-camera text-primary"></i></span>
                </div>

                <div>
                  <p class="profile-name mb-1"><?= htmlspecialchars($name ?? '') ?></p>
                  <p class="profile-sub mb-0"><?= htmlspecialchars($designation ?: '-') ?></p>
                  <p class="profile-sub mb-0"><i class="fas fa-building me-1 text-primary"></i><?= htmlspecialchars($branch) ?></p>
                </div>
              </div>
              <div class="profile-body">
                <div class="details-grid">
                  <div class="detail-label"><i class="far fa-calendar me-1 text-primary"></i>Date</div>
                  <div class="detail-value"><?= $date ?></div>

                  <div class="detail-label"><i class="far fa-clock me-1 text-primary"></i>Time</div>
                  <div class="detail-value" id="liveClock"><?= $current_time ?></div>

                  <div class="detail-label"><i class="far fa-id-badge me-1 text-primary"></i>User ID</div>
                  <div class="detail-value"><?= htmlspecialchars($hris) ?></div>

                  <div class="detail-label"><i class="fas fa-microchip me-1 text-primary"></i>System Version</div>
                  <div class="detail-value"><?= $version ?></div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /row -->
      </div><!-- /card-body -->
    </div>
  </div>
</div>

<!-- Command Palette Modal -->
<div class="modal fade" id="cmdPalette" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="fas fa-terminal me-2"></i>Command Palette</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="cmdPaletteInput" class="form-control" placeholder="Type a page name…">
        <div class="cmd-list mt-2" id="cmdList"></div>
        <div class="small text-muted mt-2">
          ↑/↓ to navigate, <span class="kbd">Enter</span> to open, <span class="kbd">⭐</span> to pin/unpin, <span class="kbd">Esc</span> to close
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Avatar Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="fas fa-camera me-2"></i>Update Avatar</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="avatarMsg" class="alert d-none mb-2" role="alert"></div>

        <div class="mb-3">
          <label class="form-label">Choose an image (JPG/PNG/WEBP, max 2MB)</label>
          <input type="file" class="form-control" id="avatarInput" accept="image/jpeg,image/png,image/webp">
        </div>

        <img id="avatarPreview" alt="Preview">

        <div class="small text-muted mt-2">
          Tip: Use a square image for best fit.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnRemoveAvatar" class="btn btn-outline-danger">Remove</button>
        <button type="button" id="btnUploadAvatar" class="btn btn-primary">Upload</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// Make allowed pages + user id + server prefs available to JS
window.ALLOWED_PAGES = <?php echo json_encode($allowedPages, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
window.USER_HRIS     = <?php echo json_encode($hris_id ?? 'anon'); ?>;
window.SERVER_PREFS  = <?php echo json_encode($server_prefs, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
</script>

<script>
(function(){
  // ---------- tooltips + live clock ----------
  if (window.bootstrap && typeof bootstrap.Tooltip === 'function') {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  }
  const clockEl = document.getElementById('liveClock');
  if (clockEl){
    setInterval(() => {
      const d = new Date();
      clockEl.textContent = d.toLocaleTimeString('en-US', {hour:'numeric', minute:'numeric', second:'numeric', hour12:true});
    }, 1000);
  }

  // ---------- overlay helpers ----------
  function scrubOverlays(){
    document.querySelectorAll('.modal-backdrop,.offcanvas-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
  }
  window.closeAllOverlays = function(){
    document.querySelectorAll('.modal.show').forEach(m => {
      try { bootstrap.Modal.getInstance(m)?.hide(); } catch(e){}
      m.classList.remove('show'); m.setAttribute('aria-hidden','true'); m.style.display='none';
    });
    scrubOverlays();
  };
  document.addEventListener('hidden.bs.modal', ()=> setTimeout(scrubOverlays, 10));

  // ---------- race-proof AJAX navigation ----------
  let inFlight = null, navToken = 0, lastNavAt = 0;
  const NAV_THROTTLE_MS = 350;
  function showSkeleton(){
    const t = document.querySelector('#contentArea'); if(!t) return;
    t.innerHTML = '<div class="p-3">'
      +'<div class="skel skel-line" style="width:35%"></div>'
      +'<div class="skel skel-line" style="width:75%"></div>'
      +'<div class="skel skel-line" style="width:65%"></div>'
      +'<div class="skel skel-line" style="width:90%"></div>'
      +'<div class="skel skel-line" style="width:55%"></div>'
      +'</div>';
  }
  function execInlineScripts(target){
    target.querySelectorAll('script').forEach(s => {
      const ns = document.createElement('script');
      for (const a of s.attributes) ns.setAttribute(a.name, a.value);
      ns.textContent = s.textContent;
      s.replaceWith(ns);
    });
  }
  window.loadPage = function(page, opts={}){
    const now = Date.now();
    if (now - lastNavAt < NAV_THROTTLE_MS && !opts.force) return;
    lastNavAt = now;

    const target = document.querySelector('#contentArea');
    if (!page) return;
    if (!window.jQuery || !target) { window.location.href = page; return; }

    if (inFlight && inFlight.readyState !== 4) { try { inFlight.abort(); } catch(_) {} inFlight = null; }

    const myToken = ++navToken;
    showSkeleton();

    inFlight = $.ajax({ url: page, method: 'GET', cache: true })
      .done(function(html){
        if (myToken !== navToken) return;

        // ✅ Robust content extraction
        const wrapper = document.createElement('div');
        wrapper.innerHTML = (html || '').trim();
        const root = wrapper.querySelector('#content-root');
        // If the fetched page provides #content-root, use its innerHTML; else drop in full markup
        const inject = root ? root.innerHTML : wrapper.innerHTML;

        target.innerHTML = inject;
        execInlineScripts(target);

        const it = (ALLOWED_PAGES || []).find(a => a.page === page) || {label: page, page};
        addRecent({label: it.label, page: it.page});
        setLast(page);
        renderRecent(); renderContinue();

        if (!opts.noHistory) history.pushState({page}, '', '#' + encodeURIComponent(page));
        requestAnimationFrame(scrubOverlays);
      })
      .fail(function(_,status){ if (status !== 'abort') alert('Failed to load the page. Please try again.'); })
      .always(function(){ inFlight = null; });
  };
  window.addEventListener('popstate', ev=>{
    const page = ev.state?.page || (location.hash ? decodeURIComponent(location.hash.slice(1)) : '');
    if (page) loadPage(page, { noHistory:true, force:true });
  });

  // ---------- per-user prefs (LS + DB sync) ----------
  const ALLOWED = window.ALLOWED_PAGES || [];
  const USER_HRIS = String(window.USER_HRIS || 'anon').trim() || 'anon';
  const LS_PREFIX = `app.${USER_HRIS}.`;
  const RECENT_KEY = LS_PREFIX + 'recentPages';
  const PIN_KEY    = LS_PREFIX + 'pinnedPages';
  const LAST_KEY   = LS_PREFIX + 'lastPage';

  const LS = {
    get: (k, def) => { try { const v = JSON.parse(localStorage.getItem(k)); return v ?? def; } catch(_){ return def; } },
    set: (k, v) => localStorage.setItem(k, JSON.stringify(v))
  };

  window.getPins = function(){ return LS.get(PIN_KEY, []); };
  function setPins(arr){ LS.set(PIN_KEY, arr || []); savePrefsDebounced(); }

  window.togglePin = function(item){
    let pins = window.getPins();
    const idx = pins.findIndex(x => x.page === item.page);
    if (idx >= 0) pins.splice(idx,1); else pins.unshift({label:item.label, page:item.page});
    setPins(pins);
    renderPins();
  };

  function addRecent(item){
    const max = 10;
    let list = LS.get(RECENT_KEY, []);
    list = list.filter(x => x.page !== item.page);
    list.unshift(item);
    if (list.length > max) list = list.slice(0, max);
    LS.set(RECENT_KEY, list);
    savePrefsDebounced();
  }
  function getRecent(){ return LS.get(RECENT_KEY, []); }

  function setLast(page){ localStorage.setItem(LAST_KEY, page || ''); savePrefsDebounced(); }
  function getLast(){ return localStorage.getItem(LAST_KEY) || ''; }

  // DB sync (avoid saving during initial hydrate)
  let saveTimer = null;
  let hydrating = true;
  function savePrefsDebounced(){ if (hydrating) return; clearTimeout(saveTimer); saveTimer = setTimeout(savePrefsNow, 500); }
  function savePrefsNow(){
    if (!window.jQuery) return;
    $.post('user-prefs-save.php', {
      pinned_json: JSON.stringify(window.getPins()),
      recents_json: JSON.stringify(getRecent()),
      last_page: getLast()
    }).always(function(){ /* silent */ });
  }

  // hydrate from server
  (function hydrate(){
    const sp = window.SERVER_PREFS || {};
    if (Array.isArray(sp.pinned)) setPins(sp.pinned);
    if (Array.isArray(sp.recents)) LS.set(RECENT_KEY, sp.recents);
    if (typeof sp.last === 'string') localStorage.setItem(LAST_KEY, sp.last);
    hydrating = false; // ✅ now allow saves
  })();

  // ---------- renderers ----------
  function escapeHTML(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  function chip(label, page){
    return `<a class="chip-pill" href="${escapeHTML(page)}" data-load="${escapeHTML(page)}">${escapeHTML(label)}</a>`;
  }

  window.renderPins = function(){
    const pins = window.getPins().filter(p => ALLOWED.some(a => a.page === p.page));
    const wrap = document.getElementById('pinnedList');
    const none = document.getElementById('noPins');
    if (!wrap) return;

    wrap.innerHTML = '';
    if (!pins.length){
      if (none) none.style.display = 'block';
      return;
    }
    if (none) none.style.display = 'none';

    // chip() already gives a clickable badge that uses [data-load]
    pins.forEach(p => {
      wrap.insertAdjacentHTML('beforeend', chip(p.label, p.page));
    });
  };

  window.renderRecent = function(){
    const recents = getRecent().filter(r => ALLOWED.some(a => a.page === r.page));
    const wrap = document.getElementById('recentList');
    const none = document.getElementById('noRecent');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!recents.length){ if(none) none.style.display='block'; return; }
    if(none) none.style.display='none';
    recents.forEach(r => wrap.insertAdjacentHTML('beforeend', chip(r.label, r.page)));
  };
  window.renderContinue = function(){
    const last = getLast();
    const btn = document.getElementById('continueBtn');
    if (!btn) return;
    if (last && ALLOWED.some(a => a.page === last)){
      const item = ALLOWED.find(a => a.page === last);
      btn.style.display='inline-block';
      btn.setAttribute('data-load', item.page);
      btn.textContent = 'Continue: ' + item.label;
    } else {
      btn.style.display='none';
    }
  };
// ---------- command palette ----------
  const paletteModal = document.getElementById('cmdPalette');
  const paletteInput = document.getElementById('cmdPaletteInput');
  const paletteList  = document.getElementById('cmdList');

  function paletteInst(){
    return bootstrap.Modal.getOrCreateInstance(paletteModal, {backdrop:true, keyboard:true});
  }
  // ---------- Avatar upload handlers ----------
  (function(){
    const avatarEl   = document.getElementById('profileAvatar');
    const modalEl    = document.getElementById('avatarModal');
    const inputEl    = document.getElementById('avatarInput');
    const previewEl  = document.getElementById('avatarPreview');
    const msgEl      = document.getElementById('avatarMsg');
    const btnUpload  = document.getElementById('btnUploadAvatar');
    const btnRemove  = document.getElementById('btnRemoveAvatar');

    if (!avatarEl || !modalEl) return;

    function showMsg(kind, text){
      if (!msgEl) return;
      msgEl.className = 'alert alert-' + (kind || 'secondary');
      msgEl.textContent = text || '';
      msgEl.classList.remove('d-none');
    }
    function clearMsg(){
      if (msgEl) { msgEl.classList.add('d-none'); msgEl.textContent = ''; }
    }

    function openAvatarModal(){
      clearMsg();
      if (previewEl) { previewEl.src = ''; previewEl.style.display = 'none'; }
      if (inputEl)   { inputEl.value = ''; }
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    avatarEl.addEventListener('click', openAvatarModal);

    if (inputEl){
      inputEl.addEventListener('change', function(){
        clearMsg();
        const f = this.files && this.files[0];
        if (!f) { if (previewEl){ previewEl.style.display='none'; previewEl.src=''; } return; }
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
          showMsg('warning', 'Only JPG, PNG or WEBP allowed.'); this.value=''; return;
        }
        if (f.size > 2*1024*1024) {
          showMsg('warning', 'Max file size is 2MB.'); this.value=''; return;
        }
        const reader = new FileReader();
        reader.onload = e => { if (previewEl){ previewEl.src=e.target.result; previewEl.style.display='block'; } };
        reader.readAsDataURL(f);
      });
    }

    if (btnUpload){
      btnUpload.addEventListener('click', function(){
        clearMsg();
        const f = inputEl && inputEl.files && inputEl.files[0];
        if (!f) { showMsg('secondary','Please choose an image first.'); return; }

        const fd = new FormData();
        fd.append('avatar', f);

        btnUpload.disabled = true; btnRemove.disabled = true;
        btnUpload.textContent = 'Uploading...';

        $.ajax({
          url: 'avatar-upload.php',
          method: 'POST',
          data: fd,
          processData: false,
          contentType: false
        }).done(function(resp){
          if (resp && resp.ok) {
            // Update avatar on the card
            const url = String(resp.url || '');
            if (url) {
              const img = avatarEl.querySelector('img');
              if (img) {
                img.src = url + '?v=' + Date.now();
              } else {
                avatarEl.innerHTML = '<img src="'+url+'?v='+Date.now()+'" alt="Avatar" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">'
                  + '<span class="cam"><i class="fas fa-camera text-primary"></i></span>';
              }
            }
            showMsg('success','Avatar updated successfully.');
            setTimeout(()=> bootstrap.Modal.getOrCreateInstance(modalEl).hide(), 600);
          } else {
            showMsg('danger', (resp && resp.error) ? resp.error : 'Upload failed.');
          }
        }).fail(function(){
          showMsg('danger', 'Network or server error.');
        }).always(function(){
          btnUpload.disabled = false; btnRemove.disabled = false;
          btnUpload.textContent = 'Upload';
        });
      });
    }

    if (btnRemove){
      btnRemove.addEventListener('click', function(){
        clearMsg();
        btnUpload.disabled = true; btnRemove.disabled = true;
        $.post('avatar-remove.php', {})
          .done(function(resp){
            if (resp && resp.ok){
              // revert to initials
              const initials = <?= json_encode($initials ?? 'U') ?>;
              avatarEl.innerHTML = (initials ? initials : 'U') + '<span class="cam"><i class="fas fa-camera text-primary"></i></span>';
              showMsg('success', 'Avatar removed.');
              setTimeout(()=> bootstrap.Modal.getOrCreateInstance(modalEl).hide(), 500);
            } else {
              showMsg('danger', (resp && resp.error) ? resp.error : 'Failed to remove.');
            }
          })
          .fail(function(){ showMsg('danger','Network or server error.'); })
          .always(function(){ btnUpload.disabled = false; btnRemove.disabled = false; });
      });
    }
  })();

  // tiny escape helper (defends against odd labels)
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  function buildList(items){
    paletteList.innerHTML = '';
    if (!items?.length){
      paletteList.innerHTML = '<div class="text-muted px-2 py-2">No matches</div>';
      return;
    }
    const pins = window.getPins();
    items.forEach((it, idx)=>{
      const pinned = pins.some(p => p.page === it.page);
      paletteList.insertAdjacentHTML('beforeend', `
        <div class="cmd-item ${idx===0?'active':''}" data-page="${esc(it.page)}" data-label="${esc(it.label)}">
          <div>
            <div>${esc(it.label)}</div>
            <div class="cmd-meta">${esc(it.page)}${it.shortcut ? ' • hotkey: ' + esc(it.shortcut) : ''}</div>
          </div>
          <button type="button" class="btn btn-sm ${pinned?'btn-warning':'btn-outline-secondary'}" data-pin="${esc(it.page)}" title="${pinned?'Unpin':'Pin'}">⭐</button>
        </div>`);
    });
  }

  function hardCloseAllModals(){
    // fully remove backdrops + unlock body
    document.querySelectorAll('.modal-backdrop').forEach(b=>b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
    // restore focus for hotkeys
    if (document.body){ document.body.tabIndex = -1; document.body.focus(); document.body.removeAttribute('tabIndex'); }
  }

  function openPalette(){
    // make sure no stale backdrops exist
    hardCloseAllModals();
    buildList(ALLOWED);
    const inst = paletteInst();
    inst.show();
    setTimeout(()=> paletteInput?.focus(), 80);
  }

  function navigateFromPalette(page){
    if (!page) return;
    try{
      const inst = paletteInst();
      // fully dispose to remove Bootstrap’s focus trap + handlers
      paletteModal.addEventListener('hidden.bs.modal', function once(){
        paletteModal.removeEventListener('hidden.bs.modal', once);
        // clean any leftovers before navigating
        hardCloseAllModals();
        // small delay avoids layout thrash with hide() animations
        setTimeout(()=> loadPage(page, {force:true}), 10);
      }, {once:true});
      inst.hide();
      // belt & braces fallback if hidden never fires
      setTimeout(()=>{
        if (!document.querySelector('.modal.show')) {
          hardCloseAllModals();
          loadPage(page, {force:true});
        }
      }, 250);
    }catch(_){
      hardCloseAllModals();
      loadPage(page, {force:true});
    }
  }

  // Hotkey (capture phase) with a tiny throttle so it never “dies”
  if (!window.__cmdHotkeyBound){
    let lastHotkeyAt = 0;
    const hotkey = function(e){
      const isK = e.key === 'k' || e.key === 'K' || e.code === 'KeyK';
      if (isK && (e.ctrlKey || e.metaKey)){
        const now = Date.now();
        if (now - lastHotkeyAt < 150) return; // throttle
        lastHotkeyAt = now;
        e.preventDefault(); e.stopPropagation();
        openPalette();
      }
      if (paletteModal?.classList.contains('show')) {
        if (e.key === 'Enter'){
          const act = paletteList.querySelector('.cmd-item.active');
          if (act){
            e.preventDefault();
            const page = act.getAttribute('data-page');
            const label = act.getAttribute('data-label');
            addRecent({label, page}); setLast(page);
            navigateFromPalette(page);
          }
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp'){
          e.preventDefault();
          const items = [...paletteList.querySelectorAll('.cmd-item')];
          if (!items.length) return;
          let i = items.findIndex(x => x.classList.contains('active')); i = i<0?0:i;
          items[i].classList.remove('active');
          i = e.key === 'ArrowDown' ? (i+1)%items.length : (i-1+items.length)%items.length;
          items[i].classList.add('active');
          items[i].scrollIntoView({block:'nearest'});
        } else if (e.key === 'Escape'){
          // close cleanly
          try { paletteInst().hide(); } catch(_){}
        }
      }
    };
    window.addEventListener('keydown', hotkey, true);   // capture
    document.addEventListener('keydown', hotkey, true); // extra safety
    window.__cmdHotkeyBound = true;
  }

  // Rebind open buttons (no duplicates across AJAX reloads)
  ['openPalette','btnCmdPalette'].forEach(id=>{
    const old = document.getElementById(id);
    if (old){ const clone = old.cloneNode(true); old.replaceWith(clone); clone.addEventListener('click', openPalette); }
  });

  // palette clicks (pin / open) — jQuery path
  if (window.jQuery) {
    $(paletteList).off('click.palette').on('click.palette', function(e){
      const pinBtn = e.target.closest('[data-pin]');
      if (pinBtn){
        // 🔒 stop everything so it never bubbles into the item click
        e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
        const page = pinBtn.getAttribute('data-pin');
        const it = ALLOWED.find(x => x.page === page) || {label: page, page};
        window.togglePin({label: it.label, page: it.page});
        const pinned = window.getPins().some(p => p.page === page);
        pinBtn.className = 'btn btn-sm ' + (pinned ? 'btn-warning' : 'btn-outline-secondary');
        return false;
      }
      const item = e.target.closest('.cmd-item');
      if (item){
        e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
        const page = item.getAttribute('data-page');
        const label = item.getAttribute('data-label');
        addRecent({label, page}); setLast(page);
        navigateFromPalette(page);
        return false;
      }
    });
  }

  // Native delegate for safety (in case jQuery isn’t ready)
  paletteList.addEventListener('click', function(e){
    const pinBtn = e.target.closest('[data-pin]');
    if (pinBtn){
      e.preventDefault(); e.stopPropagation();
      const page = pinBtn.getAttribute('data-pin');
      const it = ALLOWED.find(x => x.page === page) || {label: page, page};
      window.togglePin({label: it.label, page: it.page});
      const pinned = window.getPins().some(p => p.page === page);
      pinBtn.className = 'btn btn-sm ' + (pinned ? 'btn-warning' : 'btn-outline-secondary');
      return;
    }
    const item = e.target.closest('.cmd-item');
    if (item){
      e.preventDefault(); e.stopPropagation();
      const page = item.getAttribute('data-page');
      const label = item.getAttribute('data-label');
      addRecent({label, page}); setLast(page);
      navigateFromPalette(page);
    }
  }, true);

  // Generic [data-load] links anywhere (works with or without jQuery)
  document.addEventListener('click', function(e){
    const link = e.target.closest('[data-load]');
    if (!link) return;
    if (e.defaultPrevented) return;
    e.preventDefault();
    const page = link.getAttribute('data-load') || link.getAttribute('href');
    // ensure no modal residue before navigation
    hardCloseAllModals();
    const openModal = document.querySelector('.modal.show');
    if (openModal) {
      try {
        const inst = bootstrap.Modal.getOrCreateInstance(openModal);
        openModal.addEventListener('hidden.bs.modal', ()=> loadPage(page, {force:true}), {once:true});
        inst.hide();
      } catch(_){
        loadPage(page, {force:true});
      }
    } else {
      loadPage(page);
    }
  }, true);

  // After each AJAX inject, double-clean any stubborn overlays
  const _origLoadPage = window.loadPage;
  window.loadPage = function(page, opts={}){
    _origLoadPage(page, opts);
    setTimeout(hardCloseAllModals, 0);
    setTimeout(hardCloseAllModals, 200);
  };

  // initial render
  renderPins(); renderRecent(); renderContinue();

  // keep everything BELOW this unchanged…
})();
</script>
<script>
(function(){
  /* ===== Special Notes badge (poll + bounce + click-to-open) ===== */
  const NS = '.homeSN';
  let snTimer = null;
  let snLast = null;

  function bump(el){
    if (!el) return;
    el.classList.remove('bump');   // restart CSS animation
    // reflow
    // eslint-disable-next-line no-unused-expressions
    el.offsetWidth;
    el.classList.add('bump');
  }

  function setBadge(count){
    const badge = document.getElementById('badgeSpecialNotes');
    if (!badge) return;
    const n = Number(count) || 0;

    if (n > 0){
      badge.textContent = String(n);
      badge.classList.remove('d-none');
    } else {
      badge.textContent = '0';
      badge.classList.add('d-none');
    }

    if (snLast !== null && n > snLast) bump(badge);
    snLast = n;
  }

  function fetchCount(){
    $.getJSON('ajax-special-notes-count.php')
      .done(function(r){
        setBadge((r && typeof r.count === 'number') ? r.count : 0);
      })
      .fail(function(){ /* silent */ });
  }

  // initial + interval + resume on visibility
  fetchCount();
  snTimer = setInterval(fetchCount, 30000);
  document.addEventListener('visibilitychange', function(){
    if (!document.hidden) fetchCount();
  });

  // click handler: open Special Notes page
  $(document).off('click' + NS, '#btnSpecialNotes, .load-special-notes')
    .on('click' + NS, '#btnSpecialNotes, .load-special-notes', function(e){
      e.preventDefault();
      if (typeof window.loadPage === 'function') {
        window.loadPage('special-notes.php', { force: true });
      } else {
        // very safe fallback
        $('#contentArea').html('<div class="text-center p-4">Loading Special Notes…</div>');
        $.get('special-notes.php').done(function(html){ $('#contentArea').html(html); });
      }
      // OPTIONAL: immediately mark all as read on open (uncomment if you like)
      // $.post('ajax-special-notes-mark-read.php').always(fetchCount);
    });

  // clean up when navigating away (PageSandbox will call window.stopEverything if present)
  const prevStop = window.stopEverything;
  window.stopEverything = function(){
    if (snTimer){ clearInterval(snTimer); snTimer = null; }
    $(document).off(NS);
    if (typeof prevStop === 'function') { try { prevStop(); } catch(_){} }
  };
})();
</script>
