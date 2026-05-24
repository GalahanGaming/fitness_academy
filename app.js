/* ═══════════════════════════════════════════════════════════════
   Fitness Academy — Shared JS
   Include this in every dashboard: <script src="app.js"></script>
═══════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {

    // ── 1. AUTO-DISMISS ALERTS ──────────────────────────────────
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Fade in
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        alert.style.transform = 'translateY(-8px)';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                alert.style.opacity = '1';
                alert.style.transform = 'translateY(0)';
            });
        });

        // Auto dismiss after 4 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 400);
        }, 4000);

        // Click to dismiss early
        alert.style.cursor = 'pointer';
        alert.title = 'Click to dismiss';
        alert.addEventListener('click', () => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.style.display = 'none', 400);
        });
    });

    // ── 2. CUSTOM CONFIRM DIALOG ────────────────────────────────
    // Intercepts all form submits that have data-confirm attribute
    // Usage: <form data-confirm="Are you sure?">
    // Also intercepts onsubmit="return confirm(...)" automatically

    // Build the modal once
    const confirmModal = document.createElement('div');
    confirmModal.id = 'confirm-modal';
    confirmModal.innerHTML = `
        <div id="confirm-box">
            <div id="confirm-icon">⚠️</div>
            <div id="confirm-title">Are you sure?</div>
            <div id="confirm-message"></div>
            <div id="confirm-actions">
                <button id="confirm-cancel">Cancel</button>
                <button id="confirm-ok">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(confirmModal);

    // Style it via JS so no extra CSS file needed
    const style = document.createElement('style');
    style.textContent = `
        #confirm-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeInModal 0.2s ease;
        }
        #confirm-modal.open { display: flex; }
        @keyframes fadeInModal {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        #confirm-box {
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 2rem 2.5rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
            animation: slideUpModal 0.25s ease;
        }
        @keyframes slideUpModal {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        #confirm-icon   { font-size: 2.5rem; margin-bottom: 0.75rem; }
        #confirm-title  { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        #confirm-message { font-size: 13px; color: #666; margin-bottom: 1.5rem; line-height: 1.6; }
        #confirm-actions { display: flex; gap: 10px; justify-content: center; }
        #confirm-cancel {
            background: #1e1e1e;
            color: #888;
            border: 1px solid #2a2a2a;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        #confirm-cancel:hover { color: #fff; border-color: #555; }
        #confirm-ok {
            background: #ff4d4d;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        #confirm-ok:hover { background: #e03c3c; }
        #confirm-ok.ok-yellow {
            background: #e8ff47;
            color: #000;
        }
        #confirm-ok.ok-yellow:hover { background: #d4eb3a; }
    `;
    document.head.appendChild(style);

    let pendingForm = null;

    function showConfirm(message, okLabel, okClass, onConfirm) {
        document.getElementById('confirm-message').textContent = message;
        document.getElementById('confirm-ok').textContent      = okLabel || 'Confirm';
        document.getElementById('confirm-ok').className        = okClass || '';
        confirmModal.classList.add('open');
        pendingCallback = onConfirm;
    }

    let pendingCallback = null;

    document.getElementById('confirm-ok').addEventListener('click', () => {
        confirmModal.classList.remove('open');
        if (pendingCallback) { pendingCallback(); pendingCallback = null; }
    });

    document.getElementById('confirm-cancel').addEventListener('click', () => {
        confirmModal.classList.remove('open');
        pendingForm     = null;
        pendingCallback = null;
    });

    // Close on overlay click
    confirmModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
            pendingForm     = null;
            pendingCallback = null;
        }
    });

    // ── Intercept forms with onsubmit confirm() ──────────────────
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        const onsubmitAttr = form.getAttribute('onsubmit');
        if (!onsubmitAttr || !onsubmitAttr.includes('confirm(')) return;

        // Extract message from confirm('...')
        const match = onsubmitAttr.match(/confirm\(['"](.+?)['"]\)/);
        const message = match ? match[1] : 'Are you sure?';

        // Determine button style based on message content
        const isDelete      = message.toLowerCase().includes('delete');
        const isDeactivate  = message.toLowerCase().includes('deactivate');
        const isReactivate  = message.toLowerCase().includes('reactivate');

        let okLabel = 'Confirm';
        let okClass = '';
        let icon    = '⚠️';

        if (isDelete) {
            okLabel = 'Yes, Delete';
            okClass = '';
            icon    = '🗑️';
        } else if (isDeactivate) {
            okLabel = 'Deactivate';
            okClass = '';
            icon    = '🚫';
        } else if (isReactivate) {
            okLabel = 'Reactivate';
            okClass = 'ok-yellow';
            icon    = '✅';
        }

        // Remove original onsubmit to prevent double-firing
        form.removeAttribute('onsubmit');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            document.getElementById('confirm-icon').textContent = icon;
            showConfirm(message, okLabel, okClass, () => {
                form.removeEventListener('submit', arguments.callee);
                form.submit();
            });
        });
    });

    // ── 3. SMOOTH TAB SWITCHING ─────────────────────────────────
    // Wrap existing switchTab to add fade effect
    const originalSwitchTab = window.switchTab;
    if (typeof originalSwitchTab === 'function') {
        window.switchTab = function(name, btn) {
            // Fade out current active tab
            const current = document.querySelector('.tab-content.active');
            if (current) {
                current.style.transition = 'opacity 0.15s ease';
                current.style.opacity = '0';
                setTimeout(() => {
                    originalSwitchTab(name, btn);
                    const next = document.getElementById('tab-' + name);
                    if (next) {
                        next.style.opacity = '0';
                        next.style.transition = 'opacity 0.2s ease';
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                next.style.opacity = '1';
                            });
                        });
                    }
                }, 150);
            } else {
                originalSwitchTab(name, btn);
            }
        };
    }

    // ── 4. BUTTON PRESS RIPPLE EFFECT ───────────────────────────
    document.querySelectorAll('.btn, .submit-btn, .renew-btn, .btn-lookup, .btn-filter').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255,255,255,0.15);
                transform: scale(0);
                animation: ripple 0.5s ease-out;
                pointer-events: none;
                width: 100px; height: 100px;
                left: ${e.offsetX - 50}px;
                top: ${e.offsetY - 50}px;
            `;
            const existingStyle = this.style.position;
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 500);
        });
    });

    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        @keyframes ripple {
            to { transform: scale(3); opacity: 0; }
        }
    `;
    document.head.appendChild(rippleStyle);

});


/* ═══════════════════════════════════════════════════════════════
   PHOTO LIGHTBOX — click any .member-photo to view full size
═══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function () {

    // Build lightbox once
    const lightbox = document.createElement('div');
    lightbox.id = 'photo-lightbox';
    lightbox.innerHTML = `
        <div id="lightbox-backdrop"></div>
        <div id="lightbox-box">
            <button id="lightbox-close">✕</button>
            <img id="lightbox-img" src="" alt="Member Photo">
            <div id="lightbox-name"></div>
        </div>
    `;
    document.body.appendChild(lightbox);

    const lbStyle = document.createElement('style');
    lbStyle.textContent = `
        #photo-lightbox {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9998;
            align-items: center;
            justify-content: center;
        }
        #photo-lightbox.open { display: flex; }
        #lightbox-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.92);
            animation: fadeInModal 0.2s ease;
        }
        #lightbox-box {
            position: relative;
            z-index: 1;
            text-align: center;
            animation: slideUpModal 0.25s ease;
        }
        #lightbox-img {
            width: 280px;
            height: 280px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e8ff47;
            display: block;
            margin: 0 auto 1rem;
            box-shadow: 0 0 40px rgba(232,255,71,0.15);
        }
        #lightbox-name {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        #lightbox-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: #888;
            font-size: 22px;
            cursor: pointer;
            transition: color 0.2s;
        }
        #lightbox-close:hover { color: #fff; }
        .member-photo { cursor: pointer; transition: opacity 0.2s, transform 0.2s; }
        .member-photo:hover { opacity: 0.8; transform: scale(1.05); }
    `;
    document.head.appendChild(lbStyle);

    // Open lightbox on any .member-photo click
    document.addEventListener('click', function(e) {
        const photo = e.target.closest('.member-photo');
        if (!photo) return;
        const src  = photo.dataset.src;
        const name = photo.dataset.name || '';
        if (!src) return;

        document.getElementById('lightbox-img').src   = src;
        document.getElementById('lightbox-name').textContent = name;
        document.getElementById('photo-lightbox').classList.add('open');
    });

    // Close on backdrop or X click
    document.getElementById('lightbox-backdrop').addEventListener('click', closeLightbox);
    document.getElementById('lightbox-close').addEventListener('click', closeLightbox);

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });

    function closeLightbox() {
        document.getElementById('photo-lightbox').classList.remove('open');
        document.getElementById('lightbox-img').src = '';
    }
});
