<?php
// asset-card-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

// Shared-host safe session
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

header('Content-Type: text/plain; charset=utf-8');

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'Session expired. Please login again.']);
  exit;
}

function out($arr){ echo json_encode($arr); exit; }
function fail($msg){ out(['ok'=>false,'msg'=>$msg]); }
function ok($arr){ $arr['ok']=true; out($arr); }

$action = strtoupper(trim($_POST['action'] ?? ''));

if ($action === 'RESERVE') {
  $category_id = (int)($_POST['category_id'] ?? 0);
  $budget_id   = (int)($_POST['budget_id'] ?? 0);
  $cancel_id   = (int)($_POST['cancel_reservation_id'] ?? 0);

  if (!$category_id || !$budget_id) fail('Category and Budget are required.');

  $conn->begin_transaction();
  try {
    // Cancel old reservation (cleanup)
    if ($cancel_id > 0) {
      $stmt = $conn->prepare("
        UPDATE tbl_admin_asset_code_reservations
        SET status='CANCELLED'
        WHERE id=? AND reserved_by=? AND status='RESERVED'
      ");
      $stmt->bind_param("ii", $cancel_id, $uid);
      $stmt->execute();
      $stmt->close();
    }

    // Get codes
    $stmt = $conn->prepare("SELECT category_code FROM tbl_admin_categories WHERE id=? AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $cat = $stmt->get_result()->fetch_assoc()['category_code'] ?? '';
    $stmt->close();

    $stmt = $conn->prepare("SELECT budget_code FROM tbl_admin_budgets WHERE id=? AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $budget_id);
    $stmt->execute();
    $bud = $stmt->get_result()->fetch_assoc()['budget_code'] ?? '';
    $stmt->close();

    $cat = strtoupper(trim($cat));
    $bud = strtoupper(trim($bud));
    if (strlen($cat) !== 3) throw new Exception('Category code must be 3 letters.');
    if (strlen($bud) !== 2) throw new Exception('Budget code must be 2 letters.');

    // Ensure sequence exists
    $stmt = $conn->prepare("
      INSERT IGNORE INTO tbl_admin_asset_code_sequences (category_id, budget_id, last_number)
      VALUES (?, ?, 0)
    ");
    $stmt->bind_param("ii", $category_id, $budget_id);
    if (!$stmt->execute()) throw new Exception('Sequence init failed: '.$conn->error);
    $stmt->close();

    // Lock sequence
    $stmt = $conn->prepare("
      SELECT last_number
      FROM tbl_admin_asset_code_sequences
      WHERE category_id=? AND budget_id=?
      FOR UPDATE
    ");
    $stmt->bind_param("ii", $category_id, $budget_id);
    $stmt->execute();
    $seq = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$seq) throw new Exception('Sequence row missing.');

    $newNum = ((int)$seq['last_number']) + 1;

    // Update sequence
    $stmt = $conn->prepare("
      UPDATE tbl_admin_asset_code_sequences
      SET last_number=?
      WHERE category_id=? AND budget_id=?
    ");
    $stmt->bind_param("iii", $newNum, $category_id, $budget_id);
    if (!$stmt->execute()) throw new Exception('Sequence update failed: '.$conn->error);
    $stmt->close();

    $item_code = $cat . $bud . str_pad((string)$newNum, 4, '0', STR_PAD_LEFT);

    // Insert reservation
    $stmt = $conn->prepare("
      INSERT INTO tbl_admin_asset_code_reservations
        (category_id, budget_id, item_code, reserved_by, reserved_at, status)
      VALUES (?,?,?,?,NOW(),'RESERVED')
    ");
    $stmt->bind_param("iisi", $category_id, $budget_id, $item_code, $uid);
    if (!$stmt->execute()) throw new Exception('Reservation insert failed: '.$conn->error);
    $reservation_id = (int)$stmt->insert_id;
    $stmt->close();

    $conn->commit();

    ok([
      'reservation_id' => $reservation_id,
      'item_code'      => $item_code
    ]);

  } catch (Exception $e) {
    $conn->rollback();
    fail($e->getMessage());
  }
}

if ($action === 'SUBMIT') {
  $reservation_id = (int)($_POST['reservation_id'] ?? 0);
  $item_name      = trim($_POST['item_name'] ?? '');
  $asset_type_id  = (int)($_POST['asset_type_id'] ?? 0);

  if (!$reservation_id) fail('Reservation missing. Select Category + Budget again.');
  if (!$item_name) fail('Item Name is required.');
  if (!$asset_type_id) fail('Asset Type is required.');

  $maker_hris = trim($_SESSION['hris'] ?? '');
  $maker_name = trim($_SESSION['name'] ?? '');

  $conn->begin_transaction();
  try {
    // Lock reservation
    $stmt = $conn->prepare("
      SELECT id, category_id, budget_id, item_code, reserved_by, status
      FROM tbl_admin_asset_code_reservations
      WHERE id=? FOR UPDATE
    ");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $resv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resv) throw new Exception('Reservation not found. Please regenerate code.');
    if ((int)$resv['reserved_by'] !== $uid) throw new Exception('This reservation does not belong to you.');
    if ($resv['status'] !== 'RESERVED') throw new Exception('Reservation already used/cancelled. Please regenerate code.');

    $category_id = (int)$resv['category_id'];
    $budget_id   = (int)$resv['budget_id'];
    $item_code   = $resv['item_code'];

    $status = 'PENDING';

    $stmt = $conn->prepare("
      INSERT INTO tbl_admin_assets
        (item_name, asset_type_id, category_id, budget_id, status, item_code,
         created_by, created_by_hris, created_by_name, created_at)
      VALUES (?,?,?,?,?,?, ?,?,?, NOW())
    ");
    $stmt->bind_param(
      "siiississ",
      $item_name, $asset_type_id, $category_id, $budget_id, $status, $item_code,
      $uid, $maker_hris, $maker_name
    );

    if (!$stmt->execute()) {
      if ($conn->errno == 1062) {
        throw new Exception('Item Name already exists. Please use a different Item Name.');
      }
      throw new Exception('Asset insert failed: '.$conn->error);
    }

    $asset_id = (int)$stmt->insert_id;
    $stmt->close();

    // Mark reservation USED
    $stmt = $conn->prepare("
      UPDATE tbl_admin_asset_code_reservations
      SET status='USED', used_asset_id=?
      WHERE id=? AND status='RESERVED'
    ");
    $stmt->bind_param("ii", $asset_id, $reservation_id);
    if (!$stmt->execute()) throw new Exception('Reservation update failed: '.$conn->error);
    $stmt->close();

    $conn->commit();

    ok([
      'asset_id' => $asset_id,
      'item_code'=> $item_code,
      'msg'      => "Submitted for approval. ID: <b>{$asset_id}</b> | Code: <b>{$item_code}</b>"
    ]);

  } catch (Exception $e) {
    $conn->rollback();
    fail($e->getMessage());
  }
}

fail('Invalid action.');
