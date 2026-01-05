<div class="content font-size">
    <div class="container-fluid">
        <div class="card p-4 shadow-sm">
            <h5 class="mb-4 text-primary">Branch Contract Records</h5>

            <div class="mb-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by Branch or Lease Number...">
            </div>

            <div id="tableContainer">
                <div class="text-muted">Loading records...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="contractModal" tabindex="-1" aria-labelledby="contractModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-primary" id="contractModalLabel">Branch Contract Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalBodyContent">Loading details...</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Upload Successful</h5>
        <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Contract version uploaded successfully.
      </div>
    </div>
  </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="errorModalLabel">Upload Failed</h5>
        <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        An error occurred while uploading. Please try again.
      </div>
    </div>
  </div>
</div>

<script>
function loadTable(search = '', page = 1) {
    $('#tableContainer').load('ajax-branch-contracts-report.php?page=' + page + '&search=' + encodeURIComponent(search));
}

$(document).ready(function () {
    // Initial table load
    loadTable();

    // Live search with delay
    $('#searchInput').on('keyup', function () {
        clearTimeout($.data(this, 'timer'));
        const wait = setTimeout(() => {
            const keyword = $('#searchInput').val();
            loadTable(keyword);
        }, 500);
        $(this).data('timer', wait);
    });

    // Delegate: View contract details
    $(document).on('click', '.view-details', function () {
        const id = $(this).data('id');
        $.get('fetch-contract-details.php', { id }, function (html) {
            $('#modalBodyContent').html(html);
            const modal = new bootstrap.Modal(document.getElementById('contractModal'), {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        });
    });

    // Delegate: Pagination click
    $(document).on('click', '.pagination a.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        const keyword = $('#searchInput').val();
        loadTable(keyword, page);
    });

    // Delegate: Upload form submit
    $(document).on('submit', '#uploadForm', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        $.ajax({
            url: 'upload-contract-version.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                successModal.show();
                setTimeout(() => {
                    successModal.hide();
                    $('#contractModal').modal('hide');
                    loadTable($('#searchInput').val());
                }, 2000);
            },
            error: function () {
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                errorModal.show();
            }
        });
    });
});
</script>

