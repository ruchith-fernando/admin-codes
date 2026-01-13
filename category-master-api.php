<?php
// category-master-api.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

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
function alertHtml($type, $msg, $savedId = 0){
  $extra = $savedId ? ' <span data-saved-category-id="'.$savedId.'"></span>' : '';
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'
    .$msg.$extra.
    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }

$action = strtolower(trim($_POST['action'] ?? ''));

/* LOAD GLs */
if ($action === 'load_gls') {
  $hasActive = false;
  $rs = $mysqli->query("SHOW COLUMNS FROM tbl_admin_gl_account LIKE 'is_active'");
  if ($rs && $rs->num_rows > 0) $hasActive = true;

  $sql = $hasActive
    ? "SELECT gl_id, gl_code, gl_name FROM tbl_admin_gl_account WHERE is_active=1 ORDER BY gl_code ASC"
    : "SELECT gl_id, gl_code, gl_name FROM tbl_admin_gl_account ORDER BY gl_code ASC";

  $res = $mysqli->query($sql);
  if (!$res) { echo ''; exit; }

  while ($row = $res->fetch_assoc()) {
    $id = (int)$row['gl_id'];
    $label = htmlspecialchars($row['gl_code'].' - '.$row['gl_name']);
    echo "<option value=\"{$id}\">{$label}</option>";
  }
  exit;
}

/* CHECK CODE (unique within GL) */
if ($action === 'check_code') {
  $category_id = (int)($_POST['category_id'] ?? 0);
  $gl_id = (int)($_POST['gl_id'] ?? 0);
  $code = strtoupper(trim($_POST['category_code'] ?? ''));

  if ($gl_id <= 0 || $code === '') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT category_id, category_name FROM tbl_admin_category WHERE gl_id=? AND category_code=? LIMIT 1");
  $stmt->bind_param("is", $gl_id, $code);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    if ((int)$row['category_id'] === $category_id) echo alertHtml('success', "Code <b>{$code}</b> is yours (editing).");
    else echo alertHtml('warning', "Code <b>{$code}</b> already exists for <b>".htmlspecialchars($row['category_name'])."</b>.");
  } else {
    echo alertHtml('success', "Code <b>{$code}</b> is available.");
  }
  exit;
}

/* CHECK NAME (unique within GL since parent removed) */
if ($action === 'check_name') {
  $category_id = (int)($_POST['category_id'] ?? 0);
  $gl_id = (int)($_POST['gl_id'] ?? 0);
  $name = trim($_POST['category_name'] ?? '');

  if ($gl_id <= 0 || $name === '') { echo ''; exit; }

  $stmt = $mysqli->prepare("SELECT category_id FROM tbl_admin_category WHERE gl_id=? AND category_name=? LIMIT 1");
  $stmt->bind_param("is", $gl_id, $name);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if ($row) {
    if ((int)$row['category_id'] === $category_id) echo alertHtml('success', "Name is yours (editing).");
    else echo alertHtml('warning', "Name already exists in this GL.");
  } else {
    echo alertHtml('success', "Name is available.");
  }
  exit;
}

