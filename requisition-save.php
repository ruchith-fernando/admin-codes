<?php
// requisition-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'Session expired. Please login again.']);
  exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = strtoupper(trim($_POST['action'] ?? ''));

function jfail($msg){ echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
function jsucc($arr){ echo json_encode(array_merge(['ok'=>true], $arr)); exit; }

function tmpReqNo(): string {
  $t = time();
  $rand = strtoupper(bin2hex(random_bytes(4)));
  return "TMP-$t-$rand";
}

if ($action !== 'SUBMIT') jfail('Invalid action.');

$priority = trim($_POST['priority'] ?? 'NORMAL');
$required_date = trim($_POST['required_date'] ?? '');
$overall_justification = trim($_POST['overall_justification'] ?? '');

$lines_json = $_POST['lines_json'] ?? '';
$lines = json_decode($lines_json, true);

if (!in_array($priority, ['NORMAL','URGENT'], true)) $priority = 'NORMAL';
if (!is_array($lines) || count($lines) === 0) jfail('Add at least 1 line item.');

$required_date_sql = null;
if ($required_date !== '') {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $required_date)) jfail('Invalid required date.');
  $required_date_sql = $required_date;
}

// LOCK department based on user profile (category/category_auto) -> tbl_admin_departments.department_name
$user = null;
if ($stmt = $conn->prepare("SELECT id, category, category_auto, designation, name FROM tbl_admin_users WHERE id=? LIMIT 1")) {
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
}
if (!$user) jfail('User not found.');

$dept_label = trim((string)($user['category'] ?? ''));
if ($dept_label === '') $dept_label = trim((string)($user['category_auto'] ?? ''));
if ($dept_label === '') jfail('Your user department (category) is not set.');

$department_id = 0;
if ($stmt = $conn->prepare("SELECT department_id FROM tbl_admin_departments WHERE is_active=1 AND department_name=? LIMIT 1")) {
  $stmt->bind_param("s", $dept_label);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();
  $department_id = (int)($row['department_id'] ?? 0);
}
if ($department_id <= 0) jfail('Your department is not mapped in tbl_admin_departments. Please add it first.');

$conn->begin_transaction();

$reqIdInt = 0;

