<!-- File: issued-requests.php -->
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-3 text-primary">Issued Requests</h5>

            <ul class="nav nav-tabs" id="requestTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="courier-tab" data-toggle="tab" href="#dailyCourier" role="tab">Daily Courier</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="stationary-tab" data-toggle="tab" href="#stationaryPack" role="tab">Stationary Pack</a>
                </li>
            </ul>

            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="dailyCourier" role="tabpanel"></div>
                <div class="tab-pane fade" id="stationaryPack" role="tabpanel"></div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="assets/js/issued-requests.js"></script>
