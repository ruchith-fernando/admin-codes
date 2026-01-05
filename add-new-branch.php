<!-- ADD NEW MODAL -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">

    <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Add New Branch</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">

        <form id="newBranchForm">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Branch Code</label>
                    <input type="text" class="form-control" id="new_branch_code" required>
                </div>
                <div class="col-md-6">
                    <label>Branch Name</label>
                    <input type="text" class="form-control" id="new_branch_name" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label>Vendor Name</label>
                    <select id="new_vendor_name" class="form-select"></select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Water Type</label>
                    <select id="new_water_type" class="form-select" required>
                        <option value="NWSDB">NWSDB</option>
                        <option value="BOTTLE">Bottle</option>
                        <option value="MACHINE">Machine</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Account Number</label>
                    <input type="text" id="new_account_number" class="form-control">
                </div>
            </div>

            <!-- MACHINE -->
            <div id="new_machine_fields" class="border rounded p-3 mb-3 d-none">
                <h6 class="text-secondary">Machine Settings</h6>
                <div class="row">
                    <div class="col-md-6">
                        <label>No. of Machines</label>
                        <input type="number" id="new_no_of_machines" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>Monthly Charge</label>
                        <input type="number" id="new_monthly_charge" class="form-control">
                    </div>
                </div>
            </div>

            <!-- BOTTLE -->
            <div id="new_bottle_fields" class="border rounded p-3 mb-3 d-none">
                <h6 class="text-secondary">Bottle Settings</h6>

                <div class="row">
                    <div class="col-md-6">
                        <label>Bottle Rate</label>
                        <input type="number" id="new_bottle_rate" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>Cooler Rental</label>
                        <input type="number" id="new_cooler_rental_rate" class="form-control">
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label>SSCL %</label>
                        <input type="number" id="new_sscl_percentage" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>VAT %</label>
                        <input type="number" id="new_vat_percentage" class="form-control">
                    </div>
                </div>
            </div>

        </form>

    </div>

    <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="saveNewBranchBtn" class="btn btn-success">Add Branch</button>
    </div>

</div>
</div>
</div>

<script>
// OPEN ADD MODAL
$('#addNewBtn').click(function () {

    // Load default values
    $.post('ajax-get-default-values.php', {}, function(data){

        $('#new_branch_code').val('');
        $('#new_branch_name').val('');
        $('#new_account_number').val('');

        loadVendorsForAdd();

        $('#addBranchModal').modal('show');
    }, 'json');

});

// Load vendor list for Add New
function loadVendorsForAdd(){
    $.getJSON('get-vendors.php', function(list){

        $('#new_vendor_name').empty().append('<option></option>');

        list.forEach(v => {
            $('#new_vendor_name').append(`<option value="${v}">${v}</option>`);
        });

        $('#new_vendor_name').select2({
            dropdownParent: $('#addBranchModal'),
            width: '100%',
            placeholder: "Select Vendor",
            allowClear: true
        });
    });
}

// SHOW/HIDE FIELDS BASED ON TYPE
$('#new_water_type').on('change', function(){
    let type = $(this).val();
    $('#new_machine_fields, #new_bottle_fields').addClass('d-none');

    if (type === 'MACHINE') $('#new_machine_fields').removeClass('d-none');
    if (type === 'BOTTLE') $('#new_bottle_fields').removeClass('d-none');
});

// SAVE INSERT
$('#saveNewBranchBtn').click(function () {

    let fd = new FormData(document.getElementById('newBranchForm'));

    fd.append("vendor_name", $('#new_vendor_name').val());

    $.ajax({
        url: 'ajax-insert-branch.php',
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        success: function (res) {

            try { res = JSON.parse(res); } catch(e){}

            alert(res.message);

            if (res.success) {
                location.reload();
            }
        }
    });

});
</script>
