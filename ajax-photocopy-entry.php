<?php
include("connections/connection.php");

// Handle AJAX save
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ajax']) && $_POST['ajax'] === '1' &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
) {
    $serial     = $_POST['serial_number'];
    $branchCode = $_POST['branch_code'];
    $branchName = $_POST['branch_name'];
    $rate       = $_POST['rate'];
    $copies     = $_POST['number_of_copy'];
    $amount     = $_POST['amount'];
    $sscl       = $_POST['sscl'];
    $vat        = $_POST['vat'];
    $total      = $_POST['total'];
    $recordDate = $_POST['record_date'];

    $check = $conn->prepare("SELECT id FROM tbl_admin_actual_photocopy WHERE record_date = ? AND serial_number = ?");
    $check->bind_param("ss", $recordDate, $serial);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo 'exists';
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO tbl_admin_actual_photocopy 
        (record_date, serial_number, branch_name, branch_code, number_of_copy, rate, amount, sscl, vat, total) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssiddddd", $recordDate, $serial, $branchName, $branchCode, $copies, $rate, $amount, $sscl, $vat, $total);
    echo $stmt->execute() ? 'success' : 'fail';
    exit;
}

$branches = [];
$result = $conn->query("SELECT serial_number, branch_code, branch_name, rate FROM tbl_admin_branch_photocopy");
while ($row = $result->fetch_assoc()) {
    $branches[] = $row;
}

$savedData = [];
$recordMonth = isset($_GET['record_month']) ? urldecode($_GET['record_month']) : '';
if ($recordMonth !== '') {
    $stmt = $conn->prepare("SELECT * FROM tbl_admin_actual_photocopy WHERE record_date = ?");
    $stmt->bind_param("s", $recordMonth);
    $stmt->execute();
    $resultSaved = $stmt->get_result();
    while ($row = $resultSaved->fetch_assoc()) {
        $savedData[$row['serial_number']] = $row;
    }
}
?>

<form id="loadMonthForm" class="mb-3">
    <label><strong>Record Month (e.g., April 2025):</strong></label>
    <div class="input-group mt-2">
        <input type="text" name="record_month" id="record_month" class="form-control"
               placeholder="e.g., May 2025"
               value="<?= htmlspecialchars($recordMonth) ?>" required>
        <button type="submit" class="btn btn-primary">Load</button>
    </div>
</form>

<table class="table table-bordered">
    <thead class="table-light">
        <tr>
            <th>#</th>
            <th>Serial No</th>
            <th>Branch Code</th>
            <th>Branch Name</th>
            <th>Rate</th>
            <th>No. of Copies</th>
            <th>Amount</th>
            <th>SSCL</th>
            <th>VAT</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($branches as $index => $b): 
            $isSaved = isset($savedData[$b['serial_number']]);
            $saved = $isSaved ? $savedData[$b['serial_number']] : null;
        ?>
        <tr data-index="<?= $index ?>" class="<?= $isSaved ? 'success-row' : '' ?>">
            <td><?= $index + 1 ?></td>
            <td><?= $b['serial_number'] ?><input type="hidden" class="serial" value="<?= $b['serial_number'] ?>"></td>
            <td><?= $b['branch_code'] ?><input type="hidden" class="code" value="<?= $b['branch_code'] ?>"></td>
            <td><?= $b['branch_name'] ?><input type="hidden" class="name" value="<?= htmlspecialchars($b['branch_name']) ?>"></td>
            <td><?= $b['rate'] ?><input type="hidden" class="rate" value="<?= $b['rate'] ?>"></td>
            <td>
                <input type="number" class="form-control copies"
                       value="<?= $isSaved ? $saved['number_of_copy'] : '' ?>"
                       <?= $isSaved ? 'readonly' : '' ?>
                       oninput="updateCalculations(<?= $index ?>)"
                       onkeydown="handleKey(event, <?= $index ?>)">
            </td>
            <td><input type="text" class="form-control amount" value="<?= $isSaved ? $saved['amount'] : '' ?>" readonly tabindex="-1"></td>
            <td><input type="text" class="form-control sscl" value="<?= $isSaved ? $saved['sscl'] : '' ?>" readonly tabindex="-1"></td>
            <td><input type="text" class="form-control vat" value="<?= $isSaved ? $saved['vat'] : '' ?>" readonly tabindex="-1"></td>
            <td><input type="text" class="form-control total" value="<?= $isSaved ? $saved['total'] : '' ?>" readonly tabindex="-1"></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
function updateCalculations(rowIndex) {
    const row = document.querySelector(`tr[data-index='${rowIndex}']`);
    const rate = parseFloat(row.querySelector(".rate").value) || 0;
    const copies = parseInt(row.querySelector(".copies").value) || 0;
    const amount = rate * copies;
    const sscl = amount * 0.025;
    const totasscl = sscl + amount;
    const vat = totasscl * 0.18;
    const total = amount + sscl + vat;

    row.querySelector(".amount").value = amount.toFixed(2);
    row.querySelector(".sscl").value = sscl.toFixed(2);
    row.querySelector(".vat").value = vat.toFixed(2);
    row.querySelector(".total").value = total.toFixed(2);
}

function handleKey(event, rowIndex) {
    if (event.key === "Enter") {
        event.preventDefault();

        const record_month = document.getElementById('record_month').value.trim();
        const row = document.querySelector(`tr[data-index='${rowIndex}']`);
        const serial = row.querySelector(".serial").value;
        const code = row.querySelector(".code").value;
        const name = row.querySelector(".name").value;
        const rate = parseFloat(row.querySelector(".rate").value);
        const copies = parseInt(row.querySelector(".copies").value) || 0;
        const amount = parseFloat(row.querySelector(".amount").value) || 0;
        const sscl = parseFloat(row.querySelector(".sscl").value) || 0;
        const vat = parseFloat(row.querySelector(".vat").value) || 0;
        const total = parseFloat(row.querySelector(".total").value) || 0;

        if (!record_month || !copies) {
            alert("Please enter the Record Month and valid number of copies.");
            return;
        }

        $.post('ajax-photocopy-entry.php', {
            ajax: '1', serial_number: serial, branch_code: code, branch_name: name,
            rate: rate, number_of_copy: copies, amount: amount.toFixed(2),
            sscl: sscl.toFixed(2), vat: vat.toFixed(2), total: total.toFixed(2),
            record_date: record_month
        }, function(response) {
            if (response.trim() === "success") {
                row.classList.add("success-row");
                row.querySelector(".copies").readOnly = true;
                const nextRow = document.querySelector(`tr[data-index='${rowIndex + 1}']`);
                if (nextRow) {
                    const nextInput = nextRow.querySelector(".copies:not([readonly])");
                    if (nextInput) nextInput.focus();
                }
            } else if (response.trim() === "exists") {
                alert("This record already exists.");
            } else {
                alert("Error saving entry: " + response);
            }
        });
    }
}

$(document).ready(function () {
    $("input.copies:not([readonly])").first().focus();

    $('#loadMonthForm').submit(function (e) {
        e.preventDefault();
        const month = $('#record_month').val().trim();

        if (month === '') {
            alert('Please enter a record month.');
            return;
        }

        $('#contentArea').load('photocopy-entry.php?record_month=' + encodeURIComponent(month));
    });
});
</script>

<style>
.success-row { background-color: #d4edda !important; }
input[readonly] { background-color: #e9ecef !important; }
</style>
