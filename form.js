/* Backend contract is documented in docs/switch-review.md. No tracking scripts. */
(() => {
  'use strict';
  const config = Object.freeze({ endpoint: '/api/switch-review', metaTrackingEnabled: false });
  // TODO: Meta tracking integration belongs here only after explicit configuration/consent.
  const form = document.querySelector('form');
  const sections = [...document.querySelectorAll('[data-step]')];
  const errors = document.querySelector('#errors');
  const nav = document.querySelector('.step-nav');
  const progress = document.querySelector('.progress');
  const params = new URLSearchParams(location.search);
  for (const key of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'fbclid']) {
    form.elements.namedItem(key).value = (params.get(key) || '').slice(0, 2000);
  }
  let step = 1;
  let editing = false;
  let submitting = false;
  document.body.classList.add('enhanced');
  form.noValidate = true;
  const data = () => new FormData(form);
  const other = document.querySelector('#other-field');
  const updateOther = () => { other.hidden = !data().getAll('concerns').includes('Something else'); };
  updateOther();
  function showError(message) {
    errors.textContent = message;
    errors.hidden = false;
    errors.focus();
  }
  function summary() {
    const values = data();
    const root = document.querySelector('#summary');
    root.replaceChildren();
    const concerns = values.getAll('concerns');
    if (concerns.includes('Something else') && values.get('other_concern')) concerns.push(values.get('other_concern'));
    const rows = [
      ['Your team', values.get('staff_size') + ' people', 1],
      ['Agreement renewal', values.get('renewal'), 2],
      ['What could be better', concerns.join('\n') || 'No concerns selected', 3],
      ['Your details', [values.get('name'), values.get('business_name'), values.get('email'), values.get('phone')].filter(Boolean).join('\n'), 4],
    ];
    for (const [title, value, target] of rows) {
      const row = document.createElement('div'); row.className = 'summary-row';
      const heading = document.createElement('b'); heading.textContent = title;
      const text = document.createElement('p'); text.textContent = value;
      const edit = document.createElement('button'); edit.type = 'button'; edit.textContent = 'Edit';
      edit.setAttribute('aria-label', 'Edit ' + title.toLowerCase());
      edit.addEventListener('click', () => { editing = true; show(target); });
      row.append(heading, text, edit); root.append(row);
    }
  }
  function show(next, focus = true) {
    step = next;
    sections.forEach((section, i) => { section.hidden = i !== step; });
    nav.hidden = step === 0 || step === 6;
    document.querySelector('#back').hidden = step <= 1;
    errors.hidden = true;
    document.querySelector('#step-count').textContent = `${step} of 5`;
    progress.setAttribute('aria-valuenow', String(step));
    progress.firstElementChild.style.width = `${step / 6 * 100}%`;
    if (step === 5) summary();
    const heading = sections[step].querySelector('h1,h2');
    document.querySelector('#announcement').textContent = heading.textContent;
    if (focus) { heading.focus({ preventScroll: true }); window.scrollTo({ top: 0, behavior: 'instant' }); }
  }
  function valid(index) {
    for (const field of sections[index].querySelectorAll('input,textarea')) {
      if (!field.checkValidity()) { show(index); field.reportValidity(); return false; }
    }
    return true;
  }
  function next() {
    if (!valid(step)) return;
    if (editing) { editing = false; show(5); } else show(step + 1);
  }
  document.querySelector('#back').addEventListener('click', () => { editing = false; show(Math.max(1, step - 1)); });
  document.querySelectorAll('[data-next]').forEach(button => button.addEventListener('click', next));
  form.addEventListener('change', event => {
    updateOther();
    if (event.target.matches('input[type=radio]')) next();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submitting) return;
    if (step !== 5) { next(); return; }
    for (let i = 1; i <= 4; i++) if (!valid(i)) return;
    submitting = true;
    const button = form.querySelector('[type=submit]');
    button.disabled = true; button.textContent = 'Sending…';
    errors.hidden = true;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(config.endpoint, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
        body: new URLSearchParams(data()), signal: controller.signal,
      });
      const result = await response.json().catch(() => null);
      if (!response.ok || result?.ok !== true) {
        const details = result?.errors && typeof result.errors === 'object' ? Object.values(result.errors).filter(value => typeof value === 'string').join(' ') : '';
        throw new Error(details || (response.status === 429 ? 'Too many requests. Please try again later or call 409-937-2798.' : 'We couldn’t send your review. Please try again or call 409-937-2798.'));
      }
      form.hidden = true; show(6);
    } catch (error) {
      showError(error.name === 'AbortError' ? 'The request timed out. Please call 409-937-2798 to check whether your review arrived before submitting again.' : error.message);
    } finally {
      clearTimeout(timer); submitting = false; button.disabled = false; button.innerHTML = 'Submit <span aria-hidden="true">→</span>';
    }
  });
  show(1, false);
})();
