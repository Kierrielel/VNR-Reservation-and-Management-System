<link href="{{ asset('css/confirmation-modal.css') }}" rel="stylesheet">

<div class="confirmation-modal" id="confirmationModal" aria-hidden="true" role="dialog" aria-modal="true"
    aria-labelledby="confirmationModalTitle" aria-describedby="confirmationModalMessage">
    <div class="confirmation-modal__card" role="document">
        <h2 class="confirmation-modal__title" id="confirmationModalTitle">Are you sure?</h2>
        <p class="confirmation-modal__message" id="confirmationModalMessage"></p>

        <div class="confirmation-modal__actions">
            <button type="button" class="confirmation-modal__button confirmation-modal__button--cancel"
                data-confirm-cancel>Cancel</button>
            <button type="button" class="confirmation-modal__button confirmation-modal__button--confirm"
                data-confirm-submit>Yes, continue</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('confirmationModal');
        const title = modal.querySelector('#confirmationModalTitle');
        const message = modal.querySelector('#confirmationModalMessage');
        const submitButton = modal.querySelector('[data-confirm-submit]');
        const cancelButton = modal.querySelector('[data-confirm-cancel]');
        let pendingAction = null;
        let previousFocus = null;

        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('confirmation-modal-open');
            pendingAction = null;

            if (previousFocus) {
                previousFocus.focus();
            }
        };

        const openModal = (action) => {
            pendingAction = action;
            previousFocus = document.activeElement;
            title.textContent = action.title;
            message.textContent = action.message;
            submitButton.textContent = action.confirmLabel;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('confirmation-modal-open');
            submitButton.focus();
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-confirm]');

            if (!trigger) {
                return;
            }

            event.preventDefault();

            openModal({
                title: trigger.dataset.confirmTitle || 'Are you sure?',
                message: trigger.dataset.confirmMessage || 'This action cannot be undone.',
                confirmLabel: trigger.dataset.confirmLabel || 'Yes, continue',
                action: () => {
                    if (trigger.tagName === 'FORM') {
                        trigger.submit();
                        return;
                    }

                    window.location.assign(trigger.href);
                },
            });
        });

        cancelButton.addEventListener('click', closeModal);

        submitButton.addEventListener('click', () => {
            if (pendingAction) {
                pendingAction.action();
            }
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    })();
</script>
