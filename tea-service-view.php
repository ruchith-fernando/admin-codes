<!-- tea-service.php -->
<div class="content font-size">
    <div class="container-fluid">
        <div class="card p-4 shadow-sm">
            <h5 class="mb-4 text-primary">Admin Tea Service Monthly View</h5>
            <div id="teaServiceContent">
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
    // Load the view section dynamically
    $('#teaServiceContent').load('ajax-tea-service-view.php');
});
</script>
