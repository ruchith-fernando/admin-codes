// security-branch-firm-map.js
$(document).ready(function () {

    // 🔹 Enhance branch code dropdown with Select2 (codes only)
    if ($.fn.select2) {
        $('#branch_code_select').select2({
            width: '100%',
            placeholder: '-- Select Branch Code --',
            allowClear: true
        });
    }

    // Helper: update branch name textbox from selected branch code
    function updateBranchNameFromSelect() {
        let code = $("#branch_code_select").val();
        if (!code) {
            $("#branch_name").val('');
            return;
        }

        // Find the real <option> with that value
        let opt = $("#branch_code_select").find('option[value="' + code + '"]');
        let branch_name = opt.attr('data-branch-name') || '';
        $("#branch_name").val(branch_name);
    }

    // Fire on normal change
    $("#branch_code_select").on('change', function () {
        updateBranchNameFromSelect();
    });

    // Fire on select2 select event as well
    $("#branch_code_select").on('select2:select', function () {
        updateBranchNameFromSelect();
    });

    // 🔹 Load ALL mappings (for all firms)
    function loadAllMappings() {
        $.post("ajax-branch-firm-list.php", {}, function (res) {
            if (res.success) {
                let rows = res.rows || [];
                let html = '';

                if (rows.length) {
                    rows.forEach(function (r) {
                        html += `
                            <tr data-id="${r.id}" data-code="${r.branch_code}" data-name="${r.branch_name}">
                                <td>${r.firm_name || ''}</td>
                                <td>${r.branch_code}</td>
                                <td>${r.branch_name}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-row">Edit</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-row">Delete</button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="4" class="text-center text-muted">No branches linked to any firm yet.</td></tr>`;
                }

                $("#mapping_table tbody").html(html);
            } else {
                $("#mapping_status").html(`<div class="alert alert-danger">${res.message}</div>`);
                $("#mapping_table tbody").html('<tr><td colspan="4" class="text-center text-muted">No data.</td></tr>');
            }
        }, 'json');
    }

    // Load all mappings on page load
    loadAllMappings();

    // 🔹 When firm selected → just show/hide form, reset inputs
    $("#firm_select").change(function () {
        let firm_id = $(this).val();
        $("#mapping_status").html('');
        $("#branch_code_select").val(null).trigger('change');
        $("#branch_name").val('');

        if (!firm_id) {
            $("#mapping_form").addClass('d-none');
        } else {
            $("#mapping_form").removeClass('d-none');
        }

        // Table is NOT filtered anymore; we always show all mappings.
    });

    // 🔹 Save mapping (add / update)
    $("#save_mapping_btn").click(function () {
        let firm_id = $("#firm_select").val();
        let branch_code = $("#branch_code_select").val();
        let branch_name = $("#branch_name").val().trim();

        if (!firm_id) {
            $("#mapping_status").html(`<div class="alert alert-danger">Please select a firm first.</div>`);
            return;
        }
        if (!branch_code) {
            $("#mapping_status").html(`<div class="alert alert-danger">Please select a branch code.</div>`);
            return;
        }
        if (!branch_name) {
            $("#mapping_status").html(`<div class="alert alert-danger">Branch name not found for this code.</div>`);
            return;
        }

        $.post("ajax-branch-firm-save.php", {
            firm_id: firm_id,
            branch_code: branch_code,
            branch_name: branch_name
        }, function (res) {
            if (res.success) {
                $("#mapping_status").html(`<div class="alert alert-success">${res.message}</div>`);

                // Remove this branch from dropdown so it's not reused
                let $opt = $("#branch_code_select").find('option[value="' + branch_code + '"]');
                if ($opt.length) {
                    $opt.remove();
                    $("#branch_code_select").val(null).trigger('change');
                }

                $("#branch_name").val('');
                loadAllMappings(); // reload full table
            } else {
                $("#mapping_status").html(`<div class="alert alert-danger">${res.message}</div>`);
            }
        }, 'json');
    });

    // 🔹 Edit: load row back into form
    $(document).on('click', '.edit-row', function () {
        let tr = $(this).closest('tr');
        let code = tr.data('code');
        let name = tr.data('name');

        // If this code is no longer in the dropdown (because we removed it),
        // add it back just for editing.
        if ($("#branch_code_select option[value='" + code + "']").length === 0) {
            let newOpt = new Option(code, code, false, false);
            $(newOpt).attr('data-branch-name', name);
            $("#branch_code_select").append(newOpt);
        }

        $("#branch_code_select").val(code).trigger('change');
        $("#branch_name").val(name);

        // Optional: auto-select correct firm in dropdown?
        // We didn't include firm_id in data attributes,
        // but if you want that, you can add data-firm-id in the row
        // and set $("#firm_select").val(firmId).change();
    });

    // 🔹 Delete mapping (soft delete)
    $(document).on('click', '.delete-row', function () {
        if (!confirm('Remove this branch mapping from this firm?')) return;

        let tr = $(this).closest('tr');
        let id = tr.data('id');

        $.post("ajax-branch-firm-delete.php", { id: id }, function (res) {
            if (res.success) {
                $("#mapping_status").html(`<div class="alert alert-success">${res.message}</div>`);
                loadAllMappings(); // reload full table
            } else {
                $("#mapping_status").html(`<div class="alert alert-danger">${res.message}</div>`);
            }
        }, 'json');
    });

});
