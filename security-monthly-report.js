// security-monthly-report.js
$(document).ready(function () {

    // =========================
    // Helpers
    // =========================

    // firm id 3 = "Other" → invoice mode
    function isInvoiceFirm() {
        return $('#firm_select').val() === '3';
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/[&<>"']/g, function (c) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#039;'
            }[c];
        });
    }

    function getReasonOptionsHtml() {
        if (typeof SECURITY_REASON_OPTIONS !== 'undefined') {
            let s = String(SECURITY_REASON_OPTIONS || '').trim();
            if (s.length) return s;
        }
        return '<option value="">-- Select Reason --</option>';
    }

    // Defaults so summary always shows names even before invoice list loads
    let INVOICE_BRANCH_NAME_MAP = {
        '2014': 'Police',
        '2015': 'Additional Security',
        '2016': 'Radio Transmission'
    };

    // =========================
    // Row Lock (after save)
    // =========================
    function lockRow(row) {
        row.addClass('saved-row');
        row.find('input, textarea').prop('readonly', true);
        row.find('select').prop('disabled', true);
        row.find('input, textarea, select').addClass('bg-light');
    }

    // =========================
    // Invoice summary container + button
    // =========================
    function ensureInvoiceTotalsContainer() {
        if ($('#invoice_totals_container').length) return;

        let wrap = $('#manual_form .table-responsive').first();
        if (wrap.length) {
            // After manual entry table (below manual entry), before anything else
            wrap.after('<div id="invoice_totals_container" class="mt-2"></div>');
        } else if ($('#manual_form').length) {
            $('#manual_form').append('<div id="invoice_totals_container" class="mt-2"></div>');
        } else {
            $('body').append('<div id="invoice_totals_container" class="mt-2"></div>');
        }
    }

    function ensureInvoiceSaveButton() {
        ensureInvoiceTotalsContainer();

        if (!$('#invoice_save_entry_wrap').length) {
            $('#invoice_totals_container').prepend(`
                <div id="invoice_save_entry_wrap" class="mb-2">
                    <button type="button" id="invoice_save_entry" class="btn btn-success">
                        Save Entry
                    </button>
                </div>
            `);
        }
    }

    function toggleSaveButtons() {
        if (isInvoiceFirm()) {
            ensureInvoiceSaveButton();
            $('#invoice_save_entry_wrap').removeClass('d-none');
            $('#save_entry').addClass('d-none'); // hide original in invoice mode
        } else {
            $('#save_entry').removeClass('d-none');
            $('#invoice_save_entry_wrap').addClass('d-none');
        }
    }

    // Summary includes "2014 - BranchName"
    function renderInvoiceTotalsTable(totals) {
        ensureInvoiceTotalsContainer();
        ensureInvoiceSaveButton();

        const codes = ['2014', '2015', '2016'];

        function fmt(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // keep button, replace everything under it
        $('#invoice_totals_container').children().not('#invoice_save_entry_wrap').remove();

        // ✅ CHANGED: smaller + tighter summary table
        let html = `
            <div class="mt-2 table-responsive d-inline-block"
                 style="max-width: 700px; font-size: 12px; line-height: 2.5;">
                <table class="table table-bordered table-sm w-auto mb-0 small">
                    <thead>
                        <tr class="table-light">
                            <th style="white-space:nowrap;">Branch</th>
                            <th style="white-space:nowrap;">Pending (Total)</th>
                            <th style="white-space:nowrap;">Approved (Total)</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        codes.forEach(function (c) {
            const row = (totals && totals[c]) ? totals[c] : { pending: 0, approved: 0 };
            const bn = INVOICE_BRANCH_NAME_MAP[c] ? ` - ${escapeHtml(INVOICE_BRANCH_NAME_MAP[c])}` : '';
            html += `
                <tr>
                    <td style="white-space:nowrap;">${c}${bn}</td>
                    <td style="white-space:nowrap;">${fmt(row.pending)}</td>
                    <td style="white-space:nowrap;">${fmt(row.approved)}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        $('#invoice_totals_container').append(html);
        toggleSaveButtons();
    }

    function loadInvoiceTotalsForFirmMonth(firm_id, month) {
        ensureInvoiceTotalsContainer();
        ensureInvoiceSaveButton();

        $('#invoice_totals_container').children().not('#invoice_save_entry_wrap').remove();
        if (!firm_id || !month) return;

        $('#invoice_totals_container').append('<div class="text-muted invoiceTotalsLoading mt-2">Loading totals...</div>');

        $.ajax({
            url: 'security-2000-invoice-totals.php',
            method: 'POST',
            dataType: 'json',
            data: { firm_id: firm_id, month: month }
        })
        .done(function (res) {
            $('.invoiceTotalsLoading').remove();
            if (res && res.success) {
                renderInvoiceTotalsTable(res.totals || {});
            } else {
                let msg = (res && res.message) ? res.message : 'Unknown error loading totals.';
                $('#invoice_totals_container').append(`<div class="alert alert-danger">${escapeHtml(msg)}</div>`);
            }
        })
        .fail(function (xhr) {
            $('.invoiceTotalsLoading').remove();
            let txt = xhr && xhr.responseText ? xhr.responseText : (xhr.statusText || 'Request failed');
            $('#invoice_totals_container').append(
                `<div class="alert alert-danger">
                    Failed to load totals. Check <b>security-2000-invoice-totals.php</b> exists and returns JSON.<br>
                    <small>${escapeHtml(txt)}</small>
                 </div>`
            );
        });
    }

    // =========================
    // Layout
    // =========================
    function setLayoutForFirm(row) {
        const invoiceMode = isInvoiceFirm();

        if (invoiceMode) {
            $('.col-budget, .col-shifts, .col-reason').addClass('d-none');
            $('.col-invoice').removeClass('d-none');

            row.find('.budget_shifts').val('');
            row.find('.shifts').val('');
            row.find('.reason_select').addClass('d-none').val('');

            row.find('.invoice_no').removeClass('d-none');
            row.find('.amount').prop('readonly', false);
        } else {
            $('.col-budget, .col-shifts, .col-reason').removeClass('d-none');
            $('.col-invoice').addClass('d-none');

            row.find('.invoice_no').addClass('d-none').val('');
            row.find('.amount').prop('readonly', true);
        }

        toggleSaveButtons();
    }

    function clearRow(row) {
        row.find('.branch_code').val('');
        row.find('.branch_name').val('');
        row.find('.budget_shifts').val('');
        row.find('.shifts').val('').prop('readonly', false);
        row.find('.amount').val('').prop('readonly', true);
        row.find('.previous_month_info').val('');
        row.find('.reason_select').addClass('d-none').val('');
        row.find('.provision').val('no').prop('disabled', false);
        row.find('.invoice_no').val('');
        row.data('budget_shifts', 0);

        setLayoutForFirm(row);
    }

    function appendBlankRow() {
        const reasonOptions = getReasonOptionsHtml();

        $('#entry_rows').append(`
<tr>
    <td><input type="text" class="form-control branch_code" maxlength="5" /></td>
    <td><input type="text" class="form-control branch_name" readonly /></td>
    <td class="col-budget"><input type="number" class="form-control budget_shifts" readonly /></td>
    <td class="col-shifts"><input type="number" class="form-control shifts" min="1" /></td>
    <td class="col-reason">
        <select class="form-select reason_select d-none">
            ${reasonOptions}
        </select>
    </td>
    <td>
        <select class="form-select provision">
            <option value="no">No</option>
            <option value="yes">Yes</option>
        </select>
    </td>
    <td>
        <textarea class="form-control previous_month_info" readonly rows="1"
            style="resize:none;overflow:hidden;height:auto;"></textarea>
    </td>
    <td class="col-invoice d-none">
        <input type="text" class="form-control invoice_no" />
    </td>
    <td><input type="text" class="form-control amount" readonly /></td>
</tr>`);
        setLayoutForFirm($('#entry_rows tr').last());
    }

    // =========================
    // Load existing invoices (Other / id 3)
    // NOTE: requires provision to be returned by PHP
    // =========================
    function loadInvoicesForFirmMonth(firm_id, month) {
        $('#entry_rows').empty();

        $.ajax({
            url: 'security-2000-invoice-list.php',
            method: 'POST',
            dataType: 'json',
            data: { firm_id: firm_id, month: month }
        })
        .done(function (res) {
            if (res && Array.isArray(res.invoices) && res.invoices.length) {
                res.invoices.forEach(function (inv) {
                    const branch_code = escapeHtml(inv.branch_code || '');
                    const branch_name = escapeHtml(inv.branch_name || '');
                    const invoice_no  = escapeHtml(inv.invoice_no || '');

                    if (branch_code && branch_name) {
                        INVOICE_BRANCH_NAME_MAP[String(branch_code)] = String(inv.branch_name);
                    }

                    let statusLabel = '';
                    if (inv.status) {
                        const s = String(inv.status).toLowerCase();
                        if (s === 'approved') statusLabel = ' (Approved)';
                        else if (s === 'pending') statusLabel = ' (Pending)';
                        else if (s === 'rejected') statusLabel = ' (Rejected)';
                    }

                    const prov = (String(inv.provision || 'no').toLowerCase() === 'yes') ? 'yes' : 'no';
                    const provNoSel = (prov === 'no') ? 'selected' : '';
                    const provYesSel = (prov === 'yes') ? 'selected' : '';

                    let amountFormatted = '';
                    if (inv.amount !== undefined && inv.amount !== null && inv.amount !== '') {
                        const num = Number(inv.amount);
                        amountFormatted = !isNaN(num)
                            ? num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                            : escapeHtml(inv.amount);
                    }

                    $('#entry_rows').append(`
<tr class="existing-invoice-row">
    <td><input type="text" class="form-control" value="${branch_code}" readonly /></td>
    <td><input type="text" class="form-control" value="${branch_name}" readonly /></td>

    <td class="col-budget d-none"><input type="number" class="form-control" readonly /></td>
    <td class="col-shifts d-none"><input type="number" class="form-control" readonly /></td>
    <td class="col-reason d-none"><select class="form-select" disabled><option value="">--</option></select></td>

    <td>
        <select class="form-select provision" disabled>
            <option value="no" ${provNoSel}>No</option>
            <option value="yes" ${provYesSel}>Yes</option>
        </select>
    </td>

    <td>
        <textarea class="form-control previous_month_info" readonly rows="1"
            style="resize:none;overflow:hidden;height:auto;"></textarea>
    </td>

    <td class="col-invoice">
        <input type="text" class="form-control" value="${invoice_no + statusLabel}" readonly />
    </td>

    <td><input type="text" class="form-control" value="${amountFormatted}" readonly /></td>
</tr>`);
                });
            }
        })
        .always(function () {
            appendBlankRow();
        });
    }

    // =========================
    // Alerts (unchanged)
    // =========================
    function updateSecurityAlerts(res, month, alertSelector) {
        let output = '';

        if (res.missing_by_firm && Object.keys(res.missing_by_firm).length) {
            let groups = Object.keys(res.missing_by_firm).map(function (key) {
                return res.missing_by_firm[key];
            });

            groups.sort(function (a, b) {
                let aid = parseInt(a.firm_id || 0, 10);
                let bid = parseInt(b.firm_id || 0, 10);
                let aIsOther = (aid === 3);
                let bIsOther = (bid === 3);
                if (aIsOther && !bIsOther) return 1;
                if (!aIsOther && bIsOther) return -1;
                if (aid === bid) return 0;
                return aid - bid;
            });

            let totalMissing = 0;
            groups.forEach(function (g) {
                let list = g.branches || [];
                totalMissing += list.length;
            });

            if (totalMissing > 0) {
                output += `<b>${totalMissing} branches have not submitted security charges for ${month}:</b><br>`;
            }

            groups.forEach(function (g) {
                let list = g.branches || [];
                if (!list.length) return;
                let firmName = g.firm_name || '-';
                output += `<div class="mt-2"><b>${firmName}</b><br>${list.join(', ')}</div>`;
            });

        } else if (res.missing && res.missing.length) {
            output += `<b>${res.missing.length} branches</b> have not submitted security charges for <b>${month}</b>:<br>${res.missing.join(', ')}`;
        }

        if (res.provisions && res.provisions.length) {
            output += `<br><b>${res.provisions.length} branches</b> have provisional data:<br>${res.provisions.join(', ')}`;
        }

        if (res.pending_count && res.pending_count > 0) {
            output += `<hr class="my-2">`;
            output += `<span class="text-danger fw-bold">Additionally, ${res.pending_count} branches have submitted security charges that are still pending approval for ${month}.</span>`;
        }

        if (output) {
            $(alertSelector).removeClass('d-none').html(output);
        } else {
            $(alertSelector).addClass('d-none').html('');
        }
    }

    // =========================
    // Amount: allow NEGATIVE for:
    //   - invoice mode (Other)
    //   - provision == yes (normal firms adjustments)
    // =========================
    $(document).on('input', '.amount', function () {
        let v = String($(this).val() || '');

        // allow digits, comma, dot, minus
        v = v.replace(/[^0-9.,-]/g, '');

        // only ONE minus, and ONLY at start
        v = v.replace(/(?!^)-/g, '');
        if (v.length > 1) {
            v = (v[0] === '-') ? ('-' + v.slice(1).replace(/-/g, '')) : v.replace(/-/g, '');
        }

        // allow only ONE dot
        let firstDot = v.indexOf('.');
        if (firstDot !== -1) {
            let before = v.slice(0, firstDot + 1);
            let after = v.slice(firstDot + 1).replace(/\./g, '');
            v = before + after;
        }

        $(this).val(v);
    });

    $(document).on('blur', '.amount', function () {
        let raw = String($(this).val() || '').replace(/,/g, '').trim();

        // allow empty or just "-"
        if (raw === '' || raw === '-') return;

        let num = Number(raw);
        if (!isNaN(num)) {
            $(this).val(num.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        }
    });

    // =========================
    // MULTI-SAVE BUG FIX (double-load / double-bind / spam click)
    // =========================
    let SAVE_IN_FLIGHT = false;

    function setSaveBusy(isBusy) {
        SAVE_IN_FLIGHT = !!isBusy;
        $('#save_entry').prop('disabled', isBusy);
        $('#invoice_save_entry').prop('disabled', isBusy);
    }

    function doSaveEntry() {
        if (SAVE_IN_FLIGHT) return;

        let firm_id = $('#firm_select').val();
        let month = $('#month_manual').val();
        let row = $('#entry_rows tr').last();

        let branch_code = row.find('.branch_code').val().trim();
        let branch_name = row.find('.branch_name').val();
        let provision   = row.find('.provision').val();
        let amount_raw  = String(row.find('.amount').val() || '').replace(/,/g, '').trim();
        let reason_id   = row.find('.reason_select').is(':visible') ? row.find('.reason_select').val() : '';

        if (!firm_id) {
            $('#status_msg').html(`<div class='alert alert-danger'>Select a firm</div>`);
            return;
        }
        if (!month || !branch_code || !branch_name) {
            $('#status_msg').html(`<div class='alert alert-danger'>Fill all fields</div>`);
            return;
        }

        const invoiceMode = isInvoiceFirm();

        // Validate amount:
        // - invoiceMode: allow negative
        // - normal: allow negative ONLY if provision == yes
        if (!amount_raw || amount_raw === '-' || !/^-?\d+(\.\d{1,2})?$/.test(amount_raw)) {
            $('#status_msg').html(`<div class='alert alert-danger'>
                Enter a valid amount (example: 10000.00 or -10000.00).
            </div>`);
            return;
        }

        let amount_num = Number(amount_raw);
        if (isNaN(amount_num) || amount_num === 0) {
            $('#status_msg').html(`<div class='alert alert-danger'>Amount must be a non-zero number.</div>`);
            return;
        }

        if (!invoiceMode && provision !== 'yes' && amount_num < 0) {
            $('#status_msg').html(`<div class='alert alert-danger'>
                Negative amounts are allowed only when <b>Provision = Yes</b> (adjustments).
            </div>`);
            return;
        }

        // lock saving
        setSaveBusy(true);

        // ============ Invoice firm (Other / id 3) ============
        if (invoiceMode) {
            let invoice_no = row.find('.invoice_no').val().trim();

            if (!invoice_no) {
                setSaveBusy(false);
                $('#status_msg').html(`<div class='alert alert-danger'>Please enter Invoice/Reference No.</div>`);
                return;
            }

            $.ajax({
                url: 'security-2000-invoice-save.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    firm_id,
                    month,
                    branch_code,
                    branch_name,
                    invoice_no,
                    amount: amount_raw,   // can be negative
                    provision,
                    reason_id: ''
                }
            })
            .done(function (res) {
                if (res && res.success) {
                    $('#status_msg').html(`<div class='alert alert-success'>${res.message}</div>`);
                    lockRow(row);

                    $.post('security-monthly-fetch.php', { firm_id, month }, function (res2) {
                        updateSecurityAlerts(res2, month, '#missing_manual_branches');
                    }, 'json');

                    loadInvoiceTotalsForFirmMonth(firm_id, month);
                    appendBlankRow();
                    toggleSaveButtons();
                } else {
                    $('#status_msg').html(`<div class='alert alert-danger'>${res ? res.message : 'Error saving invoice.'}</div>`);
                }
            })
            .fail(function (xhr) {
                let txt = xhr && xhr.responseText ? xhr.responseText : (xhr.statusText || 'Request failed');
                $('#status_msg').html(`<div class='alert alert-danger'>Save failed.<br><small>${escapeHtml(txt)}</small></div>`);
            })
            .always(function () {
                setSaveBusy(false);
            });

            return;
        }

        // ============ Normal firms ============
        let shifts = row.find('.shifts').val();
        if (!shifts) {
            setSaveBusy(false);
            $('#status_msg').html(`<div class='alert alert-danger'>Fill all fields</div>`);
            return;
        }

        let budget_shifts = parseFloat(row.data('budget_shifts') || 0);
        let shifts_num = parseFloat(shifts);

        if (!isNaN(budget_shifts) && !isNaN(shifts_num) && budget_shifts > 0 && shifts_num > budget_shifts && !reason_id) {
            setSaveBusy(false);
            $('#status_msg').html(`<div class='alert alert-danger'>Please select a reason for shifts greater than budgeted.</div>`);
            return;
        }

        $.ajax({
            url: 'security-monthly-save.php',
            method: 'POST',
            dataType: 'json',
            data: {
                firm_id,
                month,
                branch_code,
                branch_name,
                shifts,
                amount: amount_raw,   // can be negative only if provision==yes
                provision,
                reason_id
            }
        })
        .done(function (res) {
            if (res && res.success) {
                $('#status_msg').html(`<div class='alert alert-success'>${res.message}</div>`);
                lockRow(row);

                $.post('security-monthly-fetch.php', { firm_id, month }, function (res2) {
                    updateSecurityAlerts(res2, month, '#missing_manual_branches');
                }, 'json');

                appendBlankRow();
            } else {
                $('#status_msg').html(`<div class='alert alert-danger'>${res ? res.message : 'Error saving record.'}</div>`);
            }
        })
        .fail(function (xhr) {
            let txt = xhr && xhr.responseText ? xhr.responseText : (xhr.statusText || 'Request failed');
            $('#status_msg').html(`<div class='alert alert-danger'>Save failed.<br><small>${escapeHtml(txt)}</small></div>`);
        })
        .always(function () {
            setSaveBusy(false);
        });
    }

    // ✅ Idempotent binding (prevents duplicate saves even if JS loads twice)
    $(document).off('click', '#invoice_save_entry').on('click', '#invoice_save_entry', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        doSaveEntry();
    });

    $(document).off('click', '#save_entry').on('click', '#save_entry', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        doSaveEntry();
    });

    // =========================
    // Events
    // =========================

    $('#firm_select').change(function () {
        $('#status_msg').html('');
        $('#missing_view_branches').addClass('d-none').html('');
        $('#missing_manual_branches').addClass('d-none').html('');
        $('#report_section').addClass('d-none').html('');
        $('#csv_download_container').addClass('d-none');
        $('#manual_form').addClass('d-none');
        $('#month_manual').val('');

        ensureInvoiceTotalsContainer();
        $('#invoice_totals_container').html('');
        toggleSaveButtons();

        let firstRow = $('#entry_rows').find('tr').first();
        clearRow(firstRow);
        setLayoutForFirm(firstRow);
    });

    // View report
    $('#month_view').change(function () {
        let month = $(this).val();

        if (month) {
            $('#manual_form').addClass('d-none');
            $('#month_manual').val('');

            $('#missing_manual_branches').addClass('d-none').html('');
            $('#missing_view_branches').removeClass('d-none').html('Loading...');
            $('#report_section').addClass('d-none').html('');
            $('#csv_download_container').addClass('d-none');

            ensureInvoiceTotalsContainer();
            $('#invoice_totals_container').html('');
            toggleSaveButtons();

            $.post('security-monthly-fetch.php', { month: month }, function (res) {
                $('#report_section').removeClass('d-none').html(res.table || '');
                $('#csv_download_container').removeClass('d-none');
                updateSecurityAlerts(res, month, '#missing_view_branches');
            }, 'json');
        } else {
            $('#report_section').addClass('d-none').html('');
            $('#missing_view_branches').addClass('d-none').html('');
            $('#csv_download_container').addClass('d-none');
            ensureInvoiceTotalsContainer();
            $('#invoice_totals_container').html('');
            toggleSaveButtons();
        }
    });

    // Manual entry month
    $('#month_manual').change(function () {
        let month = $(this).val();
        let firm_id = $('#firm_select').val();

        if (!firm_id) {
            $('#status_msg').html(`<div class='alert alert-danger'>Please select a firm first for manual entry.</div>`);
            $(this).val('');
            return;
        }

        if (month) {
            $('#manual_form').removeClass('d-none');
            $('#report_section').addClass('d-none').html('');
            $('#missing_view_branches').addClass('d-none').html('');
            $('#month_view').val('');
            $('.branch_code').first().focus();

            $('#missing_manual_branches').removeClass('d-none').html('Loading...');
            $.post('security-monthly-fetch.php', { firm_id: firm_id, month: month }, function (res) {
                updateSecurityAlerts(res, month, '#missing_manual_branches');
            }, 'json');

            ensureInvoiceTotalsContainer();

            if (isInvoiceFirm()) {
                loadInvoicesForFirmMonth(firm_id, month);
                loadInvoiceTotalsForFirmMonth(firm_id, month);
            } else {
                $('#entry_rows').empty();
                appendBlankRow();
                $('#invoice_totals_container').html('');
            }

            toggleSaveButtons();

        } else {
            $('#manual_form').addClass('d-none');
            $('#missing_manual_branches').addClass('d-none').html('');
            ensureInvoiceTotalsContainer();
            $('#invoice_totals_container').html('');
            toggleSaveButtons();
        }
    });

    // Branch Code - Input: if cleared, wipe row
    $(document).on('input', '.branch_code', function () {
        let row = $(this).closest('tr');
        let val = $(this).val().trim();
        if (val === '') {
            clearRow(row);
            $('#status_msg').html('');
        }
    });

    // Branch Code - Blur (validate + layout by firm)
    $(document).on('blur', '.branch_code', function () {
        let row = $(this).closest('tr');
        let branch_code = $(this).val().trim();
        let month = $('#month_manual').val();
        let firm_id = $('#firm_select').val();
        let invoiceMode = isInvoiceFirm();

        if (!branch_code) {
            clearRow(row);
            return;
        }
        if (!month || !firm_id) return;

        row.find('.budget_shifts').val('');
        row.data('budget_shifts', 0);

        $.post('ajax-get-branch-name.php', { branch_code, firm_id, month }, function (r) {
            if (!r || !r.success) {
                let msg = r && r.message ? r.message : `Branch code ${branch_code} is not allocated to the selected firm for ${month}.`;
                clearRow(row);
                $('#status_msg').html(`<div class='alert alert-danger'>${msg}</div>`);
                row.find('.branch_code').focus();
                return;
            }

            row.find('.branch_name').val(r.branch).prop('readonly', true);
            row.find('.shifts').val('').prop('readonly', false);
            row.find('.amount').val('').prop('readonly', true);
            row.find('.previous_month_info').val('');
            row.find('.reason_select').addClass('d-none').val('');
            row.find('.provision').val('no').prop('disabled', false);
            row.find('.invoice_no').val('');
            $('#status_msg').html('');

            setLayoutForFirm(row);

            if (invoiceMode) {
                $.post('security-fetch-previous-month.php', { branch_code, month, firm_id }, function (prev) {
                    if (prev && prev.found) {
                        row.find('.previous_month_info').val(`${prev.shifts} Shifts - ${prev.amount} (${prev.month})`);
                    } else {
                        row.find('.previous_month_info').val('No Previous Data');
                    }
                }, 'json');
                return;
            }

            $.post('ajax-get-existing-record.php', { branch_code, month, firm_id }, function (res) {
                if (res && res.exists) {
                    row.find('.branch_name').val(res.branch).prop('readonly', true);
                    row.find('.shifts').val(res.shifts);
                    row.find('.provision').val(res.provision);

                    row.find('.amount').val(
                        Number(res.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    );

                    row.find('.previous_month_info').val('Not applicable');
                    row.find('.reason_select').addClass('d-none').val('');

                    const status = (res.approval_status || '').toLowerCase();
                    const rejectionReason = res.rejection_reason || '';

                    if (status === 'approved') {

                    // ✅ NEW RULE:
                    // Approved + Provision=YES is NOT locked (so user can later convert it to actual).
                    if (String(res.provision || '').toLowerCase() === 'yes') {

                        row.find('.shifts').prop('readonly', false);
                        row.find('.provision').prop('disabled', false);

                        // Provision=yes should allow amount editing
                        row.find('.amount').prop('readonly', false);

                        $('#status_msg').html(
                            `<div class='alert alert-info'>
                            This record is <b>approved</b> because it was saved as <b>Provision = Yes</b> (auto-approved).
                            <br>If you later enter the <b>actual</b> (change Provision to <b>No</b>) and Save,
                            it will become <b>Pending</b> and go through dual control.
                            </div>`
                        );

                    } else {

                        // Approved + Provision=NO (actual) stays locked
                        row.find('.shifts').prop('readonly', true);
                        row.find('.provision').prop('disabled', true);
                        row.find('.amount').prop('readonly', true);

                        $('#status_msg').html(
                            `<div class='alert alert-danger'>
                            This record is <b>approved</b> and locked. Contact a checker/admin to change.
                            </div>`
                        );
                    }

                }else if (status === 'rejected') {
                        row.find('.shifts').prop('readonly', false);
                        row.find('.provision').prop('disabled', false);
                        row.find('.amount').prop('readonly', (res.provision === 'no'));

                        let msgHtml = `<div class='alert alert-warning'>This record was <b>rejected</b>.`;
                        if (rejectionReason) msgHtml += `<br><b>Reason:</b> ${rejectionReason}`;
                        msgHtml += `<br>You can correct the values and click Save; it will go for approval again.</div>`;
                        $('#status_msg').html(msgHtml);
                    } else {
                        row.find('.shifts').prop('readonly', false);
                        row.find('.provision').prop('disabled', false);
                        row.find('.amount').prop('readonly', (res.provision === 'no'));

                        $('#status_msg').html(
                            `<div class='alert alert-info'>
                               This record is <b>pending approval</b>. You can modify and Save; it will remain pending until a checker approves/rejects it.
                             </div>`
                        );
                    }
                } else {
                    $.post('security-fetch-previous-month.php', { branch_code, month, firm_id }, function (prev) {
                        if (prev && prev.found) {
                            row.find('.previous_month_info').val(`${prev.shifts} Shifts - ${prev.amount} (${prev.month})`);
                        } else {
                            row.find('.previous_month_info').val('No Previous Data');
                        }
                    }, 'json');
                }

                $.post('ajax-get-branch-rate.php', { branch_code, month, firm_id }, function (r2) {
                    if (r2 && r2.success) {
                        let budget = parseFloat(r2.budget_shifts || 0);
                        row.data('budget_shifts', budget);
                        row.find('.budget_shifts').val(budget || '');
                    } else {
                        row.data('budget_shifts', 0);
                        row.find('.budget_shifts').val('');
                    }
                }, 'json');
            }, 'json');

        }, 'json');
    });

    // Shifts Change → normal firms only
    $(document).on('blur', '.shifts', function () {
        if (isInvoiceFirm()) return;

        let row = $(this).closest('tr');
        let branch_code = row.find('.branch_code').val().trim();
        let shifts = parseFloat($(this).val());
        let month = $('#month_manual').val();
        let firm_id = $('#firm_select').val();
        let provision = row.find('.provision').val();

        if (!branch_code || !month || !firm_id || isNaN(shifts)) return;

        $.post('ajax-get-branch-rate.php', { branch_code, month, firm_id }, function (res) {
            if (res && res.success) {
                let budget = parseFloat(res.budget_shifts || 0);
                row.data('budget_shifts', budget);
                row.find('.budget_shifts').val(budget || '');

                if (budget > 0 && shifts > budget) {
                    row.find('.reason_select').removeClass('d-none').closest('td').removeClass('d-none');
                    if (row.find('.reason_select option').length <= 1) {
                        $('#status_msg').html(
                            `<div class="alert alert-warning">
                                Reason list is empty. Your <b>SECURITY_REASON_OPTIONS</b> variable has no options.
                             </div>`
                        );
                    }
                } else {
                    row.find('.reason_select').addClass('d-none').val('');
                }

                if (provision === 'no') {
                    let total = shifts * res.rate;
                    row.find('.amount').val(total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                }
            } else {
                row.find('.amount').val('Rate N/A');
            }
        }, 'json');
    });

    // Provision Toggle
    $(document).on('change', '.provision', function () {
        let row = $(this).closest('tr');
        let provision = $(this).val();
        let month = $('#month_manual').val();
        let firm_id = $('#firm_select').val();
        let branch_code = row.find('.branch_code').val().trim();

        if (isInvoiceFirm()) {
            row.find('.amount').prop('readonly', false);
            return;
        }

        if (provision === 'yes') {
            // allow manual entry (can be negative for reduction)
            row.find('.amount').prop('readonly', false).val('').focus();
        } else {
            row.find('.amount').prop('readonly', true);
            let shifts = parseFloat(row.find('.shifts').val());
            if (branch_code && shifts) {
                $.post('ajax-get-branch-rate.php', { branch_code, month, firm_id }, function (res) {
                    if (res && res.success) {
                        let total = shifts * res.rate;
                        row.find('.amount').val(total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    } else {
                        row.find('.amount').val('Rate N/A');
                    }
                }, 'json');
            }
        }
    });

    // Auto-resize previous month textarea
    $(document).on('input click', '.previous_month_info', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // View Reason icon → Bootstrap modal (report side)
    $(document).on('click', '.reason-view-btn', function () {
        let reason = $(this).data('reason') || 'No reason provided.';
        $('#reasonModalBody').text(reason);

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            let modalEl = document.getElementById('reasonModal');
            let modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            $('#status_msg').html(`<div class='alert alert-info'>${reason}</div>`);
        }
    });

    // CSV Download
    $('#download_csv_btn').off('click').on('click', function () {
        let table = $('#report_section table');
        if (!table.length) return;

        let csv = [];
        table.find('tr').each(function () {
            let row = [];
            $(this).find('th, td').each(function () {
                let text = $(this).text().trim().replace(/"/g, '""');
                row.push(`"${text}"`);
            });
            csv.push(row.join(','));
        });

        let csvString = csv.join('\n');
        let blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        let link = document.createElement('a');
        let month = $('#month_view').val().replace(/\s+/g, '_');

        link.setAttribute('href', URL.createObjectURL(blob));
        link.setAttribute('download', `Security_Report_${month}.csv`);
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // =========================
    // Initial setup
    // =========================
    ensureInvoiceTotalsContainer();
    ensureInvoiceSaveButton();
    toggleSaveButtons();
});
