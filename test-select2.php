<!DOCTYPE html>
<html>
<head>
  <title>Select2 Branch Test</title>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>

<h3>Test Branch Select2 (Filtered by Month)</h3>

<label>Select Month</label>
<select id="month" class="form-select" style="width: 300px;">
  <option value="">-- Choose Month --</option>
  <option value="April 2025">April 2025</option>
  <option value="May 2025">May 2025</option>
</select>

<br><br>

<label>Select Branch Code</label>
<select id="branch_code" class="form-select" style="width: 300px;"></select>

<script>
$(document).ready(function() {
    function initSelect2() {
        $('#branch_code').select2({
            placeholder: 'Select Branch Code',
            allowClear: true,
            ajax: {
                url: 'get-available-branches.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term || '',
                        month: $('#month').val()
                    };
                },
                processResults: function (data) {
                    console.log("Received:", data);
                    return {
                        results: data
                    };
                }
            }
        });
    }

    $('#month').change(function() {
        $('#branch_code').empty(); // clear previous
        initSelect2();
    });
});
</script>

</body>
</html>
