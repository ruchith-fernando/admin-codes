<?php
// side-menu.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'connections/connection.php';

function hasAccess($conn, $hris_id, $menu_key){
    $res = mysqli_query(
        $conn,
        "SELECT 1 FROM tbl_admin_user_page_access
         WHERE hris_id='$hris_id'
           AND menu_key='$menu_key'
           AND is_allowed='yes'
         LIMIT 1"
    );
    return mysqli_num_rows($res) > 0;
}

$hris_id = $_SESSION['hris'] ?? '';

/* ✅ Mark completed modules here (menu group keys) */
$completedModules = [
//   'master',
//   'security',
//   'water',
//   'tea',
//   'postage',
//   'newspaper',
//   'telecom',
//   'photocopy',
//   'employee',
//   'admin',
//   'vehicle',
//   'securityvpn',
//   'printing',
    // // // 'courier', for demo 
    // // // 'staff-transport', for demo 
    // // // 'electricity',for demo 
  // If you want to mark these completed too, just uncomment:
  // 'electricity',
  // 'printing',
  // 'courier',
  // 'securityvpn',
  // 'staff-transport',
  // 'contracts',
  // 'document-printing',
  // 'sim',
  // 'barcode',
  // 'graphs',
  // 'stationary',
];

/* helper */
function isCompleted($key, $completedModules){
  return in_array($key, $completedModules, true);
}

/* ✅ One renderer for all top-level accordion headers */
function renderMenuButton($title, $targetId, $menuKey, $completedModules){
    $isDone = isCompleted($menuKey, $completedModules);
    $cls = "accordion-button collapsed text-white bg-dark" . ($isDone ? " menu-completed" : "");
    ?>
    <button class="<?= $cls ?>" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($targetId, ENT_QUOTES) ?>">
        <?= htmlspecialchars($title, ENT_QUOTES) ?>
        <?php if ($isDone): ?>
            <!-- <span class="menu-completed-badge">COMPLETED ✓</span> -->
        <?php endif; ?>
    </button>
    <?php
}
?>
<style>
/* highlight completed accordion headers */
.accordion-button.menu-completed{
  background: #f90606ff !important;   /* bootstrap success */
  color: #fff !important;
  font-weight: 700;
}
.accordion-button.menu-completed::after{
  filter: brightness(0) invert(1);  /* make chevron white */
}
.menu-completed-badge{
  font-size: .75rem;
  background: rgba(255,255,255,.18);
  border: 1px solid rgba(255,255,255,.35);
  padding: .1rem .45rem;
  border-radius: 999px;
  margin-left: .5rem;
}
</style>

<div class="d-flex flex-column" style="width: 100%;">
    <?php if(hasAccess($conn, $hris_id, 'dashboard')): ?>
    <a href="home-content.php" class="btn btn-danger btn-block mb-2 w-100"><i class="fas fa-home"></i> Dashboard</a>
    <?php endif; ?>

    <div class="accordion" id="sideMenuAccordion">
<?php
// ===================== MASTER FILE (WITH SUBMENUS) =====================

