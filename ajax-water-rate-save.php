<?php
// ajax-water-rate-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$rate_profile_id  = isset($_POST['rate_profile_id']) && $_POST['rate_profile_id'] !== ''
    ? (int)$_POST['rate_profile_id'] : 0;

$water_type_id    = (int)($_POST['water_type_id'] ?? 0);
$vendor_id        = (int)($_POST['vendor_id'] ?? 0);
$is_active        = isset($_POST['is_active']) ? 1 : 0;

$bottle_rate        = $_POST['bottle_rate']        !== '' ? (float)$_POST['bottle_rate']        : null;
$cooler_rental_rate = $_POST['cooler_rental_rate'] !== '' ? (float)$_POST['cooler_rental_rate'] : null;
$sscl_percentage    = $_POST['sscl_percentage']    !== '' ? (float)$_POST['sscl_percentage']    : null;
$vat_percentage     = $_POST['vat_percentage']     !== '' ? (float)$_POST['vat_percentage']     : null;
$effective_from     = $_POST['effective_from']     !== '' ? $_POST['effective_from']            : null;

// profile_name exists in table but we don't use it on UI; keep it simple
$profile_name = '';

if (!$water_type_id || !$vendor_id) {
    echo json_encode(['success' => false, 'message' => 'Water type and vendor are required.']);
    exit;
}

try {

    if ($rate_profile_id > 0) {
        // UPDATE
        $sql = "
            UPDATE tbl_admin_water_rate_profiles
            SET water_type_id = ?, vendor_id = ?, profile_name = ?,
                bottle_rate = ?, cooler_rental_rate = ?,
                sscl_percentage = ?, vat_percentage = ?,
                effective_from = ?, is_active = ?
            WHERE rate_profile_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iissddddii",
            $water_type_id,
            $vendor_id,
            $profile_name,
            $bottle_rate,
            $cooler_rental_rate,
            $sscl_percentage,
            $vat_percentage,
            $effective_from,
            $is_active,
            $rate_profile_id
        );
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Update failed: '.$err]);
            exit;
        }

        userlog("Water rate profile updated #{$rate_profile_id}");
        echo json_encode(['success' => true, 'message' => 'Rate profile updated successfully.']);
        exit;

    } else {
        // INSERT
        $sql = "
            INSERT INTO tbl_admin_water_rate_profiles
              (water_type_id, vendor_id, profile_name,
               bottle_rate, cooler_rental_rate,
               sscl_percentage, vat_percentage,
               effective_from, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iissddddi",
            $water_type_id,
            $vendor_id,
            $profile_name,
            $bottle_rate,
            $cooler_rental_rate,
            $sscl_percentage,
            $vat_percentage,
            $effective_from,
            $is_active
        );
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Insert failed: '.$err]);
            exit;
        }

        userlog("Water rate profile created");
        echo json_encode(['success' => true, 'message' => 'Rate profile saved successfully.']);
        exit;
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: '.$e->getMessage()]);
    exit;
}
