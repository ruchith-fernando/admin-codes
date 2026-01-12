<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){ global $conn,$con,$mysqli; if(isset($conn)&&$conn instanceof mysqli)return $conn; if(isset($con)&&$con instanceof mysqli)return $con; if(isset($mysqli)&&$mysqli instanceof mysqli)return $mysqli; return null; }
function alertHtml($type,$msg){ return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'.$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; }

$mysqli=db();
$attribute_id=(int)($_POST['attribute_id']??0);
$code=strtoupper(trim($_POST['option_code']??''));
if(!$mysqli){ http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }
if($attribute_id<=0 || $code===''){ echo ''; exit; }

$st=$mysqli->prepare("SELECT option_name, record_status
                      FROM tbl_admin_attribute_option
                      WHERE attribute_id=? AND option_code=? LIMIT 1");
$st->bind_param("is",$attribute_id,$code);
$st->execute();
$row=$st->get_result()->fetch_assoc();

if($row){
  echo alertHtml('warning',"Option <b>{$code}</b> exists: <b>".htmlspecialchars($row['option_name'])."</b> (Status: <b>".htmlspecialchars($row['record_status'])."</b>).");
}else{
  echo alertHtml('success',"Option <b>{$code}</b> is available.");
}
