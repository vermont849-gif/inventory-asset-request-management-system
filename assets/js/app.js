/**
 * app.js
 * ---------------------------------------------------------------
 * Lightweight client-side helpers. Server-side validation in PHP
 * is the source of truth (see NFR-01 Security) — this file only
 * improves the user experience with instant feedback.
 * ---------------------------------------------------------------
 */
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss flash alerts after 6 seconds
    document.querySelectorAll('.alert-modern').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 6000);
    });

    // NOTE: form submission is intentionally NOT blocked by a custom
    // client-side required-field check here. An earlier version of
    // this script read field.value at submit time to decide whether
    // to call preventDefault(), but browser autofill / password
    // managers (very common right after a "Remember me" login) can
    // populate a field visually without that value being reflected
    // yet when this handler runs — silently blocking submission with
    // no visible error. Native HTML5 `required` validation (see the
    // markup) already covers this correctly and is not subject to
    // that timing issue; PHP-side validation is the real source of
    // truth regardless (see NFR-01 Security note above).

    // Notification bell dropdown toggle
    var bellBtn = document.getElementById('notifBellBtn');
    var panel = document.getElementById('notifPanel');
    if (bellBtn && panel) {
        bellBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!panel.contains(e.target) && e.target !== bellBtn) {
                panel.classList.remove('open');
            }
        });
    }

    // Confirm destructive actions (deactivate user, reject request, etc.)
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});
