<!-- security-budget-report.php -->
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-4 text-primary">Security Budget Summary</h5>
                <a href="download-budget-security.php" class="btn btn-outline-primary btn-sm">Download Excel</a>
            </div>

            <div id="securityBudgetContent">
                <div class="text-center py-4">🔄 Loading security budget table...</div>
            </div>
        </div>
    </div>
</div>

<script>
  // Load the security budget table via AJAX
  function loadSecurityBudgetTable() {
    const container = document.getElementById('securityBudgetContent');

    fetch('ajax-security-budget-summary.php')
      .then(response => response.text())
      .then(html => {
        container.innerHTML = html;
      })
      .catch(err => {
        console.error('❌ AJAX error:', err);
        container.innerHTML = '<div class="alert alert-danger">❌ Failed to load budget summary.</div>';
      });
  }

  // Call function immediately after content loads
  loadSecurityBudgetTable();
</script>
