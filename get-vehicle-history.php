<?php
// get-vehicle-history.php
require_once 'connections/connection.php';

$vehicleId = isset($_GET['vehicle_id']) && is_numeric($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

if ($vehicleId <= 0) {
    echo '<div class="alert alert-info">Please select a valid vehicle to view its history.</div>';
    exit;
}
?>

<!-- Vehicle History Sections -->
<div class="mb-4">
    <h6>Maintenance Records</h6>
    <input type="text" id="search-maintenance" class="form-control mb-2" placeholder="Search maintenance...">
    <div id="maintenance-container"></div>
</div>

<div class="mb-4">
    <h6>Service Records</h6>
    <input type="text" id="search-service" class="form-control mb-2" placeholder="Search service...">
    <div id="service-container"></div>
</div>

<div class="mb-4">
    <h6>License & Insurance Records</h6>
    <input type="text" id="search-license" class="form-control mb-2" placeholder="Search license...">
    <div id="license-container"></div>
</div>

<!-- Section Loader Script -->
<script>
(function () {
    const vehicleId = <?= json_encode($vehicleId) ?>;

    function loadSection(section, page = 1, query = '') {
        if (!vehicleId) return;
        const container = `#${section}-container`;
        $(container).html('<div class="text-muted p-2">Loading...</div>');

        $.get(`ajax-get-${section}.php`, { vehicle_id: vehicleId, page, query }, function (data) {
            $(container).html(data);
        });
    }

    ['maintenance', 'service', 'license'].forEach(section => {
        $(`#search-${section}`).off('keyup').on('keyup', function () {
            loadSection(section, 1, $(this).val());
        });

        $(document).off('click', `.pagination-${section} a`).on('click', `.pagination-${section} a`, function (e) {
            e.preventDefault();
            const page = $(this).data('page');
            const query = $(`#search-${section}`).val();
            loadSection(section, page, query);
        });

        loadSection(section); // initial load
    });
})();
</script>
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="modalImage" src="" class="img-fluid" alt="Preview Image">
      </div>
    </div>
  </div>
</div>

<script>
  const imageModalEl = document.getElementById('imageModal');
  const modalImageEl = document.getElementById('modalImage');
  const imageModalInstance = new bootstrap.Modal(imageModalEl);

  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('maintenance-image') || e.target.classList.contains('service-image')) {
      const imgSrc = e.target.getAttribute('data-img');
      modalImageEl.src = imgSrc;
      imageModalInstance.show();
    }
  });

  imageModalEl.addEventListener('hidden.bs.modal', function () {
    modalImageEl.src = '';
  });
</script>
