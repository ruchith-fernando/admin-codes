<?php
// slt-branches-upload-process.php (robust header + delimiter handling)
// Reads CSV and inserts/updates tbl_admin_slt_branches

require_once __DIR__ . '/connections/connection.php'; // provides $con (mysqli)
date_default_timezone_set('Asia/Colombo');

$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/slt-branches-import.log';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }

function log_line($msg) {
    global $logFile;
    @file_put_contents($logFile, '['.date('Y-m-d H:i:s').'] ' . $msg . PHP_EOL, FILE_APPEND);
}

// --- Helpers ---------------------------------------------------------------

/** Normalize a header/cell: lowercase, trim, collapse spaces, strip punctuation-ish */
function norm($s) {
    // Remove UTF-8 BOM and non-breaking spaces
    $s = preg_replace('/^\xEF\xBB\xBF/', '', (string)$s);
    $s = str_replace("\xC2\xA0", ' ', $s); // NBSP
    $s = trim($s);

    // Lowercase
    $s = mb_strtolower($s, 'UTF-8');

    // Replace common punctuation with space/underscore
    $s = str_replace(['-', '.', '—', '–', '-'], ' ', $s); // hyphen-like
    $s = str_replace(['/', '\\', '|', ':'], ' ', $s);

    // Collapse whitespace -> single space
    $s = preg_replace('/\s+/', ' ', $s);

    // Also offer an underscore variant
    return $s;
}

/** Try to guess the delimiter from a sample line */
function guess_delimiter($line) {
    $candidates = [",", ";", "\t", "|"];
    $bestDelim = ",";
    $maxParts = 1;
    foreach ($candidates as $d) {
        $parts = str_getcsv($line, $d);
        if (count($parts) > $maxParts) { $maxParts = count($parts); $bestDelim = $d; }
    }
    return $bestDelim;
}

/** Read first non-empty CSV row as header; returns [delimiter, header array, handle positioned after header] */
function open_csv_and_read_header($path) {
    $fh = fopen($path, 'r');
    if (!$fh) { throw new Exception('Unable to open uploaded CSV'); }

    // Peek first non-empty line to guess delimiter
    $firstLine = '';
    while (($line = fgets($fh)) !== false) {
        if (trim($line) !== '') { $firstLine = $line; break; }
    }
    if ($firstLine === '') { throw new Exception('CSV appears empty'); }

    $delim = guess_delimiter($firstLine);

    // Rewind to start to re-read via fgetcsv with delimiter
    rewind($fh);

    // Advance to first non-empty record to use as header
    $header = false;
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        // Skip completely empty rows
        if (count(array_filter($row, fn($v)=>trim((string)$v) !== '')) === 0) { continue; }
        $header = $row; break;
    }
    if ($header === false) { throw new Exception('CSV appears empty'); }

    // Log raw header for diagnostics
    log_line('INFO: Detected delimiter="'. ($delim === "\t" ? "\\t" : $delim) .'" header_raw=' . json_encode($header, JSON_UNESCAPED_UNICODE));

    return [$delim, $header, $fh];
}

/** Find a column index by header synonyms; returns int|false */
function find_idx_by_synonyms($headerNorm, $synonyms) {
    foreach ($headerNorm as $i => $h) {
        foreach ($synonyms as $syn) {
            if ($h === $syn) return $i;
        }
    }
    // Try loose contains
    foreach ($headerNorm as $i => $h) {
        foreach ($synonyms as $syn) {
            if (strpos($h, $syn) !== false) return $i;
        }
    }
    return false;
}

/** Heuristic: find a "connection number" column (mostly digits / + / spaces / dashes) */
function guess_connection_col($rows) {
    $scores = [];
    foreach ($rows as $row) {
        foreach ($row as $i => $v) {
            $v = trim((string)$v);
            if ($v === '') continue;
            // score: numeric density
            $digits = preg_match_all('/[0-9]/', $v);
            $len = mb_strlen($v, 'UTF-8');
            if ($len === 0) continue;
            $ratio = $digits / $len;
            if (!isset($scores[$i])) $scores[$i] = 0;
            if ($ratio >= 0.5 && $len >= 6) $scores[$i] += 1;
        }
    }
    if (!$scores) return false;
    arsort($scores);
    $best = array_key_first($scores);
    return $best;
}

/** Heuristic: find an "allocated to" column (texty strings) different from conn col */
function guess_alloc_col($rows, $connIdx) {
    $scores = [];
    foreach ($rows as $row) {
        foreach ($row as $i => $v) {
            if ($i === $connIdx) continue;
            $v = trim((string)$v);
            if ($v === '') continue;
            // prefer non-numeric, longer text
            $digits = preg_match_all('/[0-9]/', $v);
            $len = mb_strlen($v, 'UTF-8');
            if (!isset($scores[$i])) $scores[$i] = 0;
            if ($digits === 0 && $len >= 3) $scores[$i] += 1;
            if ($len >= 6) $scores[$i] += 1;
        }
    }
    if (!$scores) return false;
    arsort($scores);
    $best = array_key_first($scores);
    return $best;
}

// --- Main ------------------------------------------------------------------

