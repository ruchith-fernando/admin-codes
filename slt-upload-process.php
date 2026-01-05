<?php
/**
 * SLT PDF Upload + Parser + DB Writer (Shared-host friendly)
 * ----------------------------------------------------------
 * - Uploads 1..N PDF files (field: slt_files[])
 * - Parses with Smalot\PdfParser\Parser
 * - Extracts: bill period (text + dates), per-connection subtotals, tax_total, discount_total
 * - Inserts into:
 *      tbl_admin_slt_monthly_data
 *      tbl_admin_slt_monthly_data_connections
 *      tbl_admin_slt_monthly_data_charges
 * - Returns JSON summary per file
 *
 * Requirements:
 *   composer require smalot/pdfparser
 *   connections/connection.php must define $conn = mysqli connection (utf8mb4)
 *
 * Notes:
 *   - No HTML/DOM scraping. Pure text parsing, whitespace tolerant, layout-proof.
 *   - SR number format: SLTYYMM###### (unique per month, auto-increment per upload).
 */
// slt-upload-process.php
declare(strict_types=1);
ini_set('memory_limit', '512M');
set_time_limit(0);
date_default_timezone_set('Asia/Colombo');

header('Content-Type: application/json; charset=utf-8');

session_start();
$uploader_hris = isset($_SESSION['hris']) && $_SESSION['hris'] !== '' ? $_SESSION['hris'] : 'UNKNOWN';

// --------------------------- BOOTSTRAP ----------------------------
require_once __DIR__ . '/vendor/autoload.php';
use Smalot\PdfParser\Parser;

$logDir = __DIR__ . '/logs';
$uploadDir = __DIR__ . '/uploads/slt';
if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
$logFile = $logDir . '/slt-upload-' . date('Ymd') . '.log';

