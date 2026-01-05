<?php
session_start();
include 'connections/connection.php';

// Fetch all menu keys from tbl_admin_menu_keys grouped by menu_group
$menuData = [];
$res = mysqli_query($conn, "SELECT * FROM tbl_admin_menu_keys ORDER BY menu_group, menu_label");
while($row = mysqli_fetch_assoc($res)){
    $menuData[$row['menu_group']][] = $row;
}
?>

<style>
  
/* --- Select2 tweaks --- */
.select2-container--default .select2-selection--single {
    height: 38px !important;
    padding: 0 12px !important;
    font-size: 1rem;
    line-height: 38px !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.375rem !important;
    background-color: #fff !important;
    display: flex;
    align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px !important;
    padding-left: 0 !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px !important;
    top: 0 !important;
    right: 10px !important;
}
.select2-container { width: 100% !important; }

/* --- Switch size & disabled look --- */
.form-switch .form-check-input { width: 2.8em; height: 1.4em; cursor: pointer; }
.form-check-input:disabled { opacity: .45; cursor: not-allowed; }

/* --- Professional "settings" layout for toggle groups --- */
.settings-group {
  border: 1px solid #e9ecef;
  border-radius: .75rem;
  background: #fff;
  margin-bottom: 1rem;
  overflow: hidden;
}
.settings-group .group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .75rem 1rem;
  border-bottom: 1px solid #eef2f6;
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 1;
}
.settings-group .group-title { font-weight: 600; color: #0d6efd; }
.group-body { display: grid; grid-template-columns: 1fr; }
@media (min-width: 992px) { .group-body { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1400px) { .group-body { grid-template-columns: repeat(3, 1fr); } }
.setting-item {
  display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; padding: .6rem 1rem; border-bottom: 1px dashed #eef2f6;
}
.setting-item:last-child { border-bottom: none; }
.setting-label { font-weight: 500; color: #334155; margin: 0; }

/* Dim everything when disabled-before-user */
#groupsWrapper.settings-disabled { opacity: .6; }
.highlight-jump { animation: jumpFlash 1.2s ease; }
@keyframes jumpFlash { 0% { background: #fffbe6; } 100% { background: transparent; } }


/* Put the toggle next to the group title (left-aligned) */
.settings-group .group-header{
  justify-content: flex-start; /* was: space-between */
  gap: .75rem;                 /* space between title and switch */
}

/* optional: a tiny nudge so it hugs the title nicely */
.settings-group .group-header .form-switch{
  margin: 0; 
}

</style>
<style>
/* Floating Save button */
#saveBtn {
  position: fixed;
  bottom: 25px;
  right: 35px;
  z-index: 999;
  border-radius: 50px;
  padding: 0.75rem 1.5rem;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  transition: all 0.2s ease;
}
#saveBtn:hover {
  transform: scale(1.05);
}

/* Toast notification */
#saveToast {
  position: fixed;
  bottom: 90px;
  right: 35px;
  background: #198754;
  color: white;
  padding: 0.75rem 1.25rem;
  border-radius: 0.5rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.15);
  opacity: 0;
  transition: opacity 0.3s ease, transform 0.3s ease;
  transform: translateY(10px);
  z-index: 1000;
}
#saveToast.show {
  opacity: 1;
  transform: translateY(0);
}
</style>

