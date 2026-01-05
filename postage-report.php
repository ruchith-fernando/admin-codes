<div class="content font-size">
    <div class="container-fluid">
        <div class="card p-4 shadow-sm">
            <h5 class="mb-4 text-primary">Postage & Stamps Report</h5>

            <div id="postageReportContent">
                <div class="text-center">
                    <div class="spinner-border text-primary"></div>
                    <div>Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#postageReportContent').load('ajax-postage-report.php');
});
</script>
