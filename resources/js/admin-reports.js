document.querySelectorAll('[data-report-form]').forEach((form) => {
    const report = form.elements.type;
    const statusFields = form.querySelector('[data-report-status]');
    const dateFields = form.querySelector('[data-report-dates]');
    const updateFields = () => {
        const hasStatus = ['appointments', 'outstanding'].includes(report.value);
        statusFields.hidden = !hasStatus;
        statusFields.disabled = !hasStatus;
        dateFields.hidden = report.value === 'outstanding';
        dateFields.disabled = report.value === 'outstanding';
        form.querySelectorAll('[data-outstanding-status]').forEach((option) => {
            option.hidden = report.value === 'outstanding' && option.dataset.outstandingStatus === '0';
            option.disabled = option.hidden;
            if (option.disabled && option.selected) form.elements.status.value = '';
        });
        form.querySelectorAll('[data-report-description]').forEach((description) => {
            description.hidden = description.dataset.reportDescription !== report.value;
        });
    };
    const markStale = () => {
        const notice = document.querySelector('[data-report-preview-stale]');
        if (notice) notice.hidden = false;
    };
    report.addEventListener('change', updateFields);
    form.addEventListener('input', markStale);
    form.addEventListener('change', markStale);
    form.querySelectorAll('[data-report-from]').forEach((button) => {
        button.addEventListener('click', () => {
            form.elements.from.value = button.dataset.reportFrom;
            form.elements.to.value = button.dataset.reportTo;
            markStale();
        });
    });
    updateFields();
});
