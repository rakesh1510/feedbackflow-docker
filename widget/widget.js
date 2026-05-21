/**
 * FeedbackFlow Embeddable Widget
 * Usage: <script src="/widget/widget.js" data-key="YOUR_KEY" defer></script>
 */
(function() {
  'use strict';

  var script = document.currentScript || (function() {
    var scripts = document.querySelectorAll('script[data-key]');
    return scripts[scripts.length - 1];
  })();

  var KEY = script.getAttribute('data-key');
  var BASE_URL = script.src.replace('/widget/widget.js', '');
  if (!KEY) { console.warn('FeedbackFlow: No data-key attribute found.'); return; }

  var config = {
    color: '#6366f1', position: 'bottom-right', theme: 'light',
    title: 'Share your feedback', placeholder: 'Tell us what you think...',
    userEmail: '', userName: '',
  };

  // Fetch project config
  fetch(BASE_URL + '/widget/config.php?key=' + KEY)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.color) config.color = data.color;
      if (data.position) config.position = data.position;
      if (data.theme) config.theme = data.theme;
      if (data.title) config.title = data.title;
      if (data.placeholder) config.placeholder = data.placeholder;
      init();
    })
    .catch(function() { init(); });

  var isDark = config.theme === 'dark' || (config.theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  var isOpen = false;
  var widget, panel, btn;

  function init() {
    isDark = config.theme === 'dark' || (config.theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    injectStyles();
    createWidget();
  }

  function injectStyles() {
    var style = document.createElement('style');
    style.textContent = [
      '#ff-widget *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Inter","Segoe UI",sans-serif}',
      '#ff-btn{position:fixed;z-index:99999;cursor:pointer;display:flex;align-items:center;gap:8px;padding:12px 20px;border-radius:50px;border:none;color:#fff;font-size:14px;font-weight:600;box-shadow:0 4px 24px rgba(0,0,0,.18);transition:all .2s;white-space:nowrap}',
      '#ff-btn:hover{transform:scale(1.04);box-shadow:0 6px 28px rgba(0,0,0,.22)}',
      '#ff-panel{position:fixed;z-index:99998;width:380px;max-width:calc(100vw - 32px);background:' + (isDark?'#1e1e2e':'#fff') + ';border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.18);border:1px solid ' + (isDark?'#333':'#e5e7eb') + ';overflow:hidden;transition:all .25s cubic-bezier(.4,0,.2,1);transform-origin:bottom right}',
      '#ff-panel.ff-hidden{opacity:0;transform:scale(.95) translateY(8px);pointer-events:none}',
      '#ff-header{padding:16px 20px;display:flex;align-items:center;justify-content:space-between}',
      '#ff-header h3{margin:0;font-size:15px;font-weight:700;color:' + (isDark?'#f1f1f1':'#111827') + '}',
      '#ff-close{background:none;border:none;cursor:pointer;color:' + (isDark?'#888':'#9ca3af') + ';padding:4px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:background .15s}',
      '#ff-close:hover{background:' + (isDark?'#333':'#f3f4f6') + '}',
      '#ff-body{padding:0 20px 20px}',
      '#ff-tabs{display:flex;gap:4px;margin-bottom:16px;background:' + (isDark?'#161624':'#f3f4f6') + ';border-radius:12px;padding:4px}',
      '#ff-tabs button{flex:1;padding:8px;border:none;background:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;color:' + (isDark?'#aaa':'#6b7280') + ';transition:all .15s}',
      '#ff-tabs button.ff-active{background:#fff;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,.1)}',
      'textarea.ff-field,input.ff-field,select.ff-field{width:100%;padding:10px 14px;border:1.5px solid ' + (isDark?'#333':'#e5e7eb') + ';border-radius:12px;background:' + (isDark?'#161624':'#f9fafb') + ';color:' + (isDark?'#f1f1f1':'#111827') + ';font-size:14px;outline:none;transition:border .15s;resize:none;margin-bottom:10px;font-family:inherit}',
      'textarea.ff-field:focus,input.ff-field:focus,select.ff-field:focus{border-color:' + config.color + ';background:' + (isDark?'#1e1e2e':'#fff') + '}',
      '#ff-submit{width:100%;padding:12px;border:none;border-radius:12px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;margin-top:4px}',
      '#ff-submit:hover{opacity:.9;transform:scale(1.01)}',
      '#ff-success{text-align:center;padding:20px 0}',
      '#ff-success .ff-check{width:56px;height:56px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px}',
      '#ff-success h4{margin:0 0 4px;font-size:16px;color:' + (isDark?'#f1f1f1':'#111827') + '}',
      '#ff-success p{margin:0;font-size:13px;color:' + (isDark?'#888':'#6b7280') + '}',
      '.ff-emoji-row{display:flex;gap:8px;margin-bottom:12px}',
      '.ff-emoji{font-size:24px;cursor:pointer;border:2px solid transparent;border-radius:10px;padding:4px 8px;transition:all .15s;background:' + (isDark?'#161624':'#f3f4f6') + '}',
      '.ff-emoji:hover,.ff-emoji.ff-selected{border-color:' + config.color + ';background:' + config.color + '15}',
      '.ff-label{font-size:12px;font-weight:600;color:' + (isDark?'#888':'#6b7280') + ';margin-bottom:6px;display:block;text-transform:uppercase;letter-spacing:.4px}',
      '#ff-char{font-size:11px;color:' + (isDark?'#666':'#9ca3af') + ';text-align:right;margin-top:-8px;margin-bottom:8px}',
    ].join('');
    document.head.appendChild(style);
  }

  function posStyle() {
    var p = config.position.split('-');
    var s = {};
    s[p[0]] = '20px';
    s[p[1]] = '20px';
    return s;
  }

  function createWidget() {
    widget = document.createElement('div');
    widget.id = 'ff-widget';

    // Button
    btn = document.createElement('button');
    btn.id = 'ff-btn';
    btn.style.background = config.color;
    var ps = posStyle();
    Object.keys(ps).forEach(function(k) { btn.style[k] = ps[k]; });
    btn.innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>' + config.title;
    btn.onclick = function() { togglePanel(); };

    // Panel
    panel = document.createElement('div');
    panel.id = 'ff-panel';
    panel.className = 'ff-hidden';
    Object.keys(ps).forEach(function(k) { panel.style[k] = k === 'bottom' ? '80px' : ps[k]; });

    panel.innerHTML = buildPanelHTML();
    widget.appendChild(btn);
    widget.appendChild(panel);
    document.body.appendChild(widget);

    setupEvents();
  }

  function buildPanelHTML() {
    return '<div id="ff-header" style="border-bottom:1px solid ' + (isDark?'#333':'#f3f4f6') + '">' +
      '<h3>' + escapeHtml(config.title) + '</h3>' +
      '<button id="ff-close" onclick="FeedbackFlow.close()" title="Close"><svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>' +
    '</div>' +
    '<div id="ff-body">' +
      '<div id="ff-tabs">' +
        '<button class="ff-active" data-tab="feedback">💬 Feedback</button>' +
        '<button data-tab="bug">🐛 Bug</button>' +
        '<button data-tab="idea">💡 Idea</button>' +
      '</div>' +
      '<div id="ff-form-area">' + buildFeedbackForm('feedback') + '</div>' +
    '</div>';
  }

  function buildFeedbackForm(type) {
    var typePlaceholders = {
      feedback: config.placeholder,
      bug: 'Describe the bug and steps to reproduce...',
      idea: 'Share your feature idea...',
    };
    return '<form id="ff-form">' +
      '<label class="ff-label">Your ' + (type === 'bug' ? 'Bug Report' : type === 'idea' ? 'Idea' : 'Feedback') + '</label>' +
      '<textarea class="ff-field" name="title" rows="1" placeholder="Brief title..." maxlength="200" required style="min-height:44px;resize:none" id="ff-title"></textarea>' +
      '<textarea class="ff-field" name="description" rows="3" placeholder="' + typePlaceholders[type] + '" id="ff-desc"></textarea>' +
      '<span id="ff-char">0/1000</span>' +
      (type === 'feedback' ? '<div class="ff-emoji-row" id="ff-emojis"><div class="ff-emoji" data-emoji="😍">😍</div><div class="ff-emoji ff-selected" data-emoji="👍">👍</div><div class="ff-emoji" data-emoji="😐">😐</div><div class="ff-emoji" data-emoji="👎">👎</div><div class="ff-emoji" data-emoji="😡">😡</div></div>' : '') +
      '<input type="email" class="ff-field" name="email" placeholder="your@email.com (optional)" value="' + escapeHtml(config.userEmail) + '">' +
      '<button type="submit" id="ff-submit" style="background:' + config.color + '">Send <svg style="vertical-align:middle" width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg></button>' +
    '</form>';
  }

  function setupEvents() {
    // Tab switching
    panel.querySelectorAll('#ff-tabs button').forEach(function(tab) {
      tab.addEventListener('click', function() {
        panel.querySelectorAll('#ff-tabs button').forEach(function(t) { t.classList.remove('ff-active'); });
        tab.classList.add('ff-active');
        document.getElementById('ff-form-area').innerHTML = buildFeedbackForm(tab.getAttribute('data-tab'));
        attachFormEvents();
      });
    });
    attachFormEvents();
  }

  function attachFormEvents() {
    var form = document.getElementById('ff-form');
    var desc = document.getElementById('ff-desc');
    var charCount = document.getElementById('ff-char');
    var emojis = panel.querySelectorAll('.ff-emoji');
    var selectedEmoji = '👍';

    if (desc && charCount) {
      desc.addEventListener('input', function() {
        charCount.textContent = desc.value.length + '/1000';
        if (desc.value.length > 1000) desc.value = desc.value.slice(0, 1000);
      });
    }

    emojis.forEach(function(el) {
      el.addEventListener('click', function() {
        emojis.forEach(function(e) { e.classList.remove('ff-selected'); });
        el.classList.add('ff-selected');
        selectedEmoji = el.getAttribute('data-emoji');
      });
    });

    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var submitBtn = document.getElementById('ff-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        var tab = panel.querySelector('#ff-tabs button.ff-active');
        var catMap = { feedback: '', bug: 'bug-report', idea: 'feature-request' };

        var data = new FormData();
        data.append('key', KEY);
        data.append('title', form.querySelector('[name=title]').value);
        data.append('description', form.querySelector('[name=description]').value);
        data.append('email', form.querySelector('[name=email]').value);
        data.append('emoji', selectedEmoji);
        data.append('type', tab ? tab.getAttribute('data-tab') : 'feedback');
        data.append('page_url', window.location.href);
        data.append('browser', navigator.userAgent.slice(0, 100));

        fetch(BASE_URL + '/widget/submit.php', { method: 'POST', body: data })
          .then(function(r) { return r.json(); })
          .then(function(res) {
            if (res.ok) {
              document.getElementById('ff-form-area').innerHTML = '<div id="ff-success"><div class="ff-check">✅</div><h4>Thank you!</h4><p>Your feedback has been received.</p></div>';
              setTimeout(function() { if (isOpen) togglePanel(); }, 3500);
            } else {
              submitBtn.disabled = false;
              submitBtn.textContent = 'Try Again';
            }
          })
          .catch(function() { submitBtn.disabled = false; submitBtn.textContent = 'Try Again'; });
      });
    }
  }

  function togglePanel() {
    isOpen = !isOpen;
    if (isOpen) { panel.classList.remove('ff-hidden'); } else { panel.classList.add('ff-hidden'); }
  }

  function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  // Public API
  window.FeedbackFlow = {
    open: function() { if (!isOpen) togglePanel(); },
    close: function() { if (isOpen) togglePanel(); },
    identify: function(email, name) {
      config.userEmail = email || '';
      config.userName = name || '';
      var emailInput = document.querySelector('#ff-widget input[name=email]');
      if (emailInput) emailInput.value = config.userEmail;
    },
  };
})();
