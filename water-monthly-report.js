// water-monthly-report.js (FULL DROP-IN - YOUR FILE + PROVISION EDIT/CONVERT + LAYOUT)
$(document).ready(function () {

    /* ======================================================
   COLLAPSIBLE ALERT HELPERS
   - Default: collapsed
   - Shows a small header with Expand/Hide
    ====================================================== */
    function ensureCollapsibleAlert($box, collapseId, titleText) {
        if ($box.data("collapsibleReady")) return;

        // Keep original alert styling, but we will control its inner layout
        $box.data("collapsibleReady", true);
        $box.data("collapseId", collapseId);
        $box.data("titleText", titleText);

        // Button text sync
        $(document).off("shown.bs.collapse." + collapseId).on("shown.bs.collapse." + collapseId, "#" + collapseId, function () {
            $box.find(".water-alert-toggle").text("Hide");
        });
        $(document).off("hidden.bs.collapse." + collapseId).on("hidden.bs.collapse." + collapseId, "#" + collapseId, function () {
            $box.find(".water-alert-toggle").text("Expand");
        });
    }

    function setCollapsibleAlertContent($box, htmlBody, summaryLine) {
        const collapseId = $box.data("collapseId") || ("water_alert_" + Math.random().toString(36).slice(2));
        const titleText  = $box.data("titleText") || "Alerts";

        const hasContent = (htmlBody || "").trim().length > 0;

        if (!hasContent) {
            $box.addClass("d-none").html("");
            return;
        }

        // Default: collapsed
        const headerHtml = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">${titleText}</div>
                    ${summaryLine ? `<div class="small text-muted">${summaryLine}</div>` : ``}
                </div>
                <button type="button"
                    class="btn btn-sm btn-outline-dark water-alert-toggle"
                    data-bs-toggle="collapse"
                    data-bs-target="#${collapseId}"
                    aria-expanded="false"
                    aria-controls="${collapseId}">
                    Expand
                </button>
            </div>
        `;

        const bodyHtml = `
            <div id="${collapseId}" class="collapse mt-2">
                ${htmlBody}
            </div>
        `;

        $box.removeClass("d-none").html(headerHtml + bodyHtml);

        // Ensure it stays collapsed after refresh
        const el = document.getElementById(collapseId);
        if (el) {
            el.classList.remove("show");
        }
    }

    /* ======================================================
       CSV DOWNLOAD (VIEW MODE ONLY)
    ====================================================== */
    $("#water_download_csv_btn").on("click", function () {
        const month = $("#water_month_view").val();
        if (!month) { alert("Please select a month first."); return; }
        window.location.href = "water-monthly-export.php?month=" + encodeURIComponent(month);
    });

    /* ======================================================
       ADD ROW BUTTON: ALWAYS KEEP ONE BLANK ROW AT THE BOTTOM
    ====================================================== */
    function rowHasAnyData($r) {
        const code   = ($r.find(".water_branch_code").val() || "").trim();
        const type   = ($r.find(".water_type").val() || "").trim();
        const amount = ($r.find(".water_amount").val() || "").trim();
        const from   = ($r.find(".water_from_date").val() || "").trim();
        const to     = ($r.find(".water_to_date").val() || "").trim();
        return !!(code || type || amount || from || to);
    }

    $("#water_add_row").on("click", function () {
        if ($("#water_save_entry").data("locked") === true) return;

        const $tbody = $("#water_entry_rows");
        const $last  = $tbody.find("tr").last();

        if ($last.length && !rowHasAnyData($last)) {
            $last.find(".water_branch_code").focus();
            return;
        }

        $tbody.append(blankWaterRow());
        $("#water_confirm_checked").prop("checked", false);
        updateConfirmAndSave();
    });

    /* ======================================================
       DUPLICATE CHECK (branch + type + connection_no)
    ====================================================== */
    function getRowKey($r) {
        const branch  = ($r.find(".water_branch_code").val() || "").trim();
        const typeVal = ($r.find(".water_type").val() || "").trim(); // "typeId|connNo"
        if (!branch || !typeVal) return "";
        return branch + "|" + typeVal;
    }

    function isDuplicateBranchTypeRow(currentRow) {
        const $row = $(currentRow);
        const key = getRowKey($row);
        if (!key) return false;

        let duplicate = false;
        $("#water_entry_rows tr").each(function () {
            const $r = $(this);
            if ($r[0] === $row[0]) return;
            const k2 = getRowKey($r);
            if (k2 && k2 === key) { duplicate = true; return false; }
        });
        return duplicate;
    }

    function handleDuplicateBranchTypeRow(currentRow) {
        const $row = $(currentRow);

        $row.find(".water_type").val("");
        $row.find(".water_connection_no").val("1");

        $row.find(".water_account_number").text("");
        $row.find(".water_account_number_value").val("");

        $row.find(".water_usage_qty").val("").show().attr("placeholder", "Qty");
        $row.find(".water_amount").val("").prop("readonly", false);

        $row.find(".water_provision").val("no");

        setQtyHeaderLabel("DEFAULT");
        recalculateWaterTotal();
        updateConfirmAndSave();

        $("#water_status_msg").html(
            `<div class="alert alert-danger">
                This branch + water type + connection is already entered in another row.
            </div>`
        );
    }

    /* ======================================================
       ADD TOTAL TEXTBOX + CONFIRM SWITCH UNDER TABLE (ONCE)
    ====================================================== */
    (function addTotalBox() {
        if ($("#water_total_row").length) return;

        const totalHtml = `
            <div class="row mt-3 align-items-center" id="water_total_row">
                <div class="col-md-6 text-end">
                    <div class="form-check form-switch d-inline-flex align-items-center">
                        <input class="form-check-input" type="checkbox" id="water_confirm_checked">
                        <label class="form-check-label ms-2" for="water_confirm_checked">
                            I have checked all entries and total.
                        </label>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <label class="col-form-label fw-bold mb-4">Total Amount:</label>
                </div>
                <div class="col-md-3">
                    <input type="text" id="water_total_amount" class="form-control" readonly>
                </div>
            </div>
        `;

        const $table = $(".water-entry-table");
        if ($table.length) $table.after(totalHtml);
        else $("#water_entry_rows").closest("table").after(totalHtml);
    })();

    /* ======================================================
       HELPER: FORMAT MONEY WITH COMMAS + 2 DECIMALS
    ====================================================== */
    function formatMoney(val) {
        if (val === null || val === undefined || val === "") return "";
        const num = parseFloat(val);
        if (isNaN(num)) return "";
        return num.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /* ======================================================
       TOTAL RECALCULATION
    ====================================================== */
    function recalculateWaterTotal() {
        let total = 0;
        $("#water_entry_rows tr").each(function () {
            const $amt = $(this).find(".water_amount");
            if (!$amt.length) return;
            let v = ($amt.val() || "").toString().replace(/,/g, "").trim();
            if (!v) return;
            const num = parseFloat(v);
            if (!isNaN(num)) total += num;
        });
        $("#water_total_amount").val(total ? formatMoney(total) : "");
    }

    /* ======================================================
       HELPER: DOES ANY ROW HAVE DATA?
    ====================================================== */
    function hasAnyDataRow() {
        let has = false;
        $("#water_entry_rows tr").each(function () {
            const $r = $(this);
            const code   = ($r.find(".water_branch_code").val() || "").trim();
            const type   = ($r.find(".water_type").val() || "").trim();
            const amount = ($r.find(".water_amount").val() || "").trim();
            if (code || type || amount) { has = true; return false; }
        });
        return has;
    }

    /* ======================================================
       ENABLE/DISABLE SAVE BASED ON LOCK + SWITCH
    ====================================================== */
    function updateSaveEntryEnabled() {
        const $btn = $("#water_save_entry");
        const locked  = $btn.data("locked") === true;
        const checked = $("#water_confirm_checked").is(":checked");

        const shouldEnable = !locked && checked;
        $btn.prop("disabled", !shouldEnable);

        if (locked) {
            $btn.addClass("btn-secondary disabled").removeClass("btn-success").text("Editing Locked");
        } else if (shouldEnable) {
            $btn.addClass("btn-success").removeClass("btn-secondary disabled").text("Save Entry");
        } else {
            $btn.addClass("btn-secondary disabled").removeClass("btn-success").text("Save Entry");
        }
    }

    function updateConfirmAndSave() {
        const $switch = $("#water_confirm_checked");
        const hasData = hasAnyDataRow();

        if (!hasData) $switch.prop("checked", false).prop("disabled", true);
        else $switch.prop("disabled", false);

        updateSaveEntryEnabled();
    }

    function toggleWaterFormLock(state) {
        $("#water_entry_rows input, #water_entry_rows select").prop("disabled", state);
        $("#water_save_entry").data("locked", state);
        updateConfirmAndSave();
    }

    /* ======================================================
       BLANK ROW TEMPLATE
       ✅ Provision column is next to Type
    ====================================================== */
    const blankWaterRow = () => `
<tr>
    <td>
        <input type="text" class="form-control water_branch_code" maxlength="10">
        <input type="hidden" class="water_connection_no" value="1">
    </td>

    <td>
        <input type="text" class="form-control water_branch_name" readonly>
        <input type="hidden" class="water_account_number_value" value="">
        <small class="text-muted water_account_number"></small>
    </td>

    <td>
        <select class="form-select water_type">
            <option value="">-- Select Type --</option>
        </select>
    </td>

    <td>
        <select class="form-select water_provision">
            <option value="no" selected>No</option>
            <option value="yes">Yes</option>
        </select>
    </td>

    <td><input type="date" class="form-control water_from_date"></td>
    <td><input type="date" class="form-control water_to_date"></td>
    <td><input type="number" class="form-control water_number_of_days" readonly></td>
    <td><input type="number" class="form-control water_usage_qty" placeholder="Qty"></td>
    <td><input type="text" class="form-control water_amount" placeholder="Amount"></td>
</tr>`;

    /* ======================================================
       RESET WATER FORM
    ====================================================== */
    function resetWaterForm() {
        $("#water_entry_rows").html(blankWaterRow());
        $("#water_confirm_checked").prop("checked", false);
        toggleWaterFormLock(false);
        $("#water_status_msg").html("");
        $("#water_provision_info").addClass("d-none").html("");
        setQtyHeaderLabel("DEFAULT");
        recalculateWaterTotal();
        updateConfirmAndSave();
    }

    /* ======================================================
       QTY HEADER LABEL
    ====================================================== */
    function setQtyHeaderLabel(mode) {
        const $hdr = $(".water-entry-table .col-qty");
        if (!$hdr.length) return;
        if (mode === "NWSDB") $hdr.text("Units");
        else $hdr.text("Qty");
    }

    /* ======================================================
       NORMALIZE MODE
    ====================================================== */
    function normalizeModeFromString(raw) {
        const r = (raw || "").toString().toUpperCase();
        if (r.includes("BOTTLE") || r.includes("BOTTLED") || r.startsWith("BOT")) return "BOTTLE";
        if (r.includes("MACH") || r.includes("COOLER")) return "MACHINE";
        if (r.includes("TAP") || r.includes("NWSDB") || r.includes("LINE")) return "NWSDB";
        return "NWSDB";
    }

    /* ======================================================
       ✅ PROVISION RULES + CALCS
    ====================================================== */
    function calcMachineTotal(monthlyCharge, noMachines, sscl, vat) {
        const base     = monthlyCharge * noMachines;
        const ssclAmt  = base * (sscl / 100);
        const withSscl = base + ssclAmt;
        const vatAmt   = withSscl * (vat / 100);
        return withSscl + vatAmt;
    }

    function calcBottleTotal(rate, qty, rental, ssclP, vatP) {
        const base  = (rate * qty) + rental;
        const sscl  = base * (ssclP / 100.0);
        const vat   = (base + sscl) * (vatP / 100.0);
        return base + sscl + vat;
    }

    function applyProvisionRules(row) {
        const typeVal = (row.find(".water_type").val() || "").trim();
        const typeMap = row.data("waterTypesMap") || {};
        const t       = typeMap[typeVal] || null;

        const qtyInput = row.find(".water_usage_qty");
        const amtInput = row.find(".water_amount");

        if (!t) {
            qtyInput.show().attr("placeholder", "Qty");
            amtInput.prop("readonly", false);
            recalculateWaterTotal();
            updateConfirmAndSave();
            return;
        }

        const mode = normalizeModeFromString(t.mode || t.water_type_name);
        const prov = (row.find(".water_provision").val() || "no").toLowerCase();

        const monthlyCharge = parseFloat(t.monthly_charge || 0);
        const noMachines    = parseInt(t.no_of_machines || 1, 10);
        const bottleRate    = parseFloat(t.bottle_rate   || 0);
        const coolerRent    = parseFloat(t.cooler_rental || 0);
        const sscl          = parseFloat(t.sscl          || 0);
        const vat           = parseFloat(t.vat           || 0);

        row.data("bottle_rate", bottleRate || 0);
        row.data("cooler_rent", coolerRent || 0);
        row.data("sscl", sscl || 0);
        row.data("vat",  vat  || 0);

        setQtyHeaderLabel(mode);

        if (prov === "yes") {
            // ✅ Provision: amount ALWAYS editable for ALL types
            amtInput.prop("readonly", false);

            if (mode === "MACHINE") {
                qtyInput.hide();
                if (!amtInput.val()) {
                    const total = calcMachineTotal(monthlyCharge, noMachines, sscl, vat);
                    amtInput.val(formatMoney(total));
                }
            } else {
                // placeholder
                if (mode === "BOTTLE") qtyInput.show().attr("placeholder", "No. of bottles");
                else if (mode === "NWSDB") qtyInput.show().attr("placeholder", "Units");
                else qtyInput.show().attr("placeholder", "Qty");

                if (mode === "BOTTLE") {
                    const q = parseFloat(qtyInput.val() || 0);
                    if (q > 0 && !amtInput.val()) {
                        const total = calcBottleTotal(bottleRate, q, coolerRent, sscl, vat);
                        amtInput.val(formatMoney(total));
                    }
                }
            }
        } else {
            // ✅ Actual: original behavior
            if (mode === "MACHINE") {
                qtyInput.hide();
                const total = calcMachineTotal(monthlyCharge, noMachines, sscl, vat);
                amtInput.val(formatMoney(total)).prop("readonly", true);
            }
            else if (mode === "BOTTLE") {
                qtyInput.show().attr("placeholder", "No. of bottles");
                amtInput.prop("readonly", true);

                const q = parseFloat(qtyInput.val() || 0);
                if (q > 0) {
                    const total = calcBottleTotal(bottleRate, q, coolerRent, sscl, vat);
                    amtInput.val(formatMoney(total));
                } else {
                    amtInput.val("");
                }
            }
            else {
                qtyInput.show().attr("placeholder", "Units");
                amtInput.prop("readonly", false);
            }
        }

        recalculateWaterTotal();
        updateConfirmAndSave();
    }

    /* ======================================================
   ALERT BUILDER (GROUPED + PENDING COUNT)
   ✅ Now collapsible + collapsed by default
    ====================================================== */
    const updateAlerts = (res, month, alertSel, provSel) => {

        // --- Main alert (missing/pending/provisions list) ---
        let out = "";

        // Build the detailed HTML (same logic you already had)
        if (res.missing_groups && Object.keys(res.missing_groups).length > 0) {
            const seen = new Set();
            Object.values(res.missing_groups).forEach(list => (list || []).forEach(lbl => seen.add(lbl)));
            const totalMissing = seen.size;

            if (totalMissing > 0) {
                out += `<b>${totalMissing} connections have not submitted water charges for ${month}:</b><br><br>`;

                const order = ["Tap Line", "Bottle Water", "Machine"];
                const groups = res.missing_groups;

                order.forEach(g => {
                    const list = groups[g];
                    if (list && list.length) {
                        out += `<b>${g}</b><br>`;
                        out += list.join(", ") + "<br><br>";
                    }
                });

                Object.keys(groups).forEach(g => {
                    if (order.indexOf(g) !== -1) return;
                    const list = groups[g];
                    if (list && list.length) {
                        out += `<b>${g}</b><br>`;
                        out += list.join(", ") + "<br><br>";
                    }
                });
            }
        } else {
            if (res.missing?.length) {
                out += `<b>${res.missing.length} missing:</b><br><br>${res.missing.join('<br>')}<br><br>`;
            }
            if (res.pending?.length) {
                out += `<b>${res.pending.length} pending:</b><br><br>${res.pending.join('<br>')}<br><br>`;
            }
            if (res.provisions?.length) {
                out += `<b>${res.provisions.length} provisional:</b><br><br>${res.provisions.join('<br>')}<br><br>`;
            }
        }

        if (res.pending_count && res.pending_count > 0) {
            out += `<hr class="my-2">`;
            out += `<span class="text-danger fw-bold">Additionally, ${res.pending_count} branches have submitted water charges that are still pending approval for ${month}.</span>`;
        }

        // Build a compact summary line for the collapsed header
        const summaryParts = [];
        const missingCount =
            (res.missing_groups && Object.keys(res.missing_groups).length > 0)
                ? (() => {
                    const seen = new Set();
                    Object.values(res.missing_groups).forEach(list => (list || []).forEach(lbl => seen.add(lbl)));
                    return seen.size;
                })()
                : (res.missing?.length || 0);

        const pendingCount = (res.pending?.length || 0) + (res.pending_count || 0);
        const provCount    = (res.provisions?.length || 0);

        if (missingCount) summaryParts.push(`${missingCount} missing`);
        if (pendingCount) summaryParts.push(`${pendingCount} pending`);
        if (provCount)    summaryParts.push(`${provCount} provisional`);
        const summaryLine = summaryParts.length ? summaryParts.join(" • ") : "";

        // Make it collapsible (collapsed by default)
        const $alertBox = $(alertSel);
        ensureCollapsibleAlert($alertBox, $alertBox.attr("id") + "_body", `Alerts — ${month}`);
        setCollapsibleAlertContent($alertBox, out, summaryLine);

        // --- Provision info box (also collapsible, small) ---
        const $provBox = $(provSel);
        if (provCount) {
            ensureCollapsibleAlert($provBox, $provBox.attr("id") + "_body", `Provision info — ${month}`);
            setCollapsibleAlertContent(
                $provBox,
                `Provisional entries exist for <b>${month}</b>.`,
                `${provCount} provisional`
            );
        } else {
            $provBox.addClass("d-none").html("");
        }
    };


    /* ======================================================
       VIEW MODE
    ====================================================== */
    $("#water_month_view").on("change", function () {
        resetWaterForm();

        $("#water_month_manual").val("");
        $("#water_manual_form").addClass("d-none");
        $("#water_missing_manual_branches").addClass("d-none").html("");

        const month = $(this).val();

        $("#water_missing_view_branches").addClass("d-none").html("");
        $("#water_provision_info").addClass("d-none").html("");
        $("#water_report_section").addClass("d-none").html("");
        $("#water_csv_download_container").addClass("d-none");

        if (!month) return;

        $("#water_missing_view_branches").addClass("d-none").html("");

        $.post("water-monthly-fetch.php", { month }, function (res) {
            $("#water_report_section").removeClass("d-none").html(res.table || "");
            updateAlerts(res, month, "#water_missing_view_branches", "#water_provision_info");
            $("#water_csv_download_container").removeClass("d-none");
        }, "json");
    });

    /* ======================================================
       MANUAL MODE
    ====================================================== */
    $("#water_month_manual").change(function () {
        resetWaterForm();

        $("#water_month_view").val("");
        $("#water_report_section").addClass("d-none").html("");
        $("#water_missing_view_branches").addClass("d-none").html("");

        $("#water_csv_download_container").addClass("d-none");

        const month = $(this).val();
        $("#water_missing_manual_branches").addClass("d-none").html("");
        $("#water_provision_info").addClass("d-none").html("");

        if (!month) { $("#water_manual_form").addClass("d-none"); return; }

        $("#water_manual_form").removeClass("d-none");
        $("#water_missing_manual_branches").addClass("d-none").html("");

        $.post("water-monthly-fetch.php", { month }, function (res) {
            updateAlerts(res, month, "#water_missing_manual_branches", "#water_provision_info");
        }, "json");
    });

    
    /* ======================================================
       CLEAR ROW BEFORE LOOKUP
    ====================================================== */
    function clearRow(row) {
        row.find(".water_branch_name").val("");
        row.find(".water_account_number").text("");
        row.find(".water_account_number_value").val("");

        row.find(".water_type").empty()
            .append('<option value="">-- Select Type --</option>')
            .prop("disabled", false);

        row.find(".water_connection_no").val("1");

        row.removeData("waterTypesMap");
        row.removeData("bottle_rate");
        row.removeData("cooler_rent");
        row.removeData("sscl");
        row.removeData("vat");

        row.find(".water_from_date").val("");
        row.find(".water_to_date").val("");
        row.find(".water_number_of_days").val("");

        row.find(".water_usage_qty").val("").show().attr("placeholder", "Qty");
        row.find(".water_amount").val("").prop("readonly", false);

        row.find(".water_provision").val("no");

        $("#water_status_msg").html("");
        setQtyHeaderLabel("DEFAULT");
        recalculateWaterTotal();
    }

    /* ======================================================
       CALCULATE NUMBER OF DAYS + VALIDATE DATES
    ====================================================== */
    function updateNumberOfDays(row) {
        const from  = row.find(".water_from_date").val();
        const to    = row.find(".water_to_date").val();
        const $days = row.find(".water_number_of_days");

        if (!from || !to) { $days.val(""); return; }

        const d1 = new Date(from);
        const d2 = new Date(to);

        if (d2 < d1) {
            $("#water_status_msg").html(
                `<div class="alert alert-danger">
                    <strong>Invalid date range:</strong> "To" date cannot be earlier than "From" date.
                </div>`
            );
            row.find(".water_to_date").val("").focus();
            $days.val("");
            return;
        }

        $("#water_status_msg").html("");
        const diff = Math.floor((d2 - d1) / 86400000) + 1;
        $days.val(diff);
    }

    $(document).off("change.waterDates", ".water_from_date, .water_to_date")
        .on("change.waterDates", ".water_from_date, .water_to_date", function () {
            updateNumberOfDays($(this).closest("tr"));
        });

    /* ======================================================
       BRANCH LOOKUP (MULTI CONNECTIONS)
    ====================================================== */
    $(document).on("blur", ".water_branch_code", function () {
        const row = $(this).closest("tr");
        const branch_code = ($(this).val() || "").trim();
        const month = $("#water_month_manual").val();

        if (!branch_code) { clearRow(row); updateConfirmAndSave(); return; }

        if (!month) {
            clearRow(row);
            $("#water_status_msg").html(
                `<div class="alert alert-warning mb-2">
                    Please select a month before entering branch codes.
                </div>`
            );
            updateConfirmAndSave();
            return;
        }

        clearRow(row);

        $.post("ajax-get-water-branch.php", { branch_code, month }, function (b) {

            if (!b.success) {
                row.find(".water_branch_name").val("Not Found");
                row.find(".water_account_number").text("");
                $("#water_status_msg").html(
                    `<div class="alert alert-danger mb-2">
                        ${b.message || 'Branch not found or no water connections linked.'}
                    </div>`
                );
                updateConfirmAndSave();
                return;
            }

            row.find(".water_branch_name").val(b.branch_name);

            const $typeSel = row.find(".water_type");
            $typeSel.prop("disabled", false).empty()
                .append('<option value="">-- Select Type --</option>');

            if (b.all_types_entered || !b.types || !b.types.length) {
                $("#water_status_msg").html(`
                    <div class="alert alert-info mb-2">
                        All water connections for <b>${b.branch_name}</b> (${branch_code}) 
                        are already entered for <b>${month}</b>.<br>
                        Please enter a different branch code.
                    </div>
                `);

                clearRow(row);
                row.find(".water_branch_name")
                    .val(`${b.branch_name} (${branch_code}) - All connections already entered for this month`);

                row.find(".water_branch_code").addClass("is-invalid").focus();
                updateConfirmAndSave();
                return;
            }

            const typeMap = {}; // key = "typeId|connNo"
            b.types.forEach(function (t) {
                const mode = normalizeModeFromString(t.mode || t.water_type_name);
                const conn = (t.connection_no || 1);
                const key  = String(t.water_type_id) + "|" + String(conn);

                typeMap[key] = t;

                let label = `${t.water_type_name} (Conn ${conn})`;

                if (mode === "NWSDB") {
                    const acc = (t.account_number || "").trim();
                    if (acc) label = `${t.water_type_name} (Conn ${conn} - ${acc})`;
                } else if ((t.vendor_name || "").trim()) {
                    label = `${t.water_type_name} (Conn ${conn} - ${t.vendor_name})`;
                }

                // ✅ show provision lines clearly
                if ((t.existing_provision || "").toLowerCase() === "yes") {
                    label += " — PROVISION (edit/convert)";
                }

                $typeSel.append(`<option value="${key}">${label}</option>`);
            });

            row.data("waterTypesMap", typeMap);
            $("#water_status_msg").html("");
            updateConfirmAndSave();

        }, "json");
    });

    $(document).on("input", ".water_branch_code", function () {
        $(this).removeClass("is-invalid");
    });

    /* ======================================================
       TYPE SELECTION
       ✅ default provision = NO (Actual)
       ✅ if existing provision => prefill dates/qty/amount + set provision YES
    ====================================================== */
    $(document).off("change.waterType").on("change.waterType", ".water_type", function () {

        const row     = $(this).closest("tr");
        const typeVal = ($(this).val() || "").trim();

        if (typeVal && isDuplicateBranchTypeRow(row)) {
            handleDuplicateBranchTypeRow(row);
            return;
        }

        const labelSpan = row.find(".water_account_number");
        const hiddenAcc = row.find(".water_account_number_value");
        const qtyInput  = row.find(".water_usage_qty");
        const amtInput  = row.find(".water_amount");

        const typeMap = row.data("waterTypesMap") || {};
        const t = typeMap[typeVal];

        if (!typeVal || !t) {
            row.find(".water_connection_no").val("1");
            labelSpan.text("");
            hiddenAcc.val("");
            qtyInput.val("").show().attr("placeholder", "Qty");
            amtInput.val("").prop("readonly", false);
            row.find(".water_provision").val("no");
            setQtyHeaderLabel("DEFAULT");
            recalculateWaterTotal();
            updateConfirmAndSave();
            return;
        }

        const mode   = normalizeModeFromString(t.mode || t.water_type_name);
        const connNo = (t.connection_no || 1);
        row.find(".water_connection_no").val(String(connNo));

        const account    = (t.account_number || "").trim();
        const vendorName = (t.vendor_name || "").trim();

        if (mode === "NWSDB") {
            if (account) {
                labelSpan.text(`Account: ${account} (Conn ${connNo})`);
                hiddenAcc.val(account);
            } else {
                labelSpan.text(`Account not linked (Conn ${connNo}) – please link.`);
                hiddenAcc.val("");
            }
        } else if (vendorName) {
            labelSpan.text(`Vendor: ${vendorName} (Conn ${connNo})`);
            hiddenAcc.val("");
        } else {
            labelSpan.text(`Conn ${connNo}`);
            hiddenAcc.val("");
        }

        // default = actual
        row.find(".water_provision").val("no");

        // ✅ existing provision prefill
        if ((t.existing_provision || "").toLowerCase() === "yes") {
            row.find(".water_provision").val("yes");

            if (t.prov_from_date) row.find(".water_from_date").val(t.prov_from_date);
            if (t.prov_to_date)   row.find(".water_to_date").val(t.prov_to_date);

            updateNumberOfDays(row);

            if (qtyInput.is(":visible")) {
                if (t.prov_qty !== null && t.prov_qty !== undefined && String(t.prov_qty).trim() !== "") {
                    qtyInput.val(t.prov_qty);
                }
            }

            if (t.prov_amount !== null && t.prov_amount !== undefined && String(t.prov_amount).trim() !== "") {
                amtInput.val(formatMoney(t.prov_amount));
            }
        }

        applyProvisionRules(row);
    });

    /* ======================================================
       Provision dropdown change
    ====================================================== */
    $(document).off("change.waterProvision").on("change.waterProvision", ".water_provision", function () {
        applyProvisionRules($(this).closest("tr"));
    });

    /* ======================================================
       BOTTLE CALCULATION (qty → amount)
       ✅ BUT: if provision = YES, do NOT overwrite amount
    ====================================================== */
    $(document).off("input.waterQty").on("input.waterQty", ".water_usage_qty", function () {

        const row = $(this).closest("tr");

        const prov = (row.find(".water_provision").val() || "no").toLowerCase();
        if (prov === "yes") {
            recalculateWaterTotal();
            updateConfirmAndSave();
            return;
        }

        const rawQty = $(this).val();
        const qty    = parseFloat(rawQty || 0);

        const typeVal = (row.find(".water_type").val() || "").trim();
        const typeMap = row.data("waterTypesMap") || {};
        const t       = typeMap[typeVal] || null;

        const mode = t ? normalizeModeFromString(t.mode || t.water_type_name) : "NWSDB";

        if (!rawQty || isNaN(qty) || qty <= 0) {
            row.find(".water_amount").val("");
            recalculateWaterTotal();
            updateConfirmAndSave();
            return;
        }

        if (mode === "BOTTLE") {
            const rate   = parseFloat(row.data("bottle_rate") || 0);
            const rental = parseFloat(row.data("cooler_rent") || 0);
            const ssclP  = parseFloat(row.data("sscl") || 0);
            const vatP   = parseFloat(row.data("vat")  || 0);

            const total = calcBottleTotal(rate, qty, rental, ssclP, vatP);
            row.find(".water_amount").val(!isNaN(total) ? formatMoney(total) : "");
            recalculateWaterTotal();
            updateConfirmAndSave();
        }
    });

    /* ======================================================
       THOUSAND SEPARATOR FOR MANUAL AMOUNTS
    ====================================================== */
    $(document).on("input", ".water_amount", function () {
        if ($(this).prop("readonly")) {
            recalculateWaterTotal();
            updateConfirmAndSave();
            return;
        }

        let v = $(this).val().replace(/,/g, "");
        if (v.includes("-")) v = v.replace(/-/g, "");

        if (!isNaN(v) && v !== "") {
            let p = v.split(".");
            p[0] = Number(p[0]).toLocaleString("en-US");
            $(this).val(p.join("."));
        } else {
            $(this).val(v);
        }

        recalculateWaterTotal();
        updateConfirmAndSave();
    });

    /* ======================================================
       BLOCK NEGATIVE NUMBERS (GLOBAL)
    ====================================================== */
    $(document).on("input", "input[type='number'], .water_amount, .water_usage_qty", function () {
        let v = $(this).val();
        if (String(v).includes('-')) v = String(v).replace(/-/g, '');
        if (parseFloat(v) < 0) v = "";
        $(this).val(v);

        recalculateWaterTotal();
        updateConfirmAndSave();
    });

    $(document).on("paste", "input[type='number'], .water_amount, .water_usage_qty", function (e) {
        let paste = (e.originalEvent || e).clipboardData.getData('text');
        if (paste.includes('-')) e.preventDefault();
    });

    /* ======================================================
       ENTER KEY: BLOCK NAVIGATION + SHOW WARNING
    ====================================================== */
    $(document).on("keydown", "#water_entry_rows input, #water_entry_rows select", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            $("#water_status_msg").html(`
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    Please use the <strong>Tab</strong> key or the mouse to move between fields.
                    The <strong>Enter</strong> key cannot be used to move to the next cell.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
        }
    });

    /* ======================================================
       COLLECT ALL ROWS + VALIDATE
    ====================================================== */
    function collectRowsForSave(month) {
        const rows = [];
        let errorMsg = "";
        let rowNumberWithError = null;
        const seenKeys = {};

        $("#water_entry_rows tr").each(function (idx) {
            const $r = $(this);

            const branch_code = ($r.find(".water_branch_code").val() || "").trim();
            const branch_name = ($r.find(".water_branch_name").val() || "").trim();
            const typeVal     = ($r.find(".water_type").val() || "").trim();

            const from_date       = ($r.find(".water_from_date").val() || "").trim();
            const to_date         = ($r.find(".water_to_date").val() || "").trim();
            const number_of_days  = ($r.find(".water_number_of_days").val() || "").trim();

            const usage_qty = $r.find(".water_usage_qty").is(":visible")
                ? ($r.find(".water_usage_qty").val() || "").trim()
                : "";

            const amount_raw = ($r.find(".water_amount").val() || "").replace(/,/g, "").trim();
            const provision  = ($r.find(".water_provision").val() || "").trim();

            const hasAny = branch_code || typeVal || from_date || to_date || amount_raw;
            if (!hasAny) return;

            if (!month || !branch_code || !typeVal || !from_date || !to_date || !amount_raw) {
                errorMsg = "Please fill all required fields for each row before saving.";
                rowNumberWithError = idx + 1;
                return false;
            }

            if (seenKeys[branch_code + "|" + typeVal]) {
                errorMsg = "Duplicate branch + type + connection found in more than one row.";
                rowNumberWithError = idx + 1;
                return false;
            }
            seenKeys[branch_code + "|" + typeVal] = true;

            const parts = typeVal.split("|");
            const water_type_id = parts[0] ? parts[0].trim() : "";
            const connection_no = parts[1] ? parts[1].trim() : "1";

            const account_number = ($r.find(".water_account_number_value").val() || "").trim();

            rows.push({
                branch_code,
                branch_name,
                water_type_id,
                connection_no,
                account_number,
                from_date,
                to_date,
                number_of_days,
                usage_qty,
                amount: amount_raw,
                provision
            });
        });

        if (errorMsg) {
            return {
                ok: false,
                message: rowNumberWithError
                    ? `${errorMsg} (First problem at row ${rowNumberWithError}.)`
                    : errorMsg
            };
        }

        if (rows.length === 0) return { ok: false, message: "Please enter at least one row before saving." };
        return { ok: true, rows };
    }

    /* ======================================================
       SEQUENTIAL SAVE OF ALL ROWS
    ====================================================== */
    function saveAllRows(month, rows) {
        let idx = 0;

        function saveNext() {
            if (idx >= rows.length) {
                const m = $("#water_month_manual").val();
                if (m) {
                    $.post("water-monthly-fetch.php", { month: m }, function (r) {
                        updateAlerts(r, m, "#water_missing_manual_branches", "#water_provision_info");
                    }, "json");
                }

                $("#water_status_msg").html(`<div class="alert alert-success">All records saved successfully.</div>`);
                resetWaterForm();
                return;
            }

            const payload = Object.assign({ month }, rows[idx]);

            $.post("water-monthly-save.php", payload, function (res) {
                if (!res || !res.success) {
                    $("#water_status_msg").html(
                        `<div class="alert alert-danger">Row ${idx + 1}: ${res && res.message ? res.message : "Save failed."}</div>`
                    );
                    $("#water_entry_rows input, #water_entry_rows select").prop("disabled", false);
                    updateConfirmAndSave();
                    return;
                }

                idx++;
                saveNext();
            }, "json").fail(function () {
                $("#water_status_msg").html(`<div class="alert alert-danger">Row ${idx + 1}: Network / server error.</div>`);
                $("#water_entry_rows input, #water_entry_rows select").prop("disabled", false);
                updateConfirmAndSave();
            });
        }

        saveNext();
    }

    /* ======================================================
       SAVE ENTRY
    ====================================================== */
    $("#water_save_entry").click(function () {
        const month = $("#water_month_manual").val();
        const confirmOn = $("#water_confirm_checked").is(":checked");

        if (!confirmOn) {
            $("#water_status_msg").html(
                `<div class='alert alert-danger'>Please confirm that you have checked all entries and the total before saving.</div>`
            );
            return;
        }

        const collect = collectRowsForSave(month);
        if (!collect.ok) {
            $("#water_status_msg").html(`<div class='alert alert-danger'>${collect.message}</div>`);
            return;
        }

        $("#water_entry_rows input, #water_entry_rows select").prop("disabled", true);
        $("#water_confirm_checked").prop("disabled", true);
        $("#water_save_entry").prop("disabled", true);

        saveAllRows(month, collect.rows);
    });

    /* ======================================================
       SWITCH + AUTO-UNCHECK ON ANY EDIT
    ====================================================== */
    $(document).on("change", "#water_confirm_checked", function () {
        updateSaveEntryEnabled();
    });

    $(document).on("input change", "#water_entry_rows input, #water_entry_rows select", function (e) {
        if (e.target.id === "water_confirm_checked") return;
        $("#water_confirm_checked").prop("checked", false);
        updateConfirmAndSave();
    });

    /* ======================================================
       INITIAL STATE
    ====================================================== */
    $("#water_confirm_checked").prop("checked", false);
    $("#water_save_entry").data("locked", false);

    if (!$("#water_entry_rows tr").length) {
        $("#water_entry_rows").html(blankWaterRow());
    }

    $("#water_csv_download_container").addClass("d-none");
    recalculateWaterTotal();
    updateConfirmAndSave();
});