$masterSubMenus = [
    [
        'title' => 'Common Information',
        'id'    => 'collapseMasterCommon',
        'items' => [
            ['key'  => 'vendor-master', 'file' => 'vendor-master.php', 'label'=> 'Vendors (Master)'],
            ['key'=>'branch-water',        'file'=>'water-branch-master.php', 'label'=>'Update Branch Information'],
        ]
    ],
    [
        'title' => 'Water',
        'id'    => 'collapseMasterWater',
        'items' => [
            ['key'=>'water-types-master',  'file'=>'water-types-master.php',  'label'=>'Water - Types / Modes'],
            ['key'=>'water-vendor-map',    'file'=>'water-vendor-map.php',    'label'=>'Water - Map Types to Vendors'],
            ['key'=>'water-rate-profiles', 'file'=>'water-rate-profiles.php', 'label'=>'Water - Rate Profiles'],
            ['key'=>'branch-water-map',    'file'=>'water-branch-map.php',    'label'=>'Water - Branch ↔ Water Type Map'],
        ]
    ],
    [
        'title' => 'Photocopy',
        'id'    => 'collapseMasterPhotocopy',
        'items' => [
            ['key'=>'photocopy-budget',              'file'=>'upload-photocopy-budget.php',        'label'=>'Photocopy — Branch Budgets'],
            ['key'=>'photocopy-machines',            'file'=>'photocopy-machines-master.php',     'label'=>'Photocopy — Machines Master'],
            ['key'=>'photocopy-machine-assignments', 'file'=>'photocopy-machine-assignments.php', 'label'=>'Photocopy — Assign Machines to Branch'],
            ['key'=>'photocopy-rate-profiles',       'file'=>'photocopy-rate-profiles.php',      'label'=>'Photocopy — Rate Profiles'],
            ['key'=>'photocopy-rate-test',           'file'=>'photocopy-rate-test.php',          'label'=>'Photocopy — Rate Test'],
        ]
    ],
    [
        'title' => 'Security',
        'id'    => 'collapseMasterSecurity',
        'items' => [
            ['key'=>'security-branch-firm-map', 'file'=>'security-branch-firm-map.php', 'label'=>'Security - Add / Update Branch Information'],
        ]
    ],
    [
        'title' => 'Budget Uploads',
        'id'    => 'collapseBudgetUploads',
        'items' => [
            ['key'=>'upload-printing-budget',   'file'=>'upload-printing-budget.php',   'label'=>'Upload Budget - Printing & Stationery'],
            ['key'=>'upload-courier-budget',   'file'=>'upload-courier-budget.php',   'label'=>'Upload Budget - Courier'],
            ['key'=>'upload-security-vpn-budget',  'file'=>'upload-security-vpn-budget.php',  'label'=>'Upload Budget - Security VPN'],
        ]
    ],
    [
        'title' => 'Gifts & Stationary',
        'id'    => 'collapseGS',
        'items' => [
            ['key'=>'approval-chain-admin',   'file'=>'approval-chain-admin.php',   'label'=>'Create Approval Chain'],
            ['key'=>'approval-chain-list',   'file'=>'approval-chain-list.php',   'label'=>'View Approval Chain'],
            // ['key'=>'category-master',   'file'=>'category-master.php',   'label'=>'Category Master'],
            // ['key'=>'attribute-master',   'file'=>'attribute-master.php',   'label'=>'Attribute Master'],
            // ['key'=>'item-master-entry',   'file'=>'item-master-entry.php',   'label'=>'Item Master'],
            // ['key'=>'item-variant-entry',  'file'=>'item-variant-entry.php',  'label'=>'Item Variants (SKU)'],
            // ['key'=>'item-type-entry',            'file'=>'item-type-entry.php',            'label'=>'Item Types'],
            // ['key'=>'attribute-master-entry',     'file'=>'attribute-master-entry.php',     'label'=>'Attributes'],
            // ['key'=>'attribute-option-entry',     'file'=>'attribute-option-entry.php',     'label'=>'Attribute Options'],
            // ['key'=>'type-attribute-map-entry',   'file'=>'type-attribute-map-entry.php',   'label'=>'Type → Attribute Mapping'],
            // ['key'=>'gifts-approvals',            'file'=>'gifts-approvals.php',            'label'=>'Approvals (Maker/Checker)'],
        ]
    ],
];

// Check if user can see at least one master item
$masterVisible = false;
foreach ($masterSubMenus as $s) {
    foreach ($s['items'] as $it) {
        if (hasAccess($conn, $hris_id, $it['key'])) {
            $masterVisible = true;
            break 2;
        }
    }
}

