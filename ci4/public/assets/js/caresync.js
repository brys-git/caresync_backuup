/**
 * CareSync UI behaviour.
 * Replaces ui-consistency.js. Plain ES5-safe script, no build step, no module.
 */
(function () {
  'use strict';

  var body = document.body;

  /* ---------------------------------------------------------------- cookies */
  function setPref(name, value) {
    document.cookie = name + '=' + encodeURIComponent(value) + ';path=/;max-age=31536000;samesite=lax';
  }

  function getPref(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  /* ------------------------------------------------------- sidebar collapse */
  var collapseBtn = document.querySelector('[data-cs-collapse]');
  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      var collapsed = body.classList.toggle('cs-nav-collapsed');
      setPref('cs_nav', collapsed ? 'collapsed' : 'open');
      collapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
  }

  /* --------------------------------------------------------- mobile drawer */
  var navToggle = document.querySelector('[data-cs-navtoggle]');
  var scrim = document.querySelector('[data-cs-scrim]');

  function closeDrawer() {
    body.classList.remove('cs-nav-open');
    if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
  }

  if (navToggle) {
    navToggle.addEventListener('click', function () {
      var open = body.classList.toggle('cs-nav-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  if (scrim) scrim.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && body.classList.contains('cs-nav-open')) closeDrawer();
  });

  /* ------------------------------------------------------ active nav link */
  // Server-side marking wins; this is the fallback for pages that don't pass
  // an active key. Longest matching path wins so /staff/payments/create still
  // highlights /staff/payments.
  (function markActive() {
    if (document.querySelector('.cs-nav__link.is-active')) return;

    var here = window.location.pathname.replace(/\/+$/, '') || '/';
    var links = document.querySelectorAll('.cs-nav__link[href]');
    var best = null;
    var bestLen = -1;

    Array.prototype.forEach.call(links, function (link) {
      if (link.classList.contains('cs-nav__link--exit')) return;

      var path;
      try {
        path = new URL(link.getAttribute('href'), window.location.origin).pathname;
      } catch (err) {
        return;
      }
      path = path.replace(/\/+$/, '') || '/';

      var hit = here === path || (path !== '/' && here.indexOf(path + '/') === 0);
      if (hit && path.length > bestLen) {
        best = link;
        bestLen = path.length;
      }
    });

    if (best) {
      best.classList.add('is-active');
      best.setAttribute('aria-current', 'page');
    }
  })();

  /* ---------------------------------------------------- table density mode */
  var densityBtn = document.querySelector('[data-cs-density]');
  if (densityBtn) {
    var applyDensity = function (compact) {
      Array.prototype.forEach.call(document.querySelectorAll('.cs-table'), function (t) {
        t.classList.toggle('cs-table--compact', compact);
      });
      densityBtn.setAttribute('aria-pressed', compact ? 'true' : 'false');
    };

    applyDensity(getPref('cs_density') === 'compact');

    densityBtn.addEventListener('click', function () {
      var compact = getPref('cs_density') !== 'compact';
      setPref('cs_density', compact ? 'compact' : 'roomy');
      applyDensity(compact);
    });
  }

  /* --------------------------------------------------------------- toasts */
  function toastHost() {
    var host = document.querySelector('.cs-toasts');
    if (!host) {
      host = document.createElement('div');
      host.className = 'cs-toasts';
      host.setAttribute('role', 'status');
      host.setAttribute('aria-live', 'polite');
      document.body.appendChild(host);
    }
    return host;
  }

  window.csToast = function (message, kind) {
    var icons = { ok: 'ti-circle-check', stop: 'ti-alert-circle', info: 'ti-info-circle' };
    var type = kind || 'info';

    var el = document.createElement('div');
    el.className = 'cs-toast cs-toast--' + type;
    el.innerHTML = '<i class="ti ' + (icons[type] || icons.info) + '"></i><span></span>';
    el.querySelector('span').textContent = message;

    toastHost().appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity 180ms linear';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 200);
    }, 4200);
  };

  // Flash messages promote themselves to toasts, then stop stacking up the page.
  Array.prototype.forEach.call(document.querySelectorAll('[data-cs-flash]'), function (el) {
    window.csToast(el.textContent.trim(), el.getAttribute('data-cs-flash'));
    el.remove();
  });

  /* ----------------------------------------------- destructive confirmation */
  // Money and member records get deleted here. Ask first, and say what will go.
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-cs-confirm]');
    if (!trigger) return;
    if (!window.confirm(trigger.getAttribute('data-cs-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  /* ------------------------------------------------- double-submit guarding */
  // A resubmitted payment form is a duplicate ledger entry. Lock on submit.
  Array.prototype.forEach.call(document.querySelectorAll('form[data-cs-once]'), function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('[type="submit"]');
      if (!btn) return;
      setTimeout(function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader-2"></i> Saving…';
      }, 0);
    });
  });

  /* ------------------------------------------------------- age from DOB */
  // Age is always derived from date of birth, never typed - mirrors
  // cs_age_from_dob() server-side (caresync_helper.php), the real source
  // of truth. This just keeps an on-screen Age field in sync live as the
  // date is picked, purely for display; the server recomputes it again on
  // submit regardless of whatever this field's value ends up being.
  function csComputeAge(dobValue) {
    if (!dobValue) return '';
    var dob = new Date(dobValue + 'T00:00:00');
    if (isNaN(dob.getTime())) return '';
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    if (dob > today) return '';

    var age = today.getFullYear() - dob.getFullYear();
    var hadBirthdayThisYear = (today.getMonth() > dob.getMonth())
      || (today.getMonth() === dob.getMonth() && today.getDate() >= dob.getDate());
    if (!hadBirthdayThisYear) age -= 1;

    return age >= 0 ? String(age) : '';
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-age-source]'), function (ageField) {
    var sourceField = document.querySelector(ageField.getAttribute('data-age-source'));
    if (!sourceField) return;

    var sync = function () { ageField.value = csComputeAge(sourceField.value); };

    sourceField.addEventListener('input', sync);
    sourceField.addEventListener('change', sync);
    sync();
  });
})();
