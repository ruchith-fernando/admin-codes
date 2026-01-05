// photocopy-monthly-report.js
$(document).ready(function () {

  $("#pc_month_view").on("change", function () {
    const month = $(this).val(); // YYYY-MM-01

    $("#pc_report_section").addClass("d-none").html("");

    if (!month) return;

    $.post("photocopy-monthly-fetch.php", { month }, function (res) {
      if (res && res.error) {
        $("#pc_report_section").removeClass("d-none").html(
          `<div class="alert alert-danger">${res.error}</div>`
        );
        return;
      }
      $("#pc_report_section").removeClass("d-none").html(res.table || "");
    }, "json").fail(function () {
      $("#pc_report_section").removeClass("d-none").html(
        `<div class="alert alert-danger">Server error while generating report.</div>`
      );
    });
  });

});


$(document).ready(function () {

  function toggleCsvBtn() {
    const m = $("#pc_month_view").val();
    $("#pc_download_csv_btn").prop("disabled", !m);
  }

  toggleCsvBtn();

  $("#pc_month_view").on("change", function () {
    toggleCsvBtn();

    const month = $(this).val();
    $("#pc_report_section").addClass("d-none").html("");
    if (!month) return;

    $.post("photocopy-monthly-fetch.php", { month }, function (res) {
      if (res && res.error) {
        $("#pc_report_section").removeClass("d-none").html(
          `<div class="alert alert-danger">${res.error}</div>`
        );
        return;
      }
      $("#pc_report_section").removeClass("d-none").html(res.table || "");
    }, "json");
  });

  $("#pc_download_csv_btn").on("click", function () {
    const month = $("#pc_month_view").val();
    if (!month) { alert("Please select a month first."); return; }
    window.location.href = "photocopy-monthly-export.php?month=" + encodeURIComponent(month);
  });

});
