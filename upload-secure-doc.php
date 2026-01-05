<?php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['name']) || !in_array($_SESSION['user_level'], ['manager', 'super-admin'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
?>
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <h5 class="mb-4 text-primary">Upload Secure PDF</h5>
            <div id="messageArea"></div>

            <form id="secureDocForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>Document Number</label>
                    <input type="text" name="document_number" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" required></textarea>
                </div>
                <div class="mb-3">
                    <label>Access Level</label>
                    <select name="access_level" class="form-control" required>
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>PDF File</label>
                    <input type="file" name="pdf_file" class="form-control" accept="application/pdf" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#secureDocForm').on('submit', function(e) {
        e.preventDefault();

        let formData = new FormData(this);

        $.ajax({
            url: 'ajax-upload-secure-doc-action.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                $('#messageArea').html('<div class="alert alert-info">' + response + '</div>');
                $('#secureDocForm')[0].reset();
            },
            error: function(xhr) {
                $('#messageArea').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>
