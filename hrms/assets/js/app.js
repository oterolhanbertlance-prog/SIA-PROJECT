/* ============================================================
   HRMS - Main Application JavaScript
   ============================================================ */

'use strict';

// ── Theme ─────────────────────────────────────────────────
const ThemeManager = {
    key: 'hrms_theme',
    init() {
        const saved = localStorage.getItem(this.key) || 'light';
        this.apply(saved);
    },
    apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(this.key, theme);
        const icon = document.getElementById('themeIcon');
        if (icon) icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    },
    toggle() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        this.apply(current === 'dark' ? 'light' : 'dark');
    }
};

// ── Sidebar ───────────────────────────────────────────────
const Sidebar = {
    key: 'hrms_sidebar',
    init() {
        const collapsed = localStorage.getItem(this.key) === 'collapsed';
        if (collapsed) document.body.classList.add('sidebar-collapsed');

        // Mobile overlay
        document.querySelector('.sidebar-overlay')?.addEventListener('click', () => this.hideMobile());
    },
    toggle() {
        if (window.innerWidth < 992) {
            this.toggleMobile();
        } else {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(this.key, document.body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
        }
    },
    toggleMobile() {
        document.querySelector('.sidebar')?.classList.toggle('show');
        syncSidebarOverlayPointerEvents();
    },
    hideMobile() {
        document.querySelector('.sidebar')?.classList.remove('show');
        syncSidebarOverlayPointerEvents();
    }
};

// ── Modal / overlay safety ─────────────────────────────────
// Bootstrap can occasionally leave a backdrop/body lock behind after a
// navigation, interrupted modal, or a modal opened from a scroll container.
// Only clean it when NO modal is actually open.
function cleanupOrphanedOverlays() {
    const openModal = document.querySelector('.modal.show');
    if (openModal) return;

    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');

    // Never leave an invisible mobile sidebar overlay intercepting clicks.
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    if (sidebarOverlay && (!sidebar || !sidebar.classList.contains('show'))) {
        sidebarOverlay.style.pointerEvents = 'none';
    }
}

// Restore the mobile overlay only while the sidebar is actually shown.
function syncSidebarOverlayPointerEvents() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) return;
    overlay.style.pointerEvents = (sidebar && sidebar.classList.contains('show')) ? 'auto' : 'none';
}

