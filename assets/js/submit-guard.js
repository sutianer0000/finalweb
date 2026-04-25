(function () {
    const LOCKED = '1';

    function getSubmitButtons(form) {
        return form.querySelectorAll('button[type="submit"], input[type="submit"]');
    }

    function preserveSubmitterValue(form, submitter) {
        if (!submitter || !submitter.name || submitter.disabled) {
            return;
        }

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = submitter.name;
        hidden.value = submitter.value || '';
        hidden.setAttribute('data-submit-guard-clone', '1');
        form.appendChild(hidden);
    }

    function lockForm(form, submitter) {
        form.dataset.submitLocked = LOCKED;
        form.setAttribute('aria-busy', 'true');
        preserveSubmitterValue(form, submitter);

        getSubmitButtons(form).forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.classList.add('is-submit-locked');
        });
    }

    function unlockForm(form) {
        form.dataset.submitLocked = '';
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-submit-guard-clone]').forEach((clone) => clone.remove());

        getSubmitButtons(form).forEach((button) => {
            button.disabled = false;
            button.removeAttribute('aria-disabled');
            button.classList.remove('is-submit-locked');
        });
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-submit-lock')) {
            return;
        }

        if (form.dataset.submitLocked === LOCKED) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        lockForm(form, event.submitter);
    });

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }

        document.querySelectorAll('form[data-submit-locked="1"]').forEach(unlockForm);
    });
})();
