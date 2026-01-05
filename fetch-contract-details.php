<?php

include 'connections/connection.php';



$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {

    echo "<div class='text-danger'>Invalid contract ID.</div>";

    exit;

}



$query = "SELECT * FROM tbl_admin_branch_contracts WHERE id = $id LIMIT 1";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {

    echo "<div class='text-danger'>No contract found.</div>";

    exit;

}

$row = mysqli_fetch_assoc($result);

?>



<!-- Display contract details -->

<div class="row">

    <?php

    $labels = [

        'branch_number' => 'Branch Number',

        'branch_name' => 'Branch Name',

        'lease_agreement_number' => 'Lease Agreement Number',

        'contract_period' => 'Contract Period',

        'start_date' => 'Start Date',

        'end_date' => 'End Date',

        'total_rent' => 'Total Rent',

        'increase_of_rent' => 'Increase of Rent',

        'advance_payment_key_money' => 'Advance Payment / Key Money',

        'monthly_rental_notes' => 'Monthly Rental Notes',

        'floor_area' => 'Floor Area',

        'repairs_by_cdb' => 'Repairs by CDB',

        'deviations_within_contract' => 'Deviations within Contract'

    ];



    foreach ($labels as $field => $label) {

        $value = nl2br(htmlspecialchars($row[$field] ?? ''));

        echo "<div class='col-md-6 mb-3'><strong>$label:</strong><br>$value</div>";

    }

    ?>

</div>



<hr>

<h5 class="mb-4 text-primary">Uploaded Contract Versions</h5>

<ul class="list-group mb-4">

<?php

$versions = mysqli_query($conn, "SELECT * FROM tbl_admin_branch_contract_versions WHERE branch_contract_id = $id ORDER BY uploaded_on DESC");

if (mysqli_num_rows($versions) === 0) {

    echo "<li class='list-group-item text-muted'>No contract files uploaded yet.</li>";

}

while ($ver = mysqli_fetch_assoc($versions)): ?>

    <li class="list-group-item d-flex justify-content-between align-items-center">

        <div>

            <strong><?= htmlspecialchars($ver['version_note'] ?? 'No label') ?></strong><br>

            <small><?= $ver['uploaded_on'] ?></small>

        </div>

        <div>

            <a href="download-contract-version.php?id=<?= $ver['id'] ?>" class="btn btn-sm btn-primary" target="_blank">Download</a>

        </div>

    </li>

<?php endwhile; ?>

</ul>



<!-- Upload form -->

<h5 class="mb-4 text-primary">Upload New Contract Version</h5>

<form id="uploadForm" enctype="multipart/form-data">

    <input type="hidden" name="contract_id" value="<?= $row['id'] ?>">

    <div class="mb-2">

        <input type="file" name="contract_pdf" accept="application/pdf" class="form-control" required>

    </div>

    <div class="mb-3">

        <input type="text" name="version_note" class="form-control" placeholder="E.g. Renewal 2025" required>

    </div>

    <button type="submit" class="btn btn-secondary">Upload New Version</button>

</form>