function slog(string $msg) {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

try {
    // ----------------------- DB CONNECTION ------------------------
    require_once __DIR__ . '/connections/connection.php'; // must expose $conn (mysqli)
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection ($conn) is not available.');
    }
    mysqli_set_charset($conn, 'utf8mb4');

    // ----------------------- INPUT CHECK --------------------------
    if (!isset($_FILES['slt_files'])) {
        echo json_encode(['ok' => false, 'error' => 'No files uploaded. Expecting slt_files[].']);
        exit;
    }

    $files = restructureFilesArray($_FILES['slt_files']);
    if (empty($files)) {
        echo json_encode(['ok' => false, 'error' => 'No valid file entries.']);
        exit;
    }

    $parser = new Parser();

    $results = [];
    foreach ($files as $idx => $f) {
        $startTime = microtime(true);
        slog("---- Begin file #$idx: name={$f['name']}, size={$f['size']} ----");

        try {
            // Validate
            if ($f['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException("Upload error code {$f['error']} for {$f['name']}");
            }
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                throw new RuntimeException("Only PDF files are supported. Got: {$f['name']}");
            }

            // Store file
            $storedBase = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $storedPath = $uploadDir . '/' . $storedBase;
            if (!@move_uploaded_file($f['tmp_name'], $storedPath)) {
                // Fallback (some shared hosts block move_uploaded_file across partitions)
                if (!@rename($f['tmp_name'], $storedPath)) {
                    throw new RuntimeException("Failed to save uploaded file for {$f['name']}");
                }
            }
            $relativeStored = 'uploads/slt/' . $storedBase;

            // Parse PDF
            $pdf = $parser->parseFile($storedPath);
            $text = '';
            foreach ($pdf->getPages() as $p) {
                $text .= $p->getText() . "\n";
            }

            $norm = normalizeText($text);
            $bill = extractBillPeriod($norm); // ['text','start','end']
            if (!$bill) {
                throw new RuntimeException("Bill Period not found in {$f['name']}");
            }

            // Parse document-wide metrics
            $tax_total = extractTaxTotal($norm);
            $discount_total = extractDiscountTotal($norm);

            // Parse per-connection subtotals (charges in detail region)
            $connSubtotals = extractConnectionSubtotals($norm);

            // Quick stats
            $cntConnections = count($connSubtotals);
            $sumConnections = array_sum($connSubtotals);

            slog("Parsed billPeriod={$bill['text']} start={$bill['start']} end={$bill['end']} connections={$cntConnections} connections_sum={$sumConnections} tax_total={$tax_total} discount_total={$discount_total}");

            // ----------------------- DB WRITE -----------------------
            mysqli_begin_transaction($conn);

            // Generate SR number before insert (unique per month)
            $sr_number = generate_sr_number_slt($conn);

            $original_name = mysqli_real_escape_string($conn, (string)$f['name']);
            $stored_path   = mysqli_real_escape_string($conn, (string)$relativeStored);
            $bill_text     = mysqli_real_escape_string($conn, (string)$bill['text']);
            $period_start  = mysqli_real_escape_string($conn, (string)$bill['start']); // YYYY-mm-dd
            $period_end    = mysqli_real_escape_string($conn, (string)$bill['end']);
            $uploader      = mysqli_real_escape_string($conn, (string)$uploader_hris);
            $sr_esc        = mysqli_real_escape_string($conn, (string)$sr_number);

            $sqlParent = "
                INSERT INTO tbl_admin_slt_monthly_data
                    (original_name, stored_path, bill_period_text, bill_period_start, bill_period_end, uploader_hris, sr_number)
                VALUES
                    ('$original_name', '$stored_path', '$bill_text', '$period_start', '$period_end', '$uploader', '$sr_esc')
            ";
            if (!mysqli_query($conn, $sqlParent)) {
                throw new RuntimeException('Insert parent failed: ' . mysqli_error($conn));
            }
            $upload_id = (int)mysqli_insert_id($conn);

            // Insert per-connection rows
            if ($cntConnections > 0) {
                $values = [];
                foreach ($connSubtotals as $connection_no => $subtotal) {
                    $connection_no_esc = mysqli_real_escape_string($conn, $connection_no);
                    $subtotal_num = number_format((float)$subtotal, 6, '.', ''); // decimal(24,6)
                    $values[] = "($upload_id, '$connection_no_esc', $subtotal_num)";
                }
                $sqlConn = "
                    INSERT INTO tbl_admin_slt_monthly_data_connections
                        (upload_id, connection_no, subtotal)
                    VALUES " . implode(",\n", $values);
                if (!mysqli_query($conn, $sqlConn)) {
                    throw new RuntimeException('Insert connections failed: ' . mysqli_error($conn));
                }
            }

            // Insert charges totals (unique per upload)
            $tax_num = number_format((float)$tax_total, 6, '.', '');
            $disc_num = number_format((float)$discount_total, 6, '.', '');
            $sqlCharges = "
                INSERT INTO tbl_admin_slt_monthly_data_charges
                    (upload_id, tax_total, discount_total)
                VALUES
                    ($upload_id, $tax_num, $disc_num)
            ";
            if (!mysqli_query($conn, $sqlCharges)) {
                throw new RuntimeException('Insert charges failed: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);

            // Build result
            $elapsed = round((microtime(true) - $startTime) * 1000);
            $results[] = [
                'file' => $f['name'],
                'ok' => true,
                'upload_id' => $upload_id,
                'sr_number' => $sr_number,
                'bill_period_text' => $bill['text'],
                'bill_period_start' => $bill['start'],
                'bill_period_end' => $bill['end'],
                'connection_count' => $cntConnections,
                'connections_sum' => (float)number_format($sumConnections, 6, '.', ''),
                'tax_total' => (float)$tax_num,
                'discount_total' => (float)$disc_num,
                'ms' => $elapsed
            ];
            slog("UPLOAD_ID=$upload_id SR=$sr_number OK in {$elapsed}ms");

        } catch (Throwable $e) {
            @mysqli_rollback($conn);
            slog("ERROR: " . $e->getMessage());
            $results[] = [
                'file' => $f['name'] ?? 'unknown',
                'ok' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    slog("FATAL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}



// ====================== HELPERS & PARSERS =========================

/**
 * Restructure $_FILES[nested] to flat array entries
 */
function restructureFilesArray(array $file): array {
    $result = [];
    if (!isset($file['name'])) return $result;
    if (is_array($file['name'])) {
        foreach ($file['name'] as $i => $name) {
            $result[] = [
                'name' => $name,
                'type' => $file['type'][$i] ?? '',
                'tmp_name' => $file['tmp_name'][$i] ?? '',
                'error' => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'][$i] ?? 0
            ];
        }
    } else {
        $result[] = $file;
    }
    return $result;
}

/**
 * Normalize raw PDF text to be layout-robust
 */
function normalizeText(string $text): string {
    // Replace non-breaking space and unicode dashes
    $text = str_replace(["\xC2\xA0", "\xE2\x80\x93", "\xE2\x80\x94"], [' ', '-', '-'], $text);
    // Normalize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Collapse crazy whitespace
    $lines = preg_split('/\n/', $text);
    $normLines = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/[ \t]+/', ' ', $line));
        if ($line === '') { $normLines[] = ''; continue; }
        // Remove stray page headers/footers noise tokens
        $line = preg_replace('/\s{2,}/', ' ', $line);
        $normLines[] = $line;
    }
    return implode("\n", $normLines);
}

/**
 * Extract Bill Period (text + start/end as Y-m-d)
 * Tolerant to newlines around the dash.
 */
function extractBillPeriod(string $norm): ?array {
    $flat = preg_replace('/\s+/', ' ', $norm);
    // Primary: "Bill Period 01/06/2025 - 30/06/2025"
    if (preg_match('/bill\s*period\s*:?\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4})/i', $flat, $m)) {
        $text = $m[1] . ' - ' . $m[2];
        return [
            'text' => $text,
            'start' => toMysqlDate($m[1]),
            'end'   => toMysqlDate($m[2]),
        ];
    }
    // Fallback: dates first then the words Bill Period near by (rare)
    if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4}).{0,40}bill\s*period/i', $flat, $m)) {
        $text = $m[1] . ' - ' . $m[2];
        return [
            'text' => $text,
            'start' => toMysqlDate($m[1]),
            'end'   => toMysqlDate($m[2]),
        ];
    }
    return null;
}

/**
 * Convert dd/mm/yyyy -> yyyy-mm-dd
 */
function toMysqlDate(string $dmy): string {
    [$d,$m,$y] = array_map('intval', explode('/', $dmy));
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

/**
 * Extract overall tax total by summing lines that look like taxes/levies/VAT
 */
function extractTaxTotal(string $norm): float {
    $sum = 0.0;
    foreach (explode("\n", $norm) as $line) {
        $l = trim($line);
        if ($l === '') continue;
        if (preg_match('/\b(vat|tax|taxes|levy|levies)\b/i', $l)) {
            $amt = lastAmountInLine($l);
            if ($amt !== null) { $sum += $amt; }
        }
    }
    return round($sum, 6);
}

/**
 * Extract overall discount total (as positive) by summing absolute discount amounts
 */
function extractDiscountTotal(string $norm): float {
    $sum = 0.0;
    foreach (explode("\n", $norm) as $line) {
        $l = trim($line);
        if ($l === '') continue;
        if (preg_match('/\b(discount|rebate)\b/i', $l)) {
            $amt = lastAmountInLine($l);
            if ($amt !== null) { $sum += abs($amt); }
        }
    }
    return round($sum, 6);
}

/**
 * Identify connection id
 * - Landline: 0 + 9..10 digits
 * - 94 + 9..11 digits
 * - Codes containing FTTH
 * - Generic uppercase codes with dashes (length gating)
 */
function isConnectionId(string $line): ?string {
    $cand = trim($line);
    if ($cand === '') return null;

    // Exact numeric forms
    if (preg_match('/\b0\d{9,10}\b/', $cand, $m)) return $m[0];
    if (preg_match('/\b94\d{9,11}\b/', $cand, $m)) return $m[0];

    // Contains FTTH
    if (preg_match('/\b[A-Z0-9][A-Z0-9\-_]*FTTH[A-Z0-9\-_]*\b/i', $cand, $m)) return strtoupper($m[0]);

    // Generic uppercase code with dashes (avoid very short)
    if (preg_match('/\b[A-Z]{2,5}-[A-Z0-9][A-Z0-9\-]{3,}\b/', strtoupper($cand), $m)) {
        return $m[0];
    }

    return null;
}

/**
 * Grabs the last numeric token in a line (handles commas and negatives)
 */
function lastAmountInLine(string $line): ?float {
    if (preg_match('/(-?\d{1,3}(?:,\d{3})*(?:\.\d+)?|-?\d+(?:\.\d+)?)(?=\s*$)/', $line, $m)) {
        $n = (float)str_replace(',', '', $m[1]);
        return $n;
    }
    return null;
}

/**
 * Extract per-connection subtotals by scanning from "Charges in detail"
 * until the end (tolerant to section shifts). We:
 * - start new block when a connection id line appears
 * - add any line ending with a number to the current block,
 *   skipping lines that are "Total/Subtotal" summaries to avoid double-counting
 */
function extractConnectionSubtotals(string $norm): array {
    $lines = explode("\n", $norm);

    // Find start index of "Charges in detail" (case-insensitive)
    $startIdx = 0;
    foreach ($lines as $i => $line) {
        if (preg_match('/charges\s+in\s+detail/i', $line)) {
            $startIdx = $i;
            break;
        }
    }

    $subtotals = [];
    $current = null;

    for ($i = $startIdx; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if ($line === '') continue;

        // New connection block?
        $conn = isConnectionId($line);
        if ($conn !== null) {
            $current = $conn;
            if (!isset($subtotals[$current])) $subtotals[$current] = 0.0;
            continue;
        }

        if ($current !== null) {
            // Skip obvious summary rows inside a block
            if (preg_match('/\b(total|subtotal|balance|carried forward)\b/i', $line)) {
                continue;
            }
            // If amount at end, add it
            $amt = lastAmountInLine($line);
            if ($amt !== null) {
                $subtotals[$current] += $amt;
            }
        }
    }

    // Round each subtotal to 6 dp
    foreach ($subtotals as $k => $v) {
        $subtotals[$k] = round((float)$v, 6);
    }
    return $subtotals;
}

/**
 * Generate SR number like SLTYYMM###### (monotonic per month)
 * Uses tbl_admin_slt_monthly_data as the source of truth.
 */
function generate_sr_number_slt(mysqli $conn): string {
    $prefix = 'SLT' . date('ym'); // e.g., SLT2508
    $q = "SELECT sr_number FROM tbl_admin_slt_monthly_data
          WHERE sr_number LIKE '{$prefix}%'
          ORDER BY sr_number DESC
          LIMIT 1";
    $res = mysqli_query($conn, $q);
    $next = 1;
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $last = $row['sr_number'];
        $tail = substr($last, strlen($prefix)); // ######
        $n = (int)$tail;
        $next = $n + 1;
    }
    return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}
