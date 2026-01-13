<?php
// attribute-master-api.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

// IMPORTANT: prevent PHP warnings/notices from breaking JSON responses
ini_set('display_errors', 0);

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
function jsonError($msg){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>0,'error'=>$msg]);
  exit;
}

$mysqli = db();
$action = strtolower(trim($_POST['action'] ?? ''));

// actions that MUST return JSON
$jsonActions = ['attr_list','attr_load_one','opt_list','opt_load_one'];

if (!$mysqli) {
  if (in_array($action, $jsonActions, true)) jsonError('DB connection not found.');
  http_response_code(500);
  echo alertHtml('danger','DB connection not found.');
  exit;
}

/* ======================= ATTR: CHECK CODE (HTML) ======================= */
if ($action === 'attr_check_code') {
  $id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['attribute_code'] ?? ''));
  if ($code === '') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT attribute_id, attribute_name FROM tbl_admin_attribute WHERE attribute_code=? LIMIT 1");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    if ((int)$row['attribute_id'] === $id) echo alertHtml('success', "Code <b>{$code}</b> is yours (editing).");
    else echo alertHtml('warning', "Code <b>{$code}</b> already exists for <b>".htmlspecialchars($row['attribute_name'])."</b>.");
  } else {
    echo alertHtml('success', "Code <b>{$code}</b> is available.");
  }
  exit;
}

