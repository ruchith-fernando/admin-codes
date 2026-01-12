<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){ global $conn,$con,$mysqli; if(isset($conn)&&$conn instanceof mysqli)return $conn; if(isset($con)&&$con instanceof mysqli)return $con; if(isset($mysqli)&&$mysqli instanceof mysqli)return $mysqli; return null; }
function alertHtml($type,$msg){ return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'.$msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; }

$mysqli=db();
$name=trim($_POST['attr_name']??'');
if(!$mysqli){ http_response_code(500); echo alertHtml('danger','DB connection not found.'); exit; }
if($name===''){ echo ''; exit; }

$st=$mysqli->prepare("SELECT attr_code, record_status FROM tbl_admin_attribute WHERE attr_name=? LIMIT 1");
$st->bind_param("s",$name);
$st->execute();
$row=$st->get_result()->fetch_assoc();

if($row){
  echo alertHtml('warning',"Attribute Name exists under Code <b>".htmlspecialchars($row['attr_code'])."</b> (Status: <b>".htmlspecialchars($row['record_status'])."</b>).");
}else{
  echo alertHtml('success',"Attribute Name is available.");
}
