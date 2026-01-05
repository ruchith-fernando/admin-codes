<?php
// upload-photocopy-actuals.php  (WITH DOWNLOADABLE REPORT + FAILED PREVIEW + VIEW REPORT LINK)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "connections/connection.php"; // must define $conn (mysqli)
set_time_limit(0);

// ---------- USER ID helper (works with different session keys) ----------
function current_user_id(){
    foreach (['user_id','userid','userId','admin_id','emp_id','uid','id'] as $k) {
        if (isset($_SESSION[$k]) && $_SESSION[$k] !== '') return (string)$_SESSION[$k];
    }
    return '';
}

// ---------- Debug log ----------
$DEBUG_LOG = __DIR__ . "/photocopy-upload-debug.log";
function dbg($msg){
    global $DEBUG_LOG;
    @file_put_contents($DEBUG_LOG, date("Y-m-d H:i:s") . " | " . $msg . "\n", FILE_APPEND);
}

// ---------- Helpers ----------
function json_out($arr){
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($arr);
    exit;
}

function safe_int($v){
    $v = trim((string)$v);
    if ($v === '') return null;
    if (!preg_match('/^-?\d+$/', $v)) return null;
    return (int)$v;
}

function trunc255($s){
    $s = (string)$s;
    if (mb_strlen($s) > 255) return mb_substr($s, 0, 252) . '...';
    return $s;
}

function table_exists(mysqli $conn, $table){
    $table = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $r && $r->num_rows > 0;
}

/**
 * Parses "April 2025" / "Apr 2025" / "April-2025" etc.
 * Returns:
 *  - label      : "April 2025"
 *  - month_date : "2025-04-01" (DATE stored in DB)
 *  - period_end : "2025-04-30" (for assignment/rate effective checks)
 */
function parse_month($raw){
    $raw = trim((string)$raw);
    if ($raw === '') return false;

    $raw = preg_replace('/\s+/', ' ', $raw);
    $raw = str_replace(['-', '/', '.'], ' ', $raw);
    $raw = preg_replace('/\s+/', ' ', $raw);

    $dt = DateTime::createFromFormat('F Y', $raw);
    if (!$dt) $dt = DateTime::createFromFormat('M Y', $raw);
    if (!$dt) return false;

    return [
        "label"      => $dt->format('F Y'),
        "month_date" => $dt->format('Y-m-01'),
        "period_end" => $dt->format('Y-m-t'),
    ];
}

// =======================================================
// DOWNLOAD REPORT HANDLER (GET)
// URL: upload-photocopy-actuals.php?download_report=1&batch_id=7
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download_report']) && isset($_GET['batch_id'])) {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        http_response_code(500);
        echo "Database connection missing.";
        exit;
    }

    $batchId = (int)$_GET['batch_id'];
    if ($batchId <= 0) {
        http_response_code(400);
        echo "Invalid batch_id.";
        exit;
    }

    // use current_user_id()
    $userId = current_user_id();
    if ($userId === '') {
        http_response_code(403);
        echo "Not logged in.";
        exit;
    }

    // Optional: verify batch belongs to this user.
    // IMPORTANT: allow legacy batches where uploaded_by is blank.
    $stmt = $conn->prepare("SELECT uploaded_by FROM tbl_admin_photocopy_upload_batches WHERE batch_id=? LIMIT 1");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $batch = $stmt->get_result()->fetch_assoc();
    if (!$batch) {
        http_response_code(404);
        echo "Batch not found.";
        exit;
    }

    $uploadedBy = trim((string)$batch['uploaded_by']);
    if ($uploadedBy !== '' && $uploadedBy !== $userId) {
        http_response_code(403);
        echo "You do not have access to this report.";
        exit;
    }

    $reportDir  = __DIR__ . "/tmp_photocopy_reports";
    $reportFile = $reportDir . "/photocopy_batch_{$batchId}_report.csv";

    if (!is_file($reportFile)) {
        http_response_code(404);
        echo "Report file not found on server.";
        exit;
    }

    $downloadName = "photocopy_upload_report_batch_{$batchId}.csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$downloadName}\"");
    header("Content-Length: " . filesize($reportFile));
    readfile($reportFile);
    exit;
}

