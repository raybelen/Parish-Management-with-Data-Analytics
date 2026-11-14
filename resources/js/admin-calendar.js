import '../css/admin-calendar.css';

const root = document.querySelector('[data-admin-calendar]');
if (root) initializeCalendar(root);

function initializeCalendar(root) {
    const find = (selector) => root.querySelector(selector);
    const dateKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const parseDay = (value) => new Date(`${value.slice(0, 10)}T12:00:00`);
    const addDays = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days, 12);
    const monthName = (date) => date.toLocaleDateString('en', { month: 'long' });
    const readableDate = (value) => parseDay(value).toLocaleDateString('en', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    const time = (value) => new Date(value).toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' });
    const titleCase = (value) => value.charAt(0).toUpperCase() + value.slice(1);
    const isMarkedEvent = (event) => ['Appointment', 'Schedule'].includes(event.kind);
    const element = (tag, className = '', text = '') => {
        const node = document.createElement(tag);
        node.className = className;
        node.textContent = text;
        return node;
    };
    const button = (text, className, action) => {
        const node = element('button', className, text);
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    };
    let today = root.dataset.today;
    let selected = parseDay(today);
    let view = 'month';
    let events = [];
    let dayEventsCache = new Map();
    let loading = false;
    let renderedCalendar = '';
    let page = 1;
    let total = 0;
    let requestId = 0;
    let currentEvent = null;
    let busy = false;
    let lastTrigger = null;
    let feedController;
    const dialog = find('[data-schedule-dialog]');
    const deletion = find('[data-delete-dialog]');
    const form = find('[data-schedule-form]');
    const filters = find('[data-calendar-filters]');
    const grid = find('[data-calendar-grid]');
    const skeleton = find('[data-calendar-skeleton]');
    const message = find('[data-calendar-message]');
    const errors = find('[data-schedule-errors]');
    const canManage = root.dataset.canManage === '1';
    const csrf = form.elements._token.value;
    const priestRequired = form.querySelector('input[type="checkbox"][name="priest_required"]');
    const registration = form.querySelector('input[type="checkbox"][name="group_booking_open"]');
    const customCategory = find('[data-custom-category]');
    const locationInput = form.elements.location;
    const locationCombobox = find('[data-location-combobox]');
    const locationPopup = find('[data-location-popup]');
    const locationToggle = find('[data-location-toggle]');
    const locationOptions = [...form.querySelectorAll('[data-location-option]')];
    let activeLocation = null;
    const saveButton = find('[data-schedule-save]');
    let groupWasSelected = false;
    let activityRequiresPriest = false;
    let previousStart = '';
    let defaultedGroupTitle = false;
    const categoryValue = () => form.elements.category.value === '__custom__' ? customCategory.value.trim() : form.elements.category.value;
    const isGroupSession = () => ['group baptism', 'group wedding', 'group confirmation'].includes(categoryValue().toLowerCase());

    function range() {
        if (view === 'year') return [new Date(selected.getFullYear(), 0, 1, 12), new Date(selected.getFullYear() + 1, 0, 1, 12)];
        if (view === 'month') {
            const first = new Date(selected.getFullYear(), selected.getMonth(), 1, 12);
            const next = new Date(selected.getFullYear(), selected.getMonth() + 1, 1, 12);
            return [addDays(first, -((first.getDay() + 6) % 7)), addDays(next, (7 - ((next.getDay() + 6) % 7)) % 7)];
        }
        const start = addDays(selected, -((selected.getDay() + 6) % 7));
        return [start, addDays(start, 7)];
    }

    async function refresh(quiet = false) {
        if (quiet && loading) return;
        loading = true;
        const id = ++requestId;
        feedController?.abort();
        feedController = new AbortController();
        const [from, to] = range();
        const params = new URLSearchParams(new FormData(filters));
        params.set('from', dateKey(from));
        params.set('to', dateKey(to));
        params.set('page', page);
        if (!quiet) {
            message.textContent = 'Loading schedules…';
            skeleton.hidden = false;
            grid.hidden = true;
        }
        grid.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(`${root.dataset.feed}?${params}`, { headers: { Accept: 'application/json' }, signal: feedController.signal });
            const payload = await readResponse(response);
            if (id !== requestId) return;
            today = payload.today;
            events = payload.events;
            dayEventsCache = new Map();
            total = payload.upcoming_total;
            if (page > Math.max(1, Math.ceil(total / 20))) {
                page = Math.max(1, Math.ceil(total / 20));
                return refresh();
            }
            Object.entries(payload.summary).forEach(([key, value]) => { find(`[data-summary="${key}"]`).textContent = value; });
            const calendarSignature = JSON.stringify([view, dateKey(selected), today, events]);
            if (calendarSignature !== renderedCalendar) {
                renderCalendar();
                renderedCalendar = calendarSignature;
            }
            renderAgenda(payload.upcoming);
            const markedCount = events.filter(isMarkedEvent).length;
            message.textContent = `${markedCount} appointments and staff activities marked in this view · Updated ${new Date().toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })}`;
            find('[data-calendar-retry]').hidden = true;
        } catch (error) {
            if (error.name === 'AbortError') return;
            message.textContent = `Could not refresh schedules. ${error.message} Previously loaded data may be out of date.`;
            find('[data-calendar-retry]').hidden = false;
        } finally {
            if (id === requestId) {
                loading = false;
                grid.setAttribute('aria-busy', 'false');
                if (!quiet) {
                    skeleton.hidden = true;
                    grid.hidden = false;
                }
            }
        }
    }

    async function readResponse(response) {
        if ([401, 419].includes(response.status) || response.redirected) throw new Error('Your session expired. Sign in again or reload this page.');
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(payload.errors ? Object.values(payload.errors).flat().join('\n') : (payload.message || 'The request failed. Please try again.'));
            error.fields = payload.errors || {};
            throw error;
        }
        return payload;
    }

    const onDay = (date) => {
        const key = dateKey(date);
        if (dayEventsCache.has(key)) return dayEventsCache.get(key);
        const start = dateKey(date) + 'T00:00';
        const end = dateKey(addDays(date, 1)) + 'T00:00';
        const items = events.filter((event) => event.starts_at < end && event.ends_at > start);
        dayEventsCache.set(key, items);
        return items;
    };

    function dayButton(date, compact = false) {
        const dayEvents = onDay(date);
        const markedEvents = dayEvents.filter(isMarkedEvent);
        const node = button(String(date.getDate()), 'calendar-day', () => {
            if (canManage) openCreate(dateKey(date));
            else { selected = date; view = 'week'; refresh(); }
        });
        node.setAttribute('aria-label', `${readableDate(dateKey(date))}, ${markedEvents.length} appointments or staff activities marked, ${dayEvents.length} total events. ${canManage ? 'Add schedule' : 'View week'}`);
        if (dateKey(date) === today) node.setAttribute('aria-current', 'date');
        if (compact && markedEvents.length) {
            const dots = element('span', 'calendar-dots');
            [...new Set(markedEvents.map((event) => event.display_status))].forEach((status) => dots.append(element('span', `calendar-dot calendar-status-${status}`)));
            dots.setAttribute('aria-hidden', 'true');
            node.append(dots);
        }
        return node;
    }

    function eventButton(event) {
        const node = button('', `calendar-event calendar-status-${event.display_status}`, () => openDetails(event));
        node.append(element('span', 'calendar-event-title', event.title), element('span', 'calendar-event-time', time(event.starts_at)));
        node.title = `${event.title} · ${time(event.starts_at)}`;
        node.setAttribute('aria-label', `${event.title}, ${event.display_status}, ${readableDate(event.starts_at)}, ${time(event.starts_at)} to ${time(event.ends_at)}`);
        return node;
    }

    function monthGrid(month, compact) {
        const days = element('div', compact ? 'calendar-days' : 'calendar-days calendar-month');
        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach((day) => days.append(element('span', 'calendar-weekday', day)));
        const offset = (month.getDay() + 6) % 7;
        if (!compact) {
            const [first, end] = range();
            for (let date = first; date < end; date = addDays(date, 1)) {
                const cell = element('div', `calendar-cell${date.getMonth() !== month.getMonth() ? ' calendar-cell-outside' : ''}`);
                cell.append(dayButton(date));
                const items = onDay(date);
                items.slice(0, 3).forEach((event) => cell.append(eventButton(event)));
                if (items.length > 3) {
                    const overflow = element('details', 'calendar-overflow');
                    overflow.append(element('summary', '', `${items.length - 3} more…`));
                    items.slice(3).forEach((event) => overflow.append(eventButton(event)));
                    cell.append(overflow);
                }
                days.append(cell);
            }
            return days;
        }
        for (let index = 0; index < offset; index++) days.append(element('div'));
        const last = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
        for (let day = 1; day <= last; day++) {
            const date = new Date(month.getFullYear(), month.getMonth(), day, 12);
            days.append(dayButton(date, true));
        }
        return days;
    }

    function renderCalendar() {
        grid.replaceChildren();
        const [start, end] = range();
        find('[data-calendar-heading]').textContent = view === 'year' ? selected.getFullYear() : view === 'month' ? `${monthName(selected)} ${selected.getFullYear()}` : `${readableDate(dateKey(start))} – ${readableDate(dateKey(addDays(end, -1)))}`;
        find('[data-calendar-range]').textContent = `${readableDate(dateKey(start))} – ${readableDate(dateKey(addDays(end, -1)))}`;
        find('[data-calendar-view-select]').value = view;
        find('[data-calendar-prev]').setAttribute('aria-label', `Previous ${view}`);
        find('[data-calendar-next]').setAttribute('aria-label', `Next ${view}`);
        find('[data-calendar-prev]').disabled = start.getFullYear() <= 1900;
        find('[data-calendar-next]').disabled = selected.getFullYear() >= 2100;
        if (view === 'year') {
            const year = element('div', 'calendar-year');
            for (let month = 0; month < 12; month++) {
                const date = new Date(selected.getFullYear(), month, 1, 12);
                const card = element('section', 'calendar-mini');
                card.append(button(monthName(date), 'calendar-mini-heading', () => { selected = date; view = 'month'; refresh(); }));
                card.append(monthGrid(date, true));
                const count = events.filter((event) => isMarkedEvent(event) && event.starts_at < dateKey(new Date(selected.getFullYear(), month + 1, 1, 12)) + 'T00:00' && event.ends_at > dateKey(date) + 'T00:00').length;
                card.append(element('p', 'mt-2 text-xs text-muted', `${count} marked entries · Select month to view events`));
                year.append(card);
            }
            grid.append(year);
        } else if (view === 'month') {
            const scroll = element('div', 'calendar-month-scroll');
            scroll.append(monthGrid(new Date(selected.getFullYear(), selected.getMonth(), 1, 12), false));
            grid.append(scroll);
        } else {
            const week = element('div', 'calendar-week');
            for (let offset = 0; offset < 7; offset++) {
                const date = addDays(start, offset);
                const section = element('section', 'calendar-week-section');
                section.append(element('h3', 'text-sm font-semibold', date.toLocaleDateString('en', { weekday: 'long', month: 'short' })), dayButton(date));
                const items = onDay(date);
                if (!items.length) section.append(element('p', 'mt-3 text-xs text-muted', 'No schedules'));
                items.forEach((event) => section.append(eventButton(event)));
                week.append(section);
            }
            grid.append(week);
        }
    }

    function renderAgenda(items) {
        const agenda = find('[data-calendar-agenda]');
        agenda.replaceChildren();
        find('[data-agenda-count]').textContent = `${total} matching schedules`;
        find('[data-agenda-page]').textContent = `Page ${page} of ${Math.max(1, Math.ceil(total / 20))}`;
        find('[data-agenda-prev]').disabled = page <= 1;
        find('[data-agenda-next]').disabled = page * 20 >= total;
        if (!items.length) agenda.append(element('p', 'py-6 text-center text-muted', 'No upcoming schedules match these filters.'));
        const weekEnd = dateKey(addDays(parseDay(today), 8));
        items.forEach((event) => {
            const item = element('article', `calendar-agenda-item calendar-status-${event.display_status}`);
            const isToday = event.starts_at < dateKey(addDays(parseDay(today), 1)) + 'T00:00' && event.ends_at > today + 'T00:00';
            const soon = isToday ? 'Today' : event.starts_at.slice(0, 10) < weekEnd ? 'Next 7 days' : '';
            item.dataset.soon = isToday ? 'today' : soon ? 'week' : '';
            const heading = element('div', 'flex flex-wrap items-center justify-between gap-2');
            heading.append(button(event.title, 'calendar-agenda-title', () => openDetails(event)), element('span', 'calendar-status', titleCase(event.display_status)));
            item.append(heading);
            if (soon) item.append(element('p', 'mt-1 text-xs font-semibold', soon));
            const meta = element('div', 'calendar-agenda-meta');
            [readableDate(event.starts_at), `${time(event.starts_at)} – ${event.ends_at.slice(0, 10) !== event.starts_at.slice(0, 10) ? readableDate(event.ends_at) + ' ' : ''}${time(event.ends_at)}`, event.location || 'Location not set', event.personnel || event.department || 'Unassigned', event.category].forEach((text) => meta.append(element('span', '', text)));
            if (event.personnel && event.department) meta.append(element('span', '', event.department));
            item.append(meta);
            agenda.append(item);
        });
    }

    function showDialog() {
        lastTrigger = document.activeElement;
        dialog.showModal();
    }

    function openCreate(day) {
        currentEvent = null;
        form.reset();
        closeLocations();
        clearFormErrors();
        form.querySelectorAll('details').forEach(node => { node.open = false; });
        groupWasSelected = false;
        activityRequiresPriest = false;
        defaultedGroupTitle = false;
        form.hidden = false;
        errors.hidden = true;
        find('[data-schedule-details]').replaceChildren();
        find('[data-schedule-details]').hidden = true;
        find('#schedule-dialog-title').textContent = 'Add schedule';
        form.elements.starts_at.value = `${day}T09:00`;
        form.elements.ends_at.value = `${day}T10:00`;
        previousStart = form.elements.starts_at.value;
        form.elements.category.value = '';
        find('[data-recurrence-fields]').hidden = false;
        find('[data-recurrence-fields]').disabled = false;
        find('[data-occurrence-notice]').hidden = true;
        updateRecurrence();
        updateFormOptions();
        showDialog();
        dialog.scrollTop = 0;
        form.elements.category.focus({preventScroll: true});
    }

    function openDetails(event) {
        currentEvent = event;
        form.hidden = true;
        errors.hidden = true;
        const details = find('[data-schedule-details]');
        details.hidden = false;
        details.replaceChildren();
        find('#schedule-dialog-title').textContent = event.title;
        details.append(element('p', `calendar-status calendar-status-${event.display_status}`, `${titleCase(event.display_status)} · ${event.kind}`));
        if (event.priest_required) {
            const assignment = event.assignment_status === 'needs_reassignment' ? 'Needs priest reassignment' : event.assignment_status === 'substitute' ? 'Substitute priest assigned' : 'Priest assigned';
            details.append(element('p', event.assignment_status === 'needs_reassignment' ? 'calendar-errors' : 'text-sm font-semibold', assignment));
        }
        [
            ['Date & time', `${readableDate(event.starts_at)}, ${time(event.starts_at)} – ${readableDate(event.ends_at)}, ${time(event.ends_at)}`],
            ['Location', event.location || 'Not set'], ['Assigned personnel', event.personnel || 'Unassigned'],
            ['Department', event.department || 'Not set'], ['Category', event.category], ['Description', event.description || 'No description'], ['Notes', event.notes || 'No notes'],
        ].forEach(([label, text]) => {
            const row = element('div');
            row.append(element('p', 'text-xs font-semibold text-muted', label), element('p', 'mt-1 whitespace-pre-wrap break-words text-sm', text));
            details.append(row);
        });
        if (event.series_id) details.append(element('p', 'text-sm text-muted', `${titleCase(event.recurrence)} series through ${event.recurrence_until}. Actions affect this occurrence only.`));
        if (['Group Baptism', 'Group Wedding', 'Group Confirmation'].includes(event.category)) {
            details.append(element('p', 'text-sm font-semibold', `${event.group_booking_open ? 'Open for bookings' : 'Registration closed'} · No participant limit`));
        }
        const actions = element('div', 'flex flex-wrap gap-2');
        if (event.edit_url) {
            actions.append(button('Edit / Reschedule', 'admin-button', () => editEvent()));
            if (event.status !== 'cancelled') actions.append(button('Cancel event', 'admin-secondary', () => { editEvent(); form.elements.status.value = 'cancelled'; find('[data-schedule-extra]').open = true; updateRegistration(); updatePreview(); }));
            actions.append(button('Delete', 'admin-secondary', () => { find('[data-delete-error]').hidden = true; deletion.showModal(); }));
        }
        if (event.detail_url) {
            const link = element('a', 'admin-button', event.kind === 'Appointment' ? 'Manage appointment' : 'Manage unavailable time');
            link.href = event.detail_url;
            actions.append(link);
        }
        details.append(actions);
        showDialog();
    }

    function editEvent() {
        form.reset();
        closeLocations();
        clearFormErrors();
        groupWasSelected = false;
        activityRequiresPriest = Boolean(currentEvent.priest_required);
        defaultedGroupTitle = false;
        errors.hidden = true;
        find('[data-schedule-details]').hidden = true;
        form.hidden = false;
        find('#schedule-dialog-title').textContent = 'Edit schedule';
        if (![...form.elements.category.options].some(option => option.value === currentEvent.category)) {
            form.elements.category.add(new Option(currentEvent.category, currentEvent.category));
        }
        ['title', 'starts_at', 'ends_at', 'location', 'department', 'category', 'description', 'notes', 'personnel_id', 'status'].forEach((name) => { form.elements[name].value = currentEvent[name] ?? ''; });
        previousStart = form.elements.starts_at.value;
        form.querySelector('input[type="checkbox"][name="priest_required"]').checked = Boolean(currentEvent.priest_required);
        form.querySelector('input[type="checkbox"][name="group_booking_open"]').checked = Boolean(currentEvent.group_booking_open);
        form.elements.group_fee.value = currentEvent.group_fee ?? '';
        updateFormOptions(false);
        find('[data-schedule-extra]').open = Boolean(currentEvent.description || currentEvent.notes || currentEvent.department || currentEvent.status !== 'scheduled');
        find('[data-recurrence-fields]').hidden = true;
        find('[data-recurrence-fields]').disabled = true;
        find('[data-occurrence-notice]').hidden = !currentEvent.series_id;
        updatePreview();
        dialog.scrollTop = 0;
        form.elements.title.focus({preventScroll: true});
    }

    function updateRecurrence() {
        const repeats = form.elements.recurrence.value !== 'none';
        find('[data-recurrence-until]').hidden = !repeats;
        form.elements.recurrence_until.required = repeats;
        form.elements.recurrence_until.disabled = !repeats;
        find('[data-recurrence-summary]').textContent = form.elements.recurrence.selectedOptions[0].textContent;
        if (repeats) find('[data-recurrence-fields] details').open = true;
        updatePreview();
    }

    function updateFormOptions(adjustEnd = true) {
        const custom = form.elements.category.value === '__custom__';
        find('[data-custom-category-field]').hidden = !custom;
        customCategory.disabled = !custom;
        customCategory.required = custom;
        const isGroup = isGroupSession();
        const settings = find('[data-group-session-settings]');
        settings.hidden = !isGroup;
        settings.disabled = !isGroup;
        if (isGroup && !groupWasSelected) {
            activityRequiresPriest = priestRequired.checked;
            priestRequired.checked = true;
        } else if (!isGroup && groupWasSelected) {
            priestRequired.checked = activityRequiresPriest;
            if (defaultedGroupTitle) form.elements.title.value = '';
            defaultedGroupTitle = false;
        }
        groupWasSelected = isGroup;
        find('[data-priest-required-control]').hidden = isGroup;
        updateLocation();
        form.elements.title.placeholder = isGroup ? categoryValue() : 'e.g. Parish council meeting';
        if (isGroup && !form.elements.title.value) {
            form.elements.title.value = categoryValue();
            defaultedGroupTitle = true;
        }
        updateDuration(adjustEnd);
        updateRegistration();
        updatePreview();
    }

    function updateLocation() {
        const isGroup = isGroupSession();
        locationInput.required = isGroup;
        find('[data-group-location-required]').hidden = !isGroup;
        find('[data-schedule-location-help]').textContent = isGroup ? 'Choose a listed church or chapel for the group session. Type to search the list.' : 'Optional. Choose a parish venue or type another location in this field.';
        closeLocations();
    }

    function activateLocation(option) {
        activeLocation = option;
        locationOptions.forEach(item => item.setAttribute('aria-selected', String(item === option)));
        if (option) {
            locationInput.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
        } else {
            locationInput.removeAttribute('aria-activedescendant');
        }
    }

    function closeLocations() {
        locationPopup.hidden = true;
        locationInput.setAttribute('aria-expanded', 'false');
        locationToggle.setAttribute('aria-expanded', 'false');
        locationToggle.setAttribute('aria-label', 'Show locations');
        activateLocation(null);
    }

    function showLocations(filter = '') {
        if (busy) return;
        const query = filter.trim().toLowerCase();
        locationOptions.forEach(option => { option.hidden = !option.dataset.value.toLowerCase().includes(query); });
        const empty = find('[data-location-empty]');
        empty.hidden = locationOptions.some(option => !option.hidden);
        empty.textContent = isGroupSession() ? 'No matching venues. Choose a listed church or chapel for the group session.' : 'No matching parish venues. You can keep the location you typed.';
        locationPopup.hidden = false;
        locationInput.setAttribute('aria-expanded', 'true');
        locationToggle.setAttribute('aria-expanded', 'true');
        locationToggle.setAttribute('aria-label', 'Hide locations');
        activateLocation(null);
    }

    function selectLocation(option) {
        locationInput.value = option.dataset.value;
        locationInput.dispatchEvent(new Event('input', { bubbles: true }));
        locationInput.dispatchEvent(new Event('change', { bubbles: true }));
        closeLocations();
        locationInput.focus({ preventScroll: true });
    }

    function updateDuration(adjustEnd = true) {
        const fixedHour = isGroupSession() || priestRequired.checked;
        const start = new Date(form.elements.starts_at.value);
        if (adjustEnd && fixedHour && Number.isFinite(start.getTime())) {
            const end = new Date(start.getTime() + 60 * 60 * 1000);
            form.elements.ends_at.value = `${dateKey(end)}T${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;
        }
        form.elements.ends_at.readOnly = fixedHour;
        form.elements.ends_at.min = form.elements.starts_at.value;
        form.elements.recurrence_until.min = form.elements.starts_at.value.slice(0, 10);
        const end = new Date(form.elements.ends_at.value);
        const duration = (end - start) / 60000;
        form.elements.ends_at.setCustomValidity(Number.isFinite(duration) && duration <= 0 ? 'The end must be after the start.' : '');
        find('[data-schedule-duration]').textContent = fixedHour
            ? 'One-hour service · End time is set automatically. An available priest is assigned automatically.'
            : Number.isFinite(duration) && duration > 0 ? `Duration: ${duration >= 60 ? `${Math.floor(duration / 60)}h ` : ''}${duration % 60 ? `${duration % 60}m` : ''}`.trim() : 'Choose an end time after the start.';
        form.elements.personnel_id.disabled = fixedHour && !currentEvent;
        find('[data-schedule-personnel-help]').textContent = fixedHour
            ? currentEvent ? 'The automatic assignment keeps this priest when available.' : 'An available priest will be assigned when you save.'
            : 'Optional for activities that do not require a priest.';
    }

    function updateRegistration() {
        const scheduled = form.elements.status.value === 'scheduled';
        registration.disabled = !scheduled;
        find('[data-registration-help]').textContent = !scheduled
            ? 'Registration is closed for completed or cancelled activities.'
            : registration.checked ? 'Families can book this session as soon as you save.' : 'Registration stays closed. You can open it later by editing this schedule.';
    }

    function updatePreview() {
        const start = form.elements.starts_at.value;
        const end = form.elements.ends_at.value;
        const category = categoryValue();
        const preview = [category || 'Choose an activity'];
        if (start && Number.isFinite(new Date(start).getTime())) preview.push(`${readableDate(start)} · ${time(start)}`);
        if (end && Number.isFinite(new Date(end).getTime())) preview.push(`to ${end.slice(0, 10) !== start.slice(0, 10) ? readableDate(end) + ' ' : ''}${time(end)}`);
        if (isGroupSession()) preview.push(form.elements.status.value === 'scheduled' && registration.checked ? 'Registration opens on save' : 'Registration closed');
        if (!currentEvent && form.elements.recurrence.value !== 'none') preview.push(`${titleCase(form.elements.recurrence.value)} repeat`);
        find('[data-schedule-preview]').textContent = preview.join(' · ');
        if (!busy) saveButton.textContent = currentEvent ? 'Save changes' : isGroupSession() ? 'Create group session' : 'Create schedule';
    }

    function clearFormErrors() {
        errors.hidden = true;
        errors.textContent = '';
        form.querySelectorAll('[data-field-error]').forEach(node => node.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach(field => {
            field.removeAttribute('aria-invalid');
            const descriptions = (field.getAttribute('aria-describedby') || '').split(' ').filter(id => !id.startsWith('schedule-error-'));
            if (descriptions.length) field.setAttribute('aria-describedby', descriptions.join(' '));
            else field.removeAttribute('aria-describedby');
        });
    }

    function showFormErrors(error) {
        errors.textContent = error.message;
        errors.hidden = false;
        Object.entries(error.fields || {}).forEach(([name, messages]) => {
            const field = name === 'category' && form.elements.category.value === '__custom__' ? customCategory
                : form.querySelector(`[name="${CSS.escape(name)}"]:not([type="hidden"])`);
            if (!field || field.disabled) return;
            const detail = field.closest('details');
            if (detail) detail.open = true;
            const hint = element('span', 'text-xs text-red-700', messages.join(' '));
            hint.id = `schedule-error-${name}`;
            hint.dataset.fieldError = '';
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), hint.id].filter(Boolean).join(' '));
            field.after(hint);
        });
        errors.focus();
    }

    form.elements.category.addEventListener('change', () => updateFormOptions());
    customCategory.addEventListener('input', () => updateFormOptions());
    locationInput.addEventListener('input', () => showLocations(locationInput.value));
    locationInput.addEventListener('click', () => showLocations(locationInput.value));
    locationToggle.addEventListener('mousedown', event => event.preventDefault());
    locationToggle.addEventListener('click', () => {
        if (locationPopup.hidden) showLocations();
        else closeLocations();
        locationInput.focus({ preventScroll: true });
    });
    locationOptions.forEach(option => {
        option.addEventListener('mousedown', event => event.preventDefault());
        option.addEventListener('click', () => { if (!busy) selectLocation(option); });
    });
    locationInput.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (locationPopup.hidden) showLocations();
            const visible = locationOptions.filter(option => !option.hidden);
            if (!visible.length) return;
            const index = visible.indexOf(activeLocation);
            const next = index < 0 ? (event.key === 'ArrowDown' ? 0 : visible.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
            activateLocation(visible[next]);
        } else if (event.key === 'Enter' && !locationPopup.hidden && activeLocation) {
            event.preventDefault();
            selectLocation(activeLocation);
        } else if (event.key === 'Escape' && !locationPopup.hidden) {
            event.preventDefault();
            event.stopPropagation();
            closeLocations();
        } else if (event.key === 'Tab') closeLocations();
    });
    locationCombobox.addEventListener('focusout', event => {
        if (!locationCombobox.contains(event.relatedTarget)) closeLocations();
    });
    document.addEventListener('click', event => {
        if (!locationCombobox.contains(event.target)) closeLocations();
    });
    priestRequired.addEventListener('change', () => { updateDuration(); updatePreview(); });
    form.elements.starts_at.addEventListener('change', () => {
        const oldStart = new Date(previousStart);
        const start = new Date(form.elements.starts_at.value);
        const oldEnd = new Date(form.elements.ends_at.value);
        if (!isGroupSession() && !priestRequired.checked && Number.isFinite(start.getTime()) && oldEnd > oldStart) {
            const end = new Date(start.getTime() + (oldEnd - oldStart));
            form.elements.ends_at.value = `${dateKey(end)}T${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;
        }
        previousStart = form.elements.starts_at.value;
        updateDuration();
        updatePreview();
    });
    form.elements.title.addEventListener('input', () => { defaultedGroupTitle = false; });
    form.elements.ends_at.addEventListener('change', () => { updateDuration(false); updatePreview(); });
    registration.addEventListener('change', () => { updateRegistration(); updatePreview(); });
    form.elements.status.addEventListener('change', () => { updateRegistration(); updatePreview(); });
    form.addEventListener('input', () => { clearFormErrors(); updatePreview(); });
    form.addEventListener('invalid', event => { const detail = event.target.closest('details'); if (detail) detail.open = true; }, true);

    function setBusy(value) {
        busy = value;
        form.setAttribute('aria-busy', String(value));
        saveButton.textContent = value ? 'Saving…' : currentEvent ? 'Save changes' : isGroupSession() ? 'Create group session' : 'Create schedule';
        form.querySelectorAll('button').forEach((node) => { node.disabled = value; });
        deletion.querySelectorAll('button').forEach((node) => { node.disabled = value; });
        dialog.querySelector('[data-dialog-close]').disabled = value;
    }

    async function mutate(url, method, payload) {
        const response = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) });
        return readResponse(response);
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        closeLocations();
        clearFormErrors();
        setBusy(true);
        errors.hidden = true;
        const payload = Object.fromEntries(new FormData(form));
        delete payload._token;
        payload.category = categoryValue();
        payload.location = locationInput.value.trim();
        if (currentEvent) payload.version = currentEvent.version;
        try {
            const result = await mutate(currentEvent?.edit_url || root.dataset.store, currentEvent ? 'PUT' : 'POST', payload);
            dialog.close();
            const category = payload.category;
            if (![...filters.elements.category.options].some((option) => option.value === category)) filters.elements.category.add(new Option(category, category));
            await refresh();
            message.textContent = result.message + ' ' + message.textContent;
        } catch (error) {
            showFormErrors(error);
        } finally { setBusy(false); }
    });

    find('[data-delete-confirm]').addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        try {
            await mutate(currentEvent.edit_url, 'DELETE', { version: currentEvent.version });
            deletion.close();
            dialog.close();
            await refresh();
        } catch (error) {
            find('[data-delete-error]').textContent = error.message;
            find('[data-delete-error]').hidden = false;
        } finally { setBusy(false); }
    });
    find('[data-delete-dismiss]').addEventListener('click', () => deletion.close());
    root.querySelectorAll('[data-dialog-close]').forEach((node) => node.addEventListener('click', () => { if (!busy) dialog.close(); }));
    [dialog, deletion].forEach((node) => node.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); }));
    dialog.addEventListener('close', () => { closeLocations(); if (lastTrigger?.isConnected) lastTrigger.focus(); });
    form.elements.recurrence.addEventListener('change', updateRecurrence);
    document.querySelector('[data-calendar-add]')?.addEventListener('click', () => openCreate(dateKey(selected)));
    find('[data-calendar-view-select]').addEventListener('change', (event) => { view = event.target.value; refresh(); });
    function navigate(direction) {
        selected = view === 'year' ? new Date(selected.getFullYear() + direction, selected.getMonth(), 1, 12) : view === 'month' ? new Date(selected.getFullYear(), selected.getMonth() + direction, 1, 12) : addDays(selected, direction * 7);
        refresh();
    }
    find('[data-calendar-prev]').addEventListener('click', () => navigate(-1));
    find('[data-calendar-next]').addEventListener('click', () => navigate(1));
    find('[data-calendar-today]').addEventListener('click', () => { selected = parseDay(today); refresh(); });
    find('[data-calendar-retry]').addEventListener('click', () => refresh());
    find('[data-agenda-prev]').addEventListener('click', () => { page--; refresh(); });
    find('[data-agenda-next]').addEventListener('click', () => { page++; refresh(); });
    filters.addEventListener('submit', (event) => { event.preventDefault(); page = 1; refresh(); });
    filters.addEventListener('reset', () => { page = 1; setTimeout(() => refresh(), 0); });
    setInterval(() => { if (!document.hidden && !dialog.open && !busy) refresh(true); }, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !dialog.open && !busy) refresh(true); });
    refresh();
}
