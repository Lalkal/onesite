/**
 * LOGANX Automated Email Verification & Digital Download Delivery System
 * Client-Side Controller
 */
(() => {
  let modalOverlay = null;
  let csrfToken = '';

  // Fetch CSRF token
  async function fetchCsrfToken() {
    try {
      const res = await fetch('api/csrf-token.php');
      if (res.ok) {
        const data = await res.json();
        csrfToken = data.csrf_token || '';
      }
    } catch (e) {
      console.warn('Could not pre-fetch CSRF token:', e);
    }
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

  async function handleFormSubmit(e) {
    e.preventDefault();
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
      if (!csrfToken) {
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
    });
  } else {
    attachTriggers();
    fetchCsrfToken();
  }

  // Expose global controller
  window.LoganXDownload = {
    open: openModal,
    close: closeModal
  };
})();
