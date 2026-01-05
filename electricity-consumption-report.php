<?php
include 'connections/connection.php';

// Fetch distinct branches and payment months
$branchesQuery = "SELECT DISTINCT branch_id, branch_name FROM tbl_admin_electricity_cost ORDER BY CAST(branch_id AS UNSIGNED)";
$monthsQuery = "SELECT DISTINCT payment_month FROM tbl_admin_electricity_cost ORDER BY payment_month";

$branchesResult = $conn->query($branchesQuery);
$monthsResult = $conn->query($monthsQuery);

$branches = [];
while ($row = $branchesResult->fetch_assoc()) {
    $branches[] = $row;
}

$months = [];
while ($row = $monthsResult->fetch_assoc()) {
    $months[] = $row['payment_month'];
}

// Sort months chronologically
usort($months, function($a, $b) {
    $aDate = DateTime::createFromFormat('F Y', $a);
    $bDate = DateTime::createFromFormat('F Y', $b);
    return $aDate <=> $bDate;
});

// Fetch all data into an associative array
$data = [];
$totals = [];
$branchTotals = [];

$dataQuery = "SELECT branch_id, payment_month, SUM(units) AS total_units, SUM(amount) AS total_amount 
              FROM tbl_admin_electricity_cost 
              GROUP BY branch_id, payment_month";

$dataResult = $conn->query($dataQuery);
while ($row = $dataResult->fetch_assoc()) {
    $data[$row['branch_id']][$row['payment_month']] = [
        'units' => $row['total_units'],
        'amount' => $row['total_amount']
    ];
    
    // Calculate total per payment month (amount only)
    if (!isset($totals[$row['payment_month']])) {
        $totals[$row['payment_month']] = 0;
    }
    $totals[$row['payment_month']] += $row['total_amount'];

    // Calculate total units and total amount for each branch
    if (!isset($branchTotals[$row['branch_id']])) {
        $branchTotals[$row['branch_id']] = [
            'total_units' => 0,
            'total_amount' => 0
        ];
    }
    $branchTotals[$row['branch_id']]['total_units'] += $row['total_units'];
    $branchTotals[$row['branch_id']]['total_amount'] += $row['total_amount'];
}

// Sort branches numerically
usort($branches, function($a, $b) {
    return (int)$a['branch_id'] - (int)$b['branch_id'];
});


