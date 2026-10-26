document.querySelectorAll('form[data-availability-url]').forEach((form) => {
    const date = form.elements.namedItem('preferred_date');
    const time = form.elements.namedItem('preferred_time');
    const venue = form.elements.namedItem('preferred_church');
    const service = form.elements.namedItem('service_type');
    const status = form.querySelector('[data-availability-status]');
    if (!date || !time || !status) return;

    const mode = form.elements.namedItem('baptism_mode');
    const session = form.elements.namedItem('group_baptism_session_id');
    const fields = [date, time, venue, service, mode, session].filter(Boolean);
    const alternatives = document.createElement('div');
    alternatives.className = 'sm:col-span-2 flex flex-wrap gap-3';
    status.after(alternatives);
    let controller;
    let timer;
    let revision = 0;
    time.setAttribute('aria-describedby', [time.getAttribute('aria-describedby'), status.id].filter(Boolean).join(' '));

    const show = (message, invalid = false) => {
        status.textContent = message;
        status.hidden = !message;
        time.setCustomValidity(invalid ? message : '');
        time.setAttribute('aria-invalid', String(invalid));
    };

    async function check(currentRevision, suggest = false) {
        controller = new AbortController();
        const values = Object.fromEntries(fields.filter(field => !field.disabled).map((field) => [field.name, field.value]));
        try {
            const response = await fetch(form.dataset.availabilityUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': form.elements.namedItem('_token').value,
                },
                body: JSON.stringify({...values, suggest}),
                signal: controller.signal,
            });
            const payload = await response.json();
            if (currentRevision !== revision) return;
            if (response.status === 422) {
                show(Object.values(payload.errors || {}).flat().join(' ') || 'Please check the selected schedule.', true);
                return;
            }
            if (!response.ok || typeof payload.available !== 'boolean') throw new Error('Availability check failed');
            show(payload.available ? '' : payload.message, !payload.available);
            alternatives.replaceChildren();
            if (!payload.available && (!session || session.disabled || !session.value) && !suggest && (!mode || mode.disabled || mode.value !== 'shared')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'text-link';
                button.textContent = 'Find available times on this date';
                button.addEventListener('click', () => {
                    button.disabled = true;
                    button.textContent = 'Finding available times...';
                    check(currentRevision, true);
                });
                alternatives.append(button);
            }
            (payload.alternatives || []).forEach((option) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'border border-navy/25 px-4 py-2 text-sm';
                button.textContent = option.label;
                button.addEventListener('click', () => { time.value = option.time; time.dispatchEvent(new Event('change', {bubbles: true})); });
                alternatives.append(button);
            });
            if (suggest && !payload.alternatives?.length) alternatives.textContent = 'No alternative times are available at this venue on this date. Try another date or venue.';
        } catch (error) {
            if (error.name === 'AbortError' || currentRevision !== revision) return;
            show('Availability could not be checked right now. It will be checked again when you submit.');
        } finally {
            if (currentRevision === revision) status.removeAttribute('aria-busy');
        }
    }

    function scheduleCheck(event) {
        revision++;
        controller?.abort();
        clearTimeout(timer);
        status.removeAttribute('aria-busy');
        show('');
        alternatives.replaceChildren();
        if (fields.some((field) => !field.disabled && (!field.value || !field.validity.valid))) return;
        show('Checking schedule availability...', true);
        status.setAttribute('aria-busy', 'true');
        const currentRevision = revision;
        timer = setTimeout(() => check(currentRevision), event?.type === 'input' ? 250 : 0);
    }

    fields.forEach((field) => {
        field.addEventListener('input', scheduleCheck);
        field.addEventListener('change', scheduleCheck);
    });
    window.addEventListener('pageshow', scheduleCheck);
    scheduleCheck();
});
