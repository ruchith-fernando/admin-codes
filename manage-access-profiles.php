<?php
// manage-access-profiles.php
session_start();
include 'connections/connection.php';

// Load all menu keys grouped
$menuData = [];
$res = mysqli_query($conn, "SELECT * FROM tbl_admin_menu_keys ORDER BY menu_group, menu_label");
while($row = mysqli_fetch_assoc($res)){
  $menuData[$row['menu_group']][] = $row;
}
?>
<style>
.select2-container { width: 100% !important; }
.settings-group {
  border: 1px solid #e9ecef; border-radius: .75rem; background: #fff; margin-bottom: 1rem; overflow: hidden;
}
.settings-group .group-header{
  display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; border-bottom:1px solid #eef2f6;
}
.group-title{ font-weight:600; color:#0d6efd; }
.group-body{ display:grid; grid-template-columns:1fr; }
@media (min-width: 992px){ .group-body{ grid-template-columns:repeat(2,1fr);} }
@media (min-width: 1400px){ .group-body{ grid-template-columns:repeat(3,1fr);} }
.setting-item{ display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.6rem 1rem; border-bottom:1px dashed #eef2f6;}
.setting-item:last-child{ border-bottom:none; }
.setting-label{ font-weight:500; color:#334155; margin:0; }
</style>

<div class="content font-size" id="contentArea">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-primary mb-0">Access Profiles (Roles)</h5>
        <a href="user-access-management.php" class="btn btn-outline-secondary">← Back to User Access</a>
      </div>

      <!-- Create / Select Profile -->
      <div class="row g-3 mb-3">
        <div class="col-lg-4">
          <label class="form-label">Existing Profiles</label>
          <select id="profile_list" class="form-select">
            <option value="">— New Profile —</option>
          </select>
          <div class="form-text">Pick a profile to edit, or choose “New Profile” to create one.</div>
        </div>
        <div class="col-lg-4">
          <label class="form-label">Profile Key (unique, no spaces)</label>
          <input type="text" id="profile_key" class="form-control" placeholder="e.g. ops, viewer, admin">
        </div>
        <div class="col-lg-4">
          <label class="form-label">Profile Label</label>
          <input type="text" id="profile_label" class="form-control" placeholder="e.g. Operations, Viewer (Read-only)">
        </div>
      </div>

      <div class="mb-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="wildcard_all">
          <label class="form-check-label" for="wildcard_all">
            Grant <b>ALL</b> pages to this profile (wildcard). Ticking this will ignore individual selections.
          </label>
        </div>
      </div>

      <form id="profileForm">
        <input type="hidden" name="profile_key" id="form_profile_key">
        <div id="groupsWrapper">
          <?php foreach($menuData as $group => $items): $gid = preg_replace('/\s+/', '_', $group); ?>
            <div class="settings-group" id="group_<?php echo htmlspecialchars($gid); ?>">
              <div class="group-header">
                <div class="group-title"><?php echo htmlspecialchars($group); ?></div>
              </div>
              <div class="group-body">
                <?php foreach($items as $menu): ?>
                  <div class="setting-item">
                    <label class="setting-label" for="menu_<?php echo $menu['menu_key']; ?>"><?php echo htmlspecialchars($menu['menu_label']); ?></label>
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

        <div class="d-flex gap-2">
          <button type="button" id="btnSaveProfile" class="btn btn-success">Save Profile</button>
          <button type="button" id="btnDeleteProfile" class="btn btn-outline-danger">Delete Profile</button>
        </div>
      </form>

      <div id="statusMsg" class="mt-3"></div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function loadProfiles(){
  return fetch('fetch-profiles.php')
    .then(r=>r.json())
    .then(rows=>{
      const sel = document.getElementById('profile_list');
      for(let i=sel.options.length-1;i>=1;i--) sel.remove(i);
      rows.forEach(p=>{
        const opt=document.createElement('option');
        opt.value=p.profile_key; opt.textContent=p.profile_label + ' ('+p.profile_key+')';
        sel.appendChild(opt);
      });
    });
}
function clearChecks(){
  document.querySelectorAll('input[name="menu_keys[]"]').forEach(cb=>cb.checked=false);
}
function loadProfileItems(pkey){
  clearChecks();
  document.getElementById('wildcard_all').checked=false;
  if(!pkey) return;
  fetch('fetch-profile-items.php?profile_key='+encodeURIComponent(pkey))
    .then(r=>r.json())
    .then(keys=>{
      // detect wildcard by presence of every key
      const total = document.querySelectorAll('input[name="menu_keys[]"]').length;
      if (keys.length===0) return;
      if (keys.length >= total) {
        // it might be wildcard OR just all listed; we can’t tell perfectly—assume wildcard only if exactly all
        document.getElementById('wildcard_all').checked = (keys.length === total);
      }
      keys.forEach(k=>{
        const cb=document.getElementById('menu_'+k);
        if(cb) cb.checked=true;
      });
    });
}

document.addEventListener('DOMContentLoaded', ()=>{
  loadProfiles();

  // Switch profile selection
  document.getElementById('profile_list').addEventListener('change', function(){
    const pkey=this.value;
    document.getElementById('profile_key').value = pkey || '';
    document.getElementById('profile_label').value = this.selectedIndex>0 ? this.options[this.selectedIndex].text.replace(/\s*\(.*\)$/,'') : '';
    loadProfileItems(pkey);
  });

  // Wildcard toggle
  document.getElementById('wildcard_all').addEventListener('change', function(){
    if(this.checked){
      // Check all keys
      document.querySelectorAll('input[name="menu_keys[]"]').forEach(cb=>cb.checked=true);
    }
  });

  // Save Profile
  document.getElementById('btnSaveProfile').addEventListener('click', ()=>{
    const pkey = document.getElementById('profile_key').value.trim();
    const plabel = document.getElementById('profile_label').value.trim();
    if(!pkey || !plabel){
      document.getElementById('statusMsg').innerHTML = '<div class="alert alert-warning">Profile Key and Label are required.</div>';
      return;
    }
    const menu_keys = Array.from(document.querySelectorAll('input[name="menu_keys[]"]:checked')).map(cb=>cb.value);
    const wildcard = document.getElementById('wildcard_all').checked ? 'yes' : 'no';

    const form = new FormData();
    form.set('profile_key', pkey);
    form.set('profile_label', plabel);
    form.set('wildcard_all', wildcard);
    menu_keys.forEach(k=>form.append('menu_keys[]', k));

    fetch('save-profile.php', { method:'POST', body: form })
      .then(r=>r.text())
      .then(msg=>{
        document.getElementById('statusMsg').innerHTML = msg;
        loadProfiles().then(()=> {
          // reselect current
          const sel = document.getElementById('profile_list');
          for (let i=0;i<sel.options.length;i++){
            if(sel.options[i].value===pkey){ sel.selectedIndex=i; break; }
          }
        });
      })
      .catch(e=>{
        console.error(e);
        document.getElementById('statusMsg').innerHTML = '<div class="alert alert-danger">Failed to save profile.</div>';
      });
  });

  // Delete Profile
  document.getElementById('btnDeleteProfile').addEventListener('click', ()=>{
    const pkey = document.getElementById('profile_key').value.trim();
    if(!pkey){ document.getElementById('statusMsg').innerHTML='<div class="alert alert-warning">Pick a profile to delete.</div>'; return; }
    if(!confirm('Delete profile "'+pkey+'"? This cannot be undone.')) return;

    const form = new FormData();
    form.set('profile_key', pkey);
    fetch('delete-profile.php', { method:'POST', body: form })
      .then(r=>r.text())
      .then(msg=>{
        document.getElementById('statusMsg').innerHTML = msg;
        // reset form
        document.getElementById('profile_list').selectedIndex=0;
        document.getElementById('profile_key').value='';
        document.getElementById('profile_label').value='';
        clearChecks();
        document.getElementById('wildcard_all').checked=false;
        loadProfiles();
      })
      .catch(e=>{
        console.error(e);
        document.getElementById('statusMsg').innerHTML = '<div class="alert alert-danger">Failed to delete profile.</div>';
      });
  });
});
</script>
