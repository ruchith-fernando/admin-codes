<?php
// branch-water.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';

function esc($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<div class="content font-size">
<div class="container-fluid mt-4">

<div class="card shadow bg-white rounded p-4">

    <h5 class="text-primary mb-3">Water — Branch Master</h5>

    <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
    </div>

    <div class="mb-3">
        <button class="btn btn-primary" id="btnAddBranch">
            ➕ Add New Branch
        </button>
    </div>

    <div id="branch_alert"></div>

    <div id="branch_table_container" class="table-responsive mt-3">
        <div class="text-center text-muted py-4">Loading...</div>
    </div>

</div>
</div>
</div>

<!-- ADD BRANCH MODAL -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
 <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">

    <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Add Branch</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <form id="addBranchForm">
        <div class="modal-body">

            <div class="row g-3">
                
                <div class="col-md-4">
                    <label class="form-label">Branch Code</label>
                    <input type="text" class="form-control" name="branch_code" required maxlength="10">
                </div>

                <div class="col-md-8">
                    <label class="form-label">Branch Name</label>
                    <input type="text" class="form-control" name="branch_name" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" class="form-control" name="vendor_name">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Water Type</label>
                    <select name="water_type" class="form-select water_type_select" required>
                        <option value="">Select...</option>
                        <option value="NWSDB">NWSDB</option>
                        <option value="BOTTLE">BOTTLE</option>
                        <option value="MACHINE">MACHINE</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Account Number (optional)</label>
                    <input type="text" class="form-control" name="account_number">
                </div>
            </div>

            <!-- BOTTLE SECTION -->
            <div id="add_bottle_section" class="mt-4 border rounded p-3 bg-light d-none">
                <h6 class="text-primary">Bottle Settings</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Bottle Rate</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="bottle_rate">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cooler Rental</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="cooler_rental_rate">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">SSCL %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="sscl_percentage">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">VAT %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="vat_percentage">
                    </div>
                </div>
            </div>

            <!-- MACHINE SECTION -->
            <div id="add_machine_section" class="mt-4 border rounded p-3 bg-light d-none">
                <h6 class="text-primary">Machine Settings</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">No. of Machines</label>
                        <input type="number" min="1" class="form-control" name="no_of_machines">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monthly Charge (per machine)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="monthly_charge">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">SSCL %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="sscl_percentage_m">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">VAT %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="vat_percentage_m">
                    </div>
                </div>
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Branch</button>
        </div>
    </form>

 </div></div>
</div>


<!-- EDIT BRANCH MODAL -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
 <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">

    <div class="modal-header bg-warning text-black">
        <h5 class="modal-title">Edit Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>

    <form id="editBranchForm">
        <div class="modal-body">

            <input type="hidden" name="id" id="edit_id">

            <div class="row g-3">
                
                <div class="col-md-4">
                    <label class="form-label">Branch Code</label>
                    <input type="text" class="form-control" name="branch_code" id="edit_branch_code" required maxlength="10">
                </div>

                <div class="col-md-8">
                    <label class="form-label">Branch Name</label>
                    <input type="text" class="form-control" name="branch_name" id="edit_branch_name" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" class="form-control" name="vendor_name" id="edit_vendor_name">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Water Type</label>
                    <select name="water_type" id="edit_water_type" class="form-select water_type_select" required>
                        <option value="">Select...</option>
                        <option value="NWSDB">NWSDB</option>
                        <option value="BOTTLE">BOTTLE</option>
                        <option value="MACHINE">MACHINE</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Account Number</label>
                    <input type="text" class="form-control" name="account_number" id="edit_account_number">
                </div>
            </div>

            <!-- BOTTLE EDIT SECTION -->
            <div id="edit_bottle_section" class="mt-4 border rounded p-3 bg-light d-none">
                <h6 class="text-primary">Bottle Settings</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Bottle Rate</label>
                        <input type="number" step="0.01" min="0" name="bottle_rate" id="edit_bottle_rate" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cooler Rental</label>
                        <input type="number" step="0.01" min="0" name="cooler_rental_rate" id="edit_cooler_rental_rate" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">SSCL %</label>
                        <input type="number" step="0.01" min="0" name="sscl_percentage" id="edit_sscl_percentage" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">VAT %</label>
                        <input type="number" step="0.01" min="0" name="vat_percentage" id="edit_vat_percentage" class="form-control">
                    </div>
                </div>
            </div>

            <!-- MACHINE EDIT SECTION -->
            <div id="edit_machine_section" class="mt-4 border rounded p-3 bg-light d-none">
                <h6 class="text-primary">Machine Settings</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">No. of Machines</label>
                        <input type="number" min="1" name="no_of_machines" id="edit_no_of_machines" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monthly Charge (per machine)</label>
                        <input type="number" step="0.01" min="0" name="monthly_charge" id="edit_monthly_charge" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">SSCL %</label>
                        <input type="number" step="0.01" min="0" name="sscl_percentage_m" id="edit_sscl_percentage_m" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">VAT %</label>
                        <input type="number" step="0.01" min="0" name="vat_percentage_m" id="edit_vat_percentage_m" class="form-control">
                    </div>
                </div>
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Update Branch</button>
        </div>
    </form>

 </div></div>
</div>


<script>
(function(){

    const addModal = new bootstrap.Modal('#addBranchModal');
    const editModal = new bootstrap.Modal('#editBranchModal');

    // Load initial table
    loadBranchTable();


    function loadBranchTable() {
        document.getElementById("branch_table_container").innerHTML =
            "<div class='text-center p-4 text-muted'>Loading...</div>";

        fetch("branch-water-load.php")
        .then(r => r.text())
        .then(html => {
            document.getElementById("branch_table_container").innerHTML = html;
        });
    }


    // Show ADD modal
    document.getElementById("btnAddBranch").addEventListener("click", () => {
        resetAddForm();
        addModal.show();
    });


    function resetAddForm() {
        document.getElementById("addBranchForm").reset();
        hideSections("add");
    }


    // SELECT2
    document.querySelectorAll(".water_type_select").forEach(e=>{
        $(e).select2({ dropdownParent: $(e).closest(".modal") });
    });


    // Show/hide sections for Water Type
    function hideSections(prefix){
        document.getElementById(prefix+"_bottle_section").classList.add("d-none");
        document.getElementById(prefix+"_machine_section").classList.add("d-none");
    }

    document.body.addEventListener("change", e=>{
        if(!e.target.classList.contains("water_type_select")) return;

        const val = e.target.value;
        const modal = e.target.closest(".modal");
        const prefix = modal.id === "addBranchModal" ? "add" : "edit";

        hideSections(prefix);

        if(val === "BOTTLE"){
            document.getElementById(prefix+"_bottle_section").classList.remove("d-none");
        }
        if(val === "MACHINE"){
            document.getElementById(prefix+"_machine_section").classList.remove("d-none");
        }
    });


    // ADD BRANCH SAVE
    document.getElementById("addBranchForm").addEventListener("submit", function(e){
        e.preventDefault();
        const fd = new FormData(this);

        fetch("branch-water-save.php", { method:"POST", body:fd })
        .then(r=>r.json())
        .then(data=>{

            showBranchAlert(data.status, data.message);

            if(data.status==="success"){
                addModal.hide();
                loadBranchTable();
            }
        });
    });


    // OPEN EDIT
    document.body.addEventListener("click", e=>{
        const btn = e.target.closest(".edit-branch-btn");
        if(!btn) return;

        const id = btn.dataset.id;

        fetch("branch-water-get.php", {
            method:"POST",
            headers:{ "Content-Type":"application/x-www-form-urlencoded" },
            body: "id="+id
        })
        .then(r=>r.json())
        .then(data=>{

            if(!data.success){
                return showBranchAlert("danger","Failed to load record.");
            }

            document.getElementById("edit_id").value = data.id;
            document.getElementById("edit_branch_code").value = data.branch_code;
            document.getElementById("edit_branch_name").value = data.branch_name;

            document.getElementById("edit_vendor_name").value = data.vendor_name;
            document.getElementById("edit_account_number").value = data.account_number;

            $("#edit_water_type").val(data.water_type).trigger("change");

            // Bottle fields
            document.getElementById("edit_bottle_rate").value = data.bottle_rate;
            document.getElementById("edit_cooler_rental_rate").value = data.cooler_rental_rate;
            document.getElementById("edit_sscl_percentage").value = data.sscl_percentage;
            document.getElementById("edit_vat_percentage").value = data.vat_percentage;

            // Machine fields
            document.getElementById("edit_no_of_machines").value = data.no_of_machines;
            document.getElementById("edit_monthly_charge").value = data.monthly_charge;
            document.getElementById("edit_sscl_percentage_m").value = data.sscl_percentage_m;
            document.getElementById("edit_vat_percentage_m").value = data.vat_percentage_m;

            editModal.show();
        });
    });


    // EDIT SAVE
    document.getElementById("editBranchForm").addEventListener("submit", function(e){
        e.preventDefault();

        const fd = new FormData(this);
        fd.append("update","1");

        fetch("branch-water-save.php", { method:"POST", body:fd })
        .then(r=>r.json())
        .then(data=>{

            showBranchAlert(data.status, data.message);

            if(data.status==="success"){
                editModal.hide();
                loadBranchTable();
            }
        });

    });


    // Alert box
    function showBranchAlert(type,msg){
        const html = `
        <div class="alert alert-${type} alert-dismissible fade show mt-3">
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        document.getElementById("branch_alert").innerHTML = html;
    }

})();
</script>
