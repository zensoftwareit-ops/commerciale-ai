(() => {
  const successful = document.querySelector('[data-cai-form-success="1"]');
  if (successful && successful.dataset.caiTracked !== '1') {
    successful.dataset.caiTracked = '1';
    const eventName = successful.dataset.caiGa4Event || 'generate_lead';
    const parameters = { form_type: successful.dataset.caiFormType || 'contact' };
    if (typeof window.gtag === 'function') window.gtag('event', eventName, parameters);
    else if (Array.isArray(window.dataLayer)) window.dataLayer.push({ event: eventName, ...parameters });
    window.dispatchEvent(new CustomEvent('commerciale-ai:form-success', { detail: parameters }));
    if (window.history?.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.delete('cai_form');
      url.searchParams.delete('cai_form_type');
      window.history.replaceState({}, document.title, `${url.pathname}${url.search}${url.hash}`);
    }
  }

  document.querySelectorAll('.cai-form').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      if (!button) return;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.textContent = 'Invio in corso…';
    });
  });
})();
