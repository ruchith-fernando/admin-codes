<?php
// asset-card-approve.php
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

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) { echo '<div class="alert alert-danger">Session expired.</div>'; exit; }

$session_hris = trim($_SESSION['hris'] ?? '');

function alert($type,$msg){
  return '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">'.
         $msg.'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$action = strtoupper(trim($_POST['action'] ?? 'LIST'));

if ($action === 'LIST') {
  $sql = "
    SELECT
      a.id, a.item_name, a.item_code, a.status,
      a.created_by_hris, a.created_by_name, a.created_at,
      a.approved_by_hris, a.approved_by_name, a.approved_at,
      c.category_name, c.category_code,
      b.budget_name, b.budget_code
    FROM tbl_admin_assets a
    JOIN tbl_admin_categories c ON c.id = a.category_id
    JOIN tbl_admin_budgets b ON b.id = a.budget_id
    WHERE a.status IN ('PENDING','APPROVED','REJECTED')
    ORDER BY a.created_at DESC
    LIMIT 300
  ";
  $res = $conn->query($sql);
  if (!$res) { echo alert('danger','Query error: '.$conn->error); exit; }

  if ($res->num_rows === 0) {
    echo '<div class="text-muted">No records found.</div>';
    exit;
  }

  echo '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">';
  echo '<thead class="table-light"><tr>
          <th>ID</th>
          <th>Item</th>
          <th>Code</th>
          <th>Status</th>
          <th>Category</th>
          <th>Budget</th>
          <th>Maker HRIS</th>
          <th>Created</th>
          <th>Approver HRIS</th>
          <th>Approved</th>
          <th class="text-end">Actions</th>
        </tr></thead><tbody>';

  while($r = $res->fetch_assoc()){
    $id = (int)$r['id'];

    $row_hris = trim($r['created_by_hris'] ?? '');
    $isMaker = ($session_hris !== '' && $row_hris !== '' && $session_hris === $row_hris);
    $isPending = ($r['status'] === 'PENDING');

    $makerHris = htmlspecialchars($r['created_by_hris'] ?? '');
    $makerName = htmlspecialchars($r['created_by_name'] ?? '');
    $makerCell = $makerHris ? "<span data-bs-toggle=\"tooltip\" title=\"{$makerName}\">{$makerHris}</span>" : "";

    $approverHris = htmlspecialchars($r['approved_by_hris'] ?? '');
    $approverName = htmlspecialchars($r['approved_by_name'] ?? '');
    $approverCell = $approverHris ? "<span data-bs-toggle=\"tooltip\" title=\"{$approverName}\">{$approverHris}</span>" : "";

    echo '<tr>
      <td>'.$id.'</td>
      <td>'.htmlspecialchars($r['item_name']).'</td>
      <td><span class="fw-bold">'.htmlspecialchars($r['item_code']).'</span></td>
      <td>'.htmlspecialchars($r['status']).'</td>
      <td>'.htmlspecialchars($r['category_name']).' ('.htmlspecialchars($r['category_code']).')</td>
      <td>'.htmlspecialchars($r['budget_name']).' ('.htmlspecialchars($r['budget_code']).')</td>
      <td>'.$makerCell.'</td>
      <td>'.htmlspecialchars($r['created_at']).'</td>
      <td>'.$approverCell.'</td>
      <td>'.htmlspecialchars($r['approved_at'] ?? '').'</td>
      <td class="text-end">';

    if ($isPending && !$isMaker) {
      echo '<button type="button" class="btn btn-sm btn-success btn-approve" data-id="'.$id.'">Approve</button>
            <button type="button" class="btn btn-sm btn-danger btn-reject" data-id="'.$id.'">Reject</button>';
    } else {
      echo '<span class="text-muted">—</span>';
    }

    echo '</td></tr>';
  }

  echo '</tbody></table></div>';
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo alert('danger','Missing ID.'); exit; }

if ($action === 'APPROVE') {
  $approver_hris = trim($_SESSION['hris'] ?? '');
  $approver_name = trim($_SESSION['name'] ?? '');

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare("SELECT created_by_hris, status FROM tbl_admin_assets WHERE id=? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) throw new Exception('Record not found.');
    if ($row['status'] !== 'PENDING') throw new Exception('Only PENDING records can be approved.');
    if (trim($row['created_by_hris'] ?? '') === $session_hris) throw new Exception('Dual control: Maker cannot approve own record.');

    $stmt = $conn->prepare("
      UPDATE tbl_admin_assets
      SET status='APPROVED',
          approved_by=?,
          approved_by_hris=?,
          approved_by_name=?,
          approved_at=NOW()
      WHERE id=? AND status='PENDING'
    ");
    $stmt->bind_param("issi", $uid, $approver_hris, $approver_name, $id);
    if (!$stmt->execute()) throw new Exception('Approve failed: '.$conn->error);
    $stmt->close();

    $conn->commit();
    echo alert('success','Approved successfully.');
    exit;

  } catch (Exception $e) {
    $conn->rollback();
    echo alert('danger', htmlspecialchars($e->getMessage()));
    exit;
  }
}

if ($action === 'REJECT') {
  $reason = trim($_POST['reject_reason'] ?? '');
  if (!$reason) { echo alert('danger','Reject reason is required.'); exit; }

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare("SELECT created_by_hris, status FROM tbl_admin_assets WHERE id=? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) throw new Exception('Record not found.');
    if ($row['status'] !== 'PENDING') throw new Exception('Only PENDING records can be rejected.');
    if (trim($row['created_by_hris'] ?? '') === $session_hris) throw new Exception('Dual control: Maker cannot reject own record.');

    $stmt = $conn->prepare("
      UPDATE tbl_admin_assets
      SET status='REJECTED',
          rejected_by=?,
          rejected_at=NOW(),
          reject_reason=?
      WHERE id=? AND status='PENDING'
    ");
    $stmt->bind_param("isi", $uid, $reason, $id);
    if (!$stmt->execute()) throw new Exception('Reject failed: '.$conn->error);
    $stmt->close();

    $conn->commit();
    echo alert('success','Rejected successfully.');
    exit;

  } catch (Exception $e) {
    $conn->rollback();
    echo alert('danger', htmlspecialchars($e->getMessage()));
    exit;
  }
}

echo alert('danger','Invalid action.');
exit;
