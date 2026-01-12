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
function alertHtml($type, $msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.
    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }

$action = strtoupper(trim($_POST['action'] ?? 'DRAFT'));
$gl_id = (int)($_POST['gl_id'] ?? 0);
$item_code = strtoupper(trim($_POST['item_code'] ?? ''));
$item_name = trim($_POST['item_name'] ?? '');
$uom = trim($_POST['uom'] ?? '');
$item_type_id_raw = trim($_POST['item_type_id'] ?? '');
$item_type_id = ($item_type_id_raw === '') ? null : (int)$item_type_id_raw;
$is_active = (int)($_POST['is_active'] ?? 1);
$maker_note = trim($_POST['maker_note'] ?? '');

if ($gl_id <= 0 || $item_code === '' || $item_name === '' || $uom === '') {
  echo alertHtml('danger','GL, Item Code, Item Name and UOM are required.');
  exit;
}
if (!in_array($action, ['DRAFT','SUBMIT'], true)) $action = 'DRAFT';
$record_status = ($action === 'SUBMIT') ? 'PENDING' : 'DRAFT';
$maker_user_id = currentUserId();

$mysqli->begin_transaction();
try {
  // duplicate by name
  $stmtN = $mysqli->prepare("SELECT item_id, item_code FROM tbl_admin_item WHERE item_name = ? LIMIT 1");
  $stmtN->bind_param("s", $item_name);
  $stmtN->execute();
  $nameRow = $stmtN->get_result()->fetch_assoc();

  // exists by code?
  $stmt = $mysqli->prepare("SELECT item_id, record_status FROM tbl_admin_item WHERE item_code = ? LIMIT 1");
  $stmt->bind_param("s", $item_code);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    $item_id = (int)$row['item_id'];
    $existing_status = $row['record_status'];

    if ($nameRow && (int)$nameRow['item_id'] !== $item_id) {
      $mysqli->rollback();
      echo alertHtml('danger', 'Item Name already exists for another item. Please use a unique name.');
      exit;
    }

    if (in_array($existing_status, ['PENDING','APPROVED'], true)) {
      $mysqli->rollback();
      echo alertHtml('danger', "Cannot edit Item <b>{$item_code}</b>. Current status is <b>{$existing_status}</b>.");
      exit;
    }

    $stmt2 = $mysqli->prepare("UPDATE tbl_admin_item
      SET gl_id=?, item_name=?, uom=?, item_type_id=?, is_active=?, record_status=?,
          maker_user_id=?, maker_at=NOW(), maker_note=?,
          checker_user_id=NULL, checker_at=NULL, checker_note=NULL
      WHERE item_id=?");

    // for nullable item_type_id: if null, set to null using a separate query trick
    // easiest: bind as integer and use 0 to represent null, then convert in SQL with NULLIF
    // We'll update SQL accordingly:
    $stmt2->close();

    $stmt2 = $mysqli->prepare("UPDATE tbl_admin_item
      SET gl_id=?, item_name=?, uom=?, item_type_id=NULLIF(?,0), is_active=?, record_status=?,
          maker_user_id=?, maker_at=NOW(), maker_note=?,
          checker_user_id=NULL, checker_at=NULL, checker_note=NULL
      WHERE item_id=?");

    $typeInt = $item_type_id ? (int)$item_type_id : 0;
    $stmt2->bind_param("issii s is i", $gl_id, $item_name, $uom, $typeInt, $is_active, $record_status, $maker_user_id, $maker_note, $item_id);
    // remove spaces in string:
    $stmt2->close();

    $stmt2 = $mysqli->prepare("UPDATE tbl_admin_item
      SET gl_id=?, item_name=?, uom=?, item_type_id=NULLIF(?,0), is_active=?, record_status=?,
          maker_user_id=?, maker_at=NOW(), maker_note=?,
          checker_user_id=NULL, checker_at=NULL, checker_note=NULL
      WHERE item_id=?");
    $stmt2->bind_param("issii s isi", $gl_id, $item_name, $uom, $typeInt, $is_active, $record_status, $maker_user_id, $maker_note, $item_id);
    // final correct:
    $stmt2->close();

    $stmt2 = $mysqli->prepare("UPDATE tbl_admin_item
      SET gl_id=?, item_name=?, uom=?, item_type_id=NULLIF(?,0), is_active=?, record_status=?,
          maker_user_id=?, maker_at=NOW(), maker_note=?,
          checker_user_id=NULL, checker_at=NULL, checker_note=NULL
      WHERE item_id=?");
    $stmt2->bind_param("issii sisi", $gl_id, $item_name, $uom, $typeInt, $is_active, $record_status, $maker_user_id, $maker_note, $item_id);
    $stmt2->execute();

    $mysqli->commit();
    echo alertHtml('success', "Item <b>{$item_code}</b> updated. Status: <b>{$record_status}</b>.");
    exit;

  } else {
    if ($nameRow) {
      $mysqli->rollback();
      $code = htmlspecialchars($nameRow['item_code']);
      echo alertHtml('danger', "Item Name already exists (Item Code: <b>{$code}</b>). Please use a unique name.");
      exit;
    }

    $stmt3 = $mysqli->prepare("INSERT INTO tbl_admin_item
      (gl_id, item_code, item_name, uom, item_type_id, is_active, record_status, maker_user_id, maker_at, maker_note)
      VALUES (?,?,?,?,NULLIF(?,0),?,?,?,NOW(),?)");

    $typeInt = $item_type_id ? (int)$item_type_id : 0;
    $stmt3->bind_param("isssii sis", $gl_id, $item_code, $item_name, $uom, $typeInt, $is_active, $record_status, $maker_user_id, $maker_note);
    $stmt3->execute();

    $mysqli->commit();
    echo alertHtml('success', "Item <b>{$item_code}</b> saved. Status: <b>{$record_status}</b>.");
    exit;
  }

} catch (Throwable $e) {
  $mysqli->rollback();
  echo alertHtml('danger', 'Save failed: ' . htmlspecialchars($e->getMessage()));
  exit;
}
