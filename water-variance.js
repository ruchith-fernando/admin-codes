$(document).ready(function () {

    $("#variance_month").change(function () {

        const month = $(this).val();

        $("#variance_report_section").addClass("d-none").html("");
        $("#variance_loading").removeClass("d-none").html("Loading...");

        if (!month) {
            $("#variance_loading").addClass("d-none");
            return;
        }

        $.post("water-variance-fetch.php", { month }, function (res) {

            $("#variance_loading").addClass("d-none");

            $("#variance_report_section")
                .removeClass("d-none")
                .html(res.table || "<div class='alert alert-warning'>No data found.</div>");

        }, "json");

    });

});