if ($masterVisible):
?>
<div class="accordion-item bg-transparent border-0">
    <h2 class="accordion-header">
        <?php renderMenuButton('Master File', 'collapseMasterMain', 'master', $completedModules); ?>
    </h2>
    <div id="collapseMasterMain" class="accordion-collapse collapse" data-bs-parent="#sideMenuAccordion">
        <div class="accordion-body px-3 py-1">
            <div class="accordion" id="masterInnerAccordion">

                <?php foreach ($masterSubMenus as $submenu):
                    $submenuVisible = false;
                    foreach ($submenu['items'] as $it) {
                        if (hasAccess($conn, $hris_id, $it['key'])) {
                            $submenuVisible = true;
                            break;
                        }
                    }
                    if (!$submenuVisible) continue;
                ?>

                <div class="accordion-item bg-transparent border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed text-white bg-secondary" type="button"
                                data-bs-toggle="collapse" data-bs-target="#<?php echo $submenu['id']; ?>">
                            <?php echo $submenu['title']; ?>
                        </button>
                    </h2>
                    <div id="<?php echo $submenu['id']; ?>" class="accordion-collapse collapse"
                         data-bs-parent="#masterInnerAccordion">
                        <div class="accordion-body px-3 py-1">
                            <ul class="list-unstyled">
                                <?php foreach ($submenu['items'] as $item): ?>
                                    <?php if (hasAccess($conn, $hris_id, $item['key'])): ?>
                                        <li>
                                            <a href="<?php echo $item['file']; ?>" class="text-white">
                                                <?php echo $item['label']; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$allMenus = [
    'security' => [
        'title' => 'Security',
        'id' => 'collapseSecurity',
        'items' => [
            ['key'=>'security-budget-report',  'file'=>'security-budget-report.php',       'label'=>'View Security Budget'],
            ['key'=>'security-monthly-report', 'file'=>'security-monthly-report.php',     'label'=>'Security – Report & Monthly Data Entry'],
            ['key'=>'security-pending',        'file'=>'security-pending.php',            'label'=>'Pending, Reject, Approve - Security Entries'],
            ['key'=>'security-approved-list',  'file'=>'security-approved-downloads.php', 'label'=>'Download Approved - Security Entries'],
            ['key'=>'security-cost-report',    'file'=>'security-cost-report.php',        'label'=>'Report'],
        ]
    ],
    'electricity' => [
        'title' => 'Electricity',
        'id' => 'collapseElectricity',
        'items' => [
            ['key'=>'electricity-bills-upload',     'file'=>'upload-electricity-csv.php',     'label'=>'Upload Electricity Amounts and Usage'],
            ['key'=>'electricity-bills-useage',     'file'=>'electricity-monthly-report.php', 'label'=>'Electricity Bills and Usage'],
            ['key'=>'electricity-initial-entry',    'file'=>'electricity-initial-entry.php', 'label'=>'Initial Electricity Bill Entry'],
            ['key'=>'electricity-cheque-entry',     'file'=>'electricity-cheque-entry.php',  'label'=>'Cheque Details'],
            ['key'=>'electricity-full-report',      'file'=>'electricity-full-report.php',   'label'=>'Full Report - Monthly'],
            ['key'=>'electricity-budget-vs-actual', 'file'=>'electricity-overview.php',      'label'=>'Monthly Budget Vs Actual'],
        ]
    ],
    'printing' => [
        'title' => 'Printing & Stationary',
        'id' => 'collapsePrinting',
        'items' => [
            // ['key'=>'upload-printing-branches', 'file'=>'upload-printing-branches.php', 'label'=>'Upload Branch Details'],
            
            ['key'=>'upload-printing-actuals',  'file'=>'upload-printing-actuals.php',  'label'=>'Upload Actuals'],
            ['key'=>'printing-monthly-report',  'file'=>'printing-monthly-report.php',  'label'=>'Printing & Stationary Usage'],
            ['key'=>'printing-overview',        'file'=>'printing-overview.php',        'label'=>'Monthly Budget VS Actuals'],
        ]
    ],
    'vehicle' => [
        'title' => 'Vehicle Service & Maintenance',
        'id' => 'collapseVehicle',
        'items' => [
            ['key'=>'vehicle-information',             'file'=>'vehicle-information.php',      'label'=>'Create Vehicle Information'],
            ['key'=>'vehicle-approval-panel',          'file'=>'vehicle-approval-panel.php',   'label'=>'Verify and Approve'],
            ['key'=>'view-vehicle-information',        'file'=>'view-vehicle-information.php', 'label'=>'View Vehicle Information'],
            ['key'=>'vehicle-maintenance',             'file'=>'vehicle-maintenance.php',      'label'=>'Add Vehicle Maintenance Details'],
            ['key'=>'vehicle-approvals',               'file'=>'vehicle-approvals-pro.php',    'label'=>'Pending Vehicle Maintenance Approvals'],
            ['key'=>'vehicle-history',                 'file'=>'vehicle-history.php',          'label'=>'Vehicle History'],
            ['key'=>'vehicle-budget-vs-actual',        'file'=>'vehicle-budget-vs-actual.php', 'label'=>'Budget VS Actual'],
            ['key'=>'vehicle-maintenance-report',      'file'=>'repair-records.php',           'label'=>'Vehicle Maintenance Report'],
            ['key'=>'vehicle-budget-vs-actual-report', 'file'=>'vehicle-budget-vs-actual.php', 'label'=>'Vehicle Budget vs Actual Report'],
        ]
    ],
];

