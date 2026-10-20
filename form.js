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
  let submitting = false;
  document.body.classList.add('enhanced');
  form.noValidate = true;
  const data = () => new FormData(form);
  function showError(message) {
    errors.textContent = message;
    errors.hidden = false;
    errors.focus();
  }
  function show(next, focus = true) {
    step = next;
    sections.forEach(section => { section.hidden = Number(section.dataset.step) !== step; });
    nav.hidden = step === 0 || step === 6;
    document.querySelector('#back').hidden = step <= 1;
    errors.hidden = true;
    document.querySelector('#step-count').textContent = `${step} of 5`;
    progress.setAttribute('aria-valuenow', String(Math.min(step, 5)));
    progress.dataset.value = String(step);
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
    show(Math.min(5, step + 1));
  }
  document.querySelector('#back').addEventListener('click', () => { show(Math.max(1, step - 1)); });
  document.querySelectorAll('[data-next]').forEach(button => button.addEventListener('click', next));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submitting) return;
    if (step !== 5) { next(); return; }
    for (let i = 1; i <= 5; i++) if (!valid(i)) return;
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
