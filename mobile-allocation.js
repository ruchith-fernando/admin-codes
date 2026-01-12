// mobile-allocation.js (FRESH, dynamic-safe, NO null addEventListener)
// Uses event delegation so it works even when page content is loaded later.

(function () {
  function $(id) { return document.getElementById(id); }

  function alertHtml(type, msg) {
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
  }

  function digitsOnly(v) {
    return (v || "").toString().replace(/\D+/g, "");
  }

  function isMobile9(v) {
    return /^\d{9}$/.test(v);
  }

  function isHris6(v) {
    return /^\d{6}$/.test((v || "").trim());
  }

  async function getJSON(url) {
    const r = await fetch(url, { credentials: "same-origin" });
    return await r.json();
  }

  async function postForm(url, data) {
    const r = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(data).toString(),
    });
    return await r.json();
  }

  function setSaveState() {
    const mobileEl = $("ma_mobile");
    const hrisEl   = $("ma_hris");
    const effEl    = $("ma_eff");
    const saveBtn  = $("ma_btn_save");
    const confirm  = $("ma_confirm");

    // If we're not on this page, do nothing (important for dynamic app)
    if (!mobileEl || !hrisEl || !effEl || !saveBtn || !confirm) return;

    const m = digitsOnly(mobileEl.value);
    const h = (hrisEl.value || "").trim();
    const e = (effEl.value || "").trim();

    mobileEl.value = m;

    const ok = isMobile9(m) && h !== "" && e !== "";

    confirm.disabled = !ok;
    if (!ok) confirm.checked = false;

    saveBtn.disabled = !ok || !confirm.checked;
  }

  // Delegated "blur" doesn't bubble, use "focusout" (it bubbles)
  document.addEventListener("focusout", async (ev) => {
    const t = ev.target;

    // MOBILE check
    if (t && t.id === "ma_mobile") {
      const box = $("ma_mobile_box");
      if (!box) return;

      const m = digitsOnly(t.value);
      t.value = m;

      box.innerHTML = "";
      if (!m) { setSaveState(); return; }

      if (!isMobile9(m)) {
        box.innerHTML = alertHtml("danger", "Mobile must be exactly 9 digits (example: 765455585).");
        setSaveState();
        return;
      }

      box.innerHTML = alertHtml("secondary", `Checking mobile <b>${m}</b>...`);

      try {
        const data = await getJSON(`api-mobile-check.php?mobile=${encodeURIComponent(m)}`);
        if (!data.ok) {
          box.innerHTML = alertHtml("danger", data.error || "Mobile check failed.");
        } else if (data.allocated && data.row) {
          box.innerHTML = alertHtml(
            "warning",
            `⚠️ <b>${data.row.mobile_number}</b> allocated to <b>${data.row.owner_name || "Unknown"}</b>
             (HRIS: <b>${data.row.hris_no || "-"}</b>) since <b>${data.row.effective_from}</b>.`
          );
        } else {
          box.innerHTML = alertHtml("success", `✅ <b>${m}</b> is not currently allocated.`);
        }
      } catch (e) {
        box.innerHTML = alertHtml("danger", "Mobile check failed (network error).");
      }

      setSaveState();
      return;
    }

    // HRIS check (only if 6-digit numeric)
    if (t && t.id === "ma_hris") {
      const box = $("ma_hris_box");
      const ownerEl = $("ma_owner");
      if (!box) return;

      const h = (t.value || "").trim();
      t.value = h;

      box.innerHTML = "";
      if (!h) { setSaveState(); return; }

      if (isHris6(h)) {
        box.innerHTML = alertHtml("secondary", `Checking employee for HRIS <b>${h}</b>...`);
        try {
          const data = await getJSON(`api-employee-check.php?hris=${encodeURIComponent(h)}`);
          if (data.ok && data.found) {
            if (ownerEl && !ownerEl.value.trim()) ownerEl.value = data.owner_name || "";
            box.innerHTML = alertHtml("success", `✅ Employee: <b>${data.owner_name || "Unknown"}</b>`);
          } else {
            box.innerHTML = alertHtml("warning", "⚠️ HRIS not found in employee table. You can still save.");
          }
        } catch (e) {
          box.innerHTML = alertHtml("danger", "Employee check failed (network error).");
        }
      } else {
        box.innerHTML = alertHtml("info", "HRIS entered (no employee lookup). You can save.");
      }

      setSaveState();
      return;
    }

  }, true);

  // Click handlers (delegated)
  document.addEventListener("click", async (ev) => {
    const t = ev.target;

    // Check button
    if (t && t.id === "ma_btn_check") {
      const alertBox = $("ma_alert");
      if (alertBox) alertBox.innerHTML = alertHtml("info", "Enter Mobile + HRIS. Tab/click out to auto-check. Confirm and Save.");
      return;
    }

    // Save button
    if (t && t.id === "ma_btn_save") {
      const alertBox = $("ma_alert");
      const resultBox = $("ma_result");
      const mobileEl = $("ma_mobile");
      const hrisEl = $("ma_hris");
      const ownerEl = $("ma_owner");
      const effEl = $("ma_eff");
      const confirmEl = $("ma_confirm");

      if (!alertBox || !mobileEl || !hrisEl || !effEl || !confirmEl) return;

      const m = digitsOnly(mobileEl.value);
      const h = (hrisEl.value || "").trim();
      const o = ownerEl ? (ownerEl.value || "").trim() : "";
      const e = (effEl.value || "").trim();

      mobileEl.value = m;

      if (!isMobile9(m)) {
        alertBox.innerHTML = alertHtml("danger", "Mobile must be exactly 9 digits.");
        setSaveState();
        return;
      }
      if (!h || !e) {
        alertBox.innerHTML = alertHtml("danger", "Mobile, HRIS and Effective From are required.");
        setSaveState();
        return;
      }
      if (!confirmEl.checked) {
        alertBox.innerHTML = alertHtml("warning", "Please confirm the details first.");
        return;
      }

      // disable button while saving
      t.disabled = true;

      try {
        const res = await postForm("api-mobile-save.php", {
          mobile: m,
          hris: h,
          owner: o,
          effective_from: e
        });

        if (!res.ok) {
          alertBox.innerHTML = alertHtml("danger", res.error || "Save failed.");
          setSaveState();
          return;
        }

        alertBox.innerHTML = alertHtml("success", res.message || "Saved.");
        if (resultBox) {
          resultBox.innerHTML = alertHtml("success", `<b>Saved</b><br>Action: <b>${res.action || "-"}</b><br>Mobile: <b>${m}</b><br>HRIS: <b>${h}</b>`);
        }

        confirmEl.checked = false;
        setSaveState();

      } catch (e) {
        alertBox.innerHTML = alertHtml("danger", "Save failed (network error).");
        setSaveState();
      }

      return;
    }
  });

  // Confirm switch affects save state
  document.addEventListener("change", (ev) => {
    if (ev.target && ev.target.id === "ma_confirm") setSaveState();
    if (ev.target && ev.target.id === "ma_eff") setSaveState();
  });

  // Initial
  setInterval(setSaveState, 500); // keeps UI in sync even with dynamic page swaps
})();
