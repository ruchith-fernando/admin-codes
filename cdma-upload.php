<!-- cdma-upload.php -->

<?php session_start(); ?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">CDMA Monthly Data Upload</h5>

        <div class="mb-3">
          <form id="uploadForm" enctype="multipart/form-data">
              <label class="form-label">Select CDMA Bill (PDF)</label>
              <input type="file" name="cdma_pdf" class="form-control mb-3" accept="application/pdf" required>
              <button type="submit" class="btn btn-primary">Upload & Process</button>
          </form>
        </div>

        <div id="result" class="mb-3"></div>

    </div>
  </div>
</div>
<script>
$("#uploadForm").submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
        url: 'cdma-processor.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        beforeSend: function() {
            $("#result").html('<div class="alert alert-info">Processing PDF file, please wait...</div>');
        },
        success: function(response){
            $("#result").html(response);
        },
        error: function(){
            $("#result").html('<div class="alert alert-danger">Something went wrong. Please try again.</div>');
        }
    });
});
</script>
</body>
</html>
