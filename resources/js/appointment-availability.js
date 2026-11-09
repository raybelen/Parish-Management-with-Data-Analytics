document.querySelectorAll('form[data-availability-url]').forEach((form) => {
    const date = form.elements.namedItem('preferred_date');
    const time = form.elements.namedItem('preferred_time');
    const venue = form.elements.namedItem('preferred_church');
    const service = form.elements.namedItem('service_type');
    const status = form.querySelector('[data-availability-status]');
    if (!date || !time || !status) return;

    const timeButtons = [...form.querySelectorAll('[data-time-value]')];
    const timeHint = form.querySelector('[data-time-availability-hint]');
    const scheduleSummary = form.querySelector('[data-schedule-summary]');
    const mode = form.elements.namedItem('baptism_mode');
    const session = form.elements.namedItem('group_baptism_session_id');
    const fields = [date, time, venue, service, mode, session].filter(Boolean);
    const alternatives = document.createElement('div');
    alternatives.className = 'sm:col-span-2 flex flex-wrap gap-3';
    status.after(alternatives);
    let controller;
    let timer;
    let revision = 0;
    let checkedContext;
    let availableTimes = new Set();
    const scheduleContext = () => JSON.stringify(fields.filter(field => field !== time).map(field => [field.name, field.value, field.disabled]));
    time.setAttribute('aria-describedby', [time.getAttribute('aria-describedby'), status.id].filter(Boolean).join(' '));

    const show = (message, invalid = false) => {
        status.textContent = message;
        status.hidden = !message;
        time.setCustomValidity(invalid ? message : '');
        time.setAttribute('aria-invalid', String(invalid));
    };

    const updateScheduleSummary = (available = false) => {
        if (!scheduleSummary) return;
        const complete = fields.every(field => field.disabled || (field.value && field.validity.valid));
        scheduleSummary.hidden = !available || !complete;
        if (scheduleSummary.hidden) {
            scheduleSummary.textContent = '';
            return;
        }
        const formattedDate = new Intl.DateTimeFormat('en-PH', {dateStyle: 'full'}).format(new Date(`${date.value}T12:00:00`));
        const formattedTime = new Intl.DateTimeFormat('en-PH', {hour: 'numeric', minute: '2-digit'}).format(new Date(`2000-01-01T${time.value}:00`));
        scheduleSummary.textContent = `Preferred schedule: ${formattedDate} at ${formattedTime}. Final availability is subject to parish confirmation.`;
    };

    const disableTimeButtons = (message) => {
        timeButtons.forEach((button) => {
            button.disabled = true;
            button.title = message;
        });
        if (timeHint) timeHint.textContent = message;
    };

    async function check(currentRevision, suggest = false) {
        updateScheduleSummary();
        controller = new AbortController();
        const context = scheduleContext();
        const values = Object.fromEntries(fields.filter(field => !field.disabled).map((field) => [field.name, field.value]));
        try {
            const response = await fetch(form.dataset.availabilityUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': form.elements.namedItem('_token').value,
                },
                body: JSON.stringify({...values, preferred_time: values.preferred_time || timeButtons[0]?.dataset.timeValue, suggest, ...(timeButtons.length ? {times: timeButtons.map(button => button.dataset.timeValue)} : {})}),
                signal: controller.signal,
            });
            const payload = await response.json();
            if (currentRevision !== revision) return;
            if (response.status === 422) {
                disableTimeButtons('Please check the selected schedule.');
                show(Object.values(payload.errors || {}).flat().join(' ') || 'Please check the selected schedule.', true);
                return;
            }
            if (!response.ok || typeof payload.available !== 'boolean') throw new Error('Availability check failed');
            if (timeButtons.length) {
                if (!Array.isArray(payload.time_slots) || timeButtons.some(button => !payload.time_slots.some(slot => slot.time === button.dataset.timeValue && typeof slot.available === 'boolean'))) {
                    throw new Error('Time availability is missing');
                }
                timeButtons.forEach((button) => {
                    const slot = payload.time_slots.find(slot => slot.time === button.dataset.timeValue);
                    button.disabled = !slot.available;
                    button.title = slot.available ? 'Available' : slot.message || 'Unavailable';
                });
                if (timeHint) timeHint.textContent = timeButtons.every(button => button.disabled)
                    ? 'No suggested times available. Try another date or venue.'
                    : 'Unavailable times are disabled.';
            }
            checkedContext = context;
            availableTimes = new Set([
                ...(payload.time_slots || []).filter(slot => slot.available).map(slot => slot.time),
                ...(payload.alternatives || []).map(option => option.time),
            ]);
            show(time.value && !payload.available ? payload.message : '', Boolean(time.value) && !payload.available);
            updateScheduleSummary(payload.available);
            alternatives.replaceChildren();
            if (time.value && !payload.available && !suggest && (!session || session.disabled)) {
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
                button.addEventListener('click', () => { time.value = option.time; time.dispatchEvent(new CustomEvent('change', {bubbles: true, detail: {availabilityChecked: true}})); });
                alternatives.append(button);
            });
            if (suggest && !payload.alternatives?.length) alternatives.textContent = 'No alternative times are available at this venue on this date. Try another date or venue.';
        } catch (error) {
            if (error.name === 'AbortError' || currentRevision !== revision) return;
            show('Availability could not be checked right now. It will be checked again when you submit.');
            if (timeButtons.length) {
                disableTimeButtons('Unable to check suggested times. Retry or enter a time below.');
                alternatives.replaceChildren();
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'text-link';
                retry.textContent = 'Retry availability check';
                retry.addEventListener('click', scheduleCheck);
                alternatives.append(retry);
            }
        } finally {
            if (currentRevision === revision) status.removeAttribute('aria-busy');
        }
    }

    function scheduleCheck(event) {
        updateScheduleSummary();
        if (event?.target === time && event.detail?.availabilityChecked
            && checkedContext === scheduleContext() && availableTimes.has(time.value)) {
            revision++;
            controller?.abort();
            clearTimeout(timer);
            status.removeAttribute('aria-busy');
            show('');
            updateScheduleSummary(true);
            alternatives.replaceChildren();
            return;
        }

        checkedContext = undefined;
        availableTimes.clear();
        revision++;
        controller?.abort();
        clearTimeout(timer);
        status.removeAttribute('aria-busy');
        show('');
        alternatives.replaceChildren();
        disableTimeButtons('Choose a service, date, and venue first.');
        if (fields.some((field) => !field.disabled && !(field === time && timeButtons.length && !time.value) && (!field.value || !field.validity.valid))) return;
        disableTimeButtons('Checking available times...');
        show('Checking schedule availability...', Boolean(time.value));
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
