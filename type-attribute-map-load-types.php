<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){ global $conn,$con,$mysqli; if(isset($conn)&&$conn instanceof mysqli)return $conn; if(isset($con)&&$con instanceof mysqli)return $con; if(isset($mysqli)&&$mysqli instanceof mysqli)return $mysqli; return null; }

$mysqli=db();
if(!$mysqli){ http_response_code(500); echo ''; exit; }

$res=$mysqli->query("SELECT item_type_id, type_code, type_name FROM tbl_admin_item_type WHERE record_status='APPROVED' ORDER BY type_name ASC");
if(!$res){ echo ''; exit; }

while($r=$res->fetch_assoc()){
  $id=(int)$r['item_type_id'];
  $label=htmlspecialchars($r['type_name'].' ('.$r['type_code'].')');
  echo "<option value=\"{$id}\">{$label}</option>";
}
