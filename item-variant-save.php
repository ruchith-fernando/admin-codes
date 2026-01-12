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
$item_id = (int)($_POST['item_id'] ?? 0);
$item_type_id = (int)($_POST['item_type_id'] ?? 0);
$variant_code = strtoupper(trim($_POST['variant_code'] ?? ''));
$variant_name = trim($_POST['variant_name'] ?? '');
$variant_signature = trim($_POST['variant_signature'] ?? '');
$is_active = (int)($_POST['is_active'] ?? 1);
$maker_note = trim($_POST['maker_note'] ?? '');
$attr_values_json = $_POST['attr_values'] ?? '{}';

if ($item_id <= 0 || $variant_code === '') {
  echo alertHtml('danger','Base Item and Variant Code (SKU) are required.');
  exit;
}
if (!in_array($action, ['DRAFT','SUBMIT'], true)) $action = 'DRAFT';
$record_status = ($action === 'SUBMIT') ? 'PENDING' : 'DRAFT';
$maker_user_id = currentUserId();

$attr_values = json_decode($attr_values_json, true);
if (!is_array($attr_values)) $attr_values = [];

$mysqli->begin_transaction();
try {
  // If item_type_id is not provided by UI (should be), fetch from item
  if ($item_type_id <= 0) {
    $st = $mysqli->prepare("SELECT item_type_id FROM tbl_admin_item WHERE item_id=? LIMIT 1");
    $st->bind_param("i", $item_id);
    $st->execute();
    $rr = $st->get_result()->fetch_assoc();
    $item_type_id = (int)($rr['item_type_id'] ?? 0);
  }

  // Validate required attributes when type exists and submitting
  if ($item_type_id > 0 && $action === 'SUBMIT') {
    $stReq = $mysqli->prepare("SELECT a.attr_code
                               FROM tbl_admin_item_type_attribute ta
                               JOIN tbl_admin_attribute a ON a.attribute_id=ta.attribute_id
                               WHERE ta.item_type_id=? AND ta.is_required=1
                                 AND a.record_status='APPROVED' AND a.is_active=1");
    $stReq->bind_param("i", $item_type_id);
    $stReq->execute();
    $rsReq = $stReq->get_result();
    while ($r = $rsReq->fetch_assoc()) {
      $code = $r['attr_code'];
      $val = trim((string)($attr_values[$code] ?? ''));
      if ($val === '') {
        $mysqli->rollback();
        echo alertHtml('danger', "Missing required attribute: <b>{$code}</b>.");
        exit;
      }
    }
  }

  // Check if SKU exists
  $stExist = $mysqli->prepare("SELECT variant_id, record_status FROM tbl_admin_item_variant WHERE variant_code=? LIMIT 1");
  $stExist->bind_param("s", $variant_code);
  $stExist->execute();
  $existRow = $stExist->get_result()->fetch_assoc();

  if ($existRow) {
    $variant_id = (int)$existRow['variant_id'];
    $existing_status = $existRow['record_status'];

    if (in_array($existing_status, ['PENDING','APPROVED'], true)) {
      $mysqli->rollback();
      echo alertHtml('danger', "Cannot edit SKU <b>{$variant_code}</b>. Current status is <b>{$existing_status}</b>.");
      exit;
    }

    // update
    $stUp = $mysqli->prepare("UPDATE tbl_admin_item_variant
      SET item_id=?, item_type_id=?, variant_name=?, variant_signature=?, is_active=?, record_status=?,
          maker_user_id=?, maker_at=NOW(), maker_note=?,
          checker_user_id=NULL, checker_at=NULL, checker_note=NULL
      WHERE variant_id=?");
    $stUp->bind_param("iissisisi", $item_id, $item_type_id, $variant_name, $variant_signature, $is_active, $record_status, $maker_user_id, $maker_note, $variant_id);
    $stUp->execute();

    // replace values
    $mysqli->query("DELETE FROM tbl_admin_item_variant_value WHERE variant_id=".(int)$variant_id);

  } else {
    // insert
    if ($variant_name === '') $variant_name = $variant_code;

    $stIn = $mysqli->prepare("INSERT INTO tbl_admin_item_variant
      (item_id, item_type_id, variant_code, variant_name, variant_signature, is_active, record_status, maker_user_id, maker_at, maker_note)
      VALUES (?,?,?,?,?,?,?, ?, NOW(), ?)");
    $stIn->bind_param("iisssisis", $item_id, $item_type_id, $variant_code, $variant_name, $variant_signature, $is_active, $record_status, $maker_user_id, $maker_note);
    $stIn->execute();
    $variant_id = (int)$mysqli->insert_id;
  }

  // Insert variant attribute values
  if ($item_type_id > 0) {
    // load all attributes for type (approved+active)
    $stAttr = $mysqli->prepare("SELECT a.attribute_id, a.attr_code, a.data_type
                                FROM tbl_admin_item_type_attribute ta
                                JOIN tbl_admin_attribute a ON a.attribute_id=ta.attribute_id
                                WHERE ta.item_type_id=? AND a.record_status='APPROVED' AND a.is_active=1");
    $stAttr->bind_param("i", $item_type_id);
    $stAttr->execute();
    $rsAttr = $stAttr->get_result();

    while ($a = $rsAttr->fetch_assoc()) {
      $attribute_id = (int)$a['attribute_id'];
      $attr_code = $a['attr_code'];
      $data_type = $a['data_type'];

      $raw = trim((string)($attr_values[$attr_code] ?? ''));
      if ($raw === '') continue;

      $option_id = null;
      $value_text = null;
      $value_number = null;

      if ($data_type === 'OPTION') {
        // find option_id by code
        $stOpt = $mysqli->prepare("SELECT option_id FROM tbl_admin_attribute_option
                                   WHERE attribute_id=? AND option_code=? AND record_status='APPROVED' AND is_active=1
                                   LIMIT 1");
        $stOpt->bind_param("is", $attribute_id, $raw);
        $stOpt->execute();
        $optRow = $stOpt->get_result()->fetch_assoc();
        if (!$optRow) continue;

        $option_id = (int)$optRow['option_id'];
        $value_text = $raw; // store code too (useful for reporting)
      } elseif ($data_type === 'NUMBER') {
        $value_number = (float)$raw;
      } else {
        $value_text = $raw;
      }

      $stV = $mysqli->prepare("INSERT INTO tbl_admin_item_variant_value
        (variant_id, attribute_id, option_id, value_text, value_number)
        VALUES (?,?,?,?,?)");

      $optBind = $option_id ? $option_id : null;
      $txtBind = $value_text;
      $numBind = $value_number;

      // bind (option_id can be null; easiest: NULLIF approach not possible here; we allow 0)
      $optInt = $option_id ? (int)$option_id : 0;
      $stV->close();

      $stV = $mysqli->prepare("INSERT INTO tbl_admin_item_variant_value
        (variant_id, attribute_id, option_id, value_text, value_number)
        VALUES (?,?,NULLIF(?,0),?,?)");
      $stV->bind_param("iiisd", $variant_id, $attribute_id, $optInt, $txtBind, $numBind);
      $stV->execute();
    }
  }

  $mysqli->commit();
  echo alertHtml('success', "SKU <b>{$variant_code}</b> saved. Status: <b>{$record_status}</b>.");
  exit;

} catch (Throwable $e) {
  $mysqli->rollback();
  echo alertHtml('danger', 'Save failed: ' . htmlspecialchars($e->getMessage()));
  exit;
}
