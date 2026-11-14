document.querySelectorAll('[data-group-baptism]').forEach((section) => {
    const form = section.closest('form');
    const service = form.elements.namedItem('service_type');
    const mode = form.elements.namedItem('baptism_mode');
    const session = form.elements.namedItem('group_baptism_session_id');
    const date = form.elements.namedItem('preferred_date');
    const time = form.elements.namedItem('preferred_time');
    const venue = form.elements.namedItem('preferred_church');
    let wasShared = false;

    function update() {
        const matching = [...session.options].filter(option => option.dataset.service === service.value);
        const baptism = ['baptism', 'wedding', 'confirmation'].includes(service.value) && matching.length > 0;
        [...session.options].forEach(option => { option.hidden = Boolean(option.value) && option.dataset.service !== service.value; option.disabled = option.hidden; });
        if (session.selectedOptions[0]?.disabled) session.value = '';
        const shared = baptism && mode.value === 'shared';
        section.hidden = !baptism;
        mode.disabled = !baptism;
        session.disabled = !shared;
        session.required = shared;
        section.querySelector('[data-group-session-choice]').hidden = !shared;
        form.querySelector('[data-schedule-fields]')?.toggleAttribute('hidden', shared);
        date.readOnly = shared;
        time.readOnly = shared;
        [...venue.options].forEach(option => { option.disabled = shared && option.value !== session.selectedOptions[0]?.dataset.venue; });
        if (shared) {
            const option = session.selectedOptions[0];
            date.value = option?.dataset.date ?? '';
            time.value = option?.dataset.time ?? '';
            venue.value = option?.dataset.venue ?? '';
        } else if (wasShared) {
            date.value = '';
            time.value = '';
            venue.value = '';
            session.value = '';
        }
        wasShared = shared;
        time.dispatchEvent(new Event('change', {bubbles: true}));
        form.dispatchEvent(new Event('group-session-changed'));
    }

    [service, mode, session].forEach(field => field.addEventListener('change', update));
    window.addEventListener('pageshow', update);
    update();
});
