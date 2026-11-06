const documentSection = document.querySelector('[data-document-verification]');

if (documentSection) {
    const feedback = documentSection.querySelector('[data-document-feedback]');

    function showFeedback(message, tone = 'success') {
        feedback.hidden = false;
        feedback.textContent = message;
        feedback.className = tone === 'error'
            ? 'mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-950'
            : 'mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950';
    }

    function setDocumentState(card, state) {
        const status = card.querySelector('[data-document-status]');
        const label = card.querySelector('[data-document-status-label]');
        const isVerified = state === 'verified';
        const isDeclined = state === 'declined';

        card.dataset.documentState = state;
        status.className = isVerified
            ? 'inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800'
            : isDeclined
                ? 'inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800'
                : 'inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900';
        label.textContent = isVerified ? 'Verified' : isDeclined ? 'Declined' : 'Needs checking';
    }

    function bindConfirmation(form) {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    }

    function updatePageState(payload) {
        document.querySelectorAll('input[name="version"]').forEach((input) => {
            input.value = payload.version;
        });

        const statusBadge = document.querySelector('[data-appointment-status-badge]');
        if (statusBadge) statusBadge.innerHTML = payload.status_badge_html;

        const statusSummary = document.querySelector('[data-appointment-status-summary]');
        if (statusSummary) statusSummary.textContent = payload.status_summary;

        const scheduleHeading = document.querySelector('[data-appointment-schedule-heading]');
        if (scheduleHeading && payload.appointment_status === 'confirmed') scheduleHeading.textContent = 'Confirmed schedule';

        if (payload.appointment_status !== 'pending') {
            document.querySelector('[data-pending-document-notice]')?.remove();
        }

        const statusSlot = document.querySelector('[data-appointment-status-slot]');
        if (statusSlot) {
            statusSlot.innerHTML = payload.status_section_html;
            statusSlot.querySelectorAll('form[data-confirm]').forEach(bindConfirmation);
        }

        const statusLink = document.querySelector('[data-appointment-status-link]');
        if (statusLink) statusLink.hidden = payload.status_section_html.trim() === '';

        if (!['pending', 'confirmed'].includes(payload.appointment_status)) {
            documentSection.querySelectorAll('[data-document-decision-form]').forEach((form) => form.remove());
            documentSection.querySelector('[data-document-upload]')?.remove();
        }
    }

    documentSection.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-document-decision-form]');
        if (!form) return;

        event.preventDefault();
        const submitter = event.submitter;
        const decision = submitter?.value;
        if (!decision) {
            showFeedback('Choose Verified or Decline.', 'error');

            return;
        }
        if (decision === 'declined' && !window.confirm('Decline this document and release the appointment slot?')) return;

        const card = form.closest('[data-document-card]');
        const originalLabel = submitter?.textContent;
        documentSection.querySelectorAll('[data-document-decision-form] button').forEach((button) => { button.disabled = true; });
        documentSection.setAttribute('aria-busy', 'true');
        if (submitter) submitter.textContent = decision === 'declined' ? 'Declining...' : 'Saving...';

        try {
            const formData = new FormData(form);
            formData.set('decision', decision);
            const response = await fetch(form.action, {
                method: form.method,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });
            const payload = response.headers.get('content-type')?.includes('application/json')
                ? await response.json()
                : { message: 'The server returned an unexpected response. Please refresh and try again.' };
            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? payload.message ?? 'The document decision could not be saved.');
            }

            setDocumentState(card, payload.result === 'declined' ? 'declined' : payload.document.verified ? 'verified' : 'pending');
            updatePageState(payload);
            showFeedback(payload.message);
        } catch (error) {
            showFeedback(error.message ?? 'The document decision could not be saved.', 'error');
        } finally {
            documentSection.removeAttribute('aria-busy');
            documentSection.querySelectorAll('[data-document-decision-form] button').forEach((button) => { button.disabled = false; });
            if (submitter?.isConnected) submitter.textContent = originalLabel;
        }
    });
}
