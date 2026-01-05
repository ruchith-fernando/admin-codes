$(function () {

    const $alert = $("#wrp_alert");

    function showAlert(type, msg) {
        if (!msg) { $alert.html(""); return; }
        $alert.html(`<div class="alert alert-${type}">${msg}</div>`);
    }

    function getTypeCodeFromSelect($sel) {
        return ($sel.find("option:selected").data("code") || "")
            .toString()
            .toUpperCase();
    }

    // ----- SHOW / HIDE FIELDS BY TYPE (top form) -----
    function layoutTop(typeCode) {
        typeCode = (typeCode || "").toUpperCase();

        const $bottle = $("#grp_bottle_rate");
        const $cooler = $("#grp_cooler_rate");
        const $sscl   = $("#grp_sscl");
        const $vat    = $("#grp_vat");
        const $eff    = $("#grp_effective_from");

        if (!typeCode) {
            // If JS breaks you STILL see all fields, so don't hide anything here.
            $bottle.show(); $cooler.show(); $sscl.show(); $vat.show(); $eff.show();
            return;
        }

        if (typeCode === "BOTTLE") {
            // Bottle Water → Bottle + SSCL + VAT + From
            $bottle.show();
            $sscl.show();
            $vat.show();
            $eff.show();
            $cooler.hide();
        } else if (typeCode === "MACHINE") {
            // Water Machine → Cooler + SSCL + VAT
            $cooler.show();
            $sscl.show();
            $vat.show();

            $bottle.hide();
            $eff.hide();
        } else {
            // Tap Line / others → SSCL + VAT + From
            $bottle.hide();
            $cooler.hide();
            $sscl.show();
            $vat.show();
            $eff.show();
        }
    }

    // ----- SHOW / HIDE FIELDS BY TYPE (modal) -----
    function layoutModal(typeCode) {
        typeCode = (typeCode || "").toUpperCase();

        const $bottle = $("#grpM_bottle_rate");
        const $cooler = $("#grpM_cooler_rate");
        const $sscl   = $("#grpM_sscl");
        const $vat    = $("#grpM_vat");
        const $eff    = $("#grpM_effective_from");

        if (!typeCode) {
            $bottle.show(); $cooler.show(); $sscl.show(); $vat.show(); $eff.show();
            return;
        }

        if (typeCode === "BOTTLE") {
            $bottle.show();
            $sscl.show();
            $vat.show();
            $eff.show();
            $cooler.hide();
        } else if (typeCode === "MACHINE") {
            $cooler.show();
            $sscl.show();
            $vat.show();
            $bottle.hide();
            $eff.hide();
        } else {
            $bottle.hide();
            $cooler.hide();
            $sscl.show();
            $vat.show();
            $eff.show();
        }
    }

    // ----- LOAD VENDORS -----
    function loadVendors(water_type_id, $select, selected_id) {
        if (!water_type_id) {
            $select.prop("disabled", true)
                   .html('<option value="">-- Select water type first --</option>');
            return;
        }

        $.getJSON("ajax-water-rate-vendors.php", { water_type_id }, function (res) {
            if (!res.success) {
                $select.prop("disabled", true).html('<option value="">No vendors</option>');
                return;
            }
            let html = '<option value="">-- Select --</option>';
            res.vendors.forEach(function (v) {
                const sel = selected_id && Number(selected_id) === Number(v.vendor_id) ? "selected" : "";
                html += `<option value="${v.vendor_id}" ${sel}>${v.vendor_name}</option>`;
            });
            $select.html(html).prop("disabled", false);
        });
    }

    // ----- LOAD TABLE -----
    function loadProfilesTable() {
        $.getJSON("ajax-water-rate-list.php", function (res) {
            if (res.success) {
                $("#wrp_table_wrapper").html(res.html);
            } else {
                $("#wrp_table_wrapper").html(
                    "<div class='alert alert-danger'>Failed to load rate profiles.</div>"
                );
            }
        });
    }

    // ----- RESET TOP FORM -----
    function resetForm() {
        $("#wrp_rate_profile_id").val("");
        $("#wrp_form")[0].reset();
        $("#wrp_is_active").prop("checked", true);

        $("#wrp_vendor_id")
            .prop("disabled", true)
            .html('<option value="">-- Select water type first --</option>');

        // DO NOT HIDE ANYTHING HERE; default is “everything visible”.
        layoutTop("");
    }

    // ===== INITIAL =====
    resetForm();
    loadProfilesTable();

    // ===== TOP FORM EVENTS =====
    $("#wrp_water_type_id").on("change", function () {
        const typeId   = $(this).val();
        const typeCode = getTypeCodeFromSelect($(this));

        layoutTop(typeCode);
        loadVendors(typeId, $("#wrp_vendor_id"));
    });

    $("#wrp_form").on("submit", function (e) {
        e.preventDefault();

        $.post("ajax-water-rate-save.php", $(this).serialize(), function (res) {
            if (res.success) {
                showAlert("success", res.message);
                resetForm();
                loadProfilesTable();
            } else {
                showAlert("danger", res.message || "Failed to save rate profile.");
            }
        }, "json");
    });

    $("#wrp_reset_btn").on("click", function () {
        resetForm();
        showAlert("", "");
    });

    // ===== TABLE EDIT BUTTON → MODAL =====
    $(document).on("click", ".wrp-btn-edit", function () {
        const id = $(this).data("id");
        if (!id) return;

        $.getJSON("ajax-water-rate-get.php", { id }, function (res) {
            if (!res.success) {
                showAlert("danger", res.message || "Failed to load profile.");
                return;
            }
            const p = res.profile;

            $("#wrp_modal_rate_profile_id").val(p.rate_profile_id);
            $("#wrp_modal_water_type_id").val(p.water_type_id);
            $("#wrp_modal_is_active").prop("checked", Number(p.is_active) === 1);

            const typeCode = getTypeCodeFromSelect($("#wrp_modal_water_type_id"));
            layoutModal(typeCode);

            loadVendors(p.water_type_id, $("#wrp_modal_vendor_id"), p.vendor_id);

            $("#wrp_modal_bottle_rate").val(p.bottle_rate || "");
            $("#wrp_modal_cooler_rental_rate").val(p.cooler_rental_rate || "");
            $("#wrp_modal_sscl_percentage").val(p.sscl_percentage || "");
            $("#wrp_modal_vat_percentage").val(p.vat_percentage || "");
            $("#wrp_modal_effective_from").val(p.effective_from || "");

            const modal = new bootstrap.Modal(document.getElementById("wrp_edit_modal"));
            modal.show();
        });
    });

    // Modal: type change
    $("#wrp_modal_water_type_id").on("change", function () {
        const typeId   = $(this).val();
        const typeCode = getTypeCodeFromSelect($(this));

        layoutModal(typeCode);
        loadVendors(typeId, $("#wrp_modal_vendor_id"));
    });

    // Modal: save
    $("#wrp_modal_form").on("submit", function (e) {
        e.preventDefault();

        $.post("ajax-water-rate-save.php", $(this).serialize(), function (res) {
            if (res.success) {
                showAlert("success", res.message);
                const modalEl = document.getElementById("wrp_edit_modal");
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
                loadProfilesTable();
            } else {
                showAlert("danger", res.message || "Failed to save rate profile.");
            }
        }, "json");
    });

});