// ── Search Everywhere ─────────────────────────────────────
const GlobalSearch = {
    timeout: null,
    init() {
        const input = document.getElementById('globalSearch');
        if (!input) return;
        input.addEventListener('input', (e) => {
            clearTimeout(this.timeout);
            const q = e.target.value.trim();
            if (q.length < 2) {
                document.getElementById('searchResults').classList.add('d-none');
                return;
            }
            this.timeout = setTimeout(() => this.search(q), 350);
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#searchWrapper')) {
                document.getElementById('searchResults')?.classList.add('d-none');
            }
        });
    },
    search(q) {
        fetch(`search.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => this.renderResults(data));
    },
    renderResults(data) {
        const box = document.getElementById('searchResults');
        if (!box) return;
        if (!data.length) {
            box.innerHTML = '<div class="p-3 text-muted small">No results found</div>';
        } else {
            box.innerHTML = data.map(r =>
                `<a href="${r.url}" class="d-flex align-items-center gap-2 p-2 text-decoration-none text-dark hover-bg rounded">
                    <span class="badge bg-primary-subtle text-primary">${r.type}</span>
                    <span class="small">${r.label}</span>
                </a>`
            ).join('');
        }
        box.classList.remove('d-none');
    }
};

// ── Confirm Delete ─────────────────────────────────────────
function confirmDelete(url, name = 'this record') {
    if (typeof Swal === 'undefined') {
        if (window.confirm(`Delete ${name}? This action cannot be undone.`)) window.location.href = url;
        return false;
    }
    Swal.fire({
        title: 'Delete Confirmation',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><small class="text-danger">This action cannot be undone.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef233c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
    }).then(result => {
        if (result.isConfirmed) window.location.href = url;
    });
    return false;
}

// ── Generic Confirm (link navigation) ──────────────────────
function confirmAction(url, message, opts = {}) {
    if (typeof Swal === 'undefined') {
        if (window.confirm(message || 'Continue?')) window.location.href = url;
        return false;
    }
    Swal.fire({
        title: opts.title || 'Please Confirm',
        text: message,
        icon: opts.icon || 'question',
        showCancelButton: true,
        confirmButtonColor: opts.confirmColor || '#4361ee',
        cancelButtonColor: '#6c757d',
        confirmButtonText: opts.confirmText || 'Yes, Continue',
        cancelButtonText: 'Cancel',
    }).then(result => {
        if (result.isConfirmed) window.location.href = url;
    });
    return false;
}

// ── Generic Confirm (form submission) ──────────────────────
function confirmSubmit(form, message, opts = {}) {
    if (typeof Swal === 'undefined') {
        if (window.confirm(message || 'Continue?')) form.submit();
        return false;
    }
    Swal.fire({
        title: opts.title || 'Please Confirm',
        text: message,
        icon: opts.icon || 'warning',
        showCancelButton: true,
        confirmButtonColor: opts.confirmColor || '#ef233c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: opts.confirmText || 'Yes, Continue',
        cancelButtonText: 'Cancel',
    }).then(result => {
        if (result.isConfirmed) form.submit();
    });
    return false;
}

// ── Toast Notification ─────────────────────────────────────
function showToast(message, type = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
}

// ── Form Validation ────────────────────────────────────────
function initFormValidation() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// ── Preview Image Upload ───────────────────────────────────
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById(previewId);
            if (preview) preview.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── DataTable enhancement ──────────────────────────────────
function initSearchFilter(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// ── Auto-dismiss alerts ────────────────────────────────────
function initAlerts() {
    document.querySelectorAll('.alert-auto-dismiss').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });
}

// ── Net pay calculator ─────────────────────────────────────
function calcNetPay() {
    const v = id => parseFloat(document.getElementById(id)?.value || 0);
    const gross = v('basic_salary') + v('allowances') + v('overtime_pay') + v('bonuses');
    const deductions = v('sss_deduction') + v('philhealth_deduction') + v('pagibig_deduction') + v('tax_deduction') + v('other_deductions');
    const net = gross - deductions;
    const fmt = n => '₱ ' + n.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = fmt(val); };
    set('gross_display', gross);
    set('deductions_display', deductions);
    set('net_display', net);

    const hGross = document.getElementById('gross_pay');
    const hDed   = document.getElementById('total_deductions');
    const hNet   = document.getElementById('net_pay');
    if (hGross) hGross.value = gross.toFixed(2);
    if (hDed)   hDed.value   = deductions.toFixed(2);
    if (hNet)   hNet.value   = net.toFixed(2);
}

// ── Print ──────────────────────────────────────────────────
function printSection(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const win = window.open('', '_blank');
    win.document.write(`<html><head><title>Print</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <style>body{padding:30px} @media print{body{padding:0}}</style>
        </head><body>${el.innerHTML}<script>window.onload=()=>{window.print();}<\/script></body></html>`);
    win.document.close();
}

// ── Init ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
    Sidebar.init();
    GlobalSearch.init();
    initFormValidation();
    initAlerts();
    cleanupOrphanedOverlays();
    syncSidebarOverlayPointerEvents();

    // Payroll live calc
    document.querySelectorAll('.pay-input').forEach(el => {
        el.addEventListener('input', calcNetPay);
    });

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // SweetAlert for URL params
    const params = new URLSearchParams(window.location.search);
    if (params.get('success'))  showToast(decodeURIComponent(params.get('success')), 'success');
    if (params.get('error'))    showToast(decodeURIComponent(params.get('error')), 'error');
    if (params.get('warning'))  showToast(decodeURIComponent(params.get('warning')), 'warning');
});


// Cleanup after any Bootstrap modal finishes closing and after browser
// back/forward-cache restoration. These handlers are intentionally global.
document.addEventListener('hidden.bs.modal', () => setTimeout(cleanupOrphanedOverlays, 0));
window.addEventListener('pageshow', () => setTimeout(cleanupOrphanedOverlays, 0));
window.addEventListener('resize', syncSidebarOverlayPointerEvents);
