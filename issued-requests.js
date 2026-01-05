// File: assets/js/issued-requests.js
$(document).ready(function () {
    function loadRequests(type, targetDiv) {
        $.ajax({
            url: 'fetch-issued-requests.php',
            type: 'POST',
            data: { request_type: type },
            beforeSend: function () {
                $(targetDiv).html('<div class="text-muted">Loading...</div>');
            },
            success: function (data) {
                $(targetDiv).html(data);
            },
            error: function () {
                $(targetDiv).html('<div class="text-danger">Error loading data.</div>');
            }
        });
    }

    // Load daily courier by default
    loadRequests('daily_courier', '#dailyCourier');

    // Tab click handlers
    $('#courier-tab').on('click', function () {
        loadRequests('daily_courier', '#dailyCourier');
    });

    $('#stationary-tab').on('click', function () {
        loadRequests('stationery_pack', '#stationaryPack');
    });
});
