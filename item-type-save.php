<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){
  global $conn,$con,$mysqli;
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
function alertHtml($type,$msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'.
    $msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }

$uid = currentUserId();
if ($uid<=0) { echo alertHtml('danger','Session user not found.'); exit; }

$action = strtoupper(trim($_POST['action'] ?? 'DRAFT'));
$code = strtoupper(trim($_POST['type_code'] ?? ''));
$name = trim($_POST['type_name'] ?? '');
$note = trim($_POST['maker_note'] ?? '');

if ($code==='' || $name===''){ echo alertHtml('danger','Type Code and Type Name are required.'); exit; }
if (!in_array($action,['DRAFT','SUBMIT'],true)) $action='DRAFT';
$status = ($action==='SUBMIT') ? 'PENDING' : 'DRAFT';

$mysqli->begin_transaction();
try{
  $st = $mysqli->prepare("SELECT item_type_id, record_status FROM tbl_admin_item_type WHERE type_code=? LIMIT 1");
  $st->bind_param("s",$code);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();

  if ($row){
    $id = (int)$row['item_type_id'];
    $old = $row['record_status'];
    if (in_array($old,['PENDING','APPROVED'],true)){
      $mysqli->rollback();
      echo alertHtml('danger',"Cannot edit <b>{$code}</b>. Status is <b>{$old}</b>.");
      exit;
    }

    $up = $mysqli->prepare("UPDATE tbl_admin_item_type
      SET type_name=?, record_status=?, maker_user_id=?, maker_at=NOW(), maker_note=?,
          checker_user_id=NULL, checker_at=NULL, checker_note=NULL
      WHERE item_type_id=?");
    $up->bind_param("ssisi",$name,$status,$uid,$note,$id);
    $up->execute();
    $mysqli->commit();
    echo alertHtml('success',"Type <b>{$code}</b> updated. Status: <b>{$status}</b>.");
    exit;
  } else {
    $ins = $mysqli->prepare("INSERT INTO tbl_admin_item_type
      (type_code,type_name,record_status,maker_user_id,maker_at,maker_note)
      VALUES (?,?,?,?,NOW(),?)");
    $ins->bind_param("sssis",$code,$name,$status,$uid,$note);
    $ins->execute();
    $mysqli->commit();
    echo alertHtml('success',"Type <b>{$code}</b> saved. Status: <b>{$status}</b>.");
    exit;
  }
}catch(Throwable $e){
  $mysqli->rollback();
  echo alertHtml('danger','Save failed: '.htmlspecialchars($e->getMessage()));
  exit;
}