foreach($allMenus as $menuKey => $menu) {
    $visible = false;
    foreach($menu['items'] as $item){
        if(hasAccess($conn, $hris_id, $item['key'])){ $visible = true; break; }
    }
    if($visible):
?>
    <div class="accordion-item bg-transparent border-0">
        <h2 class="accordion-header">
            <?php renderMenuButton($menu['title'], $menu['id'], $menuKey, $completedModules); ?>
        </h2>
        <div id="<?php echo $menu['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#sideMenuAccordion">
            <div class="accordion-body px-3 py-1">
                <ul class="list-unstyled">
                    <?php foreach($menu['items'] as $item): ?>
                        <?php if(hasAccess($conn, $hris_id, $item['key'])): ?>
                            <li><a href="<?php echo $item['file']; ?>" class="text-white"><?php echo $item['label']; ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php
    endif;
}
?>

<!-- ========================================================= -->
<!-- TELECOMMUNICATION                                          -->
<!-- ========================================================= -->
<?php
$telecomSubMenus = [
    [
        'title' => 'Dialog Mobile',
        'id'    => 'collapseTelecomDialog',
        'items' => [
            ['key'=>'upload-employee-data',  'file'=>'upload-employee-data.php',  'label'=>'Upload Staff Info - Active'],
            ['key'=>'upload-issues',         'file'=>'upload-issues.php',         'label'=>'Upload New Dialog Issues'],
            ['key'=>'assign-mobile',         'file'=>'assign-mobile.php',         'label'=>'Assign New HRIS -> Connection'],
            ['key'=>'mobile-allocation-entry', 'file'=>'mobile-allocation-entry.php', 'label'=>'Issue New Dialog Connection'],
            ['key'=>'mobile-allocation-pending', 'file'=>'mobile-allocation-pending.php', 'label'=>'Pending New Dialog Connection'],
            ['key'=>'upload-invoice',        'file'=>'upload-invoice.php',        'label'=>'Upload Dialog Invoice'],
            ['key'=>'update-contribution',   'file'=>'update-contribution.php',   'label'=>'Update Company Contribution - Individual'],
            ['key'=>'view-employee-details', 'file'=>'view-employee-details.php', 'label'=>'View Staff Information'],
        ]
    ],
    [
        'title' => 'SLT Fixed Line',
        'id'    => 'collapseTelecomSLT',
        'items' => [
            ['key'=>'convert-slt',     'file'=>'html-to-single-page-pdf.php', 'label'=>'Convert HTM to PDF'],
            ['key'=>'slt-upload-cdma', 'file'=>'slt-upload-form.php',         'label'=>'Upload SLT Monthly Bill'],
        ]
    ],
    [
        'title' => 'CDMA',
        'id'    => 'collapseTelecomCDMA',
        'items' => [
            ['key'=>'cdma-upload', 'file'=>'cdma-upload-form.php', 'label'=>'Upload CDMA Monthly Dialog Bill'],
        ]
    ],
    [
        'title' => 'Reports',
        'id'    => 'collapseTelecomReports',
        'items' => [
            ['key'=>'mobile-bill-report',         'file'=>'hr-report-dialog.php',            'label'=>'Dialog Bill Report - HR'],
            ['key'=>'mobile-bill-report-finance', 'file'=>'finance-report-dialog.php',       'label'=>'Dialog Bill Report - Finance'],
            ['key'=>'cdma-report',                'file'=>'cdma-report.php',                 'label'=>'CDMA - Report'],
            ['key'=>'cdma-department-report',     'file'=>'cdma-report-group-by-suffix.php', 'label'=>'CDMA Report - Group by Department'],
            ['key'=>'cdma-contract-report',       'file'=>'cdma-report-contract.php',        'label'=>'CDMA Report - Group by Contract'],
            ['key'=>'slt-report',                 'file'=>'slt-report.php',                  'label'=>'SLT Report'],
            ['key'=>'slt-branch-report',          'file'=>'slt-branch-report.php',           'label'=>'SLT Report - Group by Branch'],
            ['key'=>'telecom-report',             'file'=>'telephone-budget-vs-actual.php',  'label'=>'Telecominication - Budget VS Actuals'],
        ]
    ],
];

$telecomVisible = false;
foreach ($telecomSubMenus as $s) {
    foreach ($s['items'] as $it) {
        if (hasAccess($conn, $hris_id, $it['key'])) { $telecomVisible = true; break 2; }
    }
}

