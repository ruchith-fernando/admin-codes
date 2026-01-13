<?php
// asset-stock-api.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) {
  $cookie = session_get_cookie_params();
  session_set_cookie_params([
    'lifetime' => $cookie['lifetime'],
    'path'     => '/',
    'domain'   => $cookie['domain'],
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid<=0) { echo json_encode(['ok'=>false,'msg'=>'Session expired']); exit; }

$hris = trim($_SESSION['hris'] ?? '');
$name = trim($_SESSION['name'] ?? '');

function alert_html($type,$msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'.
         $msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$action = strtoupper(trim($_POST['action'] ?? ''));

if ($action === 'VARIANTS') {
  $asset_id = (int)($_POST['asset_id'] ?? 0);
  if (!$asset_id){ echo json_encode(['ok'=>false,'msg'=>'asset required']); exit; }

  $stmt = $conn->prepare("
    SELECT id, variant_name, variant_code
    FROM tbl_admin_asset_variants
    WHERE asset_id=? AND status='APPROVED' AND is_active=1
    ORDER BY id DESC
    LIMIT 500
  ");
  $stmt->bind_param("i",$asset_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows=[];
  while($r=$res->fetch_assoc()) $rows[]=$r;
  $stmt->close();

  echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
}

if ($action === 'ONHAND') {
  $vid = (int)($_POST['variant_id'] ?? 0);
  if (!$vid){ echo json_encode(['ok'=>false,'msg'=>'variant required']); exit; }

  $stmt=$conn->prepare("SELECT on_hand FROM tbl_admin_stock_balances WHERE variant_id=? LIMIT 1");
  $stmt->bind_param("i",$vid);
  $stmt->execute();
  $on = (int)(($stmt->get_result()->fetch_assoc()['on_hand'] ?? 0));
  $stmt->close();

  echo json_encode(['ok'=>true,'on_hand'=>$on]); exit;
}

if ($action === 'SUBMIT') {
  $vid = (int)($_POST['variant_id'] ?? 0);
  $type = strtoupper(trim($_POST['tx_type'] ?? ''));
  $qty = (int)($_POST['qty'] ?? 0);
  $note = trim($_POST['note'] ?? '');

  if (!$vid || !in_array($type,['IN','OUT'],true) || $qty<=0){
    echo json_encode(['ok'=>false,'msg'=>'Invalid input']); exit;
  }

  $stmt=$conn->prepare("SELECT status FROM tbl_admin_asset_variants WHERE id=? LIMIT 1");
  $stmt->bind_param("i",$vid);
  $stmt->execute();
  $st = $stmt->get_result()->fetch_assoc()['status'] ?? '';
  $stmt->close();
  if ($st!=='APPROVED'){ echo json_encode(['ok'=>false,'msg'=>'Variant not approved']); exit; }

  $stmt=$conn->prepare("
    INSERT INTO tbl_admin_stock_transactions
      (variant_id, tx_type, qty, note, status,
       created_by, created_by_hris, created_by_name, created_at)
    VALUES (?,?,?,?, 'PENDING', ?,?,?, NOW())
  ");
  $stmt->bind_param("isissiis", $vid, $type, $qty, $note, $uid, $hris, $name);
  if (!$stmt->execute()){
    echo json_encode(['ok'=>false,'msg'=>'Insert failed: '.$conn->error]); exit;
  }
  $stmt->close();

  echo json_encode(['ok'=>true,'msg'=>'Transaction submitted for approval.']); exit;
}

if ($action === 'LIST') {
  $sql = "
    SELECT s.id, s.tx_type, s.qty, s.note, s.status,
           s.created_by_hris, s.created_by_name, s.created_at,
           v.variant_code, v.variant_name
    FROM tbl_admin_stock_transactions s
    JOIN tbl_admin_asset_variants v ON v.id=s.variant_id
    WHERE s.status IN ('PENDING','APPROVED','REJECTED')
    ORDER BY s.id DESC
    LIMIT 300
  ";
  $res = $conn->query($sql);
  if (!$res || $res->num_rows===0){
    echo '<div class="text-muted">No stock transactions.</div>'; exit;
  }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">';
  echo '<thead class="table-light"><tr>
          <th>ID</th><th>Variant</th><th>Code</th><th>Type</th><th>Qty</th><th>Status</th><th class="text-end">Actions</th>
        </tr></thead><tbody>';

  while($r=$res->fetch_assoc()){
    $id=(int)$r['id'];
    $isMaker = (trim($r['created_by_hris'] ?? '') === $hris);

    $tip = htmlspecialchars(($r['created_by_name'] ?? '')."<br><small>Created: ".($r['created_at'] ?? '')."</small>");
    $makerCell = '';
    if (!empty($r['created_by_hris'])){
      $makerCell = '<span data-bs-toggle="tooltip" data-bs-html="true" title="'.$tip.'">'.htmlspecialchars($r['created_by_hris']).'</span>';
    }

    echo '<tr>
      <td>'.$id.'</td>
      <td>'.htmlspecialchars($r['variant_name']).'</td>
      <td><b>'.htmlspecialchars($r['variant_code']).'</b></td>
      <td>'.htmlspecialchars($r['tx_type']).'</td>
      <td>'.(int)$r['qty'].'</td>
      <td>'.htmlspecialchars($r['status']).'<br>'.$makerCell.'</td>
      <td class="text-end">';

    if ($r['status']==='APPROVED') echo '<span class="badge bg-success">Approved</span>';
    else if ($r['status']==='REJECTED') echo '<span class="badge bg-danger">Rejected</span>';
    else {
      if (!$isMaker){
        echo '<button class="btn btn-sm btn-success btn-s-approve" data-id="'.$id.'">Approve</button>
              <button class="btn btn-sm btn-danger btn-s-reject" data-id="'.$id.'">Reject</button>';
      } else {
        echo '<span class="text-muted">Pending (Maker)</span>';
      }
    }
    echo '</td></tr>';
  }

  echo '</tbody></table></div>';
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo alert_html('danger','Missing ID'); exit; }

if ($action === 'APPROVE') {
  $conn->begin_transaction();
  try {
    $stmt=$conn->prepare("
      SELECT variant_id, tx_type, qty, status, created_by_hris
      FROM tbl_admin_stock_transactions
      WHERE id=? FOR UPDATE
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $tx=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tx) throw new Exception('Not found');
    if ($tx['status']!=='PENDING') throw new Exception('Only PENDING can be approved');
    if (trim($tx['created_by_hris'] ?? '') === $hris) throw new Exception('Dual control: maker cannot approve');

    $vid = (int)$tx['variant_id'];
    $type = $tx['tx_type'];
    $qty = (int)$tx['qty'];

    $stmt=$conn->prepare("SELECT on_hand FROM tbl_admin_stock_balances WHERE variant_id=? FOR UPDATE");
    $stmt->bind_param("i",$vid);
    $stmt->execute();
    $bal=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    $on = (int)($bal['on_hand'] ?? 0);
    if ($type==='OUT' && $on < $qty) throw new Exception("Insufficient stock. On-hand: {$on}");

    $newOn = ($type==='IN') ? ($on + $qty) : ($on - $qty);

    $stmt=$conn->prepare("UPDATE tbl_admin_stock_balances SET on_hand=? WHERE variant_id=?");
    $stmt->bind_param("ii",$newOn,$vid);
    $stmt->execute();
    $stmt->close();

    $stmt=$conn->prepare("
      UPDATE tbl_admin_stock_transactions
      SET status='APPROVED', approved_by=?, approved_by_hris=?, approved_by_name=?, approved_at=NOW()
      WHERE id=? AND status='PENDING'
    ");
    $stmt->bind_param("issi",$uid,$hris,$name,$id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo alert_html('success',"Approved. New On-hand: <b>{$newOn}</b>");
    exit;

  } catch(Exception $e){
    $conn->rollback();
    echo alert_html('danger', htmlspecialchars($e->getMessage()));
    exit;
  }
}

if ($action === 'REJECT') {
  $reason = trim($_POST['reject_reason'] ?? '');
  if (!$reason) { echo alert_html('danger','Reason required'); exit; }

  $conn->begin_transaction();
  try {
    $stmt=$conn->prepare("SELECT created_by_hris,status FROM tbl_admin_stock_transactions WHERE id=? FOR UPDATE");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) throw new Exception('Not found');
    if ($row['status']!=='PENDING') throw new Exception('Only PENDING can be rejected');
    if (trim($row['created_by_hris'] ?? '') === $hris) throw new Exception('Dual control: maker cannot reject');

    $stmt=$conn->prepare("
      UPDATE tbl_admin_stock_transactions
      SET status='REJECTED', rejected_by=?, rejected_at=NOW(), reject_reason=?
      WHERE id=? AND status='PENDING'
    ");
    $stmt->bind_param("isi",$uid,$reason,$id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo alert_html('success','Transaction rejected.');
    exit;

  } catch(Exception $e){
    $conn->rollback();
    echo alert_html('danger', htmlspecialchars($e->getMessage()));
    exit;
  }
}

echo json_encode(['ok'=>false,'msg'=>'Invalid action']);
