/**
 * FeedbackFlow — Embeddable Inline Form
 * Usage: Place <div id="ff-embed-form"></div> where you want the form,
 * then include this script with window.FFConfig set above it.
 */
(function () {
  'use strict';

  var cfg = window.FFConfig || {};
  var key = cfg.key || '';
  var baseUrl = (cfg.baseUrl || '').replace(/\/$/, '');
  var theme = cfg.theme || 'light';
  var showRating = cfg.showRating !== false;
  var ratingQuestion = cfg.ratingQuestion || 'How was your experience?';
  var source = cfg.source || 'embedded';
  var targetId = cfg.targetId || 'ff-embed-form';

  var container = document.getElementById(targetId);
  if (!container || !key || !baseUrl) return;

  // Colors
  var isDark = theme === 'dark' || (theme === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  var bg = isDark ? '#1e293b' : '#ffffff';
  var border = isDark ? '#334155' : '#e2e8f0';
  var text = isDark ? '#f1f5f9' : '#0f172a';
  var muted = isDark ? '#94a3b8' : '#64748b';
  var inputBg = isDark ? '#0f172a' : '#f8fafc';
  var inputBorder = isDark ? '#334155' : '#e2e8f0';

  // Fetch project info from API
  var apiUrl = baseUrl + '/api/feedback.php?key=' + encodeURIComponent(key);

  // Build the form HTML
  function buildForm(categories, color) {
    color = color || '#6366f1';

    var catOptions = '<option value="">— Select category —</option>';
    if (categories && categories.length) {
      categories.forEach(function (c) {
        catOptions += '<option value="' + c.id + '">' + escHtml(c.name) + '</option>';
      });
    }

    var starHtml = '';
    if (showRating) {
      starHtml = '<div class="ff-rating-wrap">' +
        '<p class="ff-rating-q">' + escHtml(ratingQuestion) + '</p>' +
        '<div class="ff-stars" role="group">';
      for (var i = 5; i >= 1; i--) {
        starHtml += '<input type="radio" name="ff_rating" id="ffs' + i + '" value="' + i + '">' +
          '<label for="ffs' + i + '" title="' + i + ' star' + (i > 1 ? 's' : '') + '">★</label>';
      }
      starHtml += '</div></div>';
    }

    return '<form class="ff-embed-form" id="ff-embed-form-el">' +
      '<div class="ff-form-inner">' +
      starHtml +
      (categories && categories.length ? '<div class="ff-field"><label>Category</label><select name="ff_category">' + catOptions + '</select></div>' : '') +
      '<div class="ff-field"><label>Your feedback <span class="ff-req">*</span></label>' +
        '<textarea name="ff_message" placeholder="Tell us what you think..." rows="4" required></textarea></div>' +
      '<div class="ff-field"><label>Short title (optional)</label>' +
        '<input type="text" name="ff_title" placeholder="e.g. Checkout is broken"></div>' +
      '<div class="ff-field-row">' +
        '<div class="ff-field"><label>Your name</label><input type="text" name="ff_name" placeholder="Name"></div>' +
        '<div class="ff-field"><label>Email</label><input type="email" name="ff_email" placeholder="you@example.com"></div>' +
      '</div>' +
      '<button type="submit" class="ff-submit">Send Feedback →</button>' +
      '<p class="ff-error" style="display:none"></p>' +
      '</div>' +
      '</form>';
  }

  function buildSuccess() {
    return '<div class="ff-success">' +
      '<div class="ff-success-icon">✓</div>' +
      '<h3>Thank you!</h3>' +
      '<p>Your feedback has been received.</p>' +
      '</div>';
  }

  function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
  }

  function injectStyles(color) {
    if (document.getElementById('ff-embed-styles')) return;
    var s = document.createElement('style');
    s.id = 'ff-embed-styles';
    s.textContent = [
      '.ff-embed-wrap{background:' + bg + ';border:1.5px solid ' + border + ';border-radius:20px;padding:28px 32px;font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif;color:' + text + ';max-width:600px}',
      '.ff-form-inner{display:flex;flex-direction:column;gap:16px}',
      '.ff-rating-wrap{text-align:center;padding:16px;background:' + inputBg + ';border-radius:14px;border:1px solid ' + inputBorder + '}',
      '.ff-rating-q{font-size:14px;font-weight:600;color:' + text + ';margin:0 0 12px}',
      '.ff-stars{display:flex;flex-direction:row-reverse;justify-content:center;gap:6px}',
      '.ff-stars input{display:none}',
      '.ff-stars label{font-size:28px;cursor:pointer;filter:grayscale(1) opacity(.3);transition:filter .1s;line-height:1}',
      '.ff-stars input:checked~label,.ff-stars label:hover,.ff-stars label:hover~label{filter:none}',
      '.ff-stars input:checked~label{filter:none}',
      '.ff-field{display:flex;flex-direction:column;gap:5px}',
      '.ff-field label{font-size:11px;font-weight:700;color:' + muted + ';text-transform:uppercase;letter-spacing:.5px}',
      '.ff-req{color:#ef4444}',
      '.ff-field input,.ff-field textarea,.ff-field select{width:100%;border:1.5px solid ' + inputBorder + ';border-radius:10px;padding:10px 12px;font-size:13px;font-family:inherit;color:' + text + ';background:' + inputBg + ';outline:none;box-sizing:border-box;transition:border-color .15s}',
      '.ff-field input:focus,.ff-field textarea:focus,.ff-field select:focus{border-color:' + color + ';box-shadow:0 0 0 3px ' + color + '22}',
      '.ff-field textarea{resize:vertical;min-height:90px}',
      '.ff-field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}',
      '.ff-submit{width:100%;padding:12px;border:none;border-radius:12px;font-size:14px;font-weight:700;color:#fff;cursor:pointer;background:linear-gradient(135deg,' + color + ',' + color + 'cc);transition:opacity .15s}',
      '.ff-submit:hover{opacity:.9}',
      '.ff-error{color:#dc2626;font-size:12px;text-align:center;margin-top:4px}',
      '.ff-success{text-align:center;padding:32px}',
      '.ff-success-icon{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;margin:0 auto 16px}',
      '.ff-success h3{font-size:18px;font-weight:700;color:' + text + ';margin:0 0 8px}',
      '.ff-success p{font-size:13px;color:' + muted + ';margin:0}',
      '@media(max-width:480px){.ff-embed-wrap{padding:20px 16px}.ff-field-row{grid-template-columns:1fr}}'
    ].join('');
    document.head.appendChild(s);
  }

  function init() {
    // Fetch categories via the existing API
    var xhr = new XMLHttpRequest();
    xhr.open('GET', apiUrl, true);
    xhr.onload = function () {
      var color = '#6366f1';
      var categories = [];
      try {
        var data = JSON.parse(xhr.responseText);
        color = data.project_color || color;
        categories = data.categories || [];
      } catch (e) {}
      render(color, categories);
    };
    xhr.onerror = function () { render('#6366f1', []); };
    xhr.send();
  }

  function render(color, categories) {
    injectStyles(color);
    var wrap = document.createElement('div');
    wrap.className = 'ff-embed-wrap';
    wrap.innerHTML = buildForm(categories, color);
    container.appendChild(wrap);
    attachEvents(wrap, color);
  }

  function attachEvents(wrap, color) {
    var form = wrap.querySelector('#ff-embed-form-el');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var errEl = form.querySelector('.ff-error');
      errEl.style.display = 'none';

      var rating = 0;
      var ratingInput = form.querySelector('input[name="ff_rating"]:checked');
      if (ratingInput) rating = parseInt(ratingInput.value);

      var category = form.querySelector('[name="ff_category"]');
      var message = form.querySelector('[name="ff_message"]').value.trim();
      var title = form.querySelector('[name="ff_title"]').value.trim() || message.substring(0, 80);
      var name = form.querySelector('[name="ff_name"]').value.trim();
      var email = form.querySelector('[name="ff_email"]').value.trim();

      if (!message) {
        errEl.textContent = 'Please write your feedback.';
        errEl.style.display = 'block';
        return;
      }

      var btn = form.querySelector('.ff-submit');
      btn.disabled = true;
      btn.textContent = 'Sending…';

      var params = new URLSearchParams({
        key: key,
        title: title,
        description: message,
        category_id: category ? category.value : '',
        submitter_name: name,
        submitter_email: email,
        rating: rating,
        source: source,
      });

      var xhr = new XMLHttpRequest();
      xhr.open('POST', baseUrl + '/api/feedback.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function () {
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.success) {
            var inner = wrap.querySelector('.ff-embed-wrap');
            if (inner) inner.innerHTML = buildSuccess();
          } else {
            errEl.textContent = res.error || 'Submission failed. Please try again.';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Send Feedback →';
          }
        } catch (ex) {
          errEl.textContent = 'An error occurred. Please try again.';
          errEl.style.display = 'block';
          btn.disabled = false;
          btn.textContent = 'Send Feedback →';
        }
      };
      xhr.onerror = function () {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Send Feedback →';
      };
      xhr.send(params.toString());
    });
  }

  // Run
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
