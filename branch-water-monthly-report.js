$(document).ready(function () {

    /* ======================================================
       BUILD BLANK ROW
    ====================================================== */
    const blankWaterRow = () => `
<tr>
    <td><input type="text" class="form-control water_branch_code" maxlength="10"></td>

    <td>
        <input type="text" class="form-control water_branch_name" readonly>
        <small class="text-muted water_account_number"></small>
    </td>

    <td><input type="text" class="form-control water_type" readonly></td>

    <td><input type="date" class="form-control water_from_date"></td>

    <td><input type="date" class="form-control water_to_date"></td>

    <td><input type="number" class="form-control water_number_of_days" readonly></td>

    <td><input type="number" class="form-control water_usage_qty"></td>

    <td><input type="text" class="form-control water_amount"></td>
</tr>`;

    /* ======================================================
       RESET FORM
    ====================================================== */
    function resetWaterForm() {
        $("#water_entry_rows").html(blankWaterRow());
        $("#water_status_msg").html("");
    }

    /* ======================================================
       VIEW MODE (TOP REPORT)
    ====================================================== */
    $("#water_month_view").change(function () {
        let month = $(this).val();

        $("#water_missing_view_branches").addClass("d-none").html("");
        $("#water_report_section").addClass("d-none").html("");

        if (!month) return;

        $("#water_missing_view_branches").removeClass("d-none").html("Loading...");

        $.post("branch-water-monthly-fetch.php", { month }, function (res) {

            $("#water_missing_view_branches").html("");

            $("#water_report_section")
                .removeClass("d-none")
                .html(res.table || "");

        }, "json");
    });

    /* ======================================================
       MANUAL MODE
    ====================================================== */
    $("#water_month_manual").change(function () {
        resetWaterForm();
        let month = $(this).val();

        $("#water_missing_manual_branches").addClass("d-none").html("");

        if (!month) {
            $("#water_manual_form").addClass("d-none");
            return;
        }

        $("#water_manual_form").removeClass("d-none");

        $("#water_missing_manual_branches").removeClass("d-none").html("Loading...");

        $.post("branch-water-monthly-fetch.php", { month }, function (res) {

            if (res.missing.length > 0) {
                $("#water_missing_manual_branches").html(
                    `<b>${res.missing.length} missing:</b><br><br>${res.missing.join("<br>")}`
                );
            } else {
                $("#water_missing_manual_branches").html(
                    `<b>All entries completed for this month.</b>`
                );
            }

        }, "json");
    });

    /* ======================================================
       CLEAR ROW BEFORE NEW LOOKUP
    ====================================================== */
    function clearRow(row) {
        row.find(".water_branch_name").val("");
        row.find(".water_account_number").text("");

        row.find(".water_type").val("");

        row.find(".water_from_date").val("");
        row.find(".water_to_date").val("");
        row.find(".water_number_of_days").val("");

        row.find(".water_usage_qty").val("").show();
        row.find(".water_amount").val("").prop("readonly", false);
    }

    /* ======================================================
       AUTO CALCULATE DAYS
    ====================================================== */
    function updateNumberOfDays(row) {
        const from = row.find(".water_from_date").val();
        const to = row.find(".water_to_date").val();

        if (from && to) {
            const d1 = new Date(from);
            const d2 = new Date(to);
            if (d2 >= d1) {
                row.find(".water_number_of_days").val(
                    Math.floor((d2 - d1) / 86400000) + 1
                );
            }
        }
    }

    $(document).on("change", ".water_from_date, .water_to_date", function () {
        updateNumberOfDays($(this).closest("tr"));
    });

    /* ======================================================
       BRANCH LOOKUP
    ====================================================== */
    $(document).on("blur", ".water_branch_code", function () {

        const row = $(this).closest("tr");
        const branch_code = $(this).val().trim();
        const month = $("#water_month_manual").val();

        if (!branch_code || !month) return;

        clearRow(row);

        /* FIRST CHECK IF ALREADY EXISTS → LOCK IMMEDIATELY */
        $.post("branch-ajax-water-get-existing.php", { branch_code, month }, function (res) {

            if (res.exists) {

                $("#water_status_msg").html(`
                    <div class="alert alert-danger">
                        Entry already submitted and approved.<br>
                        <b>${res.branch}</b> (${branch_code}) — ${month}<br><br>
                        <b>Contact Admin for changes.</b>
                    </div>
                `);

                row.find("input, select").prop("disabled", true);
                return;
            }

            /* GET MASTER BRANCH INFO */
            $.post("branch-ajax-water-get-branch.php", { branch_code }, function (b) {

                if (!b.success) {
                    row.find(".water_branch_name").val("Not Found");
                    return;
                }

                row.find(".water_branch_name").val(b.branch_name);
                row.find(".water_type").val(b.water_type);
                row.find(".water_account_number").text(b.account_number || "");

                /* MACHINE */
                if (b.water_type === "MACHINE") {

                    row.find(".water_usage_qty").hide();

                    const mc = parseFloat(b.monthly_charge || 0);
                    const machines = parseInt(b.no_of_machines || 1);
                    const sscl = parseFloat(b.sscl || 0);
                    const vat = parseFloat(b.vat || 0);

                    const base = mc * machines;
                    const sscl_amt = base * (sscl / 100);
                    const with_sscl = base + sscl_amt;
                    const vat_amt = with_sscl * (vat / 100);

                    const total = with_sscl + vat_amt;

                    row.find(".water_amount")
                        .val(total.toFixed(2))
                        .prop("readonly", true);
                }

                /* BOTTLE */
                else if (b.water_type === "BOTTLE") {

                    row.data("bottle_rate", b.bottle_rate);
                    row.data("cooler_rent", b.cooler_rental);
                    row.data("sscl", b.sscl);
                    row.data("vat", b.vat);

                    row.find(".water_usage_qty").show();
                    row.find(".water_amount").prop("readonly", true);
                }

                /* NWSDB */
                else if (b.water_type === "NWSDB") {
                    row.find(".water_usage_qty").show();
                    row.find(".water_amount").prop("readonly", false);
                }

            }, "json");

        }, "json");
    });

    /* ======================================================
       BOTTLE CALCULATION
    ====================================================== */
    $(document).on("input", ".water_usage_qty", function () {

        const row = $(this).closest("tr");
        const type = row.find(".water_type").val();
        const qty = parseFloat($(this).val() || 0);

        if (qty < 0) {
            $(this).val("");
            return;
        }

        if (type === "BOTTLE") {

            const rate = parseFloat(row.data("bottle_rate"));
            const rent = parseFloat(row.data("cooler_rent"));
            const sscl = parseFloat(row.data("sscl"));
            const vat = parseFloat(row.data("vat"));

            let subtotal = (rate * qty) + rent;

            let sscl_amt = subtotal * (sscl / 100);
            let vat_amt = (subtotal + sscl_amt) * (vat / 100);

            let total = subtotal + sscl_amt + vat_amt;

            row.find(".water_amount").val(total.toFixed(2));
        }
    });

    /* ======================================================
       SAVE ENTRY
    ====================================================== */
    $("#water_save_entry").click(function () {

        const row = $("#water_entry_rows tr").last();
        const month = $("#water_month_manual").val();

        const data = {
            month,
            branch_code: row.find(".water_branch_code").val(),
            branch_name: row.find(".water_branch_name").val(),
            water_type: row.find(".water_type").val(),
            account_number: row.find(".water_account_number").text(),

            from_date: row.find(".water_from_date").val(),
            to_date: row.find(".water_to_date").val(),
            number_of_days: row.find(".water_number_of_days").val(),

            usage_qty: row.find(".water_usage_qty").val(),
            amount: row.find(".water_amount").val().replace(/,/g, "")
        };

        if (
            !data.month ||
            !data.branch_code ||
            !data.water_type ||
            !data.from_date ||
            !data.to_date ||
            !data.amount
        ) {
            $("#water_status_msg").html(
                `<div class="alert alert-danger">Fill required fields.</div>`
            );
            return;
        }

        $.post("branch-water-monthly-save.php", data, function (res) {

            if (res.success) {

                $("#water_status_msg").html(
                    `<div class="alert alert-success">${res.message}</div>`
                );

                // refresh alerts
                $.post("branch-water-monthly-fetch.php", { month }, function (r2) {

                    if (r2.missing.length > 0) {
                        $("#water_missing_manual_branches").html(
                            `<b>${r2.missing.length} missing:</b><br><br>${r2.missing.join("<br>")}`
                        );
                    } else {
                        $("#water_missing_manual_branches").html(
                            `<b>All entries completed.</b>`
                        );
                    }

                }, "json");

                $("#water_entry_rows").append(blankWaterRow());
            }

            else {
                $("#water_status_msg").html(
                    `<div class="alert alert-danger">${res.message}</div>`
                );
            }

        }, "json");
    });

});
