<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">User Activity Logs</h5>

      <form id="logFilterForm">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label for="searchUser" class="form-label">User</label>
            <input type="text" id="searchUser" name="searchUser" class="form-control" placeholder="Enter username">
          </div>
          <div class="col-md-3">
            <label for="dateFrom" class="form-label">From</label>
            <input type="date" id="dateFrom" name="dateFrom" class="form-control">
          </div>
          <div class="col-md-3">
            <label for="dateTo" class="form-label">To</label>
            <input type="date" id="dateTo" name="dateTo" class="form-control">
          </div>
          <div class="col-md-3 d-grid">
            <button type="submit" class="btn btn-primary">Search Logs</button>
          </div>
        </div>
      </form>

      <div id="logResults" class="mt-4"></div>
    </div>
  </div>
</div>

<script>
  function loadLogs() {
    const formData = $('#logFilterForm').serialize();
    $.ajax({
      url: 'ajax-user-logs.php',
      method: 'GET',
      data: formData,
      success: function(response) {
        $('#logResults').html(response);
      },
      error: function() {
        $('#logResults').html("<div class='alert alert-danger'>Failed to load logs.</div>");
      }
    });
  }

  $(document).ready(function () {
    loadLogs();

    $('#logFilterForm').on('submit', function (e) {
      e.preventDefault();
      loadLogs();
    });
  });
</script>
