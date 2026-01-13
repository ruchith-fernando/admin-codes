<?php
// asset-variant-api.php
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

function norm_key($k){
  $k = strtoupper(trim((string)$k));
  $k = preg_replace('/\s+/', '_', $k);
  $k = preg_replace('/[^A-Z0-9_]/', '', $k);
  return $k;
}

function build_fingerprint(array $attrs): string {
  // canonical: sort by key, then "KEY=VALUE"
  $pairs = [];
  foreach ($attrs as $a){
    $k = norm_key($a['key'] ?? '');
    $v = trim((string)($a['value'] ?? ''));
    if ($k !== '' && $v !== ''){
      $pairs[$k] = $v; // unique per key
    }
  }
  ksort($pairs);
  $flat = [];
  foreach($pairs as $k=>$v) $flat[] = $k.'='.$v;
  return sha1(implode('|',$flat)); // empty attrs -> sha1('')
}

function build_variant_name(mysqli $conn, int $asset_id, array $attrs): string {
  $stmt = $conn->prepare("SELECT item_name FROM tbl_admin_assets WHERE id=? LIMIT 1");
  $stmt->bind_param("i",$asset_id);
  $stmt->execute();
  $base = $stmt->get_result()->fetch_assoc()['item_name'] ?? '';
  $stmt->close();
  $base = trim((string)$base);
  if ($base === '') $base = 'ITEM';

  $pairs = [];
  foreach($attrs as $a){
    $k = norm_key($a['key'] ?? '');
    $v = trim((string)($a['value'] ?? ''));
    if ($k!=='' && $v!=='') $pairs[] = $k.' '.$v;
  }
  if (!$pairs) return $base;
  return $base.', '.implode(', ', $pairs);
}

$action = strtoupper(trim($_POST['action'] ?? ''));

