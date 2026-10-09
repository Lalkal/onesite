/**
 * LOGANX Automated Email Verification & Digital Download Delivery System
 * Client-Side Controller & Real-Time CMS Website Synchronizer
 */
(() => {
  let modalOverlay = null;
  let csrfToken = '';
  let activeResources = [];

  // Fetch CSRF token
  async function fetchCsrfToken() {
    try {
      const res = await fetch('api/csrf-token.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (res.ok) {
        const data = await res.json();
        csrfToken = data.csrf_token || '';
      }
    } catch (e) {
      console.warn('Could not pre-fetch CSRF token:', e);
    }
  }

  // Fetch Active Resources from CMS Database & Dynamically Update Site
  async function syncActiveResources() {
    try {
      const res = await fetch('api/resources.php');
      if (!res.ok) return;

      const data = await res.json();
      if (!data.success || !Array.isArray(data.resources) || data.resources.length === 0) {
        return;
      }

      activeResources = data.resources;

      // Update Hero Download Button with Primary Resource
      const heroBtn = document.querySelector('.hero .actions [data-download-trigger], .hero [data-download-trigger]');
      const primaryRes = activeResources.find(r => r.is_primary) || activeResources[0];
      if (heroBtn && primaryRes) {
        heroBtn.setAttribute('data-resource-slug', primaryRes.slug);
        heroBtn.setAttribute('data-resource-title', primaryRes.title);
        heroBtn.setAttribute('data-resource-id', primaryRes.id);
      }

      // Update #downloads section dynamically if present
      renderDownloadsSection(activeResources);

    } catch (e) {
      console.warn('Could not sync dynamic download resources:', e);
    }
  }

  function renderDownloadsSection(resources) {
    const downloadsSection = document.getElementById('downloads');
    if (!downloadsSection) return;

    const container = downloadsSection.querySelector('.container');
    if (!container) return;

    // Check if dynamic grid container exists, or create it
    let gridWrap = downloadsSection.querySelector('#lxDynamicGrid');
    if (!gridWrap) {
      gridWrap = document.createElement('div');
      gridWrap.id = 'lxDynamicGrid';
      gridWrap.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;margin-top:28px;';
      container.appendChild(gridWrap);
    }

    // Hide old hardcoded single card if dynamic grid is populated
    const oldCard = downloadsSection.querySelector('.card:not(.dynamic-card)');
    if (oldCard && resources.length > 0) {
      oldCard.style.display = 'none';
    }

    gridWrap.innerHTML = resources.map(res => {
      const isPrimary = !!res.is_primary;
      const sizeStr = res.file_size_formatted || '';
      const versionStr = res.version ? `v${res.version}` : '';

      return `
        <div class="card dynamic-card reveal show" style="background:linear-gradient(145deg,#131825,#0c0f16);border:1px solid ${isPrimary ? 'rgba(140,255,91,.25)' : 'rgba(255,255,255,.08)'};padding:30px;border-radius:20px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 10px 35px rgba(0,0,0,.3);">
          <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
              <span class="eyebrow" style="margin:0;font-size:11px;padding:4px 10px;background:rgba(255,255,255,.05);border-radius:999px;border:1px solid rgba(255,255,255,.1);">
                ${versionStr || 'Software'}
              </span>
              ${sizeStr ? `<span style="font-size:12px;color:var(--muted,#9da5b5);font-family:monospace;">${sizeStr}</span>` : ''}
            </div>
            
            <h3 style="font-size:22px;letter-spacing:-0.5px;margin-bottom:10px;color:#fff;line-height:1.25;">
              ${escapeHtml(res.title)}
            </h3>
            
            <p style="color:var(--muted,#9da5b5);font-size:14px;line-height:1.6;margin-bottom:20px;">
              ${escapeHtml(res.description || 'Verified secure digital software package.')}
            </p>
          </div>

          <div>
            <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--accent,#8cff5b);margin-bottom:14px;font-weight:700;">
              <span>🔒 Single-Use Token Delivery</span> &bull; <span>Verified Email</span>
            </div>

            <button type="button" class="btn ${isPrimary ? 'btn-primary' : 'btn-dark'}" 
                    data-download-trigger 
                    data-resource-slug="${escapeHtml(res.slug)}" 
                    data-resource-title="${escapeHtml(res.title)}"
                    data-resource-id="${res.id}"
                    style="width:100%;justify-content:center;padding:12px 18px;font-size:13px;">
              ⚡ Download via Email &rarr;
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[m]);
  }

  // Inject Modal Markup
  function ensureModal() {
    if (document.getElementById('lxDownloadModal')) {
      return;
    }

    const modalHtml = `
      <div id="lxDownloadModal" class="lx-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="lxModalTitle">
        <div class="lx-modal-box">
          <button type="button" class="lx-modal-close" id="lxModalClose" aria-label="Close modal">&times;</button>
          
          <div id="lxFormView">
            <span class="lx-modal-badge">⚡ Instant Verification</span>
            <h2 id="lxModalTitle" class="lx-modal-title">LOGAN<span>X</span> DIGITAL DOWNLOADS</h2>
            <p class="lx-modal-subtitle">Get your file delivered directly to your inbox.</p>
            
            <div class="lx-product-preview">
              <div class="lx-product-icon">🪟</div>
              <div class="lx-product-meta">
                <div class="lx-product-label">Selected Item</div>
                <div class="lx-product-name" id="lxProductName">LOGANX Desktop Studio (Windows App)</div>
              </div>
            </div>

            <div id="lxModalError" class="lx-alert lx-alert-error" role="alert"></div>

            <form id="lxDownloadForm">
              <input type="hidden" id="lxResourceSlug" name="slug" value="windows-app">
              <input type="hidden" id="lxResourceId" name="resource_id" value="">
              
              <div class="lx-form-group">
                <label for="lxEmailInput" style="display:block;font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--lx-muted);margin-bottom:8px;">
                  Your Email Address
                </label>
                <input 
                  type="email" 
                  id="lxEmailInput" 
                  class="lx-input" 
                  name="email" 
                  required 
                  autocomplete="email" 
                  placeholder="name@business.com"
                  autofocus
                >
              </div>

              <button type="submit" id="lxSubmitBtn" class="lx-btn-send">
                <span class="lx-spinner" id="lxSpinner"></span>
                <span id="lxBtnText">Send Download Link &rarr;</span>
              </button>

              <p class="lx-agreement">
                By continuing, you agree to receive the requested verification and download emails.
              </p>
            </form>
          </div>

          <div id="lxSuccessView" class="lx-success-view">
            <div class="lx-success-icon">✓</div>
            <h3 class="lx-success-title">Check Your Inbox</h3>
            <p class="lx-success-text" id="lxSuccessText">
              If this email address is eligible, a verification link has been sent to your inbox. Please check your email (and spam folder) within 30 minutes to complete your request.
            </p>
            <button type="button" class="lx-btn-done" id="lxBtnDone">Got It</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    modalOverlay = document.getElementById('lxDownloadModal');

    // Attach listeners
    document.getElementById('lxModalClose').addEventListener('click', closeModal);
    document.getElementById('lxBtnDone').addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay) closeModal();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
        closeModal();
      }
    });

    document.getElementById('lxDownloadForm').addEventListener('submit', handleFormSubmit);
  }

  function openModal(options = {}) {
    ensureModal();

    const title = options.title || 'LOGANX Desktop Studio (Windows App)';
    const slug = options.slug || 'windows-app';
    const id = options.id || '';

    document.getElementById('lxProductName').textContent = title;
    document.getElementById('lxResourceSlug').value = slug;
    document.getElementById('lxResourceId').value = id;

    // Reset views
    document.getElementById('lxFormView').style.display = 'block';
    document.getElementById('lxSuccessView').style.display = 'none';
    hideError();

    const emailInput = document.getElementById('lxEmailInput');
    emailInput.value = '';

    modalOverlay.classList.add('active');
    setTimeout(() => emailInput.focus(), 150);

    // Refresh CSRF token
    fetchCsrfToken();
  }

  function closeModal() {
    if (modalOverlay) {
      modalOverlay.classList.remove('active');
    }
  }

  function showError(msg) {
    const box = document.getElementById('lxModalError');
    box.textContent = msg;
    box.style.display = 'block';
  }

  function hideError() {
    const box = document.getElementById('lxModalError');
    box.style.display = 'none';
    box.textContent = '';
  }

  async function handleFormSubmit(e, isRetry = false) {
    if (e && e.preventDefault) e.preventDefault();
    hideError();

    const email = document.getElementById('lxEmailInput').value.trim();
    const slug = document.getElementById('lxResourceSlug').value.trim();
    const resourceId = document.getElementById('lxResourceId').value.trim();

    if (!email || !email.includes('@')) {
      showError('Please enter a valid email address.');
      return;
    }

    const btn = document.getElementById('lxSubmitBtn');
    const spinner = document.getElementById('lxSpinner');
    const btnText = document.getElementById('lxBtnText');

    btn.disabled = true;
    spinner.style.display = 'inline-block';
    btnText.textContent = 'Sending Download Link...';

    try {
      if (!csrfToken || isRetry) {
        await fetchCsrfToken();
      }

      const payload = {
        email: email,
        slug: slug,
        resource_id: resourceId,
        csrf_token: csrfToken
      };

      const response = await fetch('api/request-download.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (response.ok && data.success) {
        // Show success / anti-enumeration confirmation state
        document.getElementById('lxFormView').style.display = 'none';
        document.getElementById('lxSuccessView').style.display = 'block';
        if (data.message) {
          document.getElementById('lxSuccessText').textContent = data.message;
        }
      } else if (response.status === 403 && data.message && data.message.includes('expired') && !isRetry) {
        // Token was stale: seamlessly fetch a fresh signed token and retry once
        await fetchCsrfToken();
        return handleFormSubmit(null, true);
      } else {
        showError(data.message || 'Unable to process your download request. Please try again.');
      }
    } catch (err) {
      console.error('Request failed:', err);
      showError('Network connection error. Please verify your connection and try again.');
    } finally {
      btn.disabled = false;
      spinner.style.display = 'none';
      btnText.innerHTML = 'Send Download Link &rarr;';
    }
  }

  // Intercept trigger buttons on the page
  function attachTriggers() {
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-download-trigger], [data-download-app], a[href="#download-app"], a[href="#download-windows-app"], .btn-download-app');
      if (trigger) {
        e.preventDefault();
        const title = trigger.getAttribute('data-resource-title') || 
                      trigger.getAttribute('data-product-title') || 
                      'LOGANX Desktop Studio (Windows App)';
        const slug = trigger.getAttribute('data-resource-slug') || 'windows-app';
        const id = trigger.getAttribute('data-resource-id') || '';

        openModal({ title, slug, id });
      }
    });
  }

  // Initialize
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      attachTriggers();
      fetchCsrfToken();
      syncActiveResources();
    });
  } else {
    attachTriggers();
    fetchCsrfToken();
    syncActiveResources();
  }

  // Expose global controller
  window.LoganXDownload = {
    open: openModal,
    close: closeModal,
    sync: syncActiveResources
  };
})();