/* ======================= ATTR: CHECK NAME (HTML) ======================= */
if ($action === 'attr_check_name') {
  $id = (int)($_POST['attribute_id'] ?? 0);
  $name = trim($_POST['attribute_name'] ?? '');
  if ($name === '') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT attribute_id FROM tbl_admin_attribute WHERE attribute_name=? LIMIT 1");
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

/* ======================= ATTR: LOAD ONE (JSON) ======================= */
if ($action === 'attr_load_one') {
  header('Content-Type: application/json; charset=utf-8');
  $id = (int)($_POST['attribute_id'] ?? 0);
  if ($id <= 0) { echo json_encode(['ok'=>0,'error'=>'Invalid attribute_id']); exit; }

  $stmt = $mysqli->prepare("SELECT attribute_id, attribute_code, attribute_name, input_type, sort_order, is_active
                            FROM tbl_admin_attribute WHERE attribute_id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) { echo json_encode(['ok'=>0,'error'=>'Not found']); exit; }
  echo json_encode(['ok'=>1,'attribute'=>$row]);
  exit;
}

/* ======================= ATTR: SAVE (HTML) ======================= */
if ($action === 'attr_save') {
  $id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['attribute_code'] ?? ''));
  $name = trim($_POST['attribute_name'] ?? '');
  $input_type = strtoupper(trim($_POST['input_type'] ?? 'SELECT'));
  $sort = (int)($_POST['sort_order'] ?? 0);
  $active = (int)($_POST['is_active'] ?? 1);

  if ($code === '' || $name === '') { echo alertHtml('danger','Code and Name are required.'); exit; }
  if (!in_array($input_type, ['SELECT','TEXT','NUMBER'], true)) $input_type='SELECT';

  // duplicate code
  $stmt = $mysqli->prepare("SELECT attribute_id FROM tbl_admin_attribute WHERE attribute_code=? LIMIT 1");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $d = $stmt->get_result()->fetch_assoc();
  if ($d && (int)$d['attribute_id'] !== $id) { echo alertHtml('danger',"Code <b>{$code}</b> already exists."); exit; }

  // duplicate name
  $stmt = $mysqli->prepare("SELECT attribute_id FROM tbl_admin_attribute WHERE attribute_name=? LIMIT 1");
  $stmt->bind_param("s", $name);
  $stmt->execute();
  $d = $stmt->get_result()->fetch_assoc();
  if ($d && (int)$d['attribute_id'] !== $id) { echo alertHtml('danger',"Name already exists."); exit; }

  $uid = currentUserId(); if ($uid<=0) $uid=1;

  $mysqli->begin_transaction();
  try {
    if ($id > 0) {
      $stmt = $mysqli->prepare("UPDATE tbl_admin_attribute
        SET attribute_code=?, attribute_name=?, input_type=?, sort_order=?, is_active=?,
            updated_by=?, updated_at=NOW()
        WHERE attribute_id=?");
      $stmt->bind_param("sssiiii", $code, $name, $input_type, $sort, $active, $uid, $id);
      $stmt->execute();
      $mysqli->commit();
      echo alertHtml('success',"Attribute updated successfully.");
      exit;
    } else {
      $stmt = $mysqli->prepare("INSERT INTO tbl_admin_attribute
        (attribute_code, attribute_name, input_type, sort_order, is_active, created_by, created_at)
        VALUES (?,?,?,?,?,?,NOW())");
      $stmt->bind_param("sssiii", $code, $name, $input_type, $sort, $active, $uid);
      $stmt->execute();
      $mysqli->commit();
      echo alertHtml('success',"Attribute saved successfully.");
      exit;
    }
  } catch (Throwable $e) {
    $mysqli->rollback();
    echo alertHtml('danger','Save failed: '.htmlspecialchars($e->getMessage()));
    exit;
  }
}

/* ======================= ATTR: LIST (JSON) ======================= */
if ($action === 'attr_list') {
  header('Content-Type: application/json; charset=utf-8');

  $q = trim($_POST['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? 1));
  $per = (int)($_POST['per_page'] ?? 10);
  if ($per<=0) $per=10; if ($per>100) $per=100;
  $offset = ($page-1)*$per;

  $where = "1=1";
  $types = "";
  $params = [];

  if ($q !== '') {
    $where .= " AND (attribute_code LIKE CONCAT('%',?,'%') OR attribute_name LIKE CONCAT('%',?,'%'))";
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

  $sql = "SELECT attribute_id, attribute_code, attribute_name, input_type, sort_order, is_active
          FROM tbl_admin_attribute
          WHERE $where
          ORDER BY sort_order ASC, attribute_name ASC
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

  echo json_encode(['ok'=>1,'page'=>$page,'pages'=>$pages,'per_page'=>$per,'total'=>$total,'from'=>$from,'to'=>$to,'rows'=>$rows]);
  exit;
}

/* ======================= OPT: LOAD ATTRS DROPDOWN (HTML) ======================= */
if ($action === 'opt_load_attrs') {
  $res = $mysqli->query("SELECT attribute_id, attribute_name, attribute_code
                         FROM tbl_admin_attribute
                         WHERE is_active=1
                         ORDER BY sort_order ASC, attribute_name ASC");
  if (!$res) { echo ''; exit; }
  while ($r=$res->fetch_assoc()) {
    $id=(int)$r['attribute_id'];
    $label=htmlspecialchars($r['attribute_name'].' ('.$r['attribute_code'].')');
    echo "<option value=\"{$id}\">{$label}</option>";
  }
  exit;
}

/* ======================= OPT: CHECK NAME (HTML) ======================= */
if ($action === 'opt_check_name') {
  $option_id = (int)($_POST['option_id'] ?? 0);
  $attribute_id = (int)($_POST['attribute_id'] ?? 0);
  $name = trim($_POST['option_name'] ?? '');
  if ($attribute_id<=0 || $name==='') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT option_id FROM tbl_admin_attribute_option WHERE attribute_id=? AND option_name=? LIMIT 1");
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

/* ======================= OPT: CHECK CODE (HTML) ======================= */
if ($action === 'opt_check_code') {
  $option_id = (int)($_POST['option_id'] ?? 0);
  $attribute_id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['option_code'] ?? ''));
  if ($attribute_id<=0 || $code==='') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT option_id, option_name FROM tbl_admin_attribute_option WHERE attribute_id=? AND option_code=? LIMIT 1");
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

/* ======================= OPT: LOAD ONE (JSON) ======================= */
if ($action === 'opt_load_one') {
  header('Content-Type: application/json; charset=utf-8');
  $id = (int)($_POST['option_id'] ?? 0);
  if ($id<=0) { echo json_encode(['ok'=>0,'error'=>'Invalid option_id']); exit; }

  $stmt = $mysqli->prepare("SELECT option_id, attribute_id, option_code, option_name, sort_order, is_active
                            FROM tbl_admin_attribute_option WHERE option_id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) { echo json_encode(['ok'=>0,'error'=>'Not found']); exit; }
  echo json_encode(['ok'=>1,'option'=>$row]);
  exit;
}

/* ======================= OPT: SAVE (HTML) ======================= */
if ($action === 'opt_save') {
  $id = (int)($_POST['option_id'] ?? 0);
  $attribute_id = (int)($_POST['attribute_id'] ?? 0);
  $code = strtoupper(trim($_POST['option_code'] ?? ''));
  $name = trim($_POST['option_name'] ?? '');
  $sort = (int)($_POST['sort_order'] ?? 0);
  $active = (int)($_POST['is_active'] ?? 1);

  if ($attribute_id<=0 || $name==='') { echo alertHtml('danger','Attribute and Option Name are required.'); exit; }

  // duplicate name per attribute
  $stmt = $mysqli->prepare("SELECT option_id FROM tbl_admin_attribute_option WHERE attribute_id=? AND option_name=? LIMIT 1");
  $stmt->bind_param("is", $attribute_id, $name);
  $stmt->execute();
  $d = $stmt->get_result()->fetch_assoc();
  if ($d && (int)$d['option_id'] !== $id) { echo alertHtml('danger','Option Name already exists for this attribute.'); exit; }

  // duplicate code per attribute if provided
  if ($code !== '') {
    $stmt = $mysqli->prepare("SELECT option_id FROM tbl_admin_attribute_option WHERE attribute_id=? AND option_code=? LIMIT 1");
    $stmt->bind_param("is", $attribute_id, $code);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();
    if ($d && (int)$d['option_id'] !== $id) { echo alertHtml('danger',"Option Code <b>{$code}</b> already exists for this attribute."); exit; }
  }

  $uid = currentUserId(); if ($uid<=0) $uid=1;

  $mysqli->begin_transaction();
  try {
    if ($id > 0) {
      $stmt = $mysqli->prepare("UPDATE tbl_admin_attribute_option
        SET attribute_id=?, option_code=?, option_name=?, sort_order=?, is_active=?,
            updated_by=?, updated_at=NOW()
        WHERE option_id=?");
      $stmt->bind_param("issiiii", $attribute_id, $code, $name, $sort, $active, $uid, $id);
      $stmt->execute();
      $mysqli->commit();
      echo alertHtml('success','Option updated successfully.');
      exit;
    } else {
      $stmt = $mysqli->prepare("INSERT INTO tbl_admin_attribute_option
        (attribute_id, option_code, option_name, sort_order, is_active, created_by, created_at)
        VALUES (?,?,?,?,?,?,NOW())");
      $stmt->bind_param("issiii", $attribute_id, $code, $name, $sort, $active, $uid);
      $stmt->execute();
      $mysqli->commit();
      echo alertHtml('success','Option saved successfully.');
      exit;
    }
  } catch (Throwable $e) {
    $mysqli->rollback();
    echo alertHtml('danger','Save failed: '.htmlspecialchars($e->getMessage()));
    exit;
  }
}

/* ======================= OPT: LIST (JSON) ======================= */
if ($action === 'opt_list') {
  header('Content-Type: application/json; charset=utf-8');

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
      a.attribute_name LIKE CONCAT('%',?,'%') OR
      a.attribute_code LIKE CONCAT('%',?,'%') OR
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

  $sql = "SELECT o.option_id, o.attribute_id, a.attribute_name, o.option_code, o.option_name, o.sort_order, o.is_active
          FROM tbl_admin_attribute_option o
          JOIN tbl_admin_attribute a ON a.attribute_id=o.attribute_id
          WHERE $where
          ORDER BY a.attribute_name ASC, o.sort_order ASC, o.option_name ASC
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

  echo json_encode(['ok'=>1,'page'=>$page,'pages'=>$pages,'per_page'=>$per,'total'=>$total,'from'=>$from,'to'=>$to,'rows'=>$rows]);
  exit;
}

/* ======================= INVALID ACTION ======================= */
if (in_array($action, $jsonActions, true)) jsonError('Invalid action');
echo alertHtml('danger','Invalid action.');
exit;