if ($telecomVisible):
?>
<div class="accordion-item bg-transparent border-0">
    <h2 class="accordion-header">
        <?php renderMenuButton('Telecommunication', 'collapseTelecom', 'telecom', $completedModules); ?>
    </h2>
    <div id="collapseTelecom" class="accordion-collapse collapse" data-bs-parent="#sideMenuAccordion">
        <div class="accordion-body px-3 py-1">
            <div class="accordion" id="telecomInnerAccordion">

                <?php foreach ($telecomSubMenus as $submenu):
                    $submenuVisible = false;
                    foreach ($submenu['items'] as $it) {
                        if (hasAccess($conn, $hris_id, $it['key'])) { $submenuVisible = true; break; }
                    }
                    if (!$submenuVisible) continue;
                ?>

                <div class="accordion-item bg-transparent border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed text-white bg-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $submenu['id']; ?>">
                            <?php echo $submenu['title']; ?>
                        </button>
                    </h2>
                    <div id="<?php echo $submenu['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#telecomInnerAccordion">
                        <div class="accordion-body px-3 py-1">
                            <ul class="list-unstyled">
                                <?php foreach ($submenu['items'] as $item): ?>
                                    <?php if (hasAccess($conn, $hris_id, $item['key'])): ?>
                                        <li><a href="<?php echo $item['file']; ?>" class="text-white"><?php echo $item['label']; ?></a></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================= -->
<!-- TEA SERVICE                                                -->
<!-- ========================================================= -->
<?php
$teaSubMenus = [
    [
        'title' => 'Tea Service - Head Office',
        'id'    => 'collapseTeaHO',
        'items' => [
            ['key'=>'tea-service', 'file'=>'tea-service.php', 'label'=>'Enter Tea Service - Head Office'],
            ['key'=>'tea-pending', 'file'=>'tea-pending.php', 'label'=>'Pending, Reject, Approve - Tea Entries'],
        ]
    ],
    [
        'title' => 'Tea Service - Branches',
        'id'    => 'collapseTeaBranches',
        'items' => [
            ['key'=>'upload-tea-branches-actuals', 'file'=>'upload-tea-branch-actuals.php', 'label'=>'Upload Actuals'],
        ]
    ],
    [
        'title' => 'Reports',
        'id'    => 'collapseTeaBranchesReports',
        'items' => [
            ['key'=>'report-tea-branch',     'file'=>'report-tea-branch.php',     'label'=>'Report - Tea (Branches)'],
            ['key'=>'tea-budget-vs-actual',  'file'=>'tea-budget-vs-actual.php',  'label'=>'Tea Service Budget VS Actuals - Head Office'],
            ['key'=>'tea-branches-overview', 'file'=>'tea-branches-overview.php', 'label'=>'Tea Service Budget VS Actuals - Branches'],
        ]
    ]
];

$teaVisible = false;
foreach ($teaSubMenus as $s) {
    foreach ($s['items'] as $it) {
        if (hasAccess($conn, $hris_id, $it['key'])) { $teaVisible = true; break 2; }
    }
}

if ($teaVisible):
?>
<div class="accordion-item bg-transparent border-0">
    <h2 class="accordion-header">
        <?php renderMenuButton('Tea Service', 'collapseTeaMain', 'tea', $completedModules); ?>
    </h2>
    <div id="collapseTeaMain" class="accordion-collapse collapse" data-bs-parent="#sideMenuAccordion">
        <div class="accordion-body px-3 py-1">
            <div class="accordion" id="teaInnerAccordion">

                <?php foreach ($teaSubMenus as $submenu):
                    $submenuVisible = false;
                    foreach ($submenu['items'] as $it) {
                        if (hasAccess($conn, $hris_id, $it['key'])) { $submenuVisible = true; break; }
                    }
                    if (!$submenuVisible) continue;
                ?>

                <div class="accordion-item bg-transparent border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed text-white bg-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $submenu['id']; ?>">
                            <?php echo $submenu['title']; ?>
                        </button>
                    </h2>
                    <div id="<?php echo $submenu['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#teaInnerAccordion">
                        <div class="accordion-body px-3 py-1">
                            <ul class="list-unstyled">
                                <?php foreach ($submenu['items'] as $item): ?>
                                    <?php if (hasAccess($conn, $hris_id, $item['key'])): ?>
                                        <li><a href="<?php echo $item['file']; ?>" class="text-white"><?php echo $item['label']; ?></a></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================= -->