try {
    if (!isset($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        log_line('ERROR: No file uploaded');
        header('Location: slt-branches-upload.php?error=1'); exit;
    }

    $allowUpdate = isset($_POST['allow_update']) && $_POST['allow_update'] === 'yes';

    // Move uploaded file to a temp location
    $uploadName = 'slt_branches_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.csv';
    $uploadPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $uploadName;
    if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $uploadPath)) {
        log_line('ERROR: Failed to move uploaded file');
        header('Location: slt-branches-upload.php?error=1'); exit;
    }

    log_line("INFO: Starting import. File=$uploadPath allowUpdate=" . ($allowUpdate ? 'yes' : 'no'));

    // Open CSV and read header with delimiter detection
    [$delim, $header, $fh] = open_csv_and_read_header($uploadPath);

    // Normalize header names
    $headerNorm = array_map('norm', $header);
    log_line('INFO: header_norm=' . json_encode($headerNorm, JSON_UNESCAPED_UNICODE));

    // Synonyms (all should be normalized with norm())
    $connSynonyms = [
        'connection number','connection_number','connection no','connection no','connection no','connection',
        'conn no','conn number','service number','service no','telephone number','tel number','tel no',
        'account number','subscription number','line number','connection#','connection num','conn'
    ];
    $allocSynonyms = [
        'allocated to','allocated_to','allocated','location','branch','branch name','branch_name',
        'department','allocated at','assigned to','assigned_to','cost center','cost centre','site','office',
        'allocated branch','allocated location'
    ];

    $idxConn  = find_idx_by_synonyms($headerNorm, $connSynonyms);
    $idxAlloc = find_idx_by_synonyms($headerNorm, $allocSynonyms);

    // If not found, try guessing using first ~50 data rows
    if ($idxConn === false || $idxAlloc === false) {
        // Peek next N rows for heuristics without consuming them permanently
        $peekRows = [];
        $pos = ftell($fh);
        $n = 0;
        while (($row = fgetcsv($fh, 0, $delim)) !== false && $n < 50) {
            // Skip empty
            if (count(array_filter($row, fn($v)=>trim((string)$v) !== '')) === 0) { continue; }
            $peekRows[] = $row; $n++;
        }
        // Restore file pointer
        fseek($fh, $pos);

        if ($idxConn === false) { $idxConn = guess_connection_col($peekRows); }
        if ($idxAlloc === false && $idxConn !== false) { $idxAlloc = guess_alloc_col($peekRows, $idxConn); }

        log_line('INFO: heuristic idxConn=' . var_export($idxConn, true) . ' idxAlloc=' . var_export($idxAlloc, true));
    }

    if ($idxConn === false || $idxAlloc === false) {
        throw new Exception('CSV must contain "Connection Number" and "Allocated To" columns (or recognizable synonyms).');
    }

    $rowNum = 1; // header line read
    $inserted = 0; $updated = 0; $skipped = 0; $errors = 0;

    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        $rowNum++;
        // skip completely empty rows
        if (count(array_filter($row, fn($v)=>trim((string)$v) !== '')) === 0) { continue; }

        $connection_number = isset($row[$idxConn]) ? trim((string)$row[$idxConn]) : '';
        $allocated_to      = isset($row[$idxAlloc]) ? trim((string)$row[$idxAlloc]) : '';

        // Normalize cells
        $connection_number = preg_replace('/^\xEF\xBB\xBF/', '', $connection_number);
        $allocated_to      = preg_replace('/^\xEF\xBB\xBF/', '', $allocated_to);
        $connection_number = str_replace("\xC2\xA0", ' ', $connection_number);
        $allocated_to      = str_replace("\xC2\xA0", ' ', $allocated_to);
        $connection_number = trim($connection_number);
        $allocated_to      = trim($allocated_to);

        if ($connection_number === '' || $allocated_to === '') {
            $skipped++; log_line("WARN: Row $rowNum skipped (missing fields)"); continue;
        }

        // sanitize basic
        $connection_number = mysqli_real_escape_string($con, $connection_number);
        $allocated_to      = mysqli_real_escape_string($con, $allocated_to);

        if ($allowUpdate) {
            // insert or update
            $sql = "INSERT INTO tbl_admin_slt_branches (connection_number, allocated_to)
                    VALUES ('$connection_number', '$allocated_to')
                    ON DUPLICATE KEY UPDATE allocated_to = VALUES(allocated_to), updated_at = CURRENT_TIMESTAMP";
            $ok = mysqli_query($con, $sql);
            if ($ok) {
                // affected_rows: 1 insert, 2 update (sometimes 1 if same value; treat non-error as success)
                if (mysqli_affected_rows($con) === 1) { $inserted++; }
                else { $updated++; }
                log_line("OK: Row $rowNum upserted: conn=$connection_number alloc=$allocated_to");
            } else {
                $errors++; log_line("ERROR: Row $rowNum SQL error: " . mysqli_error($con) . " | SQL=$sql");
            }
        } else {
            // insert only (skip duplicates)
            $chk = mysqli_query($con, "SELECT id FROM tbl_admin_slt_branches WHERE connection_number = '$connection_number' LIMIT 1");
            if ($chk && mysqli_num_rows($chk) > 0) {
                $skipped++; log_line("INFO: Row $rowNum duplicate skipped: $connection_number");
                continue;
            }
            $sql = "INSERT INTO tbl_admin_slt_branches (connection_number, allocated_to)
                    VALUES ('$connection_number', '$allocated_to')";
            $ok = mysqli_query($con, $sql);
            if ($ok) {
                $inserted++; log_line("OK: Row $rowNum inserted: conn=$connection_number alloc=$allocated_to");
            } else {
                $errors++; log_line("ERROR: Row $rowNum SQL error: " . mysqli_error($con) . " | SQL=$sql");
            }
        }
    }

    fclose($fh);
    @unlink($uploadPath);

    log_line("INFO: Import done: inserted=$inserted updated=$updated skipped=$skipped errors=$errors");

    if ($errors > 0) {
        header('Location: slt-branches-upload.php?error=1'); exit;
    } else {
        header('Location: slt-branches-upload.php?success=1'); exit;
    }

} catch (Throwable $e) {
    log_line('FATAL: ' . $e->getMessage());
    header('Location: slt-branches-upload.php?error=1'); exit;
}