if (isset($_GET['month'])) {
    $response = ['budget' => 0];
    $month = $conn->real_escape_string($_GET['month']);

    $sql = "SELECT amount FROM tbl_admin_budget 
            WHERE utility_name = 'electricity' 
              AND month_year = '$month'
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        $response['budget'] = floatval($row['amount']);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    $conn->close();
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Electricity Cost Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container">
      <div class="card p-4 shadow-sm mt-2">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-9">
              
            <div class="card mb-4">
              <div class="card-body">
                <div class="mb-3">
                  <h3 class="card-title" style="margin-left: 5px;">Electricity Cost Report</h3>
                </div>
                <div class="row">
                  <div class="col">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by Branch ID or Branch Name...">
                  </div>
                  <div class="col-auto">
                    <button id="viewMonthSummary" class="btn btn-primary">View Month Summary</button>
                  </div>
                </div>
              </div>
            </div>




                <div class="table-responsive">
                <table class="table table-hover" id="electricityTable">
                    <thead>
                    <tr>
                        <th>Branch ID</th>
                        <th>Branch Name</th>
                        <th>Months</th>
                        <th>Total Units</th>
                        <th>Total Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    include 'connections/connection.php';
                    $query = "SELECT branch_id, branch_name, payment_month, units, amount FROM tbl_admin_electricity_cost";
                    $result = $conn->query($query);
                    
                    $data = [];
                    while ($row = $result->fetch_assoc()) {
                        $branchId = $row['branch_id'];
                        $branchName = $row['branch_name'];
                        $month = $row['payment_month'];
                        $units = $row['units'];
                        $amount = $row['amount'];
                        
                        if (!isset($data[$branchId])) {
                            $data[$branchId] = [
                                'branch_name' => $branchName,
                                'months' => [],
                                'total_units' => 0,
                                'total_amount' => 0
                            ];
                        }
                        
                        $data[$branchId]['months'][$month] = [
                            'units' => $units,
                            'amount' => $amount
                        ];
                        
                        $data[$branchId]['total_units'] += $units;
                        $data[$branchId]['total_amount'] += $amount;
                    }
                    
                    foreach ($data as $branchId => $info) {
                        echo "<tr class='data-row' data-branch-id='$branchId' data-branch-name='".htmlspecialchars($info['branch_name'])."' data-months='".htmlspecialchars(json_encode($info['months']))."'>";
                        echo "<td>$branchId</td>";
                        echo "<td>".$info['branch_name']."</td>";
                        echo "<td>".count($info['months'])."</td>";
                        echo "<td>".$info['total_units']."</td>";
                        echo "<td>".number_format($info['total_amount'], 2)."</td>";
                        echo "</tr>";
                    }
                    ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
      </div>
    </div>

<!-- Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Branch Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalBody">
        <!-- Filled by JavaScript -->
      </div>
    </div>
  </div>
</div>
<!-- Modal for All Branch Summary -->
<div class="modal fade" id="allBranchesModal" tabindex="-1" aria-labelledby="allBranchesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="allBranchesModalLabel">All Branches Month Summary</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="allBranchesModalBody">
        <!-- Filled by JavaScript -->
      </div>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
  const rows = document.querySelectorAll('.data-row');
  
  rows.forEach(row => {
    row.addEventListener('click', function() {
      const branchId = this.dataset.branchId;
      const branchName = this.dataset.branchName;
      const monthsData = JSON.parse(this.dataset.months);

      window.currentMonthsData = monthsData;
      window.currentBranchInfo = { branchId, branchName };
      
      let html = `
        <p><strong>Branch ID:</strong> ${branchId}</p>
        <p><strong>Branch Name:</strong> ${branchName}</p>
        <hr>

        <div class="row mb-3">
          <div class="col">
            <label for="startDate" class="form-label">Start Date:</label>
            <input type="month" id="startDate" class="form-control">
          </div>
          <div class="col">
            <label for="endDate" class="form-label">End Date:</label>
            <input type="month" id="endDate" class="form-control">
          </div>
        </div>

        <div id="filteredResults">
          <!-- Filtered Table will appear here -->
        </div>
      `;

      document.getElementById('modalBody').innerHTML = html;
      new bootstrap.Modal(document.getElementById('detailModal')).show();

      document.getElementById('startDate').addEventListener('input', liveFilter);
      document.getElementById('endDate').addEventListener('input', liveFilter);
    });
  });
});