<!-- REMAINING MENUS START HERE                                  -->
<!-- ========================================================= -->
<?php
$remainingMenus = [
    'water' => [
        'title' => 'Water',
        'id'    => 'collapseWater',
        'items' => [
            ['key'=>'water-monthly-report',        'file'=>'water-monthly-report.php',        'label'=>'Water Bills and Usage'],
            ['key'=>'water-approval',              'file'=>'water-pending.php',               'label'=>'Pending, Reject, Approve - Water Bills'],
            ['key'=>'water-approved-list',         'file'=>'water-approved-downloads.php',    'label'=>'Download Approved - Water Bills'],
            ['key'=>'branch-water-monthly-report', 'file'=>'branch-water-monthly-report.php', 'label'=>'Water Bills and Usage - Branch'],
            ['key'=>'water-overview',              'file'=>'water-overview.php',              'label'=>'Monthly Budget VS Actuals'],
        ]
    ],
    'photocopy' => [
        'title' => 'Photocopies',
        'id' => 'collapsePhotoCopy',
        'items' => [
            ['key'=>'upload-photocopy-actuals',       'file'=>'upload-photocopy-actuals.php',       'label'=>'Upload Actuals - Photocopy'],
            ['key'=>'photocopy-actuals-manual-entry', 'file'=>'photocopy-actuals-manual-entry.php', 'label'=>'Photocopy — Manual Entry'],
            ['key'=>'photocopy-upload-report',        'file'=>'photocopy-upload-batches.php',       'label'=>'View - Error / Success Upload Report '],
            ['key'=>'photocopy-monthly-report-new',   'file'=>'photocopy-monthly-report.php',       'label'=>'Photocopy — Monthly Report'],
            ['key'=>'photocopy-overview',             'file'=>'photocopy-overview.php',             'label'=>'Monthly Budget VS Actuals'],
        ]
    ],
    'courier' => [
        'title' => 'Courier',
        'id'    => 'collapseCourier',
        'items' => [
            ['key'=>'upload-courier-branches', 'file'=>'upload-courier-branches.php', 'label'=>'Upload Branch Details'],
            ['key'=>'upload-courier-actuals',  'file'=>'upload-courier-actuals.php',  'label'=>'Upload Actuals'],
            ['key'=>'courier-monthly-report',  'file'=>'courier-monthly-report.php',  'label'=>'Courier Monthly Cost'],
            ['key'=>'courier-overview',        'file'=>'courier-overview.php',        'label'=>'Monthly Budget VS Actuals'],
        ]
    ],
    'securityvpn' => [
        'title' => 'Security VPN',
        'id' => 'collapseSecurityVPN',
        'items' => [
            
            ['key'=>'upload-security-vpn-actuals', 'file'=>'upload-security-vpn-actuals.php', 'label'=>'Upload Actuals'],
            ['key'=>'security-vpn-monthly-report', 'file'=>'security-vpn-monthly-report.php', 'label'=>'Security VPN Usage'],
            ['key'=>'security-vpn-overview',       'file'=>'security-vpn-overview.php',       'label'=>'Monthly Budget VS Actuals'],
        ]
    ],
    'staff-transport' => [
        'title' => 'Staff Transport',
        'id'    => 'collapseStaffTransport',
        'items' => [
            ['key'=>'staff-transport-entry',                 'file'=>'staff-transport-entry.php',                 'label'=>'Staff Transport - Kangaroo'],
            ['key'=>'upload-pickme',                         'file'=>'upload-pickme.php',                         'label'=>'Staff Transport - PickMe'],
            ['key'=>'staff-transport-report',                'file'=>'staff-transport-report.php',                'label'=>'Staff Transport - Report'],
            ['key'=>'staff-transport-budget-vs-actual-report','file'=>'staff-transport-budget-vs-actual-report.php','label'=>'Budget VS Actuals'],
        ]
    ],
    'newspaper' => [
        'title' => 'Newspaper',
        'id'    => 'collapseNewsPaper',
        'items' => [
            ['key'=>'upload-newspaper-actuals', 'file'=>'upload-newspaper-branch-actuals.php', 'label'=>'Upload Actuals - Newspaper'],
            ['key'=>'newspaper-monthly-report', 'file'=>'report-newspaper-branch.php',         'label'=>'Report - Newspaper (Branches)'],
            ['key'=>'newspaper-overview',       'file'=>'newspaper-overview.php',              'label'=>'Monthly Budget VS Actuals'],
        ]
    ],
    'postage' => [
        'title' => 'Postage & Stamps',
        'id' => 'collapsePostageStamps',
        'items' => [
            ['key'=>'upload-postage-branch-actuals', 'file'=>'upload-postage-branch-actuals.php', 'label'=>'Upload Actuals - Postage'],
            ['key'=>'report-postage-stamps-branch',  'file'=>'report-postage-stamps-branch.php',  'label'=>'Report - Postage & Stamps (Branches)'],
            ['key'=>'postage-budget-vs-actual',      'file'=>'postage-budget-vs-actual.php',      'label'=>'Postage & Stamps - Budget VS Actuals'],
        ]
    ],
    'employee' => [
        'title' => 'Employee Information',
        'id' => 'collapseEmployeeInformation',
        'items' => [
            ['key'=>'employee-directory', 'file'=>'employee-directory.php', 'label'=>'Employee Information']
        ]
    ],
];

