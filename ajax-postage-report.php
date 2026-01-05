<?php
include 'connections/connection.php';
$result = $conn->query("SELECT * FROM tbl_admin_actual_postage_stamps ORDER BY entry_date DESC");

$entries = [];
while ($row = $result->fetch_assoc()) {
    $entries[] = $row;
}
?>

<div class="mb-3">
    <input type="text" id="searchInput" class="form-control" placeholder="Search by Department, Date, Serial Number..." autofocus>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped" id="reportTable">
        <thead class="table-light">
            <tr>
                <th>Serial No</th>
                <th>Date</th>
                <th>Department</th>
                <th>Colombo</th>
                <th>Outstation</th>
                <th>Total</th>
                <th>Open Balance</th>
                <th>End Balance</th>
                <th><strong>Total Spent (Rs.)</strong></th>
                <th>Date Posted</th>
                <th>Postal Serial No</th>
            </tr>
        </thead>

        <tbody id="reportBody">
            <?php foreach ($entries as $row): ?>
                <?php $totalSpent = (float)$row['open_balance'] - (float)$row['end_balance']; ?>
                <tr>
                    <td><?= htmlspecialchars($row['serial_number']) ?></td>
                    <td><?= htmlspecialchars($row['entry_date']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td class="text-end"><?= number_format($row['where_to_colombo']) ?></td>
                    <td class="text-end"><?= number_format($row['where_to_outstation']) ?></td>
                    <td class="text-end"><?= number_format($row['total']) ?></td>
                    <td class="text-end"><?= number_format($row['open_balance'], 2) ?></td>
                    <td class="text-end"><?= number_format($row['end_balance'], 2) ?></td>
                    <td class="text-end text-success fw-bold"><?= number_format($totalSpent, 2) ?></td>
                    <td><?= !empty($row['date_posted']) ? htmlspecialchars($row['date_posted']) : 'Not Posted' ?></td>
                    <td><?= htmlspecialchars($row['postal_serial_number'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>
    <div id="noResults" class="text-center text-danger fw-bold" style="display:none;">No records found.</div>
</div>

<nav>
    <ul class="pagination justify-content-end mt-4" id="paginationControls"></ul>
</nav>

<script>
    const rowsPerPage = 10;
    let currentPage = 1;

    function paginate() {
        const rows = $('#reportBody tr');
        const totalRows = rows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);

        rows.hide();
        rows.slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage).show();

        let paginationHtml = '';

        paginationHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
        </li>`;

        const maxPages = 3;
        const startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
        const endPage = Math.min(totalPages, startPage + maxPages - 1);

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        paginationHtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
        </li>`;

        $('#paginationControls').html(paginationHtml);
    }

    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (!isNaN(page)) {
            currentPage = page;
            paginate();
        }
    });

    $('#searchInput').on('keyup', function () {
        const search = $(this).val().toLowerCase();
        const rows = $('#reportBody tr');
        let visibleCount = 0;

        rows.each(function () {
            const match = $(this).text().toLowerCase().indexOf(search) > -1;
            $(this).toggle(match);
            if (match) visibleCount++;
        });

        $('#noResults').toggle(visibleCount === 0);
        $('#paginationControls').toggle(search.length === 0);
    });

    $(document).ready(function () {
        paginate();
    });
</script>
