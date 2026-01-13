<?php
// attribute-master-api.php (aligned to your tables, JSON-safe)
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

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
function jsonOut($arr){
  if (ob_get_length()) ob_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr);
  exit;
}

$action = strtolower(trim($_POST['action'] ?? ''));

// json endpoints
$jsonActions = ['attr_list','attr_load_one','opt_list','opt_load_one'];

register_shutdown_function(function() use ($action, $jsonActions){
  $err = error_get_last();
  if (!$err) return;
  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
  if (!in_array($err['type'], $fatalTypes, true)) return;

  if (in_array($action, $jsonActions, true)) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>0,'error'=>'Fatal PHP error: '.$err['message'].' in '.$err['file'].':'.$err['line']]);
    exit;
  }
  if (ob_get_length()) ob_clean();
  echo alertHtml('danger','Fatal PHP error: '.htmlspecialchars($err['message']));
  exit;
});

$mysqli = db();
if (!$mysqli) {
  if (in_array($action, $jsonActions, true)) jsonOut(['ok'=>0,'error'=>'DB connection not found.']);
  http_response_code(500);
  echo alertHtml('danger','DB connection not found.');
  exit;
}

/* =========================================================
   ATTRIBUTES (tbl_admin_attribute)
   columns: attr_code, attr_name, data_type, is_active, record_status, maker_user_id, maker_at, maker_note...
========================================================= */

/* --------- ATTR CHECK CODE (HTML) --------- */
if ($action === 'attr_check_code') {
  $id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['attribute_code'] ?? ''));
  if ($code === '') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT attribute_id, attr_name FROM tbl_admin_attribute WHERE attr_code=? LIMIT 1");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    if ((int)$row['attribute_id'] === $id) echo alertHtml('success', "Code <b>{$code}</b> is yours (editing).");
    else echo alertHtml('warning', "Code <b>{$code}</b> already exists for <b>".htmlspecialchars($row['attr_name'])."</b>.");
  } else {
    echo alertHtml('success', "Code <b>{$code}</b> is available.");
  }
  exit;
}

/* --------- ATTR CHECK NAME (HTML) --------- */
if ($action === 'attr_check_name') {
  $id = (int)($_POST['attribute_id'] ?? 0);
  $name = trim($_POST['attribute_name'] ?? '');
  if ($name === '') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT attribute_id FROM tbl_admin_attribute WHERE attr_name=? LIMIT 1");
  $stmt->bind_param("s", $name);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    if ((int)$row['attribute_id'] === $id) echo alertHtml('success', "Name is yours (editing).");
    else echo alertHtml('warning', "Name already exists.");
  } else {
    echo alertHtml('success', "Name is available.");
  }
  exit;
}

/* --------- ATTR LOAD ONE (JSON) --------- */
if ($action === 'attr_load_one') {
  $id = (int)($_POST['attribute_id'] ?? 0);
  if ($id <= 0) jsonOut(['ok'=>0,'error'=>'Invalid attribute_id']);

  $stmt = $mysqli->prepare("
    SELECT
      attribute_id,
      attr_code  AS attribute_code,
      attr_name  AS attribute_name,
      data_type  AS data_type,
      is_active,
      maker_note
    FROM tbl_admin_attribute
    WHERE attribute_id=? LIMIT 1
  ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) jsonOut(['ok'=>0,'error'=>'Not found']);
  jsonOut(['ok'=>1,'attribute'=>$row]);
}

