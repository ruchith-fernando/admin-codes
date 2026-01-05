<?php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['name']) || !in_array($_SESSION['user_level'], ['authorizer', 'super-admin'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$logged_user = $_SESSION['name'];
?>
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Pending Request Approvals</h5>
            <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search by item code, description, or branch">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr class="text-nowrap">
                            <th>ID</th>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Requested Qty</th>
                            <th>Available Qty</th>
                            <th>Requested Date</th>
                            <th>Branch</th>
                            <th>Approve Qty</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="approvalTableBody">
                        <!-- Filled by AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Unified Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title" id="statusModalLabel">Status</h5>
      </div>
      <div class="modal-body" id="statusModalBody">Processing...</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" autofocus>OK</button>
      </div>
    </div>
  </div>
</div>

<script>
function loadApprovals(query = '') {
    $.get('search-stock-out-approvals.php', { q: query }, function (data) {
        $('#approvalTableBody').html(data);
    });
}

function showStatusModal(type, message) {
    const $modal = $('#statusModal');
    const $body = $('#statusModalBody');
    const $content = $modal.find('.modal-content');
    const $header = $modal.find('.modal-header');
    const $btn = $modal.find('.btn');

    $content.removeClass('border-success border-danger border-warning');
    $header.removeClass('bg-success bg-danger bg-warning bg-secondary');
    $btn.removeClass('btn-success btn-danger btn-warning btn-secondary');

    if (type === 'approved') {
        $content.addClass('border-success');
        $header.addClass('bg-success');
        $btn.addClass('btn-success');
        $body.text('Stock out request approved.');
    } else if (type === 'rejected') {
        $content.addClass('border-danger');
        $header.addClass('bg-danger');
        $btn.addClass('btn-danger');
        $body.text('Stock out request rejected.');
    } else if (type === 'inadequate') {
        $content.addClass('border-warning');
        $header.addClass('bg-warning');
        $btn.addClass('btn-warning');
        $body.text('Not enough stock available. Please adjust the quantity or check stock.');
    } else {
        $content.addClass('border-secondary');
        $header.addClass('bg-secondary');
        $btn.addClass('btn-secondary');
        $body.text(message || 'Unknown error occurred.');
    }

    const instance = new bootstrap.Modal($modal[0], { backdrop: 'static', keyboard: false });
    instance.show();

    $modal.find('.btn').off('click').on('click', function () {
        instance.hide();
        loadApprovals($('#searchInput').val());
    });
}

const reasons = [
    "Incorrect quantity requested",
    "Exceeds available stock",
    "Branch request error",
    "Duplicate request",
    "Not required anymore",
    "Other"
];

function showReasonDropdown() {
    return new Promise((resolve) => {
        const container = document.createElement('div');
        const select = document.createElement('select');
        select.className = 'form-select';
        select.required = true;
        select.innerHTML = '<option value="">-- Select a reason --</option>' + reasons.map(r => `<option value="${r}">${r}</option>`).join('');
        container.appendChild(select);

        const wrapper = document.createElement('div');
        wrapper.className = 'modal fade';
        wrapper.tabIndex = -1;
        wrapper.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Provide Reason</h5></div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary">Submit</button>
                    </div>
                </div>
            </div>`;
        wrapper.querySelector('.modal-body').appendChild(container);
        document.body.appendChild(wrapper);
        const instance = new bootstrap.Modal(wrapper, { backdrop: 'static', keyboard: false });
        instance.show();

        wrapper.querySelector('.btn-primary').onclick = () => {
            const selected = select.value;
            if (!selected) return;
            instance.hide();
            wrapper.remove();
            resolve(selected);
        };
    });
}

$(document).ready(function () {
    loadApprovals();

    $('#searchInput').on('keyup', function () {
        loadApprovals($(this).val());
    });

    $(document).on('click', '.approve-btn, .reject-btn', async function () {
        const id = $(this).data('id');
        const isReject = $(this).hasClass('reject-btn');
        const action = isReject ? 'reject' : 'approve';
        const qtyInput = $(`.qty-input[data-id="${id}"]`);
        const requestedQty = parseInt($(this).data('qty'));
        const approvedQty = parseInt(qtyInput.val());
        let remarks = '';

        if (isReject || approvedQty !== requestedQty) {
            remarks = await showReasonDropdown();
        }

        $.post('search-stock-out-approvals.php', {
            id: id,
            action: action,
            approved_quantity: approvedQty,
            remarks: remarks
        }, function (response) {
            const trimmed = response.trim();
            if (trimmed === 'approved') {
                showStatusModal('approved');
            } else if (trimmed === 'rejected') {
                showStatusModal('rejected');
            } else if (trimmed.includes('inadequate_stock')) {
                showStatusModal('inadequate');
            } else {
                showStatusModal('', 'Unexpected error: ' + response);
            }
        });
    });
});
</script>