$remainingMenus += [
    'contracts' => [
        'title' => 'Contracts',
        'id' => 'collapseContracts',
        'items' => [
            ['key'=>'branch-contracts-report', 'file'=>'branch-contracts-report.php', 'label'=>'View Branch Contracts']
        ]
    ],
    'document-printing' => [
        'title' => 'Document Printing - Branches',
        'id' => 'collapseDocumentPrinting',
        'items' => [
            ['key'=>'upload-secure-doc', 'file'=>'upload-secure-doc.php', 'label'=>'Upload Document'],
            ['key'=>'secure-documents',  'file'=>'secure-documents.php',  'label'=>'Document Printing'],
            ['key'=>'view-print-logs',   'file'=>'view-print-logs.php',   'label'=>'Print Log'],
        ]
    ],
    'sim' => [
        'title' => 'SIM / Transfer / Phone Requests',
        'id' => 'collapseSIM',
        'items' => [
            ['key'=>'sim-request',       'file'=>'sim-request.php',       'label'=>'Submit Request / Check Status'],
            ['key'=>'recommend-request', 'file'=>'recommend-request.php', 'label'=>'Recommend Requests'],
            ['key'=>'approve-request',   'file'=>'approve-request.php',   'label'=>'Approve Requests'],
            ['key'=>'manage-requests',   'file'=>'manage-requests.php',   'label'=>'Manage Requests'],
            ['key'=>'request-status',    'file'=>'request-status.php',    'label'=>'Request Status'],
            ['key'=>'view-all-requests', 'file'=>'view-all-requests.php', 'label'=>'View All Requests'],
        ]
    ],
    'admin' => [
        'title' => 'Admin',
        'id' => 'collapseFULLBackup',
        'items' => [
            ['key'=>'register',               'file'=>'register.php',               'label'=>'Register User'],
            ['key'=>'edit-user',              'file'=>'edit-user.php',              'label'=>'Edit Registered User'],
            ['key'=>'user-access-management', 'file'=>'user-access-management.php', 'label'=>'User Access'],
            ['key'=>'add-menu-key',           'file'=>'add-menu-key.php',           'label'=>'Add Menu Key'],
            ['key'=>'full-backup',            'file'=>'full-backup.php',            'label'=>'System Full Backup'],
            ['key'=>'ip-audit',               'file'=>'request-audit-report.php',   'label'=>'IP Audit'],
            ['key'=>'user-logs',              'file'=>'user-log-report.php',        'label'=>'User Log Report'],
            ['key'=>'error-logs',             'file'=>'error-log-report.php',       'label'=>'Error Log Report'],
        ]
    ],
    'barcode' => [
        'title' => 'Bar Code - Label Generator',
        'id'    => 'collapseBarCode',
        'items' => [
            ['key'=>'label-generator', 'file'=>'outgoing-mail-labels.php', 'label'=>'Generate Labels']
        ]
    ],

    /* ✅ GRAPHS — updated with Code 1 new elements */
    'graphs' => [
        'title' => 'Graphs',
        'id'    => 'collapseGraphs',
        'items' => [
            ['key'=>'staff-transport-graph-report', 'file'=>'staff-transport-graph-report.php', 'label'=>'Staff Transport - Budget VS Actuals Graph'],
            ['key'=>'telecom-graph',                'file'=>'telephone-graph-report.php',       'label'=>'Telecominication - Budget VS Actuals Graph'],

            // UPDATED + ADDED (from Code 1)
            ['key'=>'tea-chart',               'file'=>'tea-ho-graph-report.php',       'label'=>'Tea Service Head Office - Budget VS Actuals Graph'],
            ['key'=>'tea-branch-graph-report', 'file'=>'tea-branch-graph-report.php',   'label'=>'Tea Service Branch - Budget VS Actuals Graph'],
            ['key'=>'ajax-tea-all-chart',      'file'=>'tea-all-graph-report.php',      'label'=>'Tea Service All - Budget VS Actuals Graph'],
            ['key'=>'newspaper-graph-report',      'file'=>'newspaper-graph-report.php','label'=>'Newspaper - Budget VS Actuals Graph'],
            ['key'=>'electricity-graph-report', 'file'=>'electricity-graph-report.php', 'label'=>'Electricity - Budget VS Actuals Graph'],
            ['key'=>'security-graph-report',    'file'=>'security-graph-report.php',    'label'=>'Security - Budget VS Actuals Graph'],
            ['key'=>'vehicle-graph-report',     'file'=>'vehicle-graph-report.php',     'label'=>'Vehicle - Budget VS Actuals Graph'],
            ['key'=>'photocopy-graph-report',   'file'=>'photocopy-graph-report.php',   'label'=>'Photocopy - Budget VS Actuals Graph'],
            ['key'=>'postage-graph-report',     'file'=>'postage-graph-report.php',     'label'=>'Postage - Budget VS Actuals Graph'],
            ['key'=>'water-graph-report',       'file'=>'water-graph-report.php',       'label'=>'Water - Budget VS Actuals Graph'],
            ['key'=>'printing-chart',           'file'=>'printing-chart.php',           'label'=>'Printing & Stationary - Budget VS Actuals Graph'],
            ['key'=>'courier-chart',            'file'=>'courier-chart.php',            'label'=>'Courier - Budget VS Actuals Graph'],
            ['key'=>'security-vpn-chart',       'file'=>'security-vpn-chart.php',       'label'=>'Security VPN - Budget VS Actuals Graph'],
        ]
    ],

    'stationary' => [
        'title' => 'Printing & Stationary',
        'id' => 'collapseStationary',
        'items' => [
            ['key'=>'stock-in',                    'file'=>'stock-in.php',                    'label'=>'Stocks In'],
            ['key'=>'stock-out',                   'file'=>'stock-out.php',                   'label'=>'Stocks Out'],
            ['key'=>'stock-out-approval',          'file'=>'stock-out-approval.php',          'label'=>'Approve Stocks Out'],
            ['key'=>'stock-ledger-report',         'file'=>'stock-ledger-report.php',         'label'=>'Stock Ledger Report'],
            ['key'=>'monthly-stock-report',        'file'=>'monthly-stock-report.php',        'label'=>'Monthly Stock Report'],
            ['key'=>'budget-vs-actual-stationary', 'file'=>'budget-vs-actual-stationary.php', 'label'=>'Monthly Budget Vs Actual'],
            ['key'=>'stationary-stock-in',         'file'=>'stationary-stock-in.php',         'label'=>'Test Stock'],
            ['key'=>'stationary-request',          'file'=>'stationary-request.php',          'label'=>'Test Request'],
            ['key'=>'boic-requests',               'file'=>'boic-requests.php',               'label'=>'BOIC Approval'],
            ['key'=>'approval-orders',             'file'=>'approval-orders.php',             'label'=>'Store Keeper'],
        ]
    ],
    'assets' => [
        'title' => 'Gifts & Stationary',
        'id' => 'collapseAssets',
        'items' => [
            ['key'=>'requisition-card',                    'file'=>'requisition-card.php',                    'label'=>'Creating New Requisition'],
            ['key'=>'asset-card',                    'file'=>'asset-card.php',                    'label'=>'Creating New Asset Card'],
            ['key'=>'asset-card-print',                    'file'=>'asset-card-print.php','label'=>'Print Bar Codes'],
            ['key'=>'asset-variant-stock',                    'file'=>'asset-variant-stock.php','label'=>'Map Asset to Variant'],
        ]
    ],
];

/* RENDER REMAINING MENUS */
foreach($remainingMenus as $menuKey => $menu) {
    $visible = false;
    foreach($menu['items'] as $item){
        if(hasAccess($conn, $hris_id, $item['key'])){ $visible = true; break; }
    }
    if($visible):
?>
<div class="accordion-item bg-transparent border-0">
    <h2 class="accordion-header">
        <?php renderMenuButton($menu['title'], $menu['id'], $menuKey, $completedModules); ?>
    </h2>
    <div id="<?php echo $menu['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#sideMenuAccordion">
        <div class="accordion-body px-3 py-1">
            <ul class="list-unstyled">
                <?php foreach($menu['items'] as $item): ?>
                    <?php if(hasAccess($conn, $hris_id, $item['key'])): ?>
                        <li><a href="<?php echo $item['file']; ?>" class="text-white"><?php echo $item['label']; ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php
    endif;
}
?>

    </div> <!-- end accordion -->
</div> <!-- end container -->

<script>
if (window.history.replaceState) {
    const url = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.replaceState({path: url}, '', url);
}
document.querySelector('.sidebar')?.addEventListener('contextmenu', function (e) {
    e.preventDefault();
});
</script>
