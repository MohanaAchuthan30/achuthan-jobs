(function () {
  'use strict';

  function bindFilters(list) {
    if (!list || list.dataset.filtersReady === '1') return;
    list.dataset.filtersReady = '1';
    var inputs = list.querySelectorAll('[data-bbs-filter]');
    var cards = Array.from(list.querySelectorAll('.bbs-job-card'));
    var noResults = list.querySelector('.bbs-no-results');
    var count = list.querySelector('.bbs-count');
    function update() {
      var q = (list.querySelector('[data-bbs-filter="query"]')?.value || '').toLocaleLowerCase().trim();
      var department = (list.querySelector('[data-bbs-filter="department"]')?.value || '').toLocaleLowerCase();
      var type = (list.querySelector('[data-bbs-filter="type"]')?.value || '').toLocaleLowerCase();
      var visible = 0;
      cards.forEach(function (card) {
        var match = (card.dataset.query || '').includes(q) &&
          (!department || card.dataset.department === department) &&
          (!type || card.dataset.type === type);
        card.hidden = !match;
        if (match) visible++;
      });
      if (noResults) noResults.hidden = visible !== 0 || cards.length === 0;
      if (count) count.textContent = ' (' + visible + ')';
    }
    inputs.forEach(function (input) {
      input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', update);
    });
    update();
  }

  async function refreshJobs(section) {
    var list = section.querySelector('.bbs-list[data-bbs-live]');
    var ajax = section.dataset.bbsAjax;
    if (!list || !ajax || section.dataset.fetching === '1') return;
    section.dataset.fetching = '1';
    try {
      var url = new URL(ajax, window.location.href);
      url.searchParams.set('action', 'aj_live_jobs');
      url.searchParams.set('_aj', String(Date.now()));
      var response = await fetch(url.toString(), {cache: 'no-store', credentials: 'same-origin'});
      if (!response.ok) throw new Error('Jobs refresh failed');
      var payload = await response.json();
      if (!payload.success || typeof payload.data?.html !== 'string') throw new Error('Invalid jobs response');
      var wrapper = document.createElement('div');
      wrapper.innerHTML = payload.data.html;
      var current = wrapper.querySelector('.bbs-list[data-bbs-live]');
      if (!current) throw new Error('Missing jobs list');
      var q = list.querySelector('[data-bbs-filter="query"]')?.value || '';
      var d = list.querySelector('[data-bbs-filter="department"]')?.value || '';
      var t = list.querySelector('[data-bbs-filter="type"]')?.value || '';
      list.replaceWith(current);
      var search = current.querySelector('[data-bbs-filter="query"]');
      var department = current.querySelector('[data-bbs-filter="department"]');
      var type = current.querySelector('[data-bbs-filter="type"]');
      if (search) search.value = q;
      if (department) department.value = d;
      if (type) type.value = t;
      bindFilters(current);
      section.dataset.lastSync = String(Date.now());
    } catch (e) {
      // The original, server-rendered job list remains available if AJAX is blocked.
      bindFilters(list);
    } finally {
      section.dataset.fetching = '0';
    }
  }

  function showValidationError(box, message) {
    var alert = box.querySelector('.bbs-client-alert');
    if (!alert) return;
    alert.hidden = false;
    alert.textContent = message;
    alert.scrollIntoView({behavior:'smooth', block:'center'});
  }

  async function refreshFormTokens(box, section) {
    var ajax = section.dataset.bbsAjax;
    var job = box.dataset.bbsJob;
    var form = box.querySelector('.bbs-application-form');
    if (!ajax || !job || !form) return;
    try {
      var url = new URL(ajax, window.location.href);
      url.searchParams.set('action', 'aj_fresh_application_form');
      url.searchParams.set('job_id', job);
      url.searchParams.set('_aj', String(Date.now()));
      var response = await fetch(url.toString(), {cache:'no-store', credentials:'same-origin'});
      var payload = await response.json();
      if (!response.ok || !payload.success) {
        var msg = payload.data?.message || 'The application form cannot be refreshed. Please reload and try again.';
        showValidationError(box, msg);
        if (response.status === 404) { var button = form.querySelector('[type="submit"]'); if (button) button.disabled = true; }
        return;
      }
      var container = document.createElement('div');
      container.innerHTML = payload.data.html;
      var fresh = container.querySelector('.bbs-application-form');
      if (!fresh) return;
      ['bbs_nonce', 'bbs_started'].forEach(function (key) {
        var newField = fresh.elements.namedItem(key);
        var current = form.elements.namedItem(key);
        if (newField && current) current.value = newField.value;
      });
    } catch (e) {
      // Keep the fallback server form. A network outage must not silently submit
      // stale credentials; the server still checks the nonce and expiry.
    }
  }

  function validateRequiredChoices(form, box) {
    for (var group of form.querySelectorAll('.aj-choice-set[data-aj-required-choice="1"]')) {
      var selected = group.querySelector('input[type="checkbox"]:checked, input[type="radio"]:checked');
      var warning = group.querySelector('.aj-choice-error');
      if (!selected) {
        if (warning) warning.hidden = false;
        showValidationError(box, 'Please select an answer for every required multiple-choice question.');
        var first = group.querySelector('input');
        if (first) first.focus();
        return false;
      }
      if (warning) warning.hidden = true;
    }
    return true;
  }

  function bindForm(section) {
    var box = section.querySelector('.bbs-apply[data-bbs-job]');
    if (!box || box.dataset.bound === '1') return;
    box.dataset.bound = '1';
    var form = box.querySelector('.bbs-application-form');
    if (!form) return;
    var check = form.querySelector('input[name="bbs_consent"]');
    var resume = form.querySelector('input[name="resume"]');
    form.addEventListener('invalid', function (event) {
      if (event.target === check) {
        showValidationError(box, 'Please tick the privacy consent checkbox to submit your application.');
      } else {
        showValidationError(box, 'Please fill in the required fields and select a PDF resume.');
      }
    }, true);
    form.addEventListener('submit', function (event) {
      if (pending.size) {
        event.preventDefault();
        showValidationError(box, 'Additional questions are still loading.');
        return;
      }
      if (!validateRequiredChoices(form, box)) {
        event.preventDefault();
        return;
      }
      for (var input of form.querySelectorAll('input[type="file"]')) {
        for (var attachment of input.files) {
          if (attachment.size > 3145728) {
            event.preventDefault();
            showValidationError(box, 'Each resume or question attachment must be 3 MB or less.');
            return;
          }
        }
      }
      if (!check || !check.checked) {
        event.preventDefault();
        showValidationError(box, 'Please tick the privacy consent checkbox to submit your application.');
        return;
      }
      var file = resume?.files?.[0];
      if (!file || file.size > 3145728 || file.size < 100 || !/\.pdf$/i.test(file.name)) {
        event.preventDefault();
        showValidationError(box, 'Choose a valid PDF file no larger than 3 MB.');
        return;
      }
      var started = Number(form.elements.namedItem('bbs_started')?.value || 0);
      if (started && (Date.now()/1000-started)>12*60*60) {
        event.preventDefault();
        showValidationError(box, 'This form has expired. Reload the page and try again.');
        return;
      }
      var submit = form.querySelector('button[type="submit"]');
      if (submit) {
        submit.disabled = true;
        submit.textContent = 'Submitting application…';
      }
    });
    // Apply to the displayed vacancy plus at most 2 other open jobs.
    var extra = Array.from(form.querySelectorAll('[data-aj-extra-job]'));
    var containers = form.querySelector('.aj-extra-question-groups');
    var pending = new Set();
    var submitButton = form.querySelector('button[type="submit"]');
    function updateButton() {
      if (submitButton) submitButton.disabled = pending.size > 0;
    }
    extra.forEach(function (cb) {
      cb.addEventListener('change', async function () {
        var selected = extra.filter(function (c) { return c.checked; });
        if (selected.length > 2) {
          cb.checked = false;
          showValidationError(box, 'Choose up to 2 additional jobs (3 jobs total).');
          return;
        }
        var jobId = cb.value;
        var existing = containers?.querySelector('[data-aj-job-questions="'+jobId+'"]');
        if (!cb.checked) { if (existing) existing.remove(); return; }
        if (!containers || existing) return;
        pending.add(jobId); updateButton();
        try {
          var url = new URL(section.dataset.bbsAjax, window.location.href);
          url.searchParams.set('action', 'aj_extra_questions');
          url.searchParams.set('job_id', jobId);
          var response = await fetch(url.toString(), {cache:'no-store', credentials:'same-origin'});
          var result = await response.json();
          if (!response.ok || !result.success || typeof result.data?.html !== 'string') throw new Error('Unable to load questions for this job.');
          if (cb.checked) {
            var holder = document.createElement('section');
            holder.className = 'aj-job-question-group';
            holder.dataset.ajJobQuestions = jobId;
            var roleName = cb.closest('label')?.querySelector('strong')?.textContent || 'Additional role';
            var header = document.createElement('h3');
            header.textContent = 'Questions for ' + roleName;
            holder.appendChild(header);
            // HTML returned by own same-origin PHP endpoint, with all field values escaped.
            var body = document.createElement('div');
            body.innerHTML = result.data.html || '<p>No additional questions for this role.</p>';
            holder.appendChild(body);
            containers.appendChild(holder);
          }
        } catch (err) {
          cb.checked = false;
          showValidationError(box, 'Could not load additional job questions. Please retry or apply to that job separately.');
        } finally { pending.delete(jobId); updateButton(); }
      });
    });
    form.addEventListener('change', function(event) {
      var set = event.target.closest('.aj-choice-set');
      if (set && set.querySelector('input:checked')) {
        var warning = set.querySelector('.aj-choice-error');
        if (warning) warning.hidden = true;
      }
    });
    refreshFormTokens(box,section);
  }

  document.querySelectorAll('.bbs-careers').forEach(function (section) {
    var list = section.querySelector('.bbs-list[data-bbs-live]');
    if (list) { bindFilters(list); refreshJobs(section); }
    bindForm(section);
  });

  // Update job listings while the tab is open, without refreshing WordPress/Elementor.
  window.setInterval(function () {
    if (document.visibilityState !== 'visible') return;
    document.querySelectorAll('.bbs-careers .bbs-list[data-bbs-live]').forEach(function (list) {
      var section = list.closest('.bbs-careers');
      if (section) refreshJobs(section);
    });
  }, 120000);
  document.addEventListener('visibilitychange',function () {
    if (document.visibilityState !== 'visible') return;
    document.querySelectorAll('.bbs-careers .bbs-list[data-bbs-live]').forEach(function (list) {
      var section = list.closest('.bbs-careers');
      if (section && Date.now()-Number(section.dataset.lastSync||0)>60000) refreshJobs(section);
    });
  });
})();
