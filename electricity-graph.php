<!-- electricity-graph.php -->
<?php
include 'connections/connection.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['action']) && $_GET['action'] === 'fetchGraphData') {
    $start = $conn->real_escape_string($_GET['start']);
    $end = $conn->real_escape_string($_GET['end']);

    $startDate = date('Y-m-01', strtotime($start));
    $endDate = date('Y-m-t', strtotime($end));

    $months = [];
    $current = strtotime($startDate);
    $endTime = strtotime($endDate);

    while ($current <= $endTime) {
        $months[] = date('F Y', $current);
        $current = strtotime("+1 month", $current);
    }

    $labels = [];
    $budgets = [];
    $actuals = [];

    foreach ($months as $monthName) {
        // Fetch budget
        $budgetQuery = "SELECT amount FROM tbl_admin_budget 
                        WHERE utility_name = 'electricity' 
                        AND month_year = '$monthName' 
                        LIMIT 1";
        $budgetResult = $conn->query($budgetQuery);
        $budgetAmount = 0;
        if ($budgetResult && $row = $budgetResult->fetch_assoc()) {
            $budgetAmount = floatval($row['amount']);
        }

        // Fetch actual
        $actualQuery = "SELECT SUM(amount) as total_actual 
                        FROM tbl_admin_electricity_cost 
                        WHERE payment_month = '$monthName'";
        $actualResult = $conn->query($actualQuery);
        $actualAmount = 0;
        if ($actualResult && $row = $actualResult->fetch_assoc()) {
            $actualAmount = floatval($row['total_actual']);
        }

        $labels[] = $monthName;
        $budgets[] = $budgetAmount;
        $actuals[] = $actualAmount;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'labels' => $labels,
        'budgets' => $budgets,
        'actuals' => $actuals
    ]);
    exit();
}
?>
    <title>Electricity Actual Vs Budget</title>

    <link rel="stylesheet" href="styles.css">
    <style>
      #graphContainer {
        margin-top: 20px; /* Adjust the value as needed */
      }
    </style>
</head>
<body class="bg-light">
<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>

<!-- Content Area -->
<div class="content font-size" id="contentArea">
  <div class="container" >
    <div class="card p-4 shadow-sm mt-2">
      <h4 class="mb-3">Electricity Budget vs Actual</h4>
      <form id="graphForm">
        <div class="row g-3 align-items-end">
          <div class="col-md-5">
            <label for="startMonth" class="form-label">Start Month</label>
            <input type="month" class="form-control" id="startMonth" name="startMonth" required>
          </div>
          <div class="col-md-5">
            <label for="endMonth" class="form-label">End Month</label>
            <input type="month" class="form-control" id="endMonth" name="endMonth" required>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Generate</button>
          </div>
        </div>
      </form>
    </div>

    <div id="graphContainer" class="card mt-4 p-4 shadow-sm" style="margin-top: 20px;">
      <canvas id="budgetVsActualChart" height="100"></canvas>
    </div>
  </div>
</div>

<script>
function toggleMenu() {
    var sidebar = document.getElementById("sidebar");
    var content = document.getElementById("contentArea");
    sidebar.classList.toggle("hidden");
    content.classList.toggle("full");

    // Optional: prevent body scrolling when menu is open
    document.body.classList.toggle("no-scroll", sidebar.classList.contains("hidden"));
}

document.getElementById('graphForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const startMonth = document.getElementById('startMonth').value;
  const endMonth = document.getElementById('endMonth').value;

  if (!startMonth || !endMonth) {
    alert('Please select both start and end months.');
    return;
  }

  fetch(`?action=fetchGraphData&start=${startMonth}&end=${endMonth}`)
    .then(response => response.json())
    .then(data => {
      generateChart(data);
    })
    .catch(error => {
      console.error('Error fetching graph data:', error);
      alert('Failed to fetch graph data.');
    });
});

let chartInstance;

function formatNumber(value) {
  if (value >= 1000000) {
    return (value / 1000000).toFixed(1) + 'M';
  } else if (value >= 1000) {
    return (value / 1000).toFixed(1) + 'K';
  } else {
    return value;
  }
}

function generateChart(data) {
  const ctx = document.getElementById('budgetVsActualChart').getContext('2d');

  if (chartInstance) {
    chartInstance.destroy();
  }

  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [
        {
          label: 'Budget Amount',
          data: data.budgets,
          borderColor: 'rgba(54, 162, 235, 1)',
          backgroundColor: 'rgba(54, 162, 235, 0.2)',
          tension: 0.4,
          fill: false,  // Optional to disable filling under the line
        },
        {
          label: 'Actual Amount',
          data: data.actuals,
          borderColor: 'rgba(255, 99, 132, 1)',
          backgroundColor: 'rgba(255, 99, 132, 0.2)',
          tension: 0.4,
          fill: false,  // Optional to disable filling under the line
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        title: {
          display: true,
          text: 'Electricity Budget vs Actual'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                label += formatNumber(context.parsed.y);
              }
              return label;
            }
          }
        },
        datalabels: {
          display: true,
          align: 'top',
          font: {
            weight: 'bold',
            size: 12,
          },
          formatter: function(value) {
            return formatNumber(value);
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return formatNumber(value);
            }
          }
        }
      }
    },
    plugins: [ChartDataLabels]  // Ensure that this plugin is included in the options
  });
}
</script>
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
