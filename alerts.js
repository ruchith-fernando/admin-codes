// alerts.js

// Function to show the success SweetAlert
function showSuccessAlert() {
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'New record created successfully',
        position: 'center',
        allowOutsideClick: false // Disable outside clicking
    }).then(function () {
        window.location = 'security-payments.php'; // Redirect after success
    });
}