function liveFilter() {
  const startDateInput = document.getElementById('startDate').value;
  const endDateInput = document.getElementById('endDate').value;

  if (!startDateInput || !endDateInput) {
    document.getElementById('filteredResults').innerHTML = "<p class='text-danger'>Please select both Start and End dates.</p>";
    return;
  }

  const startDate = new Date(startDateInput + "-01");
  const endDate = new Date(endDateInput + "-01");
  endDate.setMonth(endDate.getMonth() + 1); // include full month

  let html = `
    <div class="table-responsive mt-3">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Month</th>
            <th>Units</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
  `;

  let totalUnits = 0;
  let totalAmount = 0;

  for (const month in window.currentMonthsData) {
    const parsedMonth = new Date(month);
    
    if (parsedMonth >= startDate && parsedMonth < endDate) {
      const entry = window.currentMonthsData[month];
      const units = entry.units;
      const amount = entry.amount;
      
      html += `
        <tr>
          <td>${month}</td>
          <td>${units}</td>
          <td>${parseFloat(amount).toFixed(2)}</td>
        </tr>
      `;

      totalUnits += parseFloat(units);
      totalAmount += parseFloat(amount);
    }
  }

  html += `
        </tbody>
      </table>
    </div>
    <hr>
    <p><strong>Total Units:</strong> ${totalUnits}</p>
    <p><strong>Total Amount:</strong> ${totalAmount.toFixed(2)}</p>
  `;

  document.getElementById('filteredResults').innerHTML = html;
}
</script>
<script>
// Live Search Functionality
document.getElementById('searchInput').addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const rows = document.querySelectorAll('#electricityTable tbody tr');

  rows.forEach(row => {
    const branchId = row.cells[0].textContent.toLowerCase();
    const branchName = row.cells[1].textContent.toLowerCase();
    
    if (branchId.includes(searchTerm) || branchName.includes(searchTerm)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show Month Summary
document.getElementById('viewMonthSummary').addEventListener('click', function() {
  let monthsSet = new Set();
  const rows = document.querySelectorAll('.data-row');

  // Collect all months
  rows.forEach(row => {
    const monthsData = JSON.parse(row.dataset.months);
    for (const month in monthsData) {
      monthsSet.add(month);
    }
  });

  const monthsArray = Array.from(monthsSet).sort((a, b) => {
    const dateA = new Date(a);
    const dateB = new Date(b);
    return dateA - dateB;
  });

  let selectHtml = `
    <label for="selectMonth" class="form-label">Select Month:</label>
    <select id="selectMonth" class="form-select mb-3">
      <option value="">-- Select a Month --</option>
  `;

  monthsArray.forEach(month => {
    selectHtml += `<option value="${month}">${month}</option>`;
  });

  selectHtml += `</select>`;

  document.getElementById('allBranchesModalBody').innerHTML = selectHtml + `
    <div id="monthSummaryResult"></div>
  `;

  new bootstrap.Modal(document.getElementById('allBranchesModal')).show();

  document.getElementById('selectMonth').addEventListener('change', function() {
    const selectedMonth = this.value;
    if (!selectedMonth) {
      document.getElementById('monthSummaryResult').innerHTML = '';
      return;
    }

    generateMonthSummary(selectedMonth);
  });
});

function generateMonthSummary(selectedMonth) {
  let rows = document.querySelectorAll('.data-row');

  let html = '';
  let grandTotalUnits = 0;
  let grandTotalAmount = 0;

  // Calculate the grand total first
  rows.forEach(row => {
    const monthsData = JSON.parse(row.dataset.months);
    if (monthsData[selectedMonth]) {
      const units = parseFloat(monthsData[selectedMonth].units) || 0;
      const amount = parseFloat(monthsData[selectedMonth].amount) || 0;

      grandTotalUnits += units;
      grandTotalAmount += amount;
    }
  });

  // Show Grand Total
  html += `
    <div class="mb-3">
      <p><strong>Grand Total Units for ${selectedMonth}:</strong> ${grandTotalUnits.toLocaleString()}</p>
      <p><strong>Grand Total Amount for ${selectedMonth}:</strong> ${grandTotalAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
      <div id="budgetInfo"></div> <!-- Placeholder for budget info -->
    </div>
  `;

  // Now, generate the table
  html += `
    <div class="table-responsive mt-3">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Branch ID</th>
            <th>Branch Name</th>
            <th>Units</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
  `;

  rows.forEach(row => {
    const branchId = row.cells[0].textContent.trim();
    const branchName = row.cells[1].textContent.trim();
    const monthsData = JSON.parse(row.dataset.months);

    if (monthsData[selectedMonth]) {
      const units = parseFloat(monthsData[selectedMonth].units) || 0;
      const amount = parseFloat(monthsData[selectedMonth].amount) || 0;

      html += `
        <tr>
          <td>${branchId}</td>
          <td>${branchName}</td>
          <td>${units.toLocaleString()}</td>
          <td>${amount.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
        </tr>
      `;
    }
  });

  html += `
        </tbody>
      </table>
    </div>
  `;

  document.getElementById('monthSummaryResult').innerHTML = html;

  // --- Fetch the Budget from Server (same file) ---
  fetch(`<?php echo basename($_SERVER['PHP_SELF']); ?>?month=${encodeURIComponent(selectedMonth)}`)
    .then(response => response.json())
    .then(data => {
      const budgetAmount = data.budget || 0;
      const balance = budgetAmount - grandTotalAmount;
      const balanceColor = balance < 0 ? 'text-danger' : 'text-success';

      let budgetHtml = `
        <p><strong>Budget Amount for ${selectedMonth}:</strong> ${budgetAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
        <p><strong>Balance:</strong> <span class="${balanceColor}">${balance.toLocaleString(undefined, {minimumFractionDigits: 2})}</span></p>
      `;

      document.getElementById('budgetInfo').innerHTML = budgetHtml;
    })
    .catch(error => {
      console.error('Error fetching budget:', error);
      document.getElementById('budgetInfo').innerHTML = '<p class="text-danger">Failed to fetch budget information.</p>';
    });
}
</script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script>
    function toggleMenu() {
        var sidebar = document.getElementById("sidebar");
        sidebar.classList.toggle("hidden");

        // Optionally toggle body overflow to prevent scrolling when sidebar is visible
        document.body.classList.toggle("no-scroll", sidebar.classList.contains("hidden"));
    }
</script>
</body>
</html>
