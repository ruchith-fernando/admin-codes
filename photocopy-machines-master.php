<?php
// photocopy-machines-master.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "connections/connection.php"; // must provide $conn (mysqli)

// ---------------- Helpers ----------------
function json_out($arr){
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode($arr);
  exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function table_has_column(mysqli $conn, $table, $column){
  $table  = $conn->real_escape_string($table);
  $column = $conn->real_escape_string($column);
  $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
  return $r && $r->num_rows > 0;
}

function pick_vendor_name_col(mysqli $conn){
  $candidates = ['vendor_name','name','vendor','vendorName','vendor_title','title'];
  foreach ($candidates as $c){
    if (table_has_column($conn, 'tbl_admin_vendors', $c)) return $c;
  }
  return null;
}

function clean_str($s, $max=255){
  $s = trim((string)$s);
  if ($max > 0 && mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
  return $s;
}

function clean_int0($v){
  $v = trim((string)$v);
  if ($v === '' || !preg_match('/^\d+$/', $v)) return 0;
  return (int)$v;
}

// ---------------- AJAX actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!isset($conn) || !($conn instanceof mysqli)) {
    json_out(["status"=>"error","message"=>"Database connection missing (\$conn)."]);
  }

  $action = $_POST['action'];

  // get rate profiles by vendor (for dropdown)
  if ($action === 'get_rate_profiles') {
    $vendorId = clean_int0($_POST['vendor_id'] ?? 0);

    $sql = "
      SELECT rate_profile_id, model_match, copy_rate, sscl_percentage, vat_percentage, effective_from, effective_to, is_active
      FROM tbl_admin_photocopy_rate_profiles
      WHERE vendor_id = ?
      ORDER BY is_active DESC, effective_from DESC, rate_profile_id DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vendorId);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($r = $res->fetch_assoc()) {
      $label = "ID {$r['rate_profile_id']} | Rate {$r['copy_rate']} | SSCL {$r['sscl_percentage']}% | VAT {$r['vat_percentage']}%";
      if (!empty($r['model_match'])) $label .= " | Model: ".$r['model_match'];
      if (!empty($r['effective_from']) || !empty($r['effective_to'])) {
        $label .= " | ".$r['effective_from']." → ".$r['effective_to'];
      }
      if ((int)$r['is_active'] !== 1) $label .= " (INACTIVE)";
      $items[] = ["id"=>(int)$r['rate_profile_id'], "label"=>$label, "is_active"=>(int)$r['is_active']];
    }

    json_out(["status"=>"success","items"=>$items]);
  }

  // create machine
  if ($action === 'create_machine') {
    $serialNo = clean_str($_POST['serial_no'] ?? '', 100);
    $model    = clean_str($_POST['model_name'] ?? '', 255);
    $vendorId = clean_int0($_POST['vendor_id'] ?? 0);
    $rateId   = clean_int0($_POST['rate_profile_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($serialNo === '') {
      json_out(["status"=>"error","message"=>"Serial No is required."]);
    }

    // Insert (NULLIF turns 0 into NULL)
    $sql = "
      INSERT INTO tbl_admin_photocopy_machines
      (model_name, serial_no, vendor_id, rate_profile_id, is_active)
      VALUES
      (?, ?, NULLIF(?,0), NULLIF(?,0), ?)
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_out(["status"=>"error","message"=>"Prepare failed: ".$conn->error]);

    $stmt->bind_param("ssiii", $model, $serialNo, $vendorId, $rateId, $isActive);

    if (!$stmt->execute()) {
      // duplicate serial
      if ($conn->errno === 1062) {
        json_out(["status"=>"error","message"=>"This serial already exists in Machines Master."]);
      }
      json_out(["status"=>"error","message"=>"Insert failed: ".$conn->error]);
    }

    json_out(["status"=>"success","message"=>"✅ Machine added successfully.", "machine_id" => (int)$conn->insert_id]);
  }

  // update machine
  if ($action === 'update_machine') {
    $machineId = clean_int0($_POST['machine_id'] ?? 0);
    $serialNo  = clean_str($_POST['serial_no'] ?? '', 100);
    $model     = clean_str($_POST['model_name'] ?? '', 255);
    $vendorId  = clean_int0($_POST['vendor_id'] ?? 0);
    $rateId    = clean_int0($_POST['rate_profile_id'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if ($machineId <= 0) json_out(["status"=>"error","message"=>"Invalid machine_id."]);
    if ($serialNo === '') json_out(["status"=>"error","message"=>"Serial No is required."]);

    $sql = "
      UPDATE tbl_admin_photocopy_machines
      SET model_name = ?,
          serial_no = ?,
          vendor_id = NULLIF(?,0),
          rate_profile_id = NULLIF(?,0),
          is_active = ?
      WHERE machine_id = ?
      LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_out(["status"=>"error","message"=>"Prepare failed: ".$conn->error]);

    $stmt->bind_param("ssiiii", $model, $serialNo, $vendorId, $rateId, $isActive, $machineId);

    if (!$stmt->execute()) {
      if ($conn->errno === 1062) {
        json_out(["status"=>"error","message"=>"This serial already exists on another machine record."]);
      }
      json_out(["status"=>"error","message"=>"Update failed: ".$conn->error]);
    }

    json_out(["status"=>"success","message"=>"✅ Machine updated successfully."]);
  }

  // toggle active
  if ($action === 'toggle_active') {
    $machineId = clean_int0($_POST['machine_id'] ?? 0);
    if ($machineId <= 0) json_out(["status"=>"error","message"=>"Invalid machine_id."]);

    $sql = "UPDATE tbl_admin_photocopy_machines SET is_active = IF(is_active=1,0,1) WHERE machine_id=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $machineId);

    if (!$stmt->execute()) {
      json_out(["status"=>"error","message"=>"Toggle failed: ".$conn->error]);
    }

    json_out(["status"=>"success","message"=>"✅ Status updated."]);
  }

  json_out(["status"=>"error","message"=>"Unknown action."]);
}

// ---------------- Page data (GET) ----------------
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("DB connection missing.");
}

$vendorNameCol = pick_vendor_name_col($conn);

/**
 * Filter vendors to only PHOTOCOPY (if vendor_type column exists)
 */
$whereVendorType = '';
if (table_has_column($conn, 'tbl_admin_vendors', 'vendor_type')) {
  $whereVendorType = "WHERE vendor_type = 'PHOTOCOPY'";
}

// Vendors dropdown
$vendors = [];
if ($vendorNameCol) {
  $qv = $conn->query("
    SELECT vendor_id, `$vendorNameCol` AS vendor_name
    FROM tbl_admin_vendors
    $whereVendorType
    ORDER BY `$vendorNameCol` ASC
  ");
  while($r = $qv->fetch_assoc()) $vendors[] = $r;
} else {
  $qv = $conn->query("
    SELECT vendor_id, vendor_id AS vendor_name
    FROM tbl_admin_vendors
    $whereVendorType
    ORDER BY vendor_id ASC
  ");
  while($r = $qv->fetch_assoc()) $vendors[] = $r;
}

// Machines list
$vendorSelect = $vendorNameCol ? "v.`$vendorNameCol` AS vendor_name" : "NULL AS vendor_name";

/**
 * OPTIONAL: show only machines linked to PHOTOCOPY vendors
 * (but still show machines with no vendor_id set)
 */
$machinesWhere = '';
if (table_has_column($conn, 'tbl_admin_vendors', 'vendor_type')) {
  $machinesWhere = "WHERE (m.vendor_id IS NULL OR m.vendor_id = 0 OR v.vendor_type = 'PHOTOCOPY')";
}

$sqlList = "
  SELECT
    m.machine_id, m.model_name, m.serial_no, m.vendor_id, m.rate_profile_id, m.is_active,
    m.created_at, m.updated_at,
    $vendorSelect,
    rp.copy_rate, rp.sscl_percentage, rp.vat_percentage, rp.model_match
  FROM tbl_admin_photocopy_machines m
  LEFT JOIN tbl_admin_vendors v ON v.vendor_id = m.vendor_id
  LEFT JOIN tbl_admin_photocopy_rate_profiles rp ON rp.rate_profile_id = m.rate_profile_id
  $machinesWhere
  ORDER BY m.machine_id DESC
";
$resList = $conn->query($sqlList);
$machines = [];
while($r = $resList->fetch_assoc()) $machines[] = $r;

// Machines list
$vendorSelect = $vendorNameCol ? "v.`$vendorNameCol` AS vendor_name" : "NULL AS vendor_name";

$sqlList = "
  SELECT
    m.machine_id, m.model_name, m.serial_no, m.vendor_id, m.rate_profile_id, m.is_active,
    m.created_at, m.updated_at,
    $vendorSelect,
    rp.copy_rate, rp.sscl_percentage, rp.vat_percentage, rp.model_match
  FROM tbl_admin_photocopy_machines m
  LEFT JOIN tbl_admin_vendors v ON v.vendor_id = m.vendor_id
  LEFT JOIN tbl_admin_photocopy_rate_profiles rp ON rp.rate_profile_id = m.rate_profile_id
  ORDER BY m.machine_id DESC
";
$resList = $conn->query($sqlList);
$machines = [];
while($r = $resList->fetch_assoc()) $machines[] = $r;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Photocopy — Machines (Master)</title>

<style>
.pc-wrap { width:100%; overflow-x:auto; }
.pc-table { width:100%; min-width: 1100px; }
.pc-table th, .pc-table td { white-space: nowrap; vertical-align: middle; }
.pc-table td.wrap { white-space: normal; }
.badge-soft { padding:.35rem .6rem; border-radius:999px; font-weight:600; }
.badge-on { background:#e8f5e9; color:#1b5e20; }
.badge-off { background:#ffebee; color:#b71c1c; }
</style>
</head>

<body>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">Photocopy — Machines (Master)</h5>

      <div id="pc_machine_msg" class="mb-3"></div>

      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-success" id="btnAddMachine">+ Add New Machine</button>
        <input class="form-control" id="txtSearch" style="max-width:320px" placeholder="Search (serial / model / vendor)…">
      </div>

      <div class="pc-wrap">
        <table class="table table-bordered table-hover pc-table" id="machinesTable">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Serial No</th>
              <th>Model Name</th>
              <th>Vendor</th>
              <th>Rate Profile</th>
              <th>Status</th>
              <th>Created</th>
              <th>Updated</th>
              <th style="width:1%">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($machines as $m): ?>
            <?php
              $vendorLabel = $m['vendor_name'] ? ($m['vendor_name']." (#".$m['vendor_id'].")") : ($m['vendor_id'] ? ("#".$m['vendor_id']) : "-");
              $rateLabel = $m['rate_profile_id']
                ? ("#".$m['rate_profile_id']." | Rate ".$m['copy_rate']." | SSCL ".$m['sscl_percentage']."% | VAT ".$m['vat_percentage']."%".(!empty($m['model_match']) ? (" | ".$m['model_match']) : ""))
                : "-";
              $isActive = ((int)$m['is_active'] === 1);
            ?>
            <tr
              data-machine_id="<?=h($m['machine_id'])?>"
              data-serial_no="<?=h($m['serial_no'])?>"
              data-model_name="<?=h($m['model_name'])?>"
              data-vendor_id="<?=h((int)$m['vendor_id'])?>"
              data-rate_profile_id="<?=h((int)$m['rate_profile_id'])?>"
              data-is_active="<?=h((int)$m['is_active'])?>"
            >
              <td><?=h($m['machine_id'])?></td>
              <td><b><?=h($m['serial_no'])?></b></td>
              <td class="wrap"><?=h($m['model_name'])?></td>
              <td><?=h($vendorLabel)?></td>
              <td class="wrap"><?=h($rateLabel)?></td>
              <td>
                <span class="badge-soft <?= $isActive ? 'badge-on' : 'badge-off' ?>">
                  <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
                </span>
              </td>
              <td><?=h($m['created_at'])?></td>
              <td><?=h($m['updated_at'])?></td>
              <td class="text-nowrap">
                <button class="btn btn-sm btn-primary btnEdit">Edit</button>
                <button class="btn btn-sm btn-outline-secondary btnToggle"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<!-- Modal: Add/Edit Machine -->
<div class="modal fade" id="machineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="machineModalTitle">Add Machine</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="machineForm">
        <div class="modal-body">
          <input type="hidden" name="action" id="formAction" value="create_machine">
          <input type="hidden" name="machine_id" id="machine_id" value="">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Serial No <span class="text-danger">*</span></label>
              <input class="form-control" name="serial_no" id="serial_no" required>
            </div>

            <div class="col-md-8">
              <label class="form-label">Model Name</label>
              <input class="form-control" name="model_name" id="model_name">
            </div>

            <div class="col-md-6">
              <label class="form-label">Vendor</label>
              <select class="form-select" name="vendor_id" id="vendor_id">
                <option value="0">-- Select Vendor --</option>
                <?php foreach($vendors as $v): ?>
                  <option value="<?=h($v['vendor_id'])?>"><?=h($v['vendor_name'])?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Vendor is recommended (rate profiles are vendor-based).</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Rate Profile</label>
              <select class="form-select" name="rate_profile_id" id="rate_profile_id">
                <option value="0">-- Select Rate Profile --</option>
              </select>
              <div class="form-text">Choose a rate profile or leave blank (auto-resolve can be used elsewhere).</div>
            </div>

            <div class="col-md-6">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                <label class="form-check-label" for="is_active">Active</label>
              </div>
            </div>

            <div class="col-12">
              <div id="modalMsg"></div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success" id="btnSaveMachine">Save</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
(function(){
  const $msg = $('#pc_machine_msg');
  const modalEl = document.getElementById('machineModal');
  const modal = new bootstrap.Modal(modalEl);

  function showMsg(type, text){
    const cls = (type==='success') ? 'alert-success' : 'alert-danger';
    $msg.html(`<div class="alert ${cls} mb-0">${text}</div>`);
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function showModalMsg(type, text){
    const cls = (type==='success') ? 'alert-success' : 'alert-danger';
    $('#modalMsg').html(`<div class="alert ${cls} mb-0">${text}</div>`);
  }

  function resetModal(){
    $('#machineModalTitle').text('Add Machine');
    $('#formAction').val('create_machine');
    $('#machine_id').val('');
    $('#serial_no').val('');
    $('#model_name').val('');
    $('#vendor_id').val('0');
    $('#rate_profile_id').html('<option value="0">-- Select Rate Profile --</option>');
    $('#is_active').prop('checked', true);
    $('#modalMsg').empty();
  }

  function loadRateProfiles(vendorId, selectedId){
    $('#rate_profile_id').html('<option value="0">Loading…</option>');
    $.post('photocopy-machines-master.php', {action:'get_rate_profiles', vendor_id: vendorId}, function(resp){
      if(resp.status !== 'success'){
        $('#rate_profile_id').html('<option value="0">-- Select Rate Profile --</option>');
        return;
      }
      let html = '<option value="0">-- Select Rate Profile --</option>';
      (resp.items || []).forEach(it => {
        const sel = (selectedId && parseInt(selectedId,10) === parseInt(it.id,10)) ? 'selected' : '';
        html += `<option value="${it.id}" ${sel}>${it.label}</option>`;
      });
      $('#rate_profile_id').html(html);
    }, 'json');
  }

  // Add button
  $('#btnAddMachine').on('click', function(){
    resetModal();
    modal.show();
  });

  // Vendor change => reload rate profiles
  $('#vendor_id').on('change', function(){
    const vid = $(this).val();
    if(parseInt(vid,10) > 0) loadRateProfiles(vid, 0);
    else $('#rate_profile_id').html('<option value="0">-- Select Rate Profile --</option>');
  });

  // Edit button
  $(document).on('click', '.btnEdit', function(){
    const $tr = $(this).closest('tr');
    resetModal();
    $('#machineModalTitle').text('Edit Machine');
    $('#formAction').val('update_machine');

    $('#machine_id').val($tr.data('machine_id'));
    $('#serial_no').val($tr.data('serial_no'));
    $('#model_name').val($tr.data('model_name') || '');
    $('#vendor_id').val($tr.data('vendor_id') || 0);

    const vid = $tr.data('vendor_id') || 0;
    const rid = $tr.data('rate_profile_id') || 0;

    if(parseInt(vid,10) > 0) loadRateProfiles(vid, rid);
    else $('#rate_profile_id').html('<option value="0">-- Select Rate Profile --</option>');

    $('#is_active').prop('checked', parseInt($tr.data('is_active'),10) === 1);

    modal.show();
  });

  // Toggle active
  $(document).on('click', '.btnToggle', function(){
    const $tr = $(this).closest('tr');
    const id = $tr.data('machine_id');
    if(!confirm('Change machine status (activate/deactivate)?')) return;

    $.post('photocopy-machines-master.php', {action:'toggle_active', machine_id:id}, function(resp){
      if(resp.status==='success'){
        showMsg('success', resp.message + " (Reloading…)"); 
        setTimeout(()=>location.reload(), 500);
      } else {
        showMsg('error', '❌ ' + (resp.message || 'Failed'));
      }
    }, 'json');
  });

  // Save (create/update)
  $('#machineForm').on('submit', function(e){
    e.preventDefault();
    $('#btnSaveMachine').prop('disabled', true);
    $('#modalMsg').empty();

    $.ajax({
      url: 'photocopy-machines-master.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(resp){
        if(resp.status==='success'){
          showModalMsg('success', resp.message + " Reloading…");
          setTimeout(()=>location.reload(), 600);
        } else {
          showModalMsg('error', '❌ ' + (resp.message || 'Failed'));
        }
      },
      error: function(xhr){
        showModalMsg('error', '❌ ' + (xhr.responseText || 'Request failed'));
      },
      complete: function(){
        $('#btnSaveMachine').prop('disabled', false);
      }
    });
  });

  // Simple client-side search
  $('#txtSearch').on('input', function(){
    const q = $(this).val().toLowerCase().trim();
    $('#machinesTable tbody tr').each(function(){
      const text = $(this).text().toLowerCase();
      $(this).toggle(text.indexOf(q) !== -1);
    });
  });

})();
</script>

</body>
</html>
