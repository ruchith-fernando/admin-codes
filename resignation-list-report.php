<!-- resignation-list-report.php -->
<?php
include 'connections/connection.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 25;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$offset = ($page - 1) * $limit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resignation List Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container">
        <div class="card shadow bg-white rounded p-4">
            <h2 class="mb-4">Resignation List Report</h2>
            <div class="mb-3 d-flex gap-2 flex-wrap">
                <input type="text" id="searchInput" class="form-control" placeholder="Search HRIS, Name, NIC, Branch" style="max-width: 600px;">
            </div>
            <input type="hidden" id="searchHidden" value="">
            <div id="tableContainer">
                <?php include 'resignation-list-table.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Resignation Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalBodyContent">Loading details...</div>
      <div class="modal-footer">
        <div id="disconnectContainer" class="me-auto"></div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function loadTable(page = 1, search = '') {
    $.get('resignation-list-table.php', { page, search }, function(data) {
        $('#tableContainer').html(data);
    });
}

$(document).ready(function () {
    $('#searchInput').on('keyup', function () {
        const search = $(this).val();
        $('#searchHidden').val(search);
        loadTable(1, search);
    });

    $('#tableContainer').on('click', '.pagination .page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        const search = $('#searchHidden').val();
        loadTable(page, search);
    });

    $('#tableContainer').on('click', '.table-row', function () {
        const row = $(this).data();
        const labels = {
            hris: 'HRIS', name: 'Name', nic: 'NIC', designation: 'Designation',
            department: 'Department', branch: 'Branch', doj: 'Date of Joining',
            category: 'Category', type: 'Employment Type', effective: 'Effective Date',
            resigtype: 'Resignation Type', reason: 'Reason', mobile: 'Mobile Number', voice_data: 'Voice / Data'
        };

        let html = '<div class="row">';
        for (const key in labels) {
            html += `<div class="col-md-6 mb-2"><strong>${labels[key]}:</strong><br><span>${row[key] ?? ''}</span></div>`;
        }
        html += '</div>';

        $('#modalBodyContent').html(html);
        $('#disconnectContainer').html(`
            <button class="btn btn-danger" id="disconnectBtn" 
                data-numbers="${row.mobile}" data-hris="${row.hris}">
                Disconnect Connection
            </button>`);

        const modal = new bootstrap.Modal(document.getElementById('detailModal'), { backdrop: 'static' });
        modal.show();
    });

    $(document).on('click', '#disconnectBtn', function () {
        const mobile = $(this).data('numbers');
        const hris = $(this).data('hris');

        if (!mobile || !hris) {
            alert('Missing mobile or HRIS data.');
            return;
        }

        if (confirm('Are you sure you want to disconnect this number?\n' + mobile)) {
            $.post('disconnect-connections.php', {
                mobile_number: mobile,
                hris: hris
            }, function (response) {
                alert(response);
                const modal = bootstrap.Modal.getInstance(document.getElementById('detailModal'));
                if (modal) modal.hide();
                const search = $('#searchHidden').val();
                loadTable(1, search);
            }).fail(function(xhr, status, error) {
                alert("Error: " + error);
            });
        }
    });
});
</script>
</body>
</html>