<?php 
  include 'nocache.php'; 
  include 'connections/connection.php'; 
?>
<!-- view-employee-details.php -->
<head>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="nocache.js"></script>
</head>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Employee List</h5>

      <div class="mb-3">
        <input
          type="text"
          id="searchInput"
          class="form-control"
          placeholder="Search HRIS, Full Name, NIC No, Mobile Number">
      </div>

      <div id="tableContainer">
        <!-- Table will be loaded here via AJAX -->
      </div>
    </div>
  </div>
</div>

<!-- EDIT / VIEW MODAL (moved here so it doesn't get duplicated on reload) -->
<div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Employee Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="employeeForm">
          <div class="row">
            <div class="col-md-6 mb-3"><label>HRIS No</label><input type="text" class="form-control" id="modalHris" readonly></div>
            <div class="col-md-6 mb-3"><label>EPF No</label><input type="text" class="form-control" id="modalEpf" readonly></div>
            <div class="col-md-6 mb-3"><label>Mobile No</label><input type="text" class="form-control" id="modalMobile"></div>
            <div class="col-md-6 mb-3"><label>Voice/Data</label><input type="text" class="form-control" id="modalVoiceData"></div>
            <div class="col-md-6 mb-3"><label>Company Contribution</label><input type="text" class="form-control" id="modalContribution"></div>
            <div class="col-md-6 mb-3"><label>Remarks</label><input type="text" class="form-control" id="modalRemarks"></div>
            <div class="col-md-6 mb-3"><label>Full Name</label><input type="text" class="form-control" id="modalFullName" readonly></div>
            <div class="col-md-6 mb-3"><label>NIC No</label><input type="text" class="form-control" id="modalNic" readonly></div>
          </div>
          <div class="form-group mt-3">
            <label for="modalConnectionStatus">Connection Status</label>
            <select id="modalConnectionStatus" class="form-control">
              <option value="connected">Connected</option>
              <option value="disconnected">Disconnected</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="saveButton">Save changes</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="btnAssignNewHris">Assign to New HRIS</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal (single instance on the page) -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Success</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="successModalBody">
        Operation completed successfully.
      </div>
    </div>
  </div>
</div>

<script>
  // if you have $logged_user from session, this keeps your audit trail code working
  const loggedUser = <?= isset($logged_user) ? json_encode($logged_user) : 'null' ?>;
</script>

<script>
$(function () {
  let typingTimer;
  const doneTypingInterval = 300;
  let currentPage = 1;
  let currentSearch = '';

  // Initial load
  loadTable(1);

  // Debounced search
  $('#searchInput').on('keyup', function () {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(function () {
      loadTable(1);
    }, doneTypingInterval);
  });

  // Handle pagination inside tableContainer, and STOP global page-loader
  $('#tableContainer').on('click', 'a.page-link', function (e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    const page = Number($(this).data('page')) || 1;
    loadTable(page);
    return false;
  });

  // Row click → open modal and populate (delegated from the page so it always works)
  $('#tableContainer').on('click', '.row-clickable', function () {
    currentPage = $('.pagination .active .page-link').data('page') || currentPage;
    currentSearch = $('#searchInput').val() || '';

    const $r = $(this);
    $('#modalHris').val($r.data('hris'));
    $('#modalEpf').val($r.data('epf'));
    $('#modalMobile').val($r.data('mobile'));
    $('#modalVoiceData').val($r.data('voicedata'));
    $('#modalContribution').val($r.data('contribution'));
    $('#modalRemarks').val($r.data('remarks'));
    $('#modalFullName').val($r.data('fullname'));
    $('#modalNic').val($r.data('nic'));
    $('#modalConnectionStatus').val($r.data('connectionstatus'));

    if (loggedUser) {
      $.post('log-user-action.php', {
        user: loggedUser,
        action: `Viewed employee HRIS: ${$r.data('hris')}, Name: ${$r.data('fullname')}, Mobile: ${$r.data('mobile')}`
      });
    }

    const modal = new bootstrap.Modal(document.getElementById('employeeModal'), {
      backdrop: 'static',
      keyboard: false
    });
    modal.show();
  });

  // Save changes
  $('#saveButton').on('click', function () {
    const hris = $('#modalHris').val();
    const mobile = $('#modalMobile').val();
    const name = $('#modalFullName').val();
    const payload = {
      hris_no: hris,
      mobile_no: mobile,
      voice_data: $('#modalVoiceData').val(),
      company_contribution: $('#modalContribution').val(),
      remarks: $('#modalRemarks').val(),
      connection_status: $('#modalConnectionStatus').val()
    };

    $.post('update-employee.php', payload, function (res) {
      const ok = (res || '').toString().trim() === 'success';
      $('#employeeModal').modal('hide');
      $('#successModalBody').text(ok ? 'Employee details updated successfully.' : 'Update failed. Please try again.');
      new bootstrap.Modal(document.getElementById('successModal'), { backdrop: 'static', keyboard: false }).show();

      if (loggedUser) {
        $.post('log-user-action.php', {
          user: loggedUser,
          action: `Updated employee HRIS: ${hris}, Name: ${name}, Mobile: ${mobile} — Changes: Voice/Data=${payload.voice_data}, Remarks=${payload.remarks}, Contribution=${payload.company_contribution}, Connection=${payload.connection_status}`
        });
      }
    });
  });

  // Assign to new HRIS
  $('#btnAssignNewHris').on('click', function () {
    const oldHris = $('#modalHris').val();
    const mobileNo = $('#modalMobile').val();
    const name = $('#modalFullName').val();
    const currentStatus = $('#modalConnectionStatus').val();

    if (currentStatus !== 'disconnected') {
      alert('Please disconnect first.');
      return;
    }
    const newHris = prompt('Enter new HRIS No:');
    if (!newHris) return;

    $.post('assign-mobile-to-new-hris.php', { mobile_no: mobileNo, new_hris_no: newHris }, function (res) {
      $('#employeeModal').modal('hide');
      const ok = (res || '').toString().trim() === 'success';
      $('#successModalBody').text(ok ? 'Mobile reassigned successfully.' : ('Error: ' + res));
      new bootstrap.Modal(document.getElementById('successModal'), { backdrop: 'static', keyboard: false }).show();

      if (loggedUser) {
        $.post('log-user-action.php', {
          user: loggedUser,
          action: `Reassigned Mobile: ${mobileNo}, Name: ${name} from HRIS: ${oldHris} to New HRIS: ${newHris}`
        });
      }
    });
  });

  // After success modal closes → reload current page with same search
  $('#successModal').on('hidden.bs.modal', function () {
    loadTable(currentPage);
  });

  // Core loader
  function loadTable(page = 1) {
    currentPage = page;
    currentSearch = $('#searchInput').val() || '';
    $.ajax({
      url: 'ajax-employee-table.php',
      method: 'GET',
      data: { search: currentSearch, page: currentPage },
      success: function (html) {
        $('#tableContainer').html(html);
      },
      error: function () {
        $('#tableContainer').html('<div class="alert alert-danger m-2">Failed to load table.</div>');
      }
    });
  }
});
</script>