try {
  // Insert requisition header as IN_APPROVAL (no draft)
  $req_no = tmpReqNo();
  $status = 'IN_APPROVAL';
  $submitted_at = date('Y-m-d H:i:s');

  if ($stmt = $conn->prepare("INSERT INTO tbl_admin_requisitions
    (req_no, requester_user_id, department_id, priority, required_date,
     overall_justification, recommended_vendor_name, recommended_vendor_contact, recommended_vendor_note,
     status, approval_chain_id, submitted_at)
    VALUES
        (?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, NULL, ?)
    ")) {
    $stmt->bind_param(
        "siissssssss",
        $req_no,
        $uid,
        $department_id,
        $priority,
        $required_date_sql,
        $overall_justification,
        $vendor_name,
        $vendor_contact,
        $vendor_note,
        $status,
        $submitted_at
    );
    $stmt->execute();
    $reqIdInt = (int)$stmt->insert_id;
    $stmt->close();
    } else {
    throw new Exception('DB error: cannot insert requisition.');
    }


  // Insert lines (no unit price)
  $insLine = $conn->prepare("
    INSERT INTO tbl_admin_requisition_lines
      (req_id, item_name, specifications, qty, uom, budget_code, estimated_unit_price, estimated_line_total, line_justification)
    VALUES
      (?, ?, ?, ?, ?, ?, NULL, NULL, ?)
  ");
  if (!$insLine) throw new Exception('DB error: cannot prepare line insert.');

  foreach ($lines as $ln) {
    $item_name = trim($ln['item_name'] ?? '');
    if ($item_name === '') continue;

    $spec = trim($ln['specifications'] ?? '');
    $qty = (float)($ln['qty'] ?? 0);
    if ($qty <= 0) $qty = 1;

    $uom = trim($ln['uom'] ?? '');
    $budget_id = (int)($ln['budget_id'] ?? 0);
    $just = trim($ln['line_justification'] ?? '');

    // Convert budget_id -> store budget_code text in budget_code column (since your line table column is varchar)
    $budget_code_store = '';
    if ($budget_id > 0) {
      if ($bst = $conn->prepare("SELECT budget_code FROM tbl_admin_budgets WHERE id=? AND is_active=1 LIMIT 1")) {
        $bst->bind_param("i", $budget_id);
        $bst->execute();
        $bres = $bst->get_result();
        if ($brow = $bres->fetch_assoc()) $budget_code_store = (string)$brow['budget_code'];
        $bst->close();
      }
    }

    $qtyStr = number_format($qty, 3, '.', '');

    $insLine->bind_param(
      "issdsss",
      $reqIdInt,
      $item_name,
      $spec,
      $qtyStr,
      $uom,
      $budget_code_store,
      $just
    );
    $insLine->execute();
  }
  $insLine->close();

  // Find active chain for this department
  $chain_id = 0;
  if ($stmt = $conn->prepare("
    SELECT chain_id
    FROM tbl_admin_approval_chains
    WHERE department_id=? AND is_active=1
    ORDER BY version_no DESC, chain_id DESC
    LIMIT 1
  ")) {
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $chain_id = (int)($row['chain_id'] ?? 0);
  }
  if ($chain_id <= 0) throw new Exception('No active approval chain found for your department.');

  // Save chain_id on requisition
  if ($stmt = $conn->prepare("UPDATE tbl_admin_requisitions SET approval_chain_id=? WHERE req_id=?")) {
    $stmt->bind_param("ii", $chain_id, $reqIdInt);
    $stmt->execute();
    $stmt->close();
  }

  // Read chain steps + snapshot from users table (name + designation)
  $steps = [];
  if ($stmt = $conn->prepare("
    SELECT s.step_order, s.approver_user_id, u.name AS approver_name, u.designation AS approver_desig
    FROM tbl_admin_approval_chain_steps s
    INNER JOIN tbl_admin_users u ON u.id = s.approver_user_id
    WHERE s.chain_id=? AND s.is_active=1
    ORDER BY s.step_order ASC
  ")) {
    $stmt->bind_param("i", $chain_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $steps[] = $r;
    $stmt->close();
  }
  if (count($steps) === 0) throw new Exception('Approval chain has no steps.');

  // Insert runtime steps
  $ins = $conn->prepare("
    INSERT INTO tbl_admin_requisition_approval_steps
      (req_id, step_order, approver_user_id, approver_name_snapshot, approver_designation_snapshot, action)
    VALUES
      (?, ?, ?, ?, ?, 'PENDING')
  ");
  if (!$ins) throw new Exception('DB error: cannot prepare approval step insert.');

  foreach($steps as $s){
    $so = (int)$s['step_order'];
    $au = (int)$s['approver_user_id'];
    $an = (string)($s['approver_name'] ?? '');
    $ad = (string)($s['approver_desig'] ?? '');
    if ($ad === '') $ad = '-';

    $ins->bind_param("iiiss", $reqIdInt, $so, $au, $an, $ad);
    $ins->execute();
  }
  $ins->close();

  $conn->commit();

} catch (Throwable $e) {
  $conn->rollback();
  jfail($e->getMessage());
}

/* ==========================
   Attachments upload (after commit)
   Requires tbl_admin_attachments table (if exists).
   ========================== */

$uploadWarnings = [];
$hasAttachmentsTable = false;

try {
  if ($st = $conn->prepare("SHOW TABLES LIKE 'tbl_admin_attachments'")) {
    $st->execute();
    $r = $st->get_result();
    $hasAttachmentsTable = ($r && $r->num_rows > 0);
    $st->close();
  }
} catch(Throwable $e){ /* ignore */ }

if (!empty($_FILES['pr_files']) && is_array($_FILES['pr_files']['name'])) {
  $baseDir = __DIR__ . '/uploads/requisitions/' . $reqIdInt;
  if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);

  $names = $_FILES['pr_files']['name'];
  $tmps  = $_FILES['pr_files']['tmp_name'];
  $errs  = $_FILES['pr_files']['error'];
  $sizes = $_FILES['pr_files']['size'];

  for ($i=0; $i<count($names); $i++) {
    if ($errs[$i] !== UPLOAD_ERR_OK) { $uploadWarnings[] = "File skipped: ".$names[$i]; continue; }
    if ($sizes[$i] > 12*1024*1024) { $uploadWarnings[] = "File too large: ".$names[$i]; continue; }

    $orig = (string)$names[$i];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    // allow: pdf + common images
    $allowed = ['pdf','jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $allowed, true)) { $uploadWarnings[] = "Not allowed: ".$orig; continue; }

    $safeBase = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $newName = $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

    $destAbs = $baseDir . '/' . $newName;
    $destRel = 'uploads/requisitions/' . $reqIdInt . '/' . $newName;

    if (!@move_uploaded_file($tmps[$i], $destAbs)) {
      $uploadWarnings[] = "Upload failed: ".$orig;
      continue;
    }

    if ($hasAttachmentsTable) {
      if ($insA = $conn->prepare("
        INSERT INTO tbl_admin_attachments (entity_type, entity_id, file_name, file_path, uploaded_by)
        VALUES ('REQ', ?, ?, ?, ?)
      ")) {
        $insA->bind_param("issi", $reqIdInt, $orig, $destRel, $uid);
        $insA->execute();
        $insA->close();
      }
    }
  }
}

$msg = 'Requisition submitted and approval workflow started.';
if (!empty($uploadWarnings)) {
  $msg .= ' (Attachments: ' . count($uploadWarnings) . ' warning(s))';
}

jsucc(['req_id'=>$reqIdInt, 'msg'=>$msg]);
