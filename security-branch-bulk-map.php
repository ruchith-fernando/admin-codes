<?php
// security-branch-bulk-map-firm2.php (plain PHP/text version)

require_once 'connections/connection.php';

// OPTIONAL: if you want userlog, uncomment:
// require_once 'includes/userlog.php';
// if (session_status() === PHP_SESSION_NONE) { session_start(); }
// $hris     = $_SESSION['hris'] ?? 'UNKNOWN';
// $username = $_SESSION['name'] ?? 'SYSTEM';

header('Content-Type: text/plain; charset=utf-8');

$firm_id   = 2;
$firm_name = 'AB SECURITAS (PVT) LIMITED';

// Branch list, cleaned: Title Case, no leading/trailing spaces
$inputBranches = [
    'Boralesgamuwa',
    'Dehiwala',
    'Embilibitiya',
    'Kegalle',
    'Kuliyapitiya',
    'Piliyandala',
    'Minuwangoda',
    'Batticaloa',
    'Katugastota',
    'Mathale',
    'Nittambuwa',
    'Nittambuwa',
    'Warakapola',
    'Rajagiriya',
    'Kaduruwela',
    'Pelmadulla',
    'Kotahena',
    'Panadura',
    'Mawathagama',
    'Aluthgama',
    'Ampara',
    'Nuwara Eliya',
    'Ragama',
    'Thalawathugoda Yd',
    'Anuradhapura',
    'Battaramulla',
    'Marawila',
    'Nittambuwa Property',
    'Moratuwa',
    'Kurunegala Yard',
];

$inserted          = [];
$already_correct   = [];
$updated_existing  = [];
$no_match          = [];
$ambiguous         = [];

foreach ($inputBranches as $cleanLabel) {
    $cleanLabel = trim($cleanLabel);
    if ($cleanLabel === '') continue;

    // Extra safety: normalize to Title Case & single spaces
    $normalized = preg_replace('/\s+/', ' ', strtolower($cleanLabel));
    $cleanLabel = ucwords($normalized);

    // Pattern for LIKE: "Thalawathugoda Yd" -> "%Thalawathugoda%Yd%"
    $pattern = '%' . preg_replace('/\s+/', '%', $cleanLabel) . '%';

    $stmt = $conn->prepare("
        SELECT DISTINCT branch_code, branch
        FROM tbl_admin_budget_security
        WHERE branch LIKE ?
        ORDER BY CAST(branch_code AS UNSIGNED), branch_code
    ");
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $res = $stmt->get_result();

    $matches = [];
    while ($row = $res->fetch_assoc()) {
        $matches[] = $row;
    }

    if (count($matches) === 1) {
        // ✅ Exactly one budget branch matched – safe to map
        $branch_code  = $matches[0]['branch_code'];
        $branch_name  = $matches[0]['branch'];

        // Check if there is already a mapping for this branch_code
        $stmtCheck = $conn->prepare("
            SELECT id, firm_id, branch_name, active
            FROM tbl_admin_branch_firm_map
            WHERE branch_code = ?
            LIMIT 1
        ");
        $stmtCheck->bind_param("s", $branch_code);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $existing = $resCheck->fetch_assoc();

        if ($existing) {
            if ((int)$existing['firm_id'] === $firm_id && $existing['active'] === 'yes') {
                // Already correct
                $already_correct[] = [
                    'clean'       => $cleanLabel,
                    'branch_code' => $branch_code,
                    'branch_name' => $branch_name,
                ];
            } else {
                // Update existing record to this firm + active=yes
                $upd = $conn->prepare("
                    UPDATE tbl_admin_branch_firm_map
                    SET firm_id = ?, branch_name = ?, active = 'yes'
                    WHERE id = ?
                ");
                $upd->bind_param("isi", $firm_id, $branch_name, $existing['id']);
                if ($upd->execute()) {
                    $updated_existing[] = [
                        'clean'       => $cleanLabel,
                        'branch_code' => $branch_code,
                        'branch_name' => $branch_name,
                        'old_firm_id' => $existing['firm_id'],
                    ];

                    // Optional log:
                    /*
                    userlog(sprintf(
                        "🔁 Bulk map updated -> firm_id=%d (%s) for branch_code=%s (%s)",
                        $firm_id, $firm_name, $branch_code, $branch_name
                    ));
                    */
                }
            }
        } else {
            // Insert new mapping
            $ins = $conn->prepare("
                INSERT INTO tbl_admin_branch_firm_map (branch_code, branch_name, firm_id, active)
                VALUES (?, ?, ?, 'yes')
            ");
            $ins->bind_param("ssi", $branch_code, $branch_name, $firm_id);
            if ($ins->execute()) {
                $inserted[] = [
                    'clean'       => $cleanLabel,
                    'branch_code' => $branch_code,
                    'branch_name' => $branch_name,
                ];

                // Optional log:
                /*
                userlog(sprintf(
                    "✅ Bulk map inserted -> firm_id=%d (%s) branch_code=%s (%s)",
                    $firm_id, $firm_name, $branch_code, $branch_name
                ));
                */
            }
        }

    } elseif (count($matches) === 0) {
        $no_match[] = $cleanLabel;

    } else {
        $ambiguous[] = [
            'clean'   => $cleanLabel,
            'matches' => $matches,
        ];
    }
}

// ----------- Plain text summary -----------
echo "Bulk Mapping to {$firm_name} (firm_id = {$firm_id})\n";
echo "=============================================\n\n";

echo "Inserted new mappings: " . count($inserted) . "\n";
foreach ($inserted as $r) {
    echo "  + {$r['clean']} -> [{$r['branch_code']}] {$r['branch_name']}\n";
}
echo "\n";

echo "Already correct mappings: " . count($already_correct) . "\n";
foreach ($already_correct as $r) {
    echo "  = {$r['clean']} -> [{$r['branch_code']}] {$r['branch_name']}\n";
}
echo "\n";

echo "Updated existing mappings to this firm: " . count($updated_existing) . "\n";
foreach ($updated_existing as $r) {
    echo "  ~ {$r['clean']} -> [{$r['branch_code']}] {$r['branch_name']} (old firm_id={$r['old_firm_id']})\n";
}
echo "\n";

echo "No match found: " . count($no_match) . "\n";
foreach ($no_match as $lbl) {
    echo "  ! {$lbl}\n";
}
echo "\n";

echo "Ambiguous matches (need manual check): " . count($ambiguous) . "\n";
foreach ($ambiguous as $item) {
    echo "  ? {$item['clean']} matched:\n";
    foreach ($item['matches'] as $m) {
        echo "       - [{$m['branch_code']}] {$m['branch']}\n";
    }
}
echo "\nDone.\n";
