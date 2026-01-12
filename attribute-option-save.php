<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){ global $conn,$con,$mysqli; if(isset($conn)&&$conn instanceof mysqli)return $conn; if(isset($con)&&$con instanceof mysqli)return $con; if(isset($mysqli)&&$mysqli instanceof mysqli)return $mysqli; return null; }
function currentUserId(){ foreach(['user_id','userid','uid','id','USER_ID','UID'] as $k){ if(isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k]; } return 0; }
function alertHtml($type,$msg){ return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'.$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; }

$mysqli=db();
if(!$mysqli){ http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }
$uid=currentUserId();
if($uid<=0){ echo alertHtml('danger','Session user not found.'); exit; }

$action=strtoupper(trim($_POST['action']??'DRAFT'));
$attribute_id=(int)($_POST['attribute_id']??0);
$code=strtoupper(trim($_POST['option_code']??''));
$name=trim($_POST['option_name']??'');
$sort=(int)($_POST['sort_order']??0);
$is_active=(int)($_POST['is_active']??1);
$note=trim($_POST['maker_note']??'');

if($attribute_id<=0 || $code==='' || $name===''){ echo alertHtml('danger','Attribute, Option Code and Option Name are required.'); exit; }
if(!in_array($action,['DRAFT','SUBMIT'],true)) $action='DRAFT';
$status=($action==='SUBMIT')?'PENDING':'DRAFT';

$mysqli->begin_transaction();
try{
  $st=$mysqli->prepare("SELECT option_id, record_status FROM tbl_admin_attribute_option WHERE attribute_id=? AND option_code=? LIMIT 1");
  $st->bind_param("is",$attribute_id,$code);
  $st->execute();
  $row=$st->get_result()->fetch_assoc();

  if($row){
    $id=(int)$row['option_id'];
    $old=$row['record_status'];
    if(in_array($old,['PENDING','APPROVED'],true)){
      $mysqli->rollback();
      echo alertHtml('danger',"Cannot edit option <b>{$code}</b>. Status is <b>{$old}</b>.");
      exit;
    }
    $up=$mysqli->prepare("UPDATE tbl_admin_attribute_option
      SET option_name=?, sort_order=?, is_active=?, record_status=?,
          maker_user_id=?, maker_at=NOW(), maker_note=?,
          checker_user_id=NULL, checker_at=NULL, checker_note=NULL
      WHERE option_id=?");
    $up->bind_param("siisisi",$name,$sort,$is_active,$status,$uid,$note,$id);
    $up->execute();
    $mysqli->commit();
    echo alertHtml('success',"Option <b>{$code}</b> updated. Status: <b>{$status}</b>.");
    exit;
  } else {
    $ins=$mysqli->prepare("INSERT INTO tbl_admin_attribute_option
      (attribute_id,option_code,option_name,sort_order,is_active,record_status,maker_user_id,maker_at,maker_note)
      VALUES (?,?,?,?,?,?,?,NOW(),?)");
    $ins->bind_param("issii sis",$attribute_id,$code,$name,$sort,$is_active,$status,$uid,$note); // type string must have no spaces -> use clean below
  }
}catch(Throwable $e){
  $mysqli->rollback();
  echo alertHtml('danger','Save failed: '.htmlspecialchars($e->getMessage()));
  exit;
}
