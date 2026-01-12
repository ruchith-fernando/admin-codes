<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){ global $conn,$con,$mysqli; if(isset($conn)&&$conn instanceof mysqli)return $conn; if(isset($con)&&$con instanceof mysqli)return $con; if(isset($mysqli)&&$mysqli instanceof mysqli)return $mysqli; return null; }

$mysqli=db();
if(!$mysqli){ http_response_code(500); echo ''; exit; }

$res=$mysqli->query("SELECT attribute_id, attr_code, attr_name, data_type
                    FROM tbl_admin_attribute
                    WHERE record_status='APPROVED' AND is_active=1
                    ORDER BY attr_name ASC");
if(!$res){ echo ''; exit; }

while($r=$res->fetch_assoc()){
  $id=(int)$r['attribute_id'];
  $label=htmlspecialchars($r['attr_name'].' ('.$r['attr_code'].' / '.$r['data_type'].')');
  echo "<option value=\"{$id}\">{$label}</option>";
}
