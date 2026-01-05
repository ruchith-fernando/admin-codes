<?php
$recordMonth = isset($_GET['record_month']) ? urlencode($_GET['record_month']) : '';
?>

<div class="content font-size" id="contentArea">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Photocopy Actual Entry</h5>

            <div id="photocopyBody">
                <div class="text-center">
                    <div class="spinner-border text-primary"></div>
                    <p>Loading form...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    const recordMonth = "<?= $recordMonth ?>";
    $('#photocopyBody').load('ajax-photocopy-entry.php?record_month=' + encodeURIComponent(recordMonth));
});
</script>
