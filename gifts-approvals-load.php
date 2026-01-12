<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(){ global $conn,$con,$mysqli; if(isset($conn)&&$conn instanceof mysqli)return $conn; if(isset($con)&&$con instanceof mysqli)return $con; if(isset($mysqli)&&$mysqli instanceof mysqli)return $mysqli; return null; }

$mysqli=db();
if(!$mysqli){ http_response_code(500); echo '<div class="alert alert-danger">DB connection not found.</div>'; exit; }

$filter=strtoupper(trim($_POST['filter']??''));

$where = "";
if ($filter !== "") $where = " WHERE entity='".$mysqli->real_escape_string($filter)."' ";

$sql = "
SELECT * FROM (
  SELECT 'GL' AS entity, gl_id AS pk_id, gl_code AS code, gl_name AS name, maker_user_id, maker_at, maker_note
  FROM tbl_admin_gl_account WHERE record_status='PENDING'
  UNION ALL
  SELECT 'ITEM', item_id, item_code, item_name, maker_user_id, maker_at, maker_note
  FROM tbl_admin_item WHERE record_status='PENDING'
  UNION ALL
  SELECT 'TYPE', item_type_id, type_code, type_name, maker_user_id, maker_at, maker_note
  FROM tbl_admin_item_type WHERE record_status='PENDING'
  UNION ALL
  SELECT 'ATTR', attribute_id, attr_code, attr_name, maker_user_id, maker_at, maker_note
  FROM tbl_admin_attribute WHERE record_status='PENDING'
  UNION ALL
  SELECT 'OPT', o.option_id, o.option_code, CONCAT(a.attr_code,' - ',o.option_name), o.maker_user_id, o.maker_at, o.maker_note
  FROM tbl_admin_attribute_option o
  JOIN tbl_admin_attribute a ON a.attribute_id=o.attribute_id
  WHERE o.record_status='PENDING'
  UNION ALL
  SELECT 'MAP', ta.item_type_attribute_id,
         CONCAT(t.type_code,' + ',a.attr_code) AS code,
         CONCAT('Required=',ta.is_required,' Sort=',ta.sort_order) AS name,
         ta.maker_user_id, ta.maker_at, ta.maker_note
  FROM tbl_admin_item_type_attribute ta
  JOIN tbl_admin_item_type t ON t.item_type_id=ta.item_type_id
  JOIN tbl_admin_attribute a ON a.attribute_id=ta.attribute_id
  WHERE ta.record_status='PENDING'
  UNION ALL
  SELECT 'SKU', v.variant_id, v.variant_code, v.variant_name, v.maker_user_id, v.maker_at, v.maker_note
  FROM tbl_admin_item_variant v WHERE v.record_status='PENDING'
) x
{$where}
ORDER BY maker_at ASC
";

$res = $mysqli->query($sql);
if(!$res){ echo '<div class="alert alert-danger">Query failed.</div>'; exit; }

if($res->num_rows===0){
  echo '<div class="alert alert-success">No pending approvals.</div>';
  exit;
}

echo '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">';
echo '<thead class="table-light"><tr>
  <th>Entity</th><th>Code</th><th>Name / Details</th><th>Maker</th><th>Maker Time</th><th>Note</th><th style="width:150px;">Action</th>
</tr></thead><tbody>';

while($r=$res->fetch_assoc()){
  $entity=htmlspecialchars($r['entity']);
  $pk=(int)$r['pk_id'];
  $code=htmlspecialchars($r['code']);
  $name=htmlspecialchars($r['name']);
  $maker=(int)$r['maker_user_id'];
  $time=htmlspecialchars($r['maker_at']);
  $note=htmlspecialchars($r['maker_note'] ?? '');

  echo "<tr>
    <td><span class=\"badge bg-info text-dark\">{$entity}</span></td>
    <td><b>{$code}</b></td>
    <td>{$name}</td>
    <td>{$maker}</td>
    <td>{$time}</td>
    <td>{$note}</td>
    <td class=\"text-end\">
      <button type=\"button\" class=\"btn btn-sm btn-success btn-approve\" data-entity=\"{$entity}\" data-id=\"{$pk}\">Approve</button>
      <button type=\"button\" class=\"btn btn-sm btn-danger btn-reject\" data-entity=\"{$entity}\" data-id=\"{$pk}\">Reject</button>
    </td>
  </tr>";
}
echo '</tbody></table></div>';