<div class="content font-size" id="contentArea">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">User Access Management</h5>

      <!-- User Dropdown -->
      <div class="mb-3">
        <label for="hris_id" class="form-label">Select User (HRIS ID)</label>
        <select id="hris_id" class="form-select" style="width: 100%;" onchange="fetchAccess()">
          <option value="">Select User</option>
          <?php
          $res = mysqli_query($conn, "SELECT hris, name FROM tbl_admin_users ORDER BY name");
          while($row = mysqli_fetch_assoc($res)){
              echo '<option value="'.$row['hris'].'">'.htmlspecialchars($row['name']).' ('.$row['hris'].')</option>';
          }
          ?>
        </select>
      </div>

      <!-- Menu Group Search Dropdown -->
      <div class="mb-3">
        <label for="menu_group_search" class="form-label">Search & Go to Access Group</label>
        <select id="menu_group_search" class="form-select" style="width: 100%;">
          <option value="">Search Group</option>
          <?php foreach(array_keys($menuData) as $group): ?>
            <option value="<?php echo htmlspecialchars($group); ?>"><?php echo htmlspecialchars($group); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Access Form -->
      <form id="accessForm">
        <input type="hidden" name="hris_id" id="form_hris_id">

        <div id="groupsWrapper">
          <?php foreach($menuData as $group => $items): ?>
            <?php $groupId = preg_replace('/\s+/', '_', $group); ?>
            <div class="settings-group" id="group_<?php echo htmlspecialchars($groupId); ?>">
              <div class="group-header">
                <div class="group-title"><?php echo htmlspecialchars($group); ?></div>
                <div class="form-check form-switch m-0">
                  <input
                    class="form-check-input group-checkbox"
                    type="checkbox" role="switch"
                    id="group_toggle_<?php echo htmlspecialchars($groupId); ?>"
                    data-group="<?php echo htmlspecialchars($groupId); ?>">
                  <label class="form-check-label small ms-2"
                         for="group_toggle_<?php echo htmlspecialchars($groupId); ?>"></label>
                </div>
              </div>

              <div class="group-body">
                <?php foreach($items as $menu): ?>
                  <div class="setting-item">
                    <label class="setting-label" for="menu_<?php echo $menu['menu_key']; ?>">
                      <?php echo htmlspecialchars($menu['menu_label']); ?>
                    </label>
                    <div class="form-check form-switch m-0">
                      <input class="form-check-input" type="checkbox" role="switch"
                             name="menu_keys[]" value="<?php echo $menu['menu_key']; ?>"
                             id="menu_<?php echo $menu['menu_key']; ?>">
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" id="saveBtn" class="btn btn-success mt-2">Save Access</button>
      </form>
      <div id="saveToast">Access saved successfully!</div>
      <div id="statusMsg" class="mt-3"></div>
    </div>
  </div>
</div>

<script>
// Enable/disable all permission controls until a user is selected
function setControlsEnabled(enabled){
  // Search select
  const searchSel = document.getElementById('menu_group_search');
  searchSel.disabled = !enabled;
  // Reflect disabled in Select2 UI
  $('#menu_group_search').prop('disabled', !enabled).trigger('change.select2');

  // Save button
  const saveBtn = document.getElementById('saveBtn');
  saveBtn.disabled = !enabled;

  // All toggles
  document.querySelectorAll('.group-checkbox, input[name="menu_keys[]"]').forEach(el => {
    el.disabled = !enabled;
  });

  // Dim groups visually when disabled
  const wrap = document.getElementById('groupsWrapper');
  if(wrap){
    wrap.classList.toggle('settings-disabled', !enabled);
  }
}

// Fetch and populate user access toggles
function fetchAccess(){
  const hris_id = document.getElementById('hris_id').value;
  document.getElementById('form_hris_id').value = hris_id;

  // Reset all toggles first
  document.querySelectorAll('input[name="menu_keys[]"]').forEach(cb => cb.checked = false);
  document.querySelectorAll('.group-checkbox').forEach(cb => cb.checked = false);

  if(hris_id){
    setControlsEnabled(true);

    fetch('fetch-user-access.php?hris_id=' + encodeURIComponent(hris_id))
      .then(res => res.json())
      .then(data => {
        data.forEach(key => {
          const checkbox = document.getElementById('menu_' + key);
          if(checkbox) checkbox.checked = true;
        });

        // Update each group's "All" switch
        document.querySelectorAll('.group-checkbox').forEach(groupCheckbox => {
          const groupName = groupCheckbox.getAttribute('data-group'); // sanitized id
          const groupContainer = document.getElementById('group_' + groupName);
          if(!groupContainer) return;
          const checkboxes = groupContainer.querySelectorAll('input[name="menu_keys[]"]');
          const allChecked = Array.from(checkboxes).length > 0 && Array.from(checkboxes).every(cb => cb.checked);
          groupCheckbox.checked = allChecked;
        });
      })
      .catch(err => {
        console.error('Fetch error:', err);
        alert('Error loading access data.');
      });
  } else {
    // No user selected -> lock controls
    setControlsEnabled(false);
  }
}

