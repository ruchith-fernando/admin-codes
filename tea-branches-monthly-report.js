$(document).ready(function () {

  /* ==========================
     TEMPLATE ROW
  ========================== */
  const blankRow = () => `
    <tr>
      <td>
        <input type="text" class="form-control tea_branches_branch_code" maxlength="10" />
      </td>
      <td>
        <input type="text" class="form-control tea_branches_branch_name" readonly />
        <small class="text-muted tea_branches_branch_note"></small>
      </td>
      <td>
        <input type="text" class="form-control tea_branches_amount" placeholder="Amount" />
      </td>
      <td>
        <select class="form-select tea_branches_provision">
          <option value="no" selected>No</option>
          <option value="yes">Yes</option>
        </select>
      </td>
      <td>
        <input type="text" class="form-control tea_branches_provision_reason" placeholder="Optional" />
      </td>
    </tr>
  `;

  /* ==========================
     TOTAL + CONFIRM (Water style)
  ========================== */
  (function addTotalBoxOnce() {
    if ($("#tea_branches_total_row").length) return;

    const totalHtml = `
      <div class="row mt-3 align-items-center" id="tea_branches_total_row">
        <div class="col-md-6 text-end">
          <div class="form-check form-switch d-inline-flex align-items-center">
            <input class="form-check-input" type="checkbox" id="tea_branches_confirm_checked">
            <label class="form-check-label ms-2" for="tea_branches_confirm_checked">
              I have checked all entries and total.
            </label>
          </div>
        </div>
        <div class="col-md-3 text-end">
          <label class="col-form-label fw-bold mb-4">Total Amount:</label>
        </div>
        <div class="col-md-3">
          <input type="text" id="tea_branches_total_amount" class="form-control" readonly>
        </div>
      </div>
    `;

    $("#tea_branches_manual_form table").after(totalHtml);
  })();

  /* ==========================
     HELPERS
  ========================== */
  function formatMoney(val) {
    const n = parseFloat(String(val ?? "").replace(/,/g, ""));
    if (isNaN(n)) return "";
    return n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function rowHasAnyData($r) {
    const code = ($r.find(".tea_branches_branch_code").val() || "").trim();
    const amt  = ($r.find(".tea_branches_amount").val() || "").trim();
    const provR= ($r.find(".tea_branches_provision_reason").val() || "").trim();
    return !!(code || amt || provR);
  }

  function hasAnyDataRow() {
    let has = false;
    $("#tea_branches_entry_rows tr").each(function () {
      if (rowHasAnyData($(this))) { has = true; return false; }
    });
    return has;
  }

  function recalcTotal() {
    let total = 0;
    $("#tea_branches_entry_rows tr").each(function () {
      let v = ($(this).find(".tea_branches_amount").val() || "").toString().replace(/,/g, "").trim();
      if (!v) return;
      const n = parseFloat(v);
      if (!isNaN(n)) total += n;
    });
    $("#tea_branches_total_amount").val(total ? formatMoney(total) : "");
  }

  function updateSaveEnabled() {
    const locked = $("#tea_branches_save_entry").data("locked") === true;
    const checked = $("#tea_branches_confirm_checked").is(":checked");
    $("#tea_branches_save_entry").prop("disabled", locked || !checked);

    if (locked) {
      $("#tea_branches_save_entry").addClass("btn-secondary disabled")
        .removeClass("btn-success").text("Editing Locked");
    } else {
      $("#tea_branches_save_entry").removeClass("btn-secondary disabled")
        .addClass("btn-success").text("Save Entry");
    }
  }

  function updateConfirmAndSave() {
    const has = hasAnyDataRow();
    if (!has) $("#tea_branches_confirm_checked").prop("checked", false).prop("disabled", true);
    else $("#tea_branches_confirm_checked").prop("disabled", false);
    updateSaveEnabled();
  }

  function resetForm() {
    $("#tea_branches_entry_rows").html(blankRow());
    $("#tea_branches_status_msg").html("");
    $("#tea_branches_provision_info").addClass("d-none").html("");
    $("#tea_branches_confirm_checked").prop("checked", false);
    $("#tea_branches_save_entry").data("locked", false);
    recalcTotal();
    updateConfirmAndSave();
  }

  /* ==========================
     ALERTS (Already done in your side)
     keep your existing updateAlerts()
  ========================== */
  // keep your updateAlerts() function here (your latest version)

  /* ==========================
     VIEW DROPDOWN (one open at a time)
  ========================== */
  $("#tea_branches_month_view").change(function () {
    resetForm();
    const month = $(this).val();

    // close manual side
    $("#tea_branches_month_manual").val("");
    $("#tea_branches_manual_form").addClass("d-none");
    $("#tea_branches_missing_manual_branches").addClass("d-none").html("");

    if (month) {
      $("#tea_branches_report_section").addClass("d-none").html("");
      $("#tea_branches_missing_view_branches").removeClass("d-none").html("Loading...");

      $.post("tea-branches-monthly-fetch.php", { month }, function (res) {
        $("#tea_branches_report_section").removeClass("d-none").html(res.table || "");
        $("#tea_branches_csv_download_container").removeClass("d-none");
        updateAlerts(res, month, "#tea_branches_missing_view_branches", "#tea_branches_provision_info");
      }, "json");
    } else {
      $("#tea_branches_report_section").addClass("d-none").html("");
      $("#tea_branches_missing_view_branches").addClass("d-none").html("");
      $("#tea_branches_csv_download_container").addClass("d-none");
    }
  });

  /* ==========================
     MANUAL DROPDOWN (one open at a time)
  ========================== */
  $("#tea_branches_month_manual").change(function () {
    resetForm();
    const month = $(this).val();

    // close view side
    $("#tea_branches_month_view").val("");
    $("#tea_branches_report_section").addClass("d-none").html("");
    $("#tea_branches_missing_view_branches").addClass("d-none").html("");

    if (month) {
      $("#tea_branches_manual_form").removeClass("d-none");
      $("#tea_branches_missing_manual_branches").removeClass("d-none").html("Loading...");

      $.post("tea-branches-monthly-fetch.php", { month }, function (res) {
        updateAlerts(res, month, "#tea_branches_missing_manual_branches", "#tea_branches_provision_info");
      }, "json");
    } else {
      $("#tea_branches_manual_form").addClass("d-none");
      $("#tea_branches_missing_manual_branches").addClass("d-none").html("");
      $("#tea_branches_provision_info").addClass("d-none").html("");
    }
  });

  /* ==========================
     ADD ROW (Water style)
  ========================== */
  $("#tea_branches_add_row").on("click", function () {
    if ($("#tea_branches_save_entry").data("locked") === true) return;

    const $tbody = $("#tea_branches_entry_rows");
    const $last = $tbody.find("tr").last();

    if ($last.length && !rowHasAnyData($last)) {
      $last.find(".tea_branches_branch_code").focus();
      return;
    }

    $tbody.append(blankRow());
    $("#tea_branches_confirm_checked").prop("checked", false);
    recalcTotal();
    updateConfirmAndSave();
  });

  /* ==========================
     DUPLICATE CHECK (same branch in same save batch)
  ========================== */
  function isDuplicateBranchInForm($row) {
    const code = ($row.find(".tea_branches_branch_code").val() || "").trim();
    if (!code) return false;

    let dup = false;
    $("#tea_branches_entry_rows tr").each(function () {
      if (this === $row[0]) return;
      const c2 = ($(this).find(".tea_branches_branch_code").val() || "").trim();
      if (c2 && c2 === code) { dup = true; return false; }
    });
    return dup;
  }

  /* ==========================
     BRANCH CODE BLUR (existing/provision logic same as before)
  ========================== */
  $(document).on("blur", ".tea_branches_branch_code", function () {
    const $row = $(this).closest("tr");
    const branch_code = ($(this).val() || "").trim();
    const month = $("#tea_branches_month_manual").val();

    $row.find(".tea_branches_branch_note").text("");
    $(this).removeClass("is-invalid");

    if (!branch_code) {
      $row.find(".tea_branches_branch_name").val("");
      recalcTotal(); updateConfirmAndSave();
      return;
    }

    if (!month) {
      $("#tea_branches_status_msg").html(`<div class='alert alert-warning'>Please select a month first.</div>`);
      return;
    }

    if (isDuplicateBranchInForm($row)) {
      $(this).addClass("is-invalid");
      $row.find(".tea_branches_branch_name").val("");
      $("#tea_branches_status_msg").html(`<div class='alert alert-danger'>This branch code is already entered in another row.</div>`);
      return;
    }

    $.post("ajax-get-existing-tea-branches.php", { branch_code, month }, function (res) {

      // If a finalized record exists -> block entry (same behavior as Water “all connections entered”)
      if (res.exists && String(res.is_provision || "no").toLowerCase() === "no") {
        $row.find(".tea_branches_branch_name").val(res.branch || "");
        $row.find(".tea_branches_amount").val("").prop("readonly", false);
        $row.find(".tea_branches_provision").val("no").prop("disabled", false);
        $row.find(".tea_branches_provision_reason").val("").prop("readonly", false);

        $row.find(".tea_branches_branch_note").text("Finalized entry already exists for this month.");

        $row.find(".tea_branches_branch_code").val("").addClass("is-invalid").focus();

        $("#tea_branches_status_msg").html(
          `<div class='alert alert-danger'>Finalized entry already exists for this branch in <b>${month}</b>. Please enter a different branch.</div>`
        );

        recalcTotal(); updateConfirmAndSave();
        return;
      }

      // Provision exists -> allow finalize/edit
      if (res.exists && String(res.is_provision || "no").toLowerCase() === "yes") {
        $row.find(".tea_branches_branch_name").val(res.branch || "").prop("readonly", true);
        $row.find(".tea_branches_amount").val(formatMoney(res.total_amount || "")).prop("readonly", false);
        $row.find(".tea_branches_provision").val("yes").prop("disabled", false);
        $row.find(".tea_branches_provision_reason").val(res.provision_reason || "").prop("readonly", false);

        $row.find(".tea_branches_branch_note").text("Provision entry — you can finalize (set Provision = No and save).");

        $("#tea_branches_status_msg").html(
          `<div class='alert alert-warning'>Provision entry loaded — you can finalize.</div>`
        );

        recalcTotal(); updateConfirmAndSave();
        return;
      }

      // No record exists -> fetch branch name
      $.post("ajax-get-tea-branches-branch.php", { branch_code }, function (b) {
        if (b.status === "success") {
          $row.find(".tea_branches_branch_name").val(b.data.branch_name).prop("readonly", true);
        } else {
          $row.find(".tea_branches_branch_name").val("Not Found").prop("readonly", true);
        }
        updateConfirmAndSave();
      }, "json");

      $row.find(".tea_branches_amount").val("").prop("readonly", false);
      $row.find(".tea_branches_provision").val("no").prop("disabled", false);
      $row.find(".tea_branches_provision_reason").val("").prop("readonly", false);
      $("#tea_branches_status_msg").html("");

      recalcTotal(); updateConfirmAndSave();

    }, "json");
  });

  /* ==========================
     AMOUNT FORMATTING + TOTAL
  ========================== */
  $(document).on("input", ".tea_branches_amount", function () {
    if ($(this).prop("readonly")) { recalcTotal(); updateConfirmAndSave(); return; }

    let v = ($(this).val() || "").toString().replace(/,/g, "");
    if (v.includes("-")) v = v.replace(/-/g, "");
    if (v === "") { $(this).val(""); recalcTotal(); updateConfirmAndSave(); return; }

    if (!isNaN(v)) {
      const parts = v.split(".");
      parts[0] = Number(parts[0] || 0).toLocaleString("en-US");
      if (parts.length > 1) parts[1] = parts[1].slice(0, 2);
      $(this).val(parts.join("."));
    }

    recalcTotal();
    updateConfirmAndSave();
  });

  /* Block negatives everywhere */
  $(document).on("input", "input[type='number'], .tea_branches_amount", function () {
    let v = String($(this).val() || "");
    if (v.includes("-")) v = v.replace(/-/g, "");
    $(this).val(v);
    recalcTotal();
    updateConfirmAndSave();
  });

  /* ==========================
     AUTO-UNCHECK CONFIRM ON ANY EDIT
  ========================== */
  $(document).on("change", "#tea_branches_confirm_checked", function () {
    updateSaveEnabled();
  });

  $(document).on("input change", "#tea_branches_entry_rows input, #tea_branches_entry_rows select", function (e) {
    if (e.target.id === "tea_branches_confirm_checked") return;
    $("#tea_branches_confirm_checked").prop("checked", false);
    updateConfirmAndSave();
  });

  /* ==========================
     Alerts
    ========================== */
  function updateAlerts(res, month, alertSel, provSel) {

    const cleanArr = (arr) => (arr || []).map(x => String(x).replace(/<br\s*\/?>/gi, " ").trim());
    const commaList = (arr) => cleanArr(arr).join(", ");

    let out = "";

    if (res.pending?.length) {
      out += `<b>${res.pending.length} pending for <b>${month}</b>:</b><br><br>${commaList(res.pending)}`;
    }

    if (res.missing?.length) {
      out += (out ? "<br><br>" : "");
      out += `<b>${res.missing.length} missing for <b>${month}</b>:</b><br><br>${commaList(res.missing)}`;
    }

    if (res.provisions?.length) {
      out += (out ? "<br><br>" : "");
      out += `<b>${res.provisions.length} provisional for <b>${month}</b>:</b><br><br>${commaList(res.provisions)}`;
    }

    if (res.pending_count && Number(res.pending_count) > 0) {
      out += (out ? "<br><br>" : "");
      out += `<hr class="my-2">`;
      out += `<span class="text-danger fw-bold">
                Additionally, ${res.pending_count} branches have submitted tea charges that are still pending approval for ${month}.
              </span>`;
    }

    if (out) $(alertSel).removeClass("d-none").html(out);
    else $(alertSel).addClass("d-none").html("");
  }

  /* ==========================
     COLLECT ROWS FOR SAVE
    ========================== */
    function collectRowsForSave(month) {
    const rows = [];
    const seen = {};
    let errorMsg = "";
    let rowNumberWithError = null;

    $("#tea_branches_entry_rows tr").each(function (idx) {
      const $r = $(this);

      const branch_code = ($r.find(".tea_branches_branch_code").val() || "").trim();
      const branch_name = ($r.find(".tea_branches_branch_name").val() || "").trim();
      const amount_raw  = ($r.find(".tea_branches_amount").val() || "").replace(/,/g, "").trim();
      const provision   = ($r.find(".tea_branches_provision").val() || "no").trim();
      const reason      = ($r.find(".tea_branches_provision_reason").val() || "").trim();

      const hasAny = branch_code || amount_raw || reason;
      if (!hasAny) return;

      if (!month || !branch_code || !branch_name || branch_name === "Not Found" || !amount_raw) {
        errorMsg = "Please fill all required fields for each row before saving.";
        rowNumberWithError = idx + 1;
        return false;
      }

      if (seen[branch_code]) {
        errorMsg = "Duplicate branch code found in more than one row.";
        rowNumberWithError = idx + 1;
        return false;
      }
      seen[branch_code] = true;

      const amt = parseFloat(amount_raw);
      if (isNaN(amt) || amt <= 0) {
        errorMsg = "Amount must be a valid number > 0.";
        rowNumberWithError = idx + 1;
        return false;
      }

      rows.push({
        branch_code,
        branch_name,
        amount: amount_raw,
        provision,
        provision_reason: reason
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

    if (rows.length === 0) {
      return { ok: false, message: "Please enter at least one row before saving." };
    }

    return { ok: true, rows };
  }


  /* ==========================
     SAVE ALL ROWS (sequential)
  ========================== */
  function saveAllRows(month, rows) {
    let i = 0;

    function next() {
      if (i >= rows.length) {
        $("#tea_branches_status_msg").html(`<div class="alert alert-success">All records saved successfully.</div>`);

        // refresh alerts
        $.post("tea-branches-monthly-fetch.php", { month }, function (r2) {
          updateAlerts(r2, month, "#tea_branches_missing_manual_branches", "#tea_branches_provision_info");
        }, "json");

        resetForm();
        return;
      }

      const payload = Object.assign({ month }, rows[i]);

      $.post("tea-branches-monthly-save.php", payload, function (res) {
        if (!res || res.status !== "success") {
          $("#tea_branches_status_msg").html(
            `<div class="alert alert-danger">Row ${i + 1}: ${res && res.message ? res.message : "Save failed."}</div>`
          );
          $("#tea_branches_entry_rows input, #tea_branches_entry_rows select").prop("disabled", false);
          $("#tea_branches_confirm_checked").prop("disabled", false);
          updateConfirmAndSave();
          return;
        }

        i++;
        next();
      }, "json").fail(function () {
        $("#tea_branches_status_msg").html(`<div class="alert alert-danger">Row ${i + 1}: Network / server error.</div>`);
        $("#tea_branches_entry_rows input, #tea_branches_entry_rows select").prop("disabled", false);
        $("#tea_branches_confirm_checked").prop("disabled", false);
        updateConfirmAndSave();
      });
    }

    next();
  }

  /* ==========================
     SAVE ENTRY (water style gated by confirm)
  ========================== */
  $("#tea_branches_save_entry").click(function () {
    const month = $("#tea_branches_month_manual").val();
    const confirmed = $("#tea_branches_confirm_checked").is(":checked");

    if (!confirmed) {
      $("#tea_branches_status_msg").html(`<div class='alert alert-danger'>Please confirm that you have checked all entries and the total before saving.</div>`);
      return;
    }

    const collected = collectRowsForSave(month);
    if (!collected.ok) {
      $("#tea_branches_status_msg").html(`<div class='alert alert-danger'>${collected.message}</div>`);
      return;
    }

    // lock UI while saving
    $("#tea_branches_entry_rows input, #tea_branches_entry_rows select").prop("disabled", true);
    $("#tea_branches_confirm_checked").prop("disabled", true);
    $("#tea_branches_save_entry").prop("disabled", true);

    saveAllRows(month, collected.rows);
  });

  /* ==========================
     INITIAL
  ========================== */
  resetForm();
});