if ($action === 'RESERVE') {
  $asset_id = (int)($_POST['asset_id'] ?? 0);
  if (!$asset_id) { echo json_encode(['ok'=>false,'msg'=>'Asset required']); exit; }

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare("INSERT IGNORE INTO tbl_admin_variant_code_sequences (asset_id,last_number) VALUES (?,0)");
    $stmt->bind_param("i",$asset_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT last_number FROM tbl_admin_variant_code_sequences WHERE asset_id=? FOR UPDATE");
    $stmt->bind_param("i",$asset_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $newNum = ((int)($row['last_number'] ?? 0)) + 1;

    $stmt = $conn->prepare("UPDATE tbl_admin_variant_code_sequences SET last_number=? WHERE asset_id=?");
    $stmt->bind_param("ii",$newNum,$asset_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT item_code FROM tbl_admin_assets WHERE id=? LIMIT 1");
    $stmt->bind_param("i",$asset_id);
    $stmt->execute();
    $parent_code = $stmt->get_result()->fetch_assoc()['item_code'] ?? '';
    $stmt->close();
    if (!$parent_code) throw new Exception('Parent item_code missing');

    $variant_code = $parent_code . '-' . str_pad((string)$newNum, 3, '0', STR_PAD_LEFT);

    $conn->commit();
    echo json_encode(['ok'=>true,'reservation_id'=>$newNum,'variant_code'=>$variant_code]);
    exit;

  } catch(Exception $e){
    $conn->rollback();
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

if ($action === 'SUBMIT') {
  $asset_id = (int)($_POST['asset_id'] ?? 0);
  $reservation_id = (int)($_POST['reservation_id'] ?? 0);
  $attrs_json = (string)($_POST['attrs_json'] ?? '[]');

  if (!$asset_id || !$reservation_id) { echo json_encode(['ok'=>false,'msg'=>'Missing reservation']); exit; }

  $attrs = json_decode($attrs_json, true);
  if (!is_array($attrs)) $attrs = [];

  // Rebuild variant_code from parent item_code + reservation_id
  $stmt = $conn->prepare("SELECT item_code FROM tbl_admin_assets WHERE id=? LIMIT 1");
  $stmt->bind_param("i",$asset_id);
  $stmt->execute();
  $parent_code = $stmt->get_result()->fetch_assoc()['item_code'] ?? '';
  $stmt->close();
  if (!$parent_code) { echo json_encode(['ok'=>false,'msg'=>'Parent item_code missing']); exit; }

  $variant_code = $parent_code . '-' . str_pad((string)$reservation_id, 3, '0', STR_PAD_LEFT);

  // fingerprint for duplicate prevention per asset_id
  $fingerprint = build_fingerprint($attrs);
  $variant_name = build_variant_name($conn, $asset_id, $attrs);

  $conn->begin_transaction();
  try {
    // insert variant header
    $stmt = $conn->prepare("
      INSERT INTO tbl_admin_asset_variants
        (asset_id, variant_code, variant_name, variant_fingerprint,
         status, created_by, created_by_hris, created_by_name, created_at)
      VALUES (?,?,?,?, 'PENDING', ?,?,?, NOW())
    ");
    $stmt->bind_param("issssisss",
      $asset_id, $variant_code, $variant_name, $fingerprint,
      $uid, $hris, $name, $name
    );
    // NOTE: bind types above: we used a safe extra $name to keep parameter count stable on older PHP;
    // It won't be used because query has only 8 placeholders after fingerprint + created_by... (see below)
    // Fix bind correctly:
    $stmt->close();

    // Correct bind (8 placeholders total after fingerprint)
    $stmt = $conn->prepare("
      INSERT INTO tbl_admin_asset_variants
        (asset_id, variant_code, variant_name, variant_fingerprint,
         status, created_by, created_by_hris, created_by_name, created_at)
      VALUES (?,?,?,?, 'PENDING', ?,?,?, NOW())
    ");
    $stmt->bind_param("isssisss",
      $asset_id, $variant_code, $variant_name, $fingerprint,
      $uid, $hris, $name
    );

    if (!$stmt->execute()){
      if ($conn->errno == 1062) {
        throw new Exception('Duplicate variant for this item (same attribute set or code).');
      }
      throw new Exception('Insert failed: '.$conn->error);
    }
    $variant_id = (int)$stmt->insert_id;
    $stmt->close();

    // insert attributes
    if (!empty($attrs)){
      $seen = [];
      $stmt = $conn->prepare("INSERT INTO tbl_admin_variant_attributes (variant_id, attr_key, attr_value) VALUES (?,?,?)");
      foreach($attrs as $a){
        $k = norm_key($a['key'] ?? '');
        $v = trim((string)($a['value'] ?? ''));
        if ($k==='' || $v==='') continue;
        if (isset($seen[$k])) continue;
        $seen[$k]=1;
        $stmt->bind_param("iss", $variant_id, $k, $v);
        $stmt->execute();
      }
      $stmt->close();
    }

    // stock balance row
    $stmt = $conn->prepare("INSERT IGNORE INTO tbl_admin_stock_balances (variant_id,on_hand) VALUES (?,0)");
    $stmt->bind_param("i",$variant_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['ok'=>true,'msg'=>"Variant submitted. Code: <b>{$variant_code}</b>"]);
    exit;

  } catch(Exception $e){
    $conn->rollback();
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

if ($action === 'LIST') {
  $sql = "
    SELECT
      v.id, v.asset_id, v.variant_name, v.variant_code, v.status,
      v.created_by_hris, v.created_by_name, v.created_at,
      v.approved_by_hris, v.approved_by_name, v.approved_at,
      a.item_name, a.item_code,
      GROUP_CONCAT(CONCAT(va.attr_key,'=',va.attr_value) ORDER BY va.attr_key SEPARATOR ', ') AS attrs
    FROM tbl_admin_asset_variants v
    JOIN tbl_admin_assets a ON a.id=v.asset_id
    LEFT JOIN tbl_admin_variant_attributes va ON va.variant_id=v.id
    WHERE v.status IN ('PENDING','APPROVED','REJECTED')
    GROUP BY v.id
    ORDER BY v.id DESC
    LIMIT 300
  ";
  $res = $conn->query($sql);
  if (!$res || $res->num_rows===0){
    echo '<div class="text-muted">No variants.</div>'; exit;
  }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">';
  echo '<thead class="table-light"><tr>
          <th>ID</th><th>Master</th><th>Variant</th><th>Code</th><th>Status</th><th class="text-end">Actions</th>
        </tr></thead><tbody>';

  while($r=$res->fetch_assoc()){
    $id=(int)$r['id'];
    $isMaker = (trim($r['created_by_hris'] ?? '') === $hris);

    $makerTitle = htmlspecialchars(($r['created_by_name'] ?? '') . "<br><small>Created: ".($r['created_at'] ?? '')."</small>");
    $makerCell = '';
    if (!empty($r['created_by_hris'])){
      $makerCell = '<span data-bs-toggle="tooltip" data-bs-html="true" title="'.$makerTitle.'">'.htmlspecialchars($r['created_by_hris']).'</span>';
    }

    $attrsText = trim((string)($r['attrs'] ?? ''));
    $variantLine = htmlspecialchars($r['variant_name']);
    if ($attrsText !== '') $variantLine .= '<div class="small text-muted">'.htmlspecialchars($attrsText).'</div>';

    echo '<tr>
      <td>'.$id.'</td>
      <td>'.htmlspecialchars($r['item_name']).' ['.htmlspecialchars($r['item_code']).']</td>
      <td>'.$variantLine.'</td>
      <td><b>'.htmlspecialchars($r['variant_code']).'</b></td>
      <td>'.htmlspecialchars($r['status']).'<br>'.$makerCell.'</td>
      <td class="text-end">';

    if ($r['status']==='APPROVED') {
      echo '<span class="badge bg-success">Approved</span>';
    } else if ($r['status']==='REJECTED') {
      echo '<span class="badge bg-danger">Rejected</span>';
    } else {
      if (!$isMaker){
        echo '<button class="btn btn-sm btn-success btn-v-approve" data-id="'.$id.'">Approve</button>
              <button class="btn btn-sm btn-danger btn-v-reject" data-id="'.$id.'">Reject</button>';
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
    $stmt=$conn->prepare("SELECT created_by_hris,status FROM tbl_admin_asset_variants WHERE id=? FOR UPDATE");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) throw new Exception('Not found');
    if ($row['status']!=='PENDING') throw new Exception('Only PENDING can be approved');
    if (trim($row['created_by_hris'] ?? '') === $hris) throw new Exception('Dual control: maker cannot approve');

    $stmt=$conn->prepare("
      UPDATE tbl_admin_asset_variants
      SET status='APPROVED',
          approved_by=?, approved_by_hris=?, approved_by_name=?, approved_at=NOW()
      WHERE id=? AND status='PENDING'
    ");
    $stmt->bind_param("issi",$uid,$hris,$name,$id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo alert_html('success','Variant approved.');
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
    $stmt=$conn->prepare("SELECT created_by_hris,status FROM tbl_admin_asset_variants WHERE id=? FOR UPDATE");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) throw new Exception('Not found');
    if ($row['status']!=='PENDING') throw new Exception('Only PENDING can be rejected');
    if (trim($row['created_by_hris'] ?? '') === $hris) throw new Exception('Dual control: maker cannot reject');

    $stmt=$conn->prepare("
      UPDATE tbl_admin_asset_variants
      SET status='REJECTED',
          rejected_by=?, rejected_at=NOW(), reject_reason=?
      WHERE id=? AND status='PENDING'
    ");
    $stmt->bind_param("isi",$uid,$reason,$id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo alert_html('success','Variant rejected.');
    exit;

  } catch(Exception $e){
    $conn->rollback();
    echo alert_html('danger', htmlspecialchars($e->getMessage()));
    exit;
  }
}

echo json_encode(['ok'=>false,'msg'=>'Invalid action']);
