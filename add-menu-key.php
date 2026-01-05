<?php
// add-menu-key.php
session_start();
include 'connections/connection.php';
?>
<div class="content font-size" id="contentArea">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Add New Menu Key</h5>

      <div id="statusMsg"></div>

      <form id="menuForm" autocomplete="off">
        <div class="mb-3">
          <label for="menu_key" class="form-label">Menu Key (Unique)</label>
          <input type="text" class="form-control" id="menu_key" name="menu_key" required>
        </div>

        <!-- Menu Label (full width) -->
        <div class="mb-3">
          <label for="menu_label" class="form-label">Menu Label (Display Text)</label>
          <input type="text" class="form-control" id="menu_label" name="menu_label" required>
        </div>

        <!-- Row: Menu Group + Color (50/50) -->
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="menu_group" class="form-label">Menu Group</label>
            <input type="text" class="form-control" id="menu_group" name="menu_group" required>
          </div>

          <div class="col-md-6 mb-3 d-flex align-items-end" id="colorContainer">
            <div class="w-100">
              <label for="menu_color" class="form-label">Menu Color (optional)</label>
              <input
                type="color"
                class="form-control form-control-color w-100"
                id="menu_color"
                name="menu_color"
                value="#ffc107"
                title="Choose color">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-success">Add Menu Key</button>
      </form>

      <hr class="my-4">

      <h6 class="text-secondary mb-3">Existing Menu Keys</h6>

      <!-- Search box -->
      <div class="row mb-3">
        <div class="col-md-6">
          <input type="text" id="searchBox" class="form-control" placeholder="Search menu keys...">
        </div>
      </div>

      <!-- Table will load here -->
      <div id="menuTable"></div>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
(function() {
  let searchQuery = "";

  function toggleColorPicker() {
    const group = ($('#menu_group').val() || "").trim().toLowerCase();
    const isGeneral = (group === 'general');

    // Show/hide and enable/disable
    if (isGeneral) {
      $('#colorContainer').removeClass('d-none');
      $('#menu_color').prop('disabled', false);
    } else {
      $('#colorContainer').addClass('d-none');
      $('#menu_color').prop('disabled', true);
    }
  }

  // Load menu keys (server should return JSON: { table: "<table>...</table>" })
  function loadMenuKeys(query = "") {
    $.get('ajax-get-menu-keys.php', { search: query })
      .done(function(data) {
        let res;
        try { res = (typeof data === 'object') ? data : JSON.parse(data); }
        catch (e) { res = { table: '<div class="text-danger">Failed to parse server response.</div>' }; }
        $('#menuTable').html(res.table || '<div class="text-muted">No data.</div>');
      })
      .fail(function() {
        $('#menuTable').html('<div class="text-danger">Error loading menu keys.</div>');
      });
  }

  $(function() {
    // Initial UI state for color picker
    $('#colorContainer').addClass('d-none');     // hide initially
    $('#menu_color').prop('disabled', true);     // disable initially

    // Live toggle on menu_group input
    $('#menu_group').on('input', toggleColorPicker);

    // Search-as-you-type
    $('#searchBox').on('keyup', function() {
      searchQuery = $(this).val();
      loadMenuKeys(searchQuery);
    });

    // Submit
    $('#menuForm').on('submit', function(e) {
      e.preventDefault();
      $.post('ajax-save-menu-key.php', $(this).serialize())
        .done(function(response) {
          $('#statusMsg').html(response);
          // reset form
          $('#menuForm')[0].reset();
          // reset color picker state after reset
          $('#menu_color').val('#ffc107');
          toggleColorPicker();
          // reload table
          loadMenuKeys(searchQuery);
        })
        .fail(function() {
          $('#statusMsg').html('<div class="alert alert-danger mb-0">Error saving. Please try again.</div>');
        });
    });

    // First load
    loadMenuKeys();
  });
})();
</script>