/* --------- ATTR SAVE (HTML) --------- */
if ($action === 'attr_save') {
  $id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['attribute_code'] ?? ''));
  $name = trim($_POST['attribute_name'] ?? '');
  $data_type = strtoupper(trim($_POST['data_type'] ?? 'OPTION'));
  $active = (int)($_POST['is_active'] ?? 1);
  $maker_note = trim($_POST['maker_note'] ?? '');

  if ($code === '' || $name === '') { echo alertHtml('danger','Code and Name are required.'); exit; }
  if (!in_array($data_type, ['OPTION','TEXT','NUMBER'], true)) $data_type = 'OPTION';

  // duplicate code
  $stmt = $mysqli->prepare("SELECT attribute_id FROM tbl_admin_attribute WHERE attr_code=? LIMIT 1");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $d = $stmt->get_result()->fetch_assoc();
  if ($d && (int)$d['attribute_id'] !== $id) { echo alertHtml('danger',"Code <b>{$code}</b> already exists."); exit; }

  // duplicate name
  $stmt = $mysqli->prepare("SELECT attribute_id FROM tbl_admin_attribute WHERE attr_name=? LIMIT 1");
  $stmt->bind_param("s", $name);
  $stmt->execute();
  $d = $stmt->get_result()->fetch_assoc();
  if ($d && (int)$d['attribute_id'] !== $id) { echo alertHtml('danger',"Name already exists."); exit; }

  $uid = currentUserId(); if ($uid<=0) $uid=1;

  $mysqli->begin_transaction();
  try {
    if ($id > 0) {
      // lock PENDING/APPROVED
      $st = $mysqli->prepare("SELECT record_status FROM tbl_admin_attribute WHERE attribute_id=? LIMIT 1");
      $st->bind_param("i", $id);
      $st->execute();
      $rs = ($st->get_result()->fetch_assoc()['record_status'] ?? 'DRAFT');
      if (in_array($rs, ['PENDING','APPROVED'], true)) {
        $mysqli->rollback();
        echo alertHtml('danger',"Cannot edit. Current status is <b>{$rs}</b>.");
        exit;
      }

      $stmt = $mysqli->prepare("UPDATE tbl_admin_attribute
        SET attr_code=?, attr_name=?, data_type=?, is_active=?,
            maker_user_id=?, maker_at=NOW(), maker_note=?,
            checker_user_id=NULL, checker_at=NULL, checker_note=NULL,
            record_status='DRAFT'
        WHERE attribute_id=?");
      $stmt->bind_param("sssiisi", $code, $name, $data_type, $active, $uid, $maker_note, $id);
      $stmt->execute();

      $mysqli->commit();
      echo alertHtml('success',"Attribute updated successfully (Status: DRAFT).");
      exit;
    } else {
      $stmt = $mysqli->prepare("INSERT INTO tbl_admin_attribute
        (attr_code, attr_name, data_type, is_active, record_status, maker_user_id, maker_at, maker_note)
        VALUES (?,?,?,?, 'DRAFT', ?, NOW(), ?)");
      $stmt->bind_param("sssiss", $code, $name, $data_type, $active, $uid, $maker_note);
      $stmt->execute();

      $mysqli->commit();
      echo alertHtml('success',"Attribute saved successfully (Status: DRAFT).");
      exit;
    }
  } catch (Throwable $e) {
    $mysqli->rollback();
    echo alertHtml('danger','Save failed: '.htmlspecialchars($e->getMessage()));
    exit;
  }
}

/* --------- ATTR LIST (JSON) --------- */
if ($action === 'attr_list') {
  $q = trim($_POST['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? 1));
  $per = (int)($_POST['per_page'] ?? 10);
  if ($per<=0) $per=10; if ($per>100) $per=100;
  $offset = ($page-1)*$per;

  $where = "1=1";
  $types = "";
  $params = [];

  if ($q !== '') {
    $where .= " AND (attr_code LIKE CONCAT('%',?,'%') OR attr_name LIKE CONCAT('%',?,'%'))";
    $types = "ss";
    $params = [$q,$q];
  }

  $sqlCnt = "SELECT COUNT(*) AS cnt FROM tbl_admin_attribute WHERE $where";
  $stmt = $mysqli->prepare($sqlCnt);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

  $pages = max(1, (int)ceil($total/$per));
  if ($page>$pages) { $page=$pages; $offset=($page-1)*$per; }

  $sql = "
    SELECT
      attribute_id,
      attr_code AS attribute_code,
      attr_name AS attribute_name,
      data_type AS data_type,
      is_active
    FROM tbl_admin_attribute
    WHERE $where
    ORDER BY attr_name ASC
    LIMIT ? OFFSET ?
  ";

  $stmt = $mysqli->prepare($sql);
  if ($types) {
    $types2 = $types."ii";
    $params2 = array_merge($params, [$per, $offset]);
    $stmt->bind_param($types2, ...$params2);
  } else {
    $stmt->bind_param("ii", $per, $offset);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($r=$res->fetch_assoc()) $rows[]=$r;

  $from = $total ? ($offset+1) : 0;
  $to = min($total, $offset+count($rows));

  jsonOut(['ok'=>1,'page'=>$page,'pages'=>$pages,'per_page'=>$per,'total'=>$total,'from'=>$from,'to'=>$to,'rows'=>$rows]);
}

/* =========================================================
   OPTIONS (tbl_admin_attribute_option)
========================================================= */

/* --------- OPTION: LOAD ATTRS DROPDOWN (HTML) --------- */
if ($action === 'opt_load_attrs') {
  // only active attributes
  $res = $mysqli->query("SELECT attribute_id, attr_name, attr_code
                         FROM tbl_admin_attribute
                         WHERE is_active=1
                         ORDER BY attr_name ASC");
  if (!$res) { echo ''; exit; }
  while ($r=$res->fetch_assoc()) {
    $id=(int)$r['attribute_id'];
    $label=htmlspecialchars($r['attr_name'].' ('.$r['attr_code'].')');
    echo "<option value=\"{$id}\">{$label}</option>";
  }
  exit;
}

/* --------- OPTION CHECK CODE (HTML) --------- */
if ($action === 'opt_check_code') {
  $option_id = (int)($_POST['option_id'] ?? 0);
  $attribute_id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['option_code'] ?? ''));
  if ($attribute_id<=0 || $code==='') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT option_id, option_name FROM tbl_admin_attribute_option
                            WHERE attribute_id=? AND option_code=? LIMIT 1");
  $stmt->bind_param("is", $attribute_id, $code);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    if ((int)$row['option_id'] === $option_id) echo alertHtml('success', "Option Code <b>{$code}</b> is yours (editing).");
    else echo alertHtml('warning', "Option Code <b>{$code}</b> already exists for <b>".htmlspecialchars($row['option_name'])."</b>.");
  } else {
    echo alertHtml('success', "Option Code <b>{$code}</b> is available.");
  }
  exit;
}

/* --------- OPTION CHECK NAME (HTML) --------- */
if ($action === 'opt_check_name') {
  $option_id = (int)($_POST['option_id'] ?? 0);
  $attribute_id = (int)($_POST['attribute_id'] ?? 0);
  $name = trim($_POST['option_name'] ?? '');
  if ($attribute_id<=0 || $name==='') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT option_id FROM tbl_admin_attribute_option
                            WHERE attribute_id=? AND option_name=? LIMIT 1");
  $stmt->bind_param("is", $attribute_id, $name);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    if ((int)$row['option_id'] === $option_id) echo alertHtml('success', "Option Name is yours (editing).");
    else echo alertHtml('warning', "Option Name already exists for this attribute.");
  } else {
    echo alertHtml('success', "Option Name is available.");
  }
  exit;
}

/* --------- OPTION LOAD ONE (JSON) --------- */
if ($action === 'opt_load_one') {
  $id = (int)($_POST['option_id'] ?? 0);
  if ($id<=0) jsonOut(['ok'=>0,'error'=>'Invalid option_id']);

  $stmt = $mysqli->prepare("
    SELECT
      o.option_id,
      o.attribute_id,
      o.option_code,
      o.option_name,
      o.sort_order,
      o.is_active,
      o.maker_note
    FROM tbl_admin_attribute_option o
    WHERE o.option_id=? LIMIT 1
  ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) jsonOut(['ok'=>0,'error'=>'Not found']);

  jsonOut(['ok'=>1,'option'=>$row]);
}

/* --------- OPTION SAVE (HTML) --------- */
if ($action === 'opt_save') {
  $id = (int)($_POST['option_id'] ?? 0);
  $attribute_id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['option_code'] ?? ''));
  $name = trim($_POST['option_name'] ?? '');
  $sort = (int)($_POST['sort_order'] ?? 0);
  $active = (int)($_POST['is_active'] ?? 1);
  $maker_note = trim($_POST['maker_note'] ?? '');

  if ($attribute_id<=0) { echo alertHtml('danger','Attribute is required.'); exit; }
  if ($code==='') { echo alertHtml('danger','Option Code is required.'); exit; }
  if ($name==='') { echo alertHtml('danger','Option Name is required.'); exit; }

  // duplicates handled by table unique keys too, but we show nice message first:
  $stmt = $mysqli->prepare("SELECT option_id FROM tbl_admin_attribute_option WHERE attribute_id=? AND option_code=? LIMIT 1");
  $stmt->bind_param("is", $attribute_id, $code);
  $stmt->execute();
  $d = $stmt->get_result()->fetch_assoc();
  if ($d && (int)$d['option_id'] !== $id) { echo alertHtml('danger',"Option Code <b>{$code}</b> already exists for this attribute."); exit; }

  $stmt = $mysqli->prepare("SELECT option_id FROM tbl_admin_attribute_option WHERE attribute_id=? AND option_name=? LIMIT 1");
  $stmt->bind_param("is", $attribute_id, $name);
  $stmt->execute();
  $d = $stmt->get_result()->fetch_assoc();
  if ($d && (int)$d['option_id'] !== $id) { echo alertHtml('danger',"Option Name already exists for this attribute."); exit; }

  $uid = currentUserId(); if ($uid<=0) $uid=1;

  $mysqli->begin_transaction();
  try {
    if ($id > 0) {
      $st = $mysqli->prepare("SELECT record_status FROM tbl_admin_attribute_option WHERE option_id=? LIMIT 1");
      $st->bind_param("i", $id);
      $st->execute();
      $rs = ($st->get_result()->fetch_assoc()['record_status'] ?? 'DRAFT');
      if (in_array($rs, ['PENDING','APPROVED'], true)) {
        $mysqli->rollback();
        echo alertHtml('danger',"Cannot edit. Current status is <b>{$rs}</b>.");
        exit;
      }

      $stmt = $mysqli->prepare("UPDATE tbl_admin_attribute_option
        SET attribute_id=?, option_code=?, option_name=?, sort_order=?, is_active=?,
            maker_user_id=?, maker_at=NOW(), maker_note=?,
            checker_user_id=NULL, checker_at=NULL, checker_note=NULL,
            record_status='DRAFT'
        WHERE option_id=?");
      $stmt->bind_param("issiii si", $attribute_id, $code, $name, $sort, $active, $uid, $maker_note, $id);
      // fix bind string
      $stmt->close();
      $stmt = $mysqli->prepare("UPDATE tbl_admin_attribute_option
        SET attribute_id=?, option_code=?, option_name=?, sort_order=?, is_active=?,
            maker_user_id=?, maker_at=NOW(), maker_note=?,
            checker_user_id=NULL, checker_at=NULL, checker_note=NULL,
            record_status='DRAFT'
        WHERE option_id=?");
      $stmt->bind_param("issiiisi", $attribute_id, $code, $name, $sort, $active, $uid, $maker_note, $id);
      $stmt->execute();

      $mysqli->commit();
      echo alertHtml('success',"Option updated successfully (Status: DRAFT).");
      exit;
    } else {
      $stmt = $mysqli->prepare("INSERT INTO tbl_admin_attribute_option
        (attribute_id, option_code, option_name, sort_order, is_active, record_status, maker_user_id, maker_at, maker_note)
        VALUES (?,?,?,?,?, 'DRAFT', ?, NOW(), ?)");
      $stmt->bind_param("issiiis", $attribute_id, $code, $name, $sort, $active, $uid, $maker_note);
      $stmt->execute();

      $mysqli->commit();
      echo alertHtml('success',"Option saved successfully (Status: DRAFT).");
      exit;
    }
  } catch (Throwable $e) {
    $mysqli->rollback();
    echo alertHtml('danger','Save failed: '.htmlspecialchars($e->getMessage()));
    exit;
  }
}

/* --------- OPTION LIST (JSON) --------- */
if ($action === 'opt_list') {
  $q = trim($_POST['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? 1));
  $per = (int)($_POST['per_page'] ?? 10);
  if ($per<=0) $per=10; if ($per>100) $per=100;
  $offset = ($page-1)*$per;

  $where = "1=1";
  $types = "";
  $params = [];

  if ($q !== '') {
    $where .= " AND (
      a.attr_name LIKE CONCAT('%',?,'%') OR
      a.attr_code LIKE CONCAT('%',?,'%') OR
      o.option_name LIKE CONCAT('%',?,'%') OR
      o.option_code LIKE CONCAT('%',?,'%')
    )";
    $types = "ssss";
    $params = [$q,$q,$q,$q];
  }

  $sqlCnt = "SELECT COUNT(*) AS cnt
             FROM tbl_admin_attribute_option o
             JOIN tbl_admin_attribute a ON a.attribute_id=o.attribute_id
             WHERE $where";
  $stmt = $mysqli->prepare($sqlCnt);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

  $pages = max(1, (int)ceil($total/$per));
  if ($page>$pages) { $page=$pages; $offset=($page-1)*$per; }

  $sql = "SELECT
            o.option_id,
            o.attribute_id,
            a.attr_name AS attribute_name,
            o.option_code,
            o.option_name,
            o.sort_order,
            o.is_active
          FROM tbl_admin_attribute_option o
          JOIN tbl_admin_attribute a ON a.attribute_id=o.attribute_id
          WHERE $where
          ORDER BY a.attr_name ASC, o.sort_order ASC, o.option_name ASC
          LIMIT ? OFFSET ?";

  $stmt = $mysqli->prepare($sql);
  if ($types) {
    $types2 = $types."ii";
    $params2 = array_merge($params, [$per, $offset]);
    $stmt->bind_param($types2, ...$params2);
  } else {
    $stmt->bind_param("ii", $per, $offset);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($r=$res->fetch_assoc()) $rows[]=$r;

  $from = $total ? ($offset+1) : 0;
  $to = min($total, $offset+count($rows));

  jsonOut(['ok'=>1,'page'=>$page,'pages'=>$pages,'per_page'=>$per,'total'=>$total,'from'=>$from,'to'=>$to,'rows'=>$rows]);
}

/* ======================= INVALID ACTION ======================= */
if (in_array($action, $jsonActions, true)) jsonOut(['ok'=>0,'error'=>'Invalid action']);
echo alertHtml('danger','Invalid action.');
exit;
