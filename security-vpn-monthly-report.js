// security-vpn-monthly-report.js (FULL DROP-IN)
// Works with your current HTML IDs: vpn_month_view / vpn_month_manual / vpn_report_section / vpn_manual_form

$(document).ready(function () {

  /* -----------------------------
     Small helpers (keep it simple)
  ------------------------------*/
  function closeReportSide() {
    $("#vpn_report_section").addClass("d-none").html("");
    $("#vpn_csv_download_container").addClass("d-none");
  }

  function closeManualSide() {
    $("#vpn_manual_form").addClass("d-none");
    $("#vpn_selected_month").text("");
    $(".vpn_amount").val("");
    $(".vpn_provision").val("no");
    $(".vpn_provision_reason").val("");
  }

  function clearStatus() {
    $("#vpn_status_msg").html("");
  }

  /* -----------------------------
     VIEW REPORT
     - If user opens report => manual must close
  ------------------------------*/
  $("#vpn_month_view").on("change", function () {
    const month = ($(this).val() || "").trim();

    // close manual side always when switching view
    $("#vpn_month_manual").val("");
    closeManualSide();
    clearStatus();

    // reset report area first
    closeReportSide();

    if (!month) return;

    $("#vpn_status_msg").html("Loading...");

    $.ajax({
      url: "security-vpn-monthly-report.php?ajax=fetch",
      type: "POST",
      dataType: "json",
      data: { month },
      success: function (res) {
        if (res.table) {
          $("#vpn_report_section").removeClass("d-none").html(res.table);
          $("#vpn_csv_download_container").removeClass("d-none");
          clearStatus();
        } else if (res.error) {
          closeReportSide();
          $("#vpn_status_msg").html(`<div class="alert alert-warning">${res.error}</div>`);
        } else {
          $("#vpn_status_msg").html(`<div class="alert alert-danger">Unexpected response.</div>`);
        }
      },
      error: function (xhr) {
        $("#vpn_status_msg").html(
          `<div class="alert alert-danger">AJAX ${xhr.status}: ${xhr.statusText}</div>`
        );
      }
    });
  });

  /* -----------------------------
     MANUAL ENTRY
     - If user opens manual => report must close
  ------------------------------*/
  $("#vpn_month_manual").on("change", function () {
    const month = ($(this).val() || "").trim();

    // close report side always when switching manual
    $("#vpn_month_view").val("");
    closeReportSide();
    clearStatus();

    if (!month) {
      closeManualSide();
      return;
    }

    $("#vpn_selected_month").text(month);
    $("#vpn_manual_form").removeClass("d-none");
  });

  /* -----------------------------
     SAVE
  ------------------------------*/
  $("#vpn_save_entry").on("click", function () {
    const month = ($("#vpn_month_manual").val() || "").trim();
    const amount = ($(".vpn_amount").val() || "").trim();
    const provision = $(".vpn_provision").val();
    const provision_reason = ($(".vpn_provision_reason").val() || "").trim();

    if (!month || !amount) {
      $("#vpn_status_msg").html(
        `<div class="alert alert-danger">Month and amount are required.</div>`
      );
      return;
    }

    $.ajax({
      url: "security-vpn-monthly-report.php?ajax=save",
      type: "POST",
      dataType: "json",
      data: { month, amount, provision, provision_reason },
      success: function (res) {
        if (res.success) {
          $("#vpn_status_msg").html(`<div class="alert alert-success">Saved successfully.</div>`);
        } else {
          $("#vpn_status_msg").html(
            `<div class="alert alert-danger">${res.message || "Save failed"}</div>`
          );
        }
      },
      error: function (xhr) {
        $("#vpn_status_msg").html(
          `<div class="alert alert-danger">AJAX ${xhr.status}: ${xhr.statusText}</div>`
        );
      }
    });
  });

  /* -----------------------------
     CSV DOWNLOAD (unchanged logic)
  ------------------------------*/
  $("#vpn_download_csv_btn").on("click", function () {
    const table = $("#vpn_report_section table");
    if (!table.length) return;

    let csv = [];
    table.find("tr").each(function () {
      let row = [];
      $(this).find("th,td").each(function () {
        let text = $(this).text().trim().replace(/"/g, '""');
        row.push(`"${text}"`);
      });
      csv.push(row.join(","));
    });

    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const month = $("#vpn_month_view").val()
      ? $("#vpn_month_view").val().replace(/\s+/g, "_")
      : "Month";

    link.href = URL.createObjectURL(blob);
    link.download = `VPN_Report_${month}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

});
