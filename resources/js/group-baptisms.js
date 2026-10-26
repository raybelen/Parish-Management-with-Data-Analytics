document.querySelectorAll('[data-baptism-options]').forEach((panel) => {
    const form = panel.closest('form');
    const service = form.elements.namedItem('service_type');
    const mode = panel.querySelector('[data-baptism-mode]');
    const sessions = panel.querySelector('[data-group-session]');
    const sharedOnly = panel.dataset.sharedOnly === '1';
    function sync() {
        const baptism = sharedOnly || service?.value === 'baptism';
        const shared = baptism && (sharedOnly || mode?.value === 'shared');
        panel.hidden = !baptism;
        if (mode) mode.disabled = !baptism;
        sessions.disabled = !shared;
        sessions.required = shared;
        panel.querySelector('[data-group-session-field]').hidden = !shared;
    }
    function selectSession() {
        sync();
        const option = sessions.selectedOptions[0];
        if (!sessions.disabled && option?.value) {
            [['preferred_date', 'date'], ['preferred_time', 'time'], ['preferred_church', 'venue']].forEach(([name, key]) => {
                const field = form.elements.namedItem(name);
                if (field) {
                    field.value = option.dataset[key];
                    field.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
        }
    }
    service?.addEventListener('change', sync);
    mode?.addEventListener('change', () => { sync(); form.elements.namedItem('preferred_time')?.dispatchEvent(new Event('change', {bubbles: true})); });
    sessions.addEventListener('change', selectSession);
    sync();
});
