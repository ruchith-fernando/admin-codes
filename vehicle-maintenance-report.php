<style>
/* Responsive table styling */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table {
    min-width: 1200px;
    white-space: nowrap;
}

th, td {
    vertical-align: middle;
}

td.text-wrap {
    white-space: normal;
    word-break: break-word;
    max-width: 300px;
}

th.left-align, td.left-align {
    text-align: left !important;
}

/* Professional Action Buttons */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    justify-content: flex-end;
    margin-bottom: 1rem;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border: none;
    border-radius: 6px;
    padding: 0.35rem 1.4rem;
    font-size: 0.93rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
    min-width: 170px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    color: #fff;
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0,0,0,0.12);
}

.btn-csv {
    background-color: #007bff;
}
.btn-csv:hover {
    background-color: #005dc1;
}

.btn-print {
    background-color: #28a745;
}
.btn-print:hover {
    background-color: #1f7a37;
}

.btn-action i {
    font-size: 1.05rem;
}
</style>
<div class="content font-size bg-light">
<div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="text-primary">Vehicle Maintenance Actual Records</h5>
            <button class="btn btn-outline-secondary btn-sm" id="back-to-dashboard">← Back to Dashboard</button>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="text" id="start_date" class="form-control datepicker" placeholder="Select start date" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="text" id="end_date" class="form-control datepicker" placeholder="Select end date" autocomplete="off">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary" id="filter-report">Show Records</button>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary" id="download-csv">
                    ⬇️ Download CSV
                </button>
            </div>

            <!-- <div class="col-md-3 d-flex align-items-end justify-content-end gap-2">
                <button class="btn btn-outline-secondary" onclick="printReport()">🖨️ Print Report</button>
            </div> -->

        </div>

        <div id="report-content">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div>Loading report...</div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
$(document).ready(function(){

    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });

    function loadVehicleReport(start = '', end = '') {
        $('#report-content').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><div>Loading report...</div></div>');
        $.get('ajax-vehicle-maintenance-report.php', { start_date: start, end_date: end }, function(res){
            $('#report-content').html(res);
            $('html, body').animate({ scrollTop: $('.card').offset().top }, 300);
        }).fail(function(){
            $('#report-content').html('<div class="alert alert-danger">Failed to load report.</div>');
        });
    }

    loadVehicleReport();

    $('#filter-report').click(function(){
        const start = $('#start_date').val();
        const end = $('#end_date').val();
        loadVehicleReport(start, end);
    });

    

    $('#back-to-dashboard').click(function(){
        $('#contentArea').html('<div class="text-center p-4">Loading dashboard...</div>');
        $.get('dashboard.php', function(res){
            $('#contentArea').html(res);
        });
    });
});

function printReport() {
    const reportContent = document.querySelector('#report-content').innerHTML;
    const printWindow = window.open('', '', 'height=900,width=1200');

    printWindow.document.write(`
        <html>
        <head>
            <title>Vehicle Maintenance Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
                th, td { border: 1px solid #000; padding: 6px; word-wrap: break-word; }

                th:nth-child(1), td:nth-child(1) { width: 4%;  text-align: center; }   /* # */
                th:nth-child(2), td:nth-child(2) { width: 12%; text-align: center; }  /* Vehicle Number */
                th:nth-child(3), td:nth-child(3) { width: 15%; text-align: center; }  /* Assigned User */
                th:nth-child(4), td:nth-child(4) { width: 11%; text-align: center; }  /* Date */
                th:nth-child(5), td:nth-child(5) { width: 28%; text-align: left; }    /* Problem Description */
                th:nth-child(6), td:nth-child(6) { width: 10%; text-align: left; }    /* Mileage / Meter */
                th:nth-child(7), td:nth-child(7) { width: 10%; text-align: right; }   /* Amount */
                th:nth-child(8), td:nth-child(8) { width: 10%; text-align: left; }    /* Handled By */

                th { background-color: #f0f0f0; }
                .text-center { text-align: center; }
                .text-end { text-align: right; }
                .text-start { text-align: left; }
                .text-wrap { white-space: normal; word-break: break-word; }

                @media print {
                    body { margin: 0; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                    thead { display: table-header-group; }
                    tfoot { display: table-footer-group; }
                }
            </style>
        </head>
        <body>
            ${reportContent}
            <script>
                window.onload = function() {
                    setTimeout(() => {
                        window.print();
                        window.close();
                    }, 300);
                };
            <\/script>
        </body>
        </html>
    `);

    printWindow.document.close();
}
</script>
<script>
(function(){
  // Build a safe filename from date inputs
  function buildFilename() {
    const s = document.getElementById('start_date')?.value || '';
    const e = document.getElementById('end_date')?.value || '';
    if (s && e) return `vehicle-maintenance-report_${s}_to_${e}.csv`;
    if (s) return `vehicle-maintenance-report_from_${s}.csv`;
    if (e) return `vehicle-maintenance-report_to_${e}.csv`;
    return 'vehicle-maintenance-report.csv';
  }

  // Turn a table element into CSV text
  function tableToCSV(table) {
    const rows = Array.from(table.querySelectorAll('tr'));
    const getText = (cell) => {
      // preserve visible text; trim; normalize spaces/newlines
      let t = (cell.textContent || '').replace(/\r?\n+/g, ' ').replace(/\s+/g, ' ').trim();
      return t;
    };
    const escapeCSV = (val) => {
      if (val == null) return '';
      const needsQuotes = /[",\n]/.test(val);
      let out = String(val).replace(/"/g, '""'); // escape quotes
      return needsQuotes ? `"${out}"` : out;
    };

    const lines = rows.map(tr => {
      const cells = Array.from(tr.querySelectorAll('th,td'));
      return cells.map(td => escapeCSV(getText(td))).join(',');
    });

    // Prepend UTF-8 BOM so Excel opens it cleanly
    return '\uFEFF' + lines.join('\n');
  }

  async function downloadCSV() {
    // find the first table inside the loaded report
    const table = document.querySelector('#report-content table');
    if (!table) {
      alert('No table found to export yet. Please load the report first.');
      return;
    }
    const csv = tableToCSV(table);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = buildFilename();
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }, 0);
  }

  // hook the button
  document.addEventListener('click', (e) => {
    if (e.target && e.target.id === 'download-csv') {
      downloadCSV();
    }
  });

  // OPTIONAL: auto-enable after each AJAX load if your report re-renders buttons
  $(document).ajaxComplete(function(){
    // no-op; listener is delegated on document above
  });
})();
</script>
