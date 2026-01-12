<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){ global $conn,$con,$mysqli; if(isset($conn)&&$conn instanceof mysqli)return $conn; if(isset($con)&&$con instanceof mysqli)return $con; if(isset($mysqli)&&$mysqli instanceof mysqli)return $mysqli; return null; }
function currentUserId(){ foreach(['user_id','userid','uid','id','USER_ID','UID'] as $k){ if(isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k]; } return 0; }
function alertHtml($type,$msg){ return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'.$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; }

$mysqli=db();
if(!$mysqli){ http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }
$checker=currentUserId();
if($checker<=0){ echo alertHtml('danger','Session user not found.'); exit; }

$entity=strtoupper(trim($_POST['entity']??''));
$id=(int)($_POST['id']??0);
$decision=strtoupper(trim($_POST['decision']??''));
$note=trim($_POST['note']??'');

if($id<=0 || !in_array($decision,['APPROVE','REJECT'],true)){ echo alertHtml('danger','Invalid request.'); exit; }
$newStatus = ($decision==='APPROVE') ? 'APPROVED' : 'REJECTED';

$map = [
  'GL'   => ['table'=>'tbl_admin_gl_account',        'pk'=>'gl_id'],
  'ITEM' => ['table'=>'tbl_admin_item',              'pk'=>'item_id'],
  'TYPE' => ['table'=>'tbl_admin_item_type',         'pk'=>'item_type_id'],
  'ATTR' => ['table'=>'tbl_admin_attribute',         'pk'=>'attribute_id'],
  'OPT'  => ['table'=>'tbl_admin_attribute_option',  'pk'=>'option_id'],
  'MAP'  => ['table'=>'tbl_admin_item_type_attribute','pk'=>'item_type_attribute_id'],
  'SKU'  => ['table'=>'tbl_admin_item_variant',      'pk'=>'variant_id'],
];
if(!isset($map[$entity])){ echo alertHtml('danger','Unknown entity.'); exit; }

$table=$map[$entity]['table'];
$pk=$map[$entity]['pk'];

$mysqli->begin_transaction();
try{
  // fetch maker + status
  $st=$mysqli->prepare("SELECT maker_user_id, record_status FROM {$table} WHERE {$pk}=? LIMIT 1");
  $st->bind_param("i",$id);
  $st->execute();
  $row=$st->get_result()->fetch_assoc();
  if(!$row){
    $mysqli->rollback();
    echo alertHtml('danger','Record not found.');
    exit;
  }
  if($row['record_status']!=='PENDING'){
    $mysqli->rollback();
    echo alertHtml('danger','Record is not PENDING.');
    exit;
  }
  $maker=(int)$row['maker_user_id'];
  if($maker === $checker){
    $mysqli->rollback();
    echo alertHtml('danger','Maker and Checker cannot be the same user.');
    exit;
  }

  $up=$mysqli->prepare("UPDATE {$table}
    SET record_status=?, checker_user_id=?, checker_at=NOW(), checker_note=?
    WHERE {$pk}=?");
  $up->bind_param("sisi",$newStatus,$checker,$note,$id);
  $up->execute();

  $mysqli->commit();
  echo alertHtml('success',"Done. Status set to <b>{$newStatus}</b>.");
  exit;

}catch(Throwable $e){
  $mysqli->rollback();
  echo alertHtml('danger','Action failed: '.htmlspecialchars($e->getMessage()));
  exit;
}
