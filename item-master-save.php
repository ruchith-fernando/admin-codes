<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db() {
  global $conn, $con, $mysqli;
  if (isset($conn) && $conn instanceof mysqli) return $conn;
  if (isset($con) && $con instanceof mysqli) return $con;
  if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
  return null;
}
function currentUserId(){
  foreach (['user_id','userid','uid','id','USER_ID','UID'] as $k){
    if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
  }
  return 0;
}
function alertHtml($type, $msg, $savedId = 0, $barcodeValue = ''){
  $extra = $savedId ? ' <span data-saved-item-id="'.$savedId.'"></span>' : '';
  $extra .= $barcodeValue ? ' <span data-barcode-value="'.htmlspecialchars($barcodeValue).'"></span>' : '';
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.$extra.
    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }

$item_id = (int)($_POST['item_id'] ?? 0);
$gl_id = (int)($_POST['gl_id'] ?? 0);
$item_code = strtoupper(trim($_POST['item_code'] ?? ''));
$item_name = trim($_POST['item_name'] ?? '');
$uom = strtoupper(trim($_POST['uom'] ?? ''));
$item_type_id_raw = trim($_POST['item_type_id'] ?? '');
$item_type_id = ($item_type_id_raw === '') ? 0 : (int)$item_type_id_raw; // 0 => NULLIF
$is_active = (int)($_POST['is_active'] ?? 1);
$maker_note = trim($_POST['maker_note'] ?? '');

if ($gl_id <= 0 || $item_code === '' || $item_name === '' || $uom === '') {
  echo alertHtml('danger','GL, Item Code, Item Name and UOM are required.');
  exit;
}

$maker_user_id = currentUserId();
if ($maker_user_id <= 0) $maker_user_id = 1;

// No approval UI => save directly as APPROVED
$record_status = 'APPROVED';

$mysqli->begin_transaction();
try {
  // uniqueness checks (edit safe)
  $stmt = $mysqli->prepare("SELECT item_id FROM tbl_admin_item WHERE item_code=? LIMIT 1");
  $stmt->bind_param("s", $item_code);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  if ($r && (int)$r['item_id'] !== $item_id) {
    $mysqli->rollback();
    echo alertHtml('danger',"Item Code <b>{$item_code}</b> already exists.");
    exit;
  }

  $stmt = $mysqli->prepare("SELECT item_id, item_code FROM tbl_admin_item WHERE item_name=? LIMIT 1");
  $stmt->bind_param("s", $item_name);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  if ($r && (int)$r['item_id'] !== $item_id) {
    $mysqli->rollback();
    $code = htmlspecialchars($r['item_code']);
    echo alertHtml('danger',"Item Name already exists (Item Code: <b>{$code}</b>).");
    exit;
  }

  if ($item_id > 0) {
    $stmtU = $mysqli->prepare("
      UPDATE tbl_admin_item
      SET gl_id=?,
          item_name=?,
          uom=?,
          item_type_id=NULLIF(?,0),
          is_active=?,
          record_status=?,
          maker_user_id=?,
          maker_at=NOW(),
          maker_note=?,
          checker_user_id=NULL,
          checker_at=NULL,
          checker_note=NULL
      WHERE item_id=?
    ");
    // types: i s s i i s i s i
    $stmtU->bind_param("issii sisi", $gl_id, $item_name, $uom, $item_type_id, $is_active, $record_status, $maker_user_id, $maker_note, $item_id);
    $stmtU->execute();
  } else {
    $stmtI = $mysqli->prepare("
      INSERT INTO tbl_admin_item
        (gl_id, item_code, item_name, uom, item_type_id, is_active, record_status, maker_user_id, maker_at, maker_note)
      VALUES
        (?,?,?,?,NULLIF(?,0),?,?,?,NOW(),?)
    ");
    // types: i s s s i i s i s
    $stmtI->bind_param("isssii sis", $gl_id, $item_code, $item_name, $uom, $item_type_id, $is_active, $record_status, $maker_user_id, $maker_note);
    $stmtI->execute();
    $item_id = (int)$mysqli->insert_id;
  }

  // Upsert barcode row (value = item_code)
  $stmtB = $mysqli->prepare("
    INSERT INTO tbl_admin_item_barcode (item_id, barcode_value, barcode_format, generated_by, generated_at)
    VALUES (?, ?, 'CODE128', ?, NOW())
    ON DUPLICATE KEY UPDATE
      barcode_value=VALUES(barcode_value),
      updated_by=VALUES(generated_by),
      updated_at=NOW()
  ");
  $stmtB->bind_param("isi", $item_id, $item_code, $maker_user_id);
  $stmtB->execute();

  $mysqli->commit();
  echo alertHtml('success', "Item <b>{$item_code}</b> saved (Status: <b>{$record_status}</b>).", $item_id, $item_code);
  exit;

} catch (Throwable $e) {
  $mysqli->rollback();
  echo alertHtml('danger', 'Save failed: ' . htmlspecialchars($e->getMessage()));
  exit;
}
