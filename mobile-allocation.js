(function(){
  const $ = (id) => document.getElementById(id);

  function show(el){ el.style.display=''; }
  function hide(el){ el.style.display='none'; }

  function resetConfirm() {
    $("alloc_confirm_checked").checked = false;
    $("alloc_confirm_checked").disabled = true;
    $("alloc_btn_save").disabled = true;
  }

  function setMsg(html, cls="alert-info"){
    $("alloc_status_msg").innerHTML = `<div class="alert ${cls} py-2 mb-0">${html}</div>`;
  }

  $("alloc_request_type").addEventListener("change", () => {
    const type = $("alloc_request_type").value;

    hide($("alloc_to_hris_row"));
    hide($("alloc_close_row"));
    resetConfirm();
    $("alloc_preview_area").innerHTML = "";
    $("alloc_existing_box").classList.add("d-none");
    $("alloc_existing_box").innerHTML = "";

    if (type === "NEW" || type === "TRANSFER") show($("alloc_to_hris_row"));
    if (type === "CLOSE") show($("alloc_close_row"));
  });

  $("alloc_btn_preview").addEventListener("click", async () => {
    resetConfirm();
    $("alloc_preview_area").innerHTML = "";
    setMsg("Previewing…");

    const payload = {
      request_type: $("alloc_request_type").value,
      mobile_number: $("alloc_mobile").value.trim(),
      effective_from: $("alloc_eff_from").value,
      to_hris_no: ($("alloc_to_hris").value || "").trim(),
      owner_name: ($("alloc_owner_name").value || "").trim(),
      note: ($("alloc_note").value || "").trim(),
      effective_to: $("alloc_eff_to") ? $("alloc_eff_to").value : "",
      close_note: $("alloc_close_note") ? $("alloc_close_note").value.trim() : ""
    };

    const res = await fetch("ajax-mobile_allocation_preview.php", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (!data.ok) {
      setMsg("❌ " + (data.error || "Preview failed"), "alert-danger");
      return;
    }

    if (data.existing_html) {
      $("alloc_existing_box").classList.remove("d-none");
      $("alloc_existing_box").innerHTML = data.existing_html;
    }

    $("alloc_preview_area").innerHTML = data.preview_html || "";
    setMsg("✅ Preview ready. Please confirm to enable Save.", "alert-success");

    $("alloc_confirm_checked").disabled = false;
    $("alloc_confirm_checked").addEventListener("change", () => {
      $("alloc_btn_save").disabled = !$("alloc_confirm_checked").checked;
    }, { once:true });
  });

  $("alloc_btn_save").addEventListener("click", async () => {
    if (!$("alloc_confirm_checked").checked) return;

    setMsg("Saving as pending…");

    const payload = {
      request_type: $("alloc_request_type").value,
      mobile_number: $("alloc_mobile").value.trim(),
      effective_from: $("alloc_eff_from").value,
      to_hris_no: ($("alloc_to_hris").value || "").trim(),
      owner_name: ($("alloc_owner_name").value || "").trim(),
      note: ($("alloc_note").value || "").trim(),
      effective_to: $("alloc_eff_to") ? $("alloc_eff_to").value : "",
      close_note: $("alloc_close_note") ? $("alloc_close_note").value.trim() : ""
    };

    const res = await fetch("ajax-mobile_allocation_save.php", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (!data.ok) {
      setMsg("❌ " + (data.error || "Save failed"), "alert-danger");
      return;
    }

    setMsg("✅ Saved as PENDING. Request ID: " + data.request_id, "alert-success");
    $("alloc_btn_save").disabled = true;
    $("alloc_confirm_checked").disabled = true;
  });

})();
