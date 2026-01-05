(function () {
    // vehicle-approval.js
  // -------- UI helpers --------
  function showSuccessModal(srNumber, actionType) {
    const cls =
      actionType === "approved"
        ? "success"
        : actionType === "deleted"
        ? "danger"
        : "warning";
    const message = `SR Number <b>${srNumber}</b> was <span class="text-${cls}">${actionType}</span> successfully.`;
    $("#success-message").html(message);
    $("#success-modal").modal("show");
  }

  function showErrorModal(message) {
    $("#error-message").html(message || "An unexpected error occurred.");
    $("#error-modal").modal("show");
  }

  // Initialize Bootstrap tooltips safely
  function initTooltips(ctx) {
    const root = ctx || document;
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    // dispose any existing then re-init
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
      try {
        const inst = bootstrap.Tooltip.getInstance(el);
        if (inst) inst.dispose();
      } catch (_) {}
    });
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
      try {
        new bootstrap.Tooltip(el, {
          placement: el.getAttribute("data-bs-placement") || "bottom",
        });
      } catch (_) {}
    });
  }

  // -------- Data loading --------
  function loadType(type, pendingDiv, rejectedDiv) {
    $(pendingDiv).html('<div class="text-muted small">Loading ' + type + '…</div>');
    $(rejectedDiv).html("");

    $.ajax({
      url: "vehicle-approval-fetch.php",
      method: "POST",
      data: { type: type },
      dataType: "json",
      timeout: 20000,
    })
      .done(function (res) {
        const pending =
          res && res.pending
            ? res.pending
            : '<div class="alert alert-warning">No pending data.</div>';
        const rejected =
          res && res.rejected
            ? res.rejected
            : '<div class="alert alert-secondary">No rejected data.</div>';

        $(pendingDiv).html('<h6 class="mb-2">Pending</h6>' + pending);
        $(rejectedDiv).html('<h6 class="mb-2 mt-4">Rejected</h6>' + rejected);
        initTooltips(document);
      })
      .fail(function (xhr) {
        const body =
          xhr && xhr.responseText
            ? $("<div/>").text(xhr.responseText).html()
            : "";
        const msg =
          `<div class="alert alert-danger"><b>Failed to load ${type}.</b>` +
          `<br><small>${xhr.status || ""} ${xhr.statusText || ""}</small>` +
          (body ? `<div class="mt-2 small">${body}</div>` : "") +
          `</div>`;
        $(pendingDiv).html(msg);
        $(rejectedDiv).html("");
      });
  }

  function loadVehicleApprovals() {
    loadType("maintenance", "#maintenancePending", "#maintenanceRejected");
    loadType("service", "#servicePending", "#serviceRejected");
    loadType("license", "#licensePending", "#licenseRejected");
  }
  window.loadVehicleApprovals = loadVehicleApprovals;

  // -------- Modal view / state --------
  let currentId = "",
    currentType = "",
    currentSr = "";

  window.viewApproval = function (id, type, sr) {
    currentId = id;
    currentType = type;
    currentSr = sr;

    $("#sr-number").text(sr);
    $("#approve-btn, #reject-btn").show();
    $("#rejection-section").hide();
    $("#rejection-reason").val("");
    $("#other-reason").hide().val("");

    $.ajax({
      url: "vehicle-approval-view.php",
      method: "POST",
      data: { id, type },
      dataType: "json",
      timeout: 20000,
    })
      .done(function (res) {
        $("#approval-details").html((res && res.html) || "");
        $("#approval-modal").modal("show");
        initTooltips(document.getElementById("approval-modal"));
      })
      .fail(function (xhr) {
        showErrorModal(
          `Failed to load details.<br><small>${xhr.status} ${xhr.statusText}</small>`
        );
      });
  };

  $("#reject-btn").on("click", function () {
    $("#approve-btn, #reject-btn").hide();
    $("#rejection-section").show();
  });

  $("#rejection-reason").on("change", function () {
    $("#other-reason").toggle($(this).val() === "Other");
  });

  $("#confirm-reject-btn").on("click", function () {
    let reason = $("#rejection-reason").val();
    if (!reason) {
      showErrorModal("Please select a rejection reason.");
      return;
    }
    if (reason === "Other") {
      reason = $("#other-reason").val().trim();
      if (!reason) {
        showErrorModal("Please enter the reason in the text box.");
        return;
      }
    }
    $.ajax({
      url: "vehicle-approval-actions.php",
      method: "POST",
      data: {
        action: "reject",
        id: currentId,
        type: currentType,
        reason,
      },
      dataType: "json",
      timeout: 20000,
    })
      .done(function (res) {
        if (res && res.status === "success") {
          $("#approval-modal").modal("hide");
          showSuccessModal(currentSr, "rejected");
          loadVehicleApprovals();
        } else {
          showErrorModal((res && res.message) || "Error rejecting the record.");
        }
      })
      .fail(function (xhr) {
        showErrorModal(
          `Reject failed.<br><small>${xhr.status} ${xhr.statusText}</small>`
        );
      });
  });

  $("#approve-btn").on("click", function () {
    $.ajax({
      url: "vehicle-approval-actions.php",
      method: "POST",
      data: {
        action: "approve",
        id: currentId,
        type: currentType,
      },
      dataType: "json",
      timeout: 20000,
    })
      .done(function (res) {
        if (res && res.status === "success") {
          $("#approval-modal").modal("hide");
          showSuccessModal(currentSr, "approved");
          loadVehicleApprovals();
        } else {
          showErrorModal((res && res.message) || "Error approving the record.");
        }
      })
      .fail(function (xhr) {
        showErrorModal(
          `Approve failed.<br><small>${xhr.status} ${xhr.statusText}</small>`
        );
      });
  });

  // -------- Delete flow --------
  let deleteId = null,
    deleteType = null,
    deleteSr = null;

  window.deleteApproval = function (id, type, sr) {
    deleteId = id;
    deleteType = type;
    deleteSr = sr;
    $("#deleteModal").modal("show");
  };

  $("#confirmDeleteBtn").on("click", function () {
    if (!deleteId || !deleteType) return;
    $.ajax({
      url: "vehicle-approval-actions.php",
      method: "POST",
      data: {
        action: "delete",
        id: deleteId,
        type: deleteType,
      },
      dataType: "json",
      timeout: 20000,
    })
      .done(function (res) {
        if (res && res.status === "success") {
          $("#deleteModal").modal("hide");
          showSuccessModal(deleteSr, "deleted");
          loadVehicleApprovals();
        } else {
          showErrorModal((res && res.message) || "Error deleting the record.");
        }
      })
      .fail(function (xhr) {
        showErrorModal(
          `Delete failed.<br><small>${xhr.status} ${xhr.statusText}</small>`
        );
      });
  });

  // Initial load
  $(document).ready(function () {
    loadVehicleApprovals();
    initTooltips(document);
  });
})();

