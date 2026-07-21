(() => {
    'use strict';

    const unlock = (form) => {
        form.dataset.submitting = 'false';
        form.classList.remove('is-submitting');
        form.querySelectorAll('[aria-disabled="true"][data-submit-guard]').forEach((control) => {
            control.removeAttribute('aria-disabled');
            control.removeAttribute('data-submit-guard');
        });
    };

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get' || form.dataset.allowRepeatSubmit === 'true') return;

        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        form.dataset.submitting = 'true';
        form.classList.add('is-submitting');
        if (event.submitter instanceof HTMLElement) {
            event.submitter.setAttribute('aria-disabled', 'true');
            event.submitter.setAttribute('data-submit-guard', 'true');
        }

        // Si la navigation echoue, l utilisateur peut reessayer sans recharger.
        window.setTimeout(() => unlock(form), 12000);
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting="true"]').forEach(unlock);
    });
})();