/* LOAD ONE */
if ($action === 'load_one') {
  header('Content-Type: application/json');
  $category_id = (int)($_POST['category_id'] ?? 0);
  if ($category_id <= 0) { echo json_encode(['ok'=>0,'error'=>'Invalid category_id']); exit; }

  $stmt = $mysqli->prepare("SELECT category_id, gl_id, category_code, category_name, sort_order, is_active, notes
                            FROM tbl_admin_category WHERE category_id=? LIMIT 1");
  $stmt->bind_param("i", $category_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row) { echo json_encode(['ok'=>0,'error'=>'Category not found']); exit; }
  echo json_encode(['ok'=>1,'category'=>$row]);
  exit;
}

/* SAVE (parent_category_id always NULL, level_no always 0) */
if ($action === 'save') {
  $category_id = (int)($_POST['category_id'] ?? 0);
  $gl_id = (int)($_POST['gl_id'] ?? 0);

  $category_code = strtoupper(trim($_POST['category_code'] ?? ''));
  $category_name = trim($_POST['category_name'] ?? '');
  $sort_order = (int)($_POST['sort_order'] ?? 0);
  $is_active = (int)($_POST['is_active'] ?? 1);
  $notes = trim($_POST['notes'] ?? '');

  if ($gl_id <= 0 || $category_name === '') {
    echo alertHtml('danger','GL and Category Name are required.');
    exit;
  }

  $user_id = currentUserId();
  if ($user_id <= 0) $user_id = 1;

  $level_no = 0;

  // duplicate checks
  if ($category_code !== '') {
    $stmtD = $mysqli->prepare("SELECT category_id FROM tbl_admin_category WHERE gl_id=? AND category_code=? LIMIT 1");
    $stmtD->bind_param("is", $gl_id, $category_code);
    $stmtD->execute();
    $drow = $stmtD->get_result()->fetch_assoc();
    if ($drow && (int)$drow['category_id'] !== $category_id) {
      echo alertHtml('danger',"Category Code <b>{$category_code}</b> already exists in this GL.");
      exit;
    }
  }

  $stmtN = $mysqli->prepare("SELECT category_id FROM tbl_admin_category WHERE gl_id=? AND category_name=? LIMIT 1");
  $stmtN->bind_param("is", $gl_id, $category_name);
  $stmtN->execute();
  $nrow = $stmtN->get_result()->fetch_assoc();
  if ($nrow && (int)$nrow['category_id'] !== $category_id) {
    echo alertHtml('danger',"Category Name already exists in this GL.");
    exit;
  }

  $mysqli->begin_transaction();
  try {
    if ($category_id > 0) {
      $stmt = $mysqli->prepare("
        UPDATE tbl_admin_category
        SET gl_id=?,
            parent_category_id=NULL,
            category_code=?,
            category_name=?,
            level_no=?,
            sort_order=?,
            is_active=?,
            notes=?,
            updated_by=?,
            updated_at=NOW()
        WHERE category_id=?
      ");
      $stmt->bind_param("issiiisii", $gl_id, $category_code, $category_name, $level_no, $sort_order, $is_active, $notes, $user_id, $category_id);
      $stmt->execute();

      $mysqli->commit();
      echo alertHtml('success',"Category updated successfully.", $category_id);
      exit;
    }

    $stmt = $mysqli->prepare("
      INSERT INTO tbl_admin_category
        (gl_id, parent_category_id, category_code, category_name, level_no, sort_order, is_active, notes, created_by, created_at)
      VALUES
        (?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issiiisi", $gl_id, $category_code, $category_name, $level_no, $sort_order, $is_active, $notes, $user_id);
    $stmt->execute();

    $newId = (int)$mysqli->insert_id;
    $mysqli->commit();
    echo alertHtml('success',"Category saved successfully.", $newId);
    exit;

  } catch (Throwable $e) {
    $mysqli->rollback();
    echo alertHtml('danger','Save failed: ' . htmlspecialchars($e->getMessage()));
    exit;
  }
}

/* LIST */
if ($action === 'list') {
  header('Content-Type: application/json');

  $q = trim($_POST['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? 1));
  $per = (int)($_POST['per_page'] ?? 10);
  if ($per <= 0) $per = 10;
  if ($per > 100) $per = 100;
  $offset = ($page - 1) * $per;

  $where = "1=1";
  $params = [];
  $types = "";

  if ($q !== '') {
    $where .= " AND (
      g.gl_code LIKE CONCAT('%',?,'%') OR
      g.gl_name LIKE CONCAT('%',?,'%') OR
      c.category_name LIKE CONCAT('%',?,'%') OR
      c.category_code LIKE CONCAT('%',?,'%')
    )";
    $params = [$q,$q,$q,$q];
    $types = "ssss";
  }

  $sqlCnt = "SELECT COUNT(*) AS cnt
             FROM tbl_admin_category c
             JOIN tbl_admin_gl_account g ON g.gl_id = c.gl_id
             WHERE $where";
  $stmt = $mysqli->prepare($sqlCnt);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

  $pages = max(1, (int)ceil($total / $per));
  if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $per; }

  $sql = "SELECT
            c.category_id,
            g.gl_code,
            c.category_code,
            c.category_name,
            c.is_active
          FROM tbl_admin_category c
          JOIN tbl_admin_gl_account g ON g.gl_id = c.gl_id
          WHERE $where
          ORDER BY g.gl_code ASC, c.sort_order ASC, c.category_name ASC
          LIMIT ? OFFSET ?";

  $stmt = $mysqli->prepare($sql);
  if ($types) {
    $types2 = $types . "ii";
    $params2 = array_merge($params, [$per, $offset]);
    $stmt->bind_param($types2, ...$params2);
  } else {
    $stmt->bind_param("ii", $per, $offset);
  }

  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;

  $from = $total ? ($offset + 1) : 0;
  $to = min($total, $offset + count($rows));

  echo json_encode([
    'ok'=>1,
    'page'=>$page,
    'pages'=>$pages,
    'per_page'=>$per,
    'total'=>$total,
    'from'=>$from,
    'to'=>$to,
    'rows'=>$rows
  ]);
  exit;
}

echo alertHtml('danger','Invalid action.');
exit;
