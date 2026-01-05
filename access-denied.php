<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<style>
    .alert-danger {
        border-left: 6px solid #dc3545;
    }
    .access-denied-card {
        padding: 30px;
        border-radius: 10px;
        background-color: #fff;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="access-denied-card">
                <h3 class="text-danger mb-3">🚫 Access Denied</h3>
                <p class="mb-0">You do not have permission to access this section.</p>
            </div>
        </div>
    </div>
</div>
