<?php
// photocopy-rate-profiles-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$rate_profile_id = isset($_POST['rate_profile_id']) ? (int)$_POST['rate_profile_id'] : 0;
$vendor_id       = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;

$model_match     = trim($_POST['model_match'] ?? '');
$copy_rate       = trim($_POST['copy_rate'] ?? '');
$sscl_percentage = trim($_POST['sscl_percentage'] ?? '0');
$vat_percentage  = trim($_POST['vat_percentage'] ?? '0');

$effective_from  = trim($_POST['effective_from'] ?? '');
$effective_to    = trim($_POST['effective_to'] ?? '');

$is_active       = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$is_active       = ($is_active === 0) ? 0 : 1;

if ($vendor_id <= 0) {
    echo json_encode(["success" => false, "message" => "Vendor is required."]);
    exit;
}
if ($copy_rate === '' || !is_numeric($copy_rate) || (float)$copy_rate < 0) {
    echo json_encode(["success" => false, "message" => "Copy rate must be a valid number."]);
    exit;
}
if ($effective_from !== '' && $effective_to !== '' && $effective_to < $effective_from) {
    echo json_encode(["success" => false, "message" => "Effective To cannot be earlier than Effective From."]);
    exit;
}

$copy_rate_f = (float)$copy_rate;
$sscl_f = (is_numeric($sscl_percentage) ? (float)$sscl_percentage : 0.0);
$vat_f  = (is_numeric($vat_percentage)  ? (float)$vat_percentage  : 0.0);

$model_match_db = ($model_match === '') ? null : $model_match;
$eff_from_db = ($effective_from === '') ? null : $effective_from;
$eff_to_db   = ($effective_to === '')   ? null : $effective_to;

/* Overlap check (only for active profiles) */
$newStart = ($eff_from_db ?: "1900-01-01");
$newEnd   = ($eff_to_db   ?: "9999-12-31");

/*
Overlap logic:
existingStart <= newEnd AND newStart <= existingEnd
NULL start => 1900-01-01
NULL end   => 9999-12-31
Same vendor + same model_match bucket (both NULL/blank OR exact same string)
*/
$overlapSql = "
SELECT rate_profile_id
FROM tbl_admin_photocopy_rate_profiles
WHERE vendor_id = ?
  AND is_active = 1
  AND (
      (model_match IS NULL AND ? IS NULL)
      OR (model_match = ?)
  )
  AND (IFNULL(effective_from,'1900-01-01') <= ?)
  AND (? <= IFNULL(effective_to,'9999-12-31'))
";
$params = [$vendor_id, $model_match_db, $model_match_db, $newEnd, $newStart];
$types  = "issss";

if ($rate_profile_id > 0) {
    $overlapSql .= " AND rate_profile_id <> ?";
    $types .= "i";
    $params[] = $rate_profile_id;
}

$stmt = $conn->prepare($overlapSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$ov = $stmt->get_result();

if ($ov && $ov->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "A conflicting ACTIVE rate profile already exists for this vendor + model match within the selected effective date range. Please deactivate old one or adjust dates."
    ]);
    exit;
}

/* Save */
if ($rate_profile_id > 0) {

    $stmt = $conn->prepare("
        UPDATE tbl_admin_photocopy_rate_profiles
        SET vendor_id=?,
            model_match=?,
            copy_rate=?,
            sscl_percentage=?,
            vat_percentage=?,
            effective_from=?,
            effective_to=?,
            is_active=?
        WHERE rate_profile_id=?
        LIMIT 1
    ");
    $stmt->bind_param(
        "isdddssii",
        $vendor_id,
        $model_match_db,
        $copy_rate_f,
        $sscl_f,
        $vat_f,
        $eff_from_db,
        $eff_to_db,
        $is_active,
        $rate_profile_id
    );

    if ($stmt->execute()) {
        userlog("✅ Photocopy Rate Profile Updated | ID: {$rate_profile_id} | Vendor: {$vendor_id} | Model: " . ($model_match ?: "DEFAULT"));
        echo json_encode(["success" => true, "message" => "Rate profile updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Update failed: " . mysqli_error($conn)]);
    }
    exit;
}

/* Insert */
$stmt = $conn->prepare("
    INSERT INTO tbl_admin_photocopy_rate_profiles
    (vendor_id, model_match, copy_rate, sscl_percentage, vat_percentage, effective_from, effective_to, is_active)
    VALUES (?,?,?,?,?,?,?,?)
");
$stmt->bind_param(
    "isdddssi",
    $vendor_id,
    $model_match_db,
    $copy_rate_f,
    $sscl_f,
    $vat_f,
    $eff_from_db,
    $eff_to_db,
    $is_active
);

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    userlog("✅ Photocopy Rate Profile Created | ID: {$newId} | Vendor: {$vendor_id} | Model: " . ($model_match ?: "DEFAULT"));
    echo json_encode(["success" => true, "message" => "Rate profile created successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Insert failed: " . mysqli_error($conn)]);
}