// Save access rights
document.getElementById('accessForm').addEventListener('submit', function(e){
  e.preventDefault();
  const hris_id = document.getElementById('form_hris_id').value;

  if (!hris_id) {
    document.getElementById('statusMsg').innerHTML =
      '<div class="alert alert-warning">Please select a user before saving access.</div>';
    return;
  }

  const formData = new FormData(this);
  fetch('save-user-access.php', {
    method: 'POST',
    body: formData
  })
  .then(() => {
    // Clear any leftover message box
    document.getElementById('statusMsg').innerHTML = '';

    // Show floating toast only
    const toast = document.getElementById('saveToast');
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  })

  .catch(err => {
    console.error('Save error:', err);
    alert('Error saving access.');
  });
});

// Initialize Select2 and handle menu group search + toggle logic
$(document).ready(function() {
  $('#hris_id').select2({
    placeholder: "Select a user",
    allowClear: false,
    width: 'resolve'
  });

  $('#menu_group_search').select2({
    placeholder: "Search access group",
    allowClear: false,
    width: 'resolve'
  });

  // Disable all controls on first load (no user selected)
  setControlsEnabled(false);

  // Jump to group on selection from search
  $('#menu_group_search').on('change', function(){
    if (this.disabled) return; // guard when disabled
    const selectedGroup = $(this).val();
    if(selectedGroup){
      const idPart = selectedGroup.replace(/\s+/g, '_');
      const groupContainer = document.getElementById('group_' + idPart);
      if(groupContainer){
        groupContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        groupContainer.classList.add('highlight-jump');
        setTimeout(() => groupContainer.classList.remove('highlight-jump'), 1200);
      }
    }
  });

  // Group "All" switch toggles all items in that group
  document.querySelectorAll('.group-checkbox').forEach(groupCheckbox => {
    groupCheckbox.addEventListener('change', function() {
      if (this.disabled) return;
      const groupName = this.getAttribute('data-group');
      const groupContainer = document.getElementById('group_' + groupName);
      if(!groupContainer) return;
      const checkboxes = groupContainer.querySelectorAll('input[name="menu_keys[]"]');
      checkboxes.forEach(cb => { if(!cb.disabled) cb.checked = this.checked; });
    });
  });

  // Individual toggle updates the group's "All" switch
  document.querySelectorAll('input[name="menu_keys[]"]').forEach(individualCheckbox => {
    individualCheckbox.addEventListener('change', function() {
      if (this.disabled) return;
      const groupContainer = this.closest('.settings-group');
      if(!groupContainer) return;
      const groupCheckbox = groupContainer.querySelector('.group-checkbox');
      const checkboxes = groupContainer.querySelectorAll('input[name="menu_keys[]"]');
      const allChecked = Array.from(checkboxes).length > 0 && Array.from(checkboxes).every(cb => cb.checked);
      if(groupCheckbox && !groupCheckbox.disabled) groupCheckbox.checked = allChecked;
    });
  });
});
</script>


<!-- 
/* Put every switch right next to its label */
.settings-group .group-header{
  justify-content: flex-start !important; /* was space-between */
  gap: .75rem;
}

.setting-item{
  justify-content: flex-start !important; /* was space-between */
  gap: .75rem;
  flex-wrap: wrap;                         /* wrap nicely on small screens */
}

.setting-label{
  margin: 0;
  flex: 0 0 auto;                          /* size to content only */
}

.setting-item .form-switch{
  margin: 0;                               /* tuck in tight to the label */
} -->