// ===================== POST HANDLER =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {

    if (!isset($conn) || !($conn instanceof mysqli)) {
        json_out(["status"=>"error","message"=>"Database connection missing (\$conn)."]);
    }

    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        json_out(["status"=>"error","message"=>"CSV upload failed (upload error code: ".$_FILES['csv']['error'].")."]);
    }

    $tmp          = $_FILES['csv']['tmp_name'];
    $originalName = $_FILES['csv']['name'] ?? null;

    $handle = fopen($tmp, "r");
    if (!$handle) {
        json_out(["status"=>"error","message"=>"Could not open uploaded file."]);
    }

    // Your batches table uses uploaded_by varchar
    $uploadedBy = current_user_id(); // ✅ IMPORTANT
    // If not logged in, still allow upload but report download will require login
    // (If you want to block uploads when not logged in, enforce it here.)
    // if ($uploadedBy === '') { json_out(["status"=>"error","message"=>"Not logged in."]); }

    // month_applicable is DATE NOT NULL, insert placeholder and update after processing
    $batchMonthDate = date('Y-m-01');

    // ---- Create batch (MATCHES YOUR tbl_admin_photocopy_upload_batches) ----
    $stmtBatch = $conn->prepare("
        INSERT INTO tbl_admin_photocopy_upload_batches
            (file_name, original_filename, month_applicable, uploaded_by,
             total_rows, inserted_rows, updated_rows, error_rows)
        VALUES
            (?, ?, ?, ?, 0, 0, 0, 0)
    ");
    if (!$stmtBatch) {
        fclose($handle);
        json_out(["status"=>"error","message"=>"Batch prepare failed: ".$conn->error]);
    }

    $stmtBatch->bind_param("ssss", $originalName, $originalName, $batchMonthDate, $uploadedBy);

    if (!$stmtBatch->execute()) {
        fclose($handle);
        json_out(["status"=>"error","message"=>"Failed to create upload batch: ".$conn->error]);
    }

    $batchId = (int)$conn->insert_id;
    dbg("BATCH START id={$batchId} file={$originalName}");

    // ----------------------------
    // Create REPORT CSV file
    // ----------------------------
    $reportDir = __DIR__ . "/tmp_photocopy_reports";
    if (!is_dir($reportDir)) { @mkdir($reportDir, 0775, true); }
    $reportFile = $reportDir . "/photocopy_batch_{$batchId}_report.csv";

    $reportFp = @fopen($reportFile, "w");
    $reportOk = (bool)$reportFp;

    $reportHeaders = [
        "row_no",
        "csv_model", "csv_serial", "csv_branch_location", "csv_start_count", "csv_end_count", "csv_month_text",
        "parsed_month_label", "month_applicable_date", "period_end_date",
        "copy_count",
        "status", "error_code", "error_message",
        "resolved_machine_id", "resolved_branch_code", "resolved_vendor_id", "resolved_rate_profile_id",
        "copy_rate", "sscl_percentage", "vat_percentage",
        "base_amount", "sscl_amount", "vat_amount", "total_amount",
        "action", "actual_id"
    ];
    if ($reportOk) {
        fputcsv($reportFp, $reportHeaders);
    }

    // ---- Prepared statements ----
    $stmtMachine = $conn->prepare("
        SELECT machine_id, model_name, serial_no, vendor_id, rate_profile_id, is_active
        FROM tbl_admin_photocopy_machines
        WHERE serial_no = ?
        LIMIT 1
    ");

    $stmtAssign = $conn->prepare("
        SELECT branch_code, vendor_id, installed_at, removed_at
        FROM tbl_admin_photocopy_machine_assignments
        WHERE machine_id = ?
          AND installed_at <= ?
          AND (removed_at IS NULL OR removed_at >= ?)
        ORDER BY installed_at DESC
        LIMIT 1
    ");

    $stmtRateById = $conn->prepare("
        SELECT rate_profile_id, copy_rate, sscl_percentage, vat_percentage
        FROM tbl_admin_photocopy_rate_profiles
        WHERE rate_profile_id = ?
          AND vendor_id = ?
          AND is_active = 1
          AND (effective_from IS NULL OR effective_from <= ?)
          AND (effective_to   IS NULL OR effective_to   >= ?)
        LIMIT 1
    ");

    $stmtRateAuto = $conn->prepare("
        SELECT rate_profile_id, copy_rate, sscl_percentage, vat_percentage
        FROM tbl_admin_photocopy_rate_profiles
        WHERE vendor_id = ?
          AND is_active = 1
          AND (effective_from IS NULL OR effective_from <= ?)
          AND (effective_to   IS NULL OR effective_to   >= ?)
        ORDER BY
          CASE
            WHEN model_match = ? THEN 0
            WHEN model_match IS NOT NULL AND model_match <> '' AND ? LIKE CONCAT('%', model_match, '%') THEN 1
            WHEN model_match IS NULL OR model_match = '' THEN 2
            ELSE 3
          END ASC,
          effective_from DESC,
          rate_profile_id DESC
        LIMIT 1
    ");

    // ---- Actuals (MATCHES YOUR tbl_admin_actual_photocopy) ----
    $stmtActualCheck = $conn->prepare("
        SELECT actual_id
        FROM tbl_admin_actual_photocopy
        WHERE machine_id = ?
          AND month_applicable = ?
        LIMIT 1
    ");

    $stmtActualInsert = $conn->prepare("
        INSERT INTO tbl_admin_actual_photocopy
        (month_applicable, machine_id, serial_no, model_name,
         branch_code, vendor_id, rate_profile_id,
         copy_rate, sscl_percentage, vat_percentage,
         start_count, end_count, copy_count,
         base_amount, sscl_amount, vat_amount, total_amount,
         excel_branch_location, uploaded_at)
        VALUES
        (?, ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?, ?,
         ?, NOW())
    ");

    $stmtActualUpdate = $conn->prepare("
        UPDATE tbl_admin_actual_photocopy
        SET serial_no = ?,
            model_name = ?,
            branch_code = ?,
            vendor_id = ?,
            rate_profile_id = ?,
            copy_rate = ?,
            sscl_percentage = ?,
            vat_percentage = ?,
            start_count = ?,
            end_count = ?,
            copy_count = ?,
            base_amount = ?,
            sscl_amount = ?,
            vat_amount = ?,
            total_amount = ?,
            excel_branch_location = ?
        WHERE actual_id = ?
        LIMIT 1
    ");

    // ---- Optional DB log table (won't break if missing) ----
    $stmtLog = null;
    if (table_exists($conn, "tbl_admin_photocopy_upload_logs")) {
        $stmtLog = $conn->prepare("
            INSERT INTO tbl_admin_photocopy_upload_logs
            (batch_id, row_no, serial_no, model_text, location_text,
             start_count, end_count, copy_count,
             resolved_machine_id, resolved_branch_code, resolved_vendor_id, resolved_rate_profile_id,
             status, error_code, error_message, created_at)
            VALUES
            (?, ?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?, ?,
             ?, ?, ?, NOW())
        ");
    }

    // ---- CSV header ----
    $header = fgetcsv($handle, 0, ",");
    if (!$header) {
        fclose($handle);
        if ($reportOk) fclose($reportFp);
        json_out(["status"=>"error","message"=>"CSV is empty."]);
    }

    $totalRows   = 0;
    $inserted    = 0;
    $updated     = 0;
    $failed      = 0;
    $lineNo      = 0;

    $monthsSeen = [];
    $failedPreview = [];
    $FAILED_PREVIEW_MAX = 25;

    while (($row = fgetcsv($handle, 0, ",")) !== false) {
        $lineNo++;

        $modelText    = trim($row[0] ?? '');
        $serialNo     = trim($row[1] ?? '');
        $locationText = trim($row[2] ?? '');
        $startRaw     = $row[3] ?? '';
        $endRaw       = $row[4] ?? '';
        $monthRaw     = trim($row[5] ?? '');

        if ($modelText === '' && $serialNo === '' && $locationText === '' && trim((string)$startRaw) === '' && trim((string)$endRaw) === '' && $monthRaw === '') {
            continue;
        }

        $totalRows++;

        $errorCode = null;
        $errorMsg  = null;

        $resolvedMachineId     = null;
        $resolvedBranchCode    = null;
        $resolvedVendorId      = null;
        $resolvedRateProfileId = null;

        $startCount = safe_int($startRaw);
        $endCount   = safe_int($endRaw);

        $monthInfo = parse_month($monthRaw);
        if ($monthInfo === false) {
            $errorCode = "INVALID_MONTH";
            $errorMsg  = "Invalid applicable month: '{$monthRaw}' (expected like 'April 2025')";
        }

        $monthLabel = $monthInfo ? $monthInfo["label"] : '';
        $monthDate  = $monthInfo ? $monthInfo["month_date"] : '';
        $periodEnd  = $monthInfo ? $monthInfo["period_end"] : '';

        if (!$errorCode && $serialNo === '') {
            $errorCode = "SERIAL_MISSING";
            $errorMsg  = "Serial is empty.";
        }

        if (!$errorCode && ($startCount === null || $endCount === null)) {
            $errorCode = "INVALID_READING";
            $errorMsg  = "Start/End must be integers.";
        }

        $copyCount = null;
        if (!$errorCode) {
            $copyCount = $endCount - $startCount;
            if ($copyCount < 0) {
                $errorCode = "INVALID_READING";
                $errorMsg  = "End Count is less than Start Count.";
            }
        }

        // Resolve machine by serial
        $machine = null;
        if (!$errorCode) {
            $stmtMachine->bind_param("s", $serialNo);
            if (!$stmtMachine->execute()) {
                $errorCode = "DB_LOOKUP_FAILED";
                $errorMsg  = "Machine lookup failed: ".$conn->error;
            } else {
                $res = $stmtMachine->get_result();
                $machine = $res ? $res->fetch_assoc() : null;

                if (!$machine) {
                    $errorCode = "SERIAL_NOT_FOUND";
                    $errorMsg  = "Serial not found in machines master.";
                } else {
                    if ((int)$machine['is_active'] !== 1) {
                        $errorCode = "MACHINE_INACTIVE";
                        $errorMsg  = "Machine is inactive in master.";
                    } else {
                        $resolvedMachineId = (int)$machine['machine_id'];
                    }
                }
            }
        }

        // Resolve assignment by period end date
        $assign = null;
        if (!$errorCode) {
            $stmtAssign->bind_param("iss", $resolvedMachineId, $periodEnd, $periodEnd);
            if (!$stmtAssign->execute()) {
                $errorCode = "DB_LOOKUP_FAILED";
                $errorMsg  = "Assignment lookup failed: ".$conn->error;
            } else {
                $res = $stmtAssign->get_result();
                $assign = $res ? $res->fetch_assoc() : null;

                if (!$assign) {
                    $errorCode = "NO_ACTIVE_ASSIGNMENT";
                    $errorMsg  = "No assignment found for this machine for period end {$periodEnd}.";
                } else {
                    $resolvedBranchCode = $assign['branch_code'];
                }
            }
        }

        // Vendor resolve
        $vendorMismatchWarn = false;
        if (!$errorCode) {
            $assignVendor  = isset($assign['vendor_id'])  && $assign['vendor_id']  !== null ? (int)$assign['vendor_id']  : null;
            $machineVendor = isset($machine['vendor_id']) && $machine['vendor_id'] !== null ? (int)$machine['vendor_id'] : null;

            if ($assignVendor && $machineVendor && $assignVendor !== $machineVendor) {
                $vendorMismatchWarn = true;
            }

            $resolvedVendorId = $assignVendor ?: ($machineVendor ?: null);

            if (!$resolvedVendorId) {
                $errorCode = "VENDOR_MISSING";
                $errorMsg  = "Vendor not set on assignment or machine.";
            }
        }

        // Resolve rate profile
        $rate = null;
        if (!$errorCode) {
            $machineRateProfileId = isset($machine['rate_profile_id']) ? (int)$machine['rate_profile_id'] : 0;
            $machineModel = trim((string)($machine['model_name'] ?? ''));

            if ($machineRateProfileId > 0) {
                $stmtRateById->bind_param("iiss", $machineRateProfileId, $resolvedVendorId, $periodEnd, $periodEnd);
                if ($stmtRateById->execute()) {
                    $res = $stmtRateById->get_result();
                    $rate = $res ? $res->fetch_assoc() : null;
                }
            }

            if (!$rate) {
                $stmtRateAuto->bind_param("issss", $resolvedVendorId, $periodEnd, $periodEnd, $machineModel, $machineModel);
                if (!$stmtRateAuto->execute()) {
                    $errorCode = "DB_LOOKUP_FAILED";
                    $errorMsg  = "Rate lookup failed: ".$conn->error;
                } else {
                    $res = $stmtRateAuto->get_result();
                    $rate = $res ? $res->fetch_assoc() : null;
                }
            }

            if (!$errorCode && !$rate) {
                $errorCode = "RATE_PROFILE_MISSING";
                $errorMsg  = "No active rate profile matched vendor/model/date.";
            }

            if (!$errorCode) {
                $resolvedRateProfileId = (int)$rate['rate_profile_id'];
            }
        }

        // Report placeholders
        $copyRate = '';
        $ssclPct = '';
        $vatPct = '';
        $amountBeforeTax = '';
        $ssclAmount = '';
        $vatAmount = '';
        $totalAmount = '';
        $action = '';
        $actualIdOut = '';

        // FAIL
        if ($errorCode) {
            $failed++;
            $status = "FAILED";
            $err = trunc255($errorMsg);

            if (count($failedPreview) < $FAILED_PREVIEW_MAX) {
                $failedPreview[] = [
                    "row_no" => $lineNo,
                    "serial" => $serialNo,
                    "month"  => $monthRaw,
                    "code"   => $errorCode,
                    "msg"    => $err
                ];
            }

            // Optional DB log
            if ($stmtLog) {
                $sc = ($startCount === null ? null : $startCount);
                $ec = ($endCount   === null ? null : $endCount);
                $cc = ($copyCount  === null ? null : $copyCount);

                $stmtLog->bind_param(
                    "iisssiiiisiisss",
                    $batchId, $lineNo, $serialNo, $modelText, $locationText,
                    $sc, $ec, $cc,
                    $resolvedMachineId, $resolvedBranchCode, $resolvedVendorId, $resolvedRateProfileId,
                    $status, $errorCode, $err
                );
                $stmtLog->execute();
            }

            // Report row
            if ($reportOk) {
                fputcsv($reportFp, [
                    $lineNo,
                    $modelText, $serialNo, $locationText, $startRaw, $endRaw, $monthRaw,
                    $monthLabel, $monthDate, $periodEnd,
                    ($copyCount === null ? '' : $copyCount),
                    $status, $errorCode, $err,
                    ($resolvedMachineId ?? ''), ($resolvedBranchCode ?? ''), ($resolvedVendorId ?? ''), ($resolvedRateProfileId ?? ''),
                    $copyRate, $ssclPct, $vatPct,
                    $amountBeforeTax, $ssclAmount, $vatAmount, $totalAmount,
                    $action, $actualIdOut
                ]);
            }

            continue;
        }

        // Costs
        $copyRate = (float)$rate['copy_rate'];
        $ssclPct  = (float)$rate['sscl_percentage'];
        $vatPct   = (float)$rate['vat_percentage'];

        $amountBeforeTax = round($copyCount * $copyRate, 2);
        $ssclAmount      = round($amountBeforeTax * ($ssclPct / 100), 2);
        $vatBase         = $amountBeforeTax + $ssclAmount;
        $vatAmount       = round($vatBase * ($vatPct / 100), 2);
        $totalAmount     = round($amountBeforeTax + $ssclAmount + $vatAmount, 2);

        // Upsert
        $stmtActualCheck->bind_param("is", $resolvedMachineId, $monthDate);
        $stmtActualCheck->execute();
        $res = $stmtActualCheck->get_result();
        $existing = $res ? $res->fetch_assoc() : null;

        $machineModelName = $machine['model_name'] ?? null;
        $serialStored     = $machine['serial_no'] ?? $serialNo;

        if (!$existing) {
            // INSERT
            $stmtActualInsert->bind_param(
                "sisssiidddiiidddds",
                $monthDate,
                $resolvedMachineId,
                $serialStored,
                $machineModelName,
                $resolvedBranchCode,
                $resolvedVendorId,
                $resolvedRateProfileId,
                $copyRate,
                $ssclPct,
                $vatPct,
                $startCount,
                $endCount,
                $copyCount,
                $amountBeforeTax,
                $ssclAmount,
                $vatAmount,
                $totalAmount,
                $locationText
            );

            if (!$stmtActualInsert->execute()) {
                $failed++;
                $status = "FAILED";
                $errorCode = "DB_INSERT_FAILED";
                $err = trunc255($conn->error);

                if (count($failedPreview) < $FAILED_PREVIEW_MAX) {
                    $failedPreview[] = ["row_no"=>$lineNo,"serial"=>$serialNo,"month"=>$monthRaw,"code"=>$errorCode,"msg"=>$err];
                }

                if ($stmtLog) {
                    $stmtLog->bind_param(
                        "iisssiiiisiisss",
                        $batchId, $lineNo, $serialNo, $modelText, $locationText,
                        $startCount, $endCount, $copyCount,
                        $resolvedMachineId, $resolvedBranchCode, $resolvedVendorId, $resolvedRateProfileId,
                        $status, $errorCode, $err
                    );
                    $stmtLog->execute();
                }

                if ($reportOk) {
                    fputcsv($reportFp, [
                        $lineNo,
                        $modelText, $serialNo, $locationText, $startRaw, $endRaw, $monthRaw,
                        $monthLabel, $monthDate, $periodEnd,
                        $copyCount,
                        $status, $errorCode, $err,
                        $resolvedMachineId, $resolvedBranchCode, $resolvedVendorId, $resolvedRateProfileId,
                        $copyRate, $ssclPct, $vatPct,
                        $amountBeforeTax, $ssclAmount, $vatAmount, $totalAmount,
                        "INSERT", ""
                    ]);
                }

                continue;
            }

            $inserted++;
            $status = "IMPORTED";
            $action = "INSERT";
            $actualIdOut = (int)$conn->insert_id;

        } else {
            // UPDATE
            $actualId = (int)$existing['actual_id'];

            $stmtActualUpdate->bind_param(
                "sssiidddiiiddddsi",
                $serialStored,
                $machineModelName,
                $resolvedBranchCode,
                $resolvedVendorId,
                $resolvedRateProfileId,
                $copyRate,
                $ssclPct,
                $vatPct,
                $startCount,
                $endCount,
                $copyCount,
                $amountBeforeTax,
                $ssclAmount,
                $vatAmount,
                $totalAmount,
                $locationText,
                $actualId
            );

            if (!$stmtActualUpdate->execute()) {
                $failed++;
                $status = "FAILED";
                $errorCode = "DB_UPDATE_FAILED";
                $err = trunc255($conn->error);

                if (count($failedPreview) < $FAILED_PREVIEW_MAX) {
                    $failedPreview[] = ["row_no"=>$lineNo,"serial"=>$serialNo,"month"=>$monthRaw,"code"=>$errorCode,"msg"=>$err];
                }

                if ($stmtLog) {
                    $stmtLog->bind_param(
                        "iisssiiiisiisss",
                        $batchId, $lineNo, $serialNo, $modelText, $locationText,
                        $startCount, $endCount, $copyCount,
                        $resolvedMachineId, $resolvedBranchCode, $resolvedVendorId, $resolvedRateProfileId,
                        $status, $errorCode, $err
                    );
                    $stmtLog->execute();
                }

                if ($reportOk) {
                    fputcsv($reportFp, [
                        $lineNo,
                        $modelText, $serialNo, $locationText, $startRaw, $endRaw, $monthRaw,
                        $monthLabel, $monthDate, $periodEnd,
                        $copyCount,
                        $status, $errorCode, $err,
                        $resolvedMachineId, $resolvedBranchCode, $resolvedVendorId, $resolvedRateProfileId,
                        $copyRate, $ssclPct, $vatPct,
                        $amountBeforeTax, $ssclAmount, $vatAmount, $totalAmount,
                        "UPDATE", $actualId
                    ]);
                }

                continue;
            }

            $updated++;
            $status = "UPDATED";
            $action = "UPDATE";
            $actualIdOut = $actualId;
        }

        // Success log (optional)
        if ($stmtLog) {
            $warnMsg = $vendorMismatchWarn ? "Vendor mismatch: assignment.vendor_id != machine.vendor_id (used assignment vendor)" : null;
            $logErrorCode = $warnMsg ? "WARN_VENDOR_MISMATCH" : null;
            $logErrorMsg  = $warnMsg ? trunc255($warnMsg) : null;

            $stmtLog->bind_param(
                "iisssiiiisiisss",
                $batchId, $lineNo, $serialNo, $modelText, $locationText,
                $startCount, $endCount, $copyCount,
                $resolvedMachineId, $resolvedBranchCode, $resolvedVendorId, $resolvedRateProfileId,
                $status, $logErrorCode, $logErrorMsg
            );
            $stmtLog->execute();
        }

        // Report row SUCCESS
        if ($reportOk) {
            fputcsv($reportFp, [
                $lineNo,
                $modelText, $serialNo, $locationText, $startRaw, $endRaw, $monthRaw,
                $monthLabel, $monthDate, $periodEnd,
                $copyCount,
                $status, "", "",
                $resolvedMachineId, $resolvedBranchCode, $resolvedVendorId, $resolvedRateProfileId,
                $copyRate, $ssclPct, $vatPct,
                $amountBeforeTax, $ssclAmount, $vatAmount, $totalAmount,
                $action, $actualIdOut
            ]);
        }

        $monthsSeen[$monthLabel] = $monthDate;
    }

    fclose($handle);
    if ($reportOk) fclose($reportFp);

    // Batch final month + counts
    $labels = array_keys($monthsSeen);
    sort($labels);

    $uniqueMonthDates = array_values(array_unique(array_values($monthsSeen)));
    sort($uniqueMonthDates);

    $finalMonthDate = $uniqueMonthDates[0] ?? $batchMonthDate;
    $isMixed = (count($uniqueMonthDates) > 1);

    $stmtBatchUpd = $conn->prepare("
        UPDATE tbl_admin_photocopy_upload_batches
        SET month_applicable = ?,
            total_rows = ?,
            inserted_rows = ?,
            updated_rows = ?,
            error_rows = ?
        WHERE batch_id = ?
        LIMIT 1
    ");
    $stmtBatchUpd->bind_param("siiiii", $finalMonthDate, $totalRows, $inserted, $updated, $failed, $batchId);
    $stmtBatchUpd->execute();

    dbg("BATCH END id={$batchId} total={$totalRows} ins={$inserted} upd={$updated} fail={$failed} months=".implode(",", $labels));

    json_out([
        "status"        => "success",
        "batch_id"      => $batchId,
        "file"          => $originalName,
        "months"        => $labels,
        "mixed_months"  => $isMixed ? 1 : 0,
        "total_rows"    => $totalRows,
        "inserted"      => $inserted,
        "updated"       => $updated,
        "failed"        => $failed,
        "report_ready"  => $reportOk ? 1 : 0,
        "report_url"    => $reportOk ? ("upload-photocopy-actuals.php?download_report=1&batch_id=".$batchId) : null,
        "report_view_url" => $reportOk ? ("photocopy-upload-report.php?batch_id=".$batchId) : null,
        "failed_preview"=> $failedPreview,
        "report_note"   => $reportOk ? null : "Report file could not be created. Check folder permissions: pages/tmp_photocopy_reports/"
    ]);
}

// ===================== HTML UI (GET) =====================
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Upload Photocopy Actuals CSV</title>
<style>
  #globalLoader{position:fixed;inset:0;background:rgba(255,255,255,.9);display:none;align-items:center;justify-content:center;z-index:9999}
  .loader-inner.line-scale>div{height:72px;width:10.8px;margin:3.6px;display:inline-block;animation:scaleStretchDelay 1.2s infinite ease-in-out}
  .loader-inner.line-scale>div:nth-child(odd){background:#0070C0}.loader-inner.line-scale>div:nth-child(even){background:#E60028}
  .loader-inner.line-scale>div:nth-child(1){animation-delay:-1.2s}.loader-inner.line-scale>div:nth-child(2){animation-delay:-1.1s}
  .loader-inner.line-scale>div:nth-child(3){animation-delay:-1.0s}.loader-inner.line-scale>div:nth-child(4){animation-delay:-0.9s}
  .loader-inner.line-scale>div:nth-child(5){animation-delay:-0.8s}
  @keyframes scaleStretchDelay{0%,40%,100%{transform:scaleY(.4)}20%{transform:scaleY(1)}}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
  .content.font-size{padding:20px}.container-fluid{max-width:1100px;margin:0 auto}
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
  .card h5{margin:0 0 16px;color:#0d6efd}
  .mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem}
  .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
  .form-control.is-invalid{border-color:#dc3545}
  .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer;text-decoration:none}
  .btn-success{background:#198754;color:#fff}.btn-success:disabled{opacity:.6;cursor:not-allowed}
  .btn-outline{background:#fff;border:1px solid #0d6efd;color:#0d6efd}
  .progress-wrap{background:#eef2ff;border:1px solid #dbeafe;border-radius:10px;padding:10px;margin-top:12px;display:none}
  .progress-bar{height:10px;width:0;background:#0d6efd;border-radius:8px;transition:width .2s}
  .progress-label{font-size:.9rem;margin-top:.35rem;color:#333}
  .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:8px 0;background:#fafafa}
  .alert{padding:.65rem 1rem;border-radius:8px;margin:8px 0}
  .alert-success{background:#e8f5e9;color:#1b5e20}
  .alert-danger{background:#ffebee;color:#b71c1c}
  .hint{font-size:.92rem;color:#555;line-height:1.45}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.9rem}
  table{border-collapse:collapse;width:100%;margin-top:10px}
  th,td{border:1px solid #e5e7eb;padding:8px;font-size:.92rem;text-align:left;vertical-align:top}
  th{background:#f3f4f6}
  .small{font-size:.9rem;color:#333}
  .action-links{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
</style>
</head>
<body>

<div id="globalLoader"><div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div></div>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <h5>Upload Photocopy Actuals CSV</h5>

      <div class="hint">
        <b>CSV format (first row must be headers):</b><br>
        <span class="mono">Model, Serial, Branch Location, Start Count, End Count, Applicable month</span><br><br>
        <b>Applicable month examples:</b>
        <span class="mono">April 2025</span>, <span class="mono">May 2025</span>, <span class="mono">July 2025</span>, <span class="mono">December 2025</span>, <span class="mono">February 2026</span><br><br>
      </div>

      <div id="uploadResult" class="result-block" style="display:none"></div>

      <form id="csvUploadForm" enctype="multipart/form-data" action="upload-photocopy-actuals.php" method="post" novalidate>
        <div class="mb-3">
          <label class="form-label" for="csv_file">Choose CSV File</label>
          <input class="form-control" type="file" id="csv_file" name="csv" accept=".csv,text/csv" required />
        </div>

        <button type="submit" class="btn btn-success">Upload &amp; Process</button>

        <div id="uploadProgress" class="progress-wrap">
          <div id="progressBar" class="progress-bar"></div>
          <div id="progressLabel" class="progress-label">Preparing upload…</div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const $form  = $('#csvUploadForm'),
        $loader= $('#globalLoader'),
        $result= $('#uploadResult');
  const $wrap  = $('#uploadProgress'),
        $bar   = $('#progressBar'),
        $label = $('#progressLabel'),
        $file  = $('#csv_file');

  function resetProgress(){ $wrap.hide(); $bar.css('width','0%'); $label.text(''); }
  function showResult(html){ $result.html(html).show(); }
  function showError(msg){
    $file.addClass('is-invalid').focus();
    showResult("<div class='alert alert-danger'><b>❌ " + msg + "</b></div>");
  }

  $file.on('change', ()=>{ $file.removeClass('is-invalid'); $result.hide().empty(); });

  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, (m)=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[m]));
  }

  $form.on('submit', function(e){
    e.preventDefault();
    $result.hide().empty();

    const file = $file[0].files[0];
    if(!file){ showError('Please choose a CSV file.'); return; }

    const fd  = new FormData(this);
    const $btn= $(this).find('button[type="submit"]');

    $btn.prop('disabled', true);
    $loader.css('display','flex');
    $wrap.show();
    $label.text('Uploading…');

    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: fd,
      contentType: false,
      processData: false,
      dataType: 'json',
      xhr: function(){
        const xhr = $.ajaxSettings.xhr();
        if(xhr.upload){
          xhr.upload.addEventListener('progress', function(e){
            if(e.lengthComputable){
              const p = Math.round((e.loaded / e.total) * 100);
              $bar.css('width', p + '%');
              $label.text('Uploading… ' + p + '%');
            }
          });
        }
        return xhr;
      },
      success: function(resp){
        if(resp.status === 'success'){
          const months = (resp.months && resp.months.length) ? resp.months.join(', ') : '';
          const mixed  = resp.mixed_months
            ? "<div style='margin-top:8px'>⚠️ CSV contains multiple months. Batch month stored as earliest month.</div>"
            : "";

          let failedTable = "";
          if(resp.failed_preview && resp.failed_preview.length){
            failedTable += "<div style='margin-top:12px'><b>Failed rows (preview):</b></div>";
            failedTable += "<table><thead><tr><th>Row No</th><th>Serial</th><th>Month</th><th>Error Code</th><th>Error</th></tr></thead><tbody>";
            resp.failed_preview.forEach(r=>{
              failedTable += "<tr>"+
                "<td>"+escapeHtml(r.row_no)+"</td>"+
                "<td>"+escapeHtml(r.serial)+"</td>"+
                "<td>"+escapeHtml(r.month)+"</td>"+
                "<td>"+escapeHtml(r.code)+"</td>"+
                "<td>"+escapeHtml(r.msg)+"</td>"+
              "</tr>";
            });
            failedTable += "</tbody></table>";
          }

          showResult(
            "<div class='alert alert-success'>"+
              "<b>✅ Upload complete.</b><br>"+
              "Batch ID: <b>"+escapeHtml(resp.batch_id)+"</b><br>"+
              "Months: <b>"+escapeHtml(months)+"</b><br>"+
              "Total Rows: <b>"+escapeHtml(resp.total_rows)+"</b> | Inserted: <b>"+escapeHtml(resp.inserted)+"</b> | Updated: <b>"+escapeHtml(resp.updated)+"</b> | Failed: <b>"+escapeHtml(resp.failed)+"</b><br>"+
              (resp.failed > 0 ? "<div style='margin-top:8px'>⚠️ Some rows failed.</div>" : "")+
              mixed+
              failedTable+
            "</div>"
          );
        } else {
          showResult("<div class='alert alert-danger'><b>❌ "+escapeHtml(resp.message || 'Upload failed')+"</b></div>");
        }
      },
      error: function(x){
        showError(x.responseText || 'Upload failed.');
      },
      complete: function(){
        $loader.hide();
        $btn.prop('disabled', false);
        setTimeout(resetProgress, 600);
      }
    });
  });
})();
</script>

</body>
</html>
