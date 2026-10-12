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
    let view = 'year';
    let events = [];
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
    const message = find('[data-calendar-message]');
    const errors = find('[data-schedule-errors]');
    const canManage = root.dataset.canManage === '1';
    const csrf = form.elements._token.value;

    function range() {
        if (view === 'year') return [new Date(selected.getFullYear(), 0, 1, 12), new Date(selected.getFullYear() + 1, 0, 1, 12)];
        if (view === 'month') return [new Date(selected.getFullYear(), selected.getMonth(), 1, 12), new Date(selected.getFullYear(), selected.getMonth() + 1, 1, 12)];
        const start = addDays(selected, -((selected.getDay() + 6) % 7));
        return [start, addDays(start, 7)];
    }

    async function refresh(quiet = false) {
        const id = ++requestId;
        feedController?.abort();
        feedController = new AbortController();
        const [from, to] = range();
        const params = new URLSearchParams(new FormData(filters));
        params.set('from', dateKey(from));
        params.set('to', dateKey(to));
        params.set('page', page);
        if (!quiet) message.textContent = 'Loading schedules…';
        grid.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(`${root.dataset.feed}?${params}`, { headers: { Accept: 'application/json' }, signal: feedController.signal });
            const payload = await readResponse(response);
            if (id !== requestId) return;
            today = payload.today;
            events = payload.events;
            total = payload.upcoming_total;
            if (page > Math.max(1, Math.ceil(total / 20))) {
                page = Math.max(1, Math.ceil(total / 20));
                return refresh();
            }
            Object.entries(payload.summary).forEach(([key, value]) => { find(`[data-summary="${key}"]`).textContent = value; });
            renderCalendar();
            renderAgenda(payload.upcoming);
            message.textContent = `${events.length} matching entries in this view · Updated ${new Date().toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })}`;
            find('[data-calendar-retry]').hidden = true;
        } catch (error) {
            if (error.name === 'AbortError') return;
            message.textContent = `Could not refresh schedules. ${error.message} Previously loaded data may be out of date.`;
            find('[data-calendar-retry]').hidden = false;
        } finally {
            if (id === requestId) grid.setAttribute('aria-busy', 'false');
        }
    }

    async function readResponse(response) {
        if ([401, 419].includes(response.status) || response.redirected) throw new Error('Your session expired. Sign in again or reload this page.');
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join('\n') : (payload.message || 'The request failed. Please try again.'));
        return payload;
    }

    const onDay = (date) => {
        const start = dateKey(date) + 'T00:00';
        const end = dateKey(addDays(date, 1)) + 'T00:00';
        return events.filter((event) => event.starts_at < end && event.ends_at > start);
    };

    function dayButton(date, compact = false) {
        const dayEvents = onDay(date);
        const node = button(String(date.getDate()), 'calendar-day', () => {
            if (canManage) openCreate(dateKey(date));
            else { selected = date; view = 'week'; refresh(); }
        });
        node.setAttribute('aria-label', `${readableDate(dateKey(date))}, ${dayEvents.length} events. ${canManage ? 'Add schedule' : 'View week'}`);
        if (dateKey(date) === today) node.setAttribute('aria-current', 'date');
        if (compact) {
            const dots = element('span', 'calendar-dots');
            [...new Set(dayEvents.map((event) => event.display_status))].forEach((status) => dots.append(element('span', `calendar-dot calendar-status-${status}`)));
            dots.setAttribute('aria-hidden', 'true');
            node.append(dots);
        }
        return node;
    }

    function eventButton(event) {
        const node = button(`${time(event.starts_at)} · ${event.title}`, `calendar-event calendar-status-${event.display_status}`, () => openDetails(event));
        node.setAttribute('aria-label', `${event.title}, ${event.display_status}, ${readableDate(event.starts_at)}, ${time(event.starts_at)} to ${time(event.ends_at)}`);
        return node;
    }

    function monthGrid(month, compact) {
        const days = element('div', compact ? 'calendar-days' : 'calendar-days calendar-month');
        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach((day) => days.append(element('span', 'calendar-weekday', day)));
        const offset = (month.getDay() + 6) % 7;
        for (let index = 0; index < offset; index++) days.append(element('div', compact ? '' : 'calendar-cell'));
        const last = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
        for (let day = 1; day <= last; day++) {
            const date = new Date(month.getFullYear(), month.getMonth(), day, 12);
            if (compact) days.append(dayButton(date, true));
            else {
                const cell = element('div', 'calendar-cell');
                cell.append(dayButton(date));
                onDay(date).forEach((event) => cell.append(eventButton(event)));
                days.append(cell);
            }
        }
        return days;
    }

    function renderCalendar() {
        grid.replaceChildren();
        const [start, end] = range();
        find('[data-calendar-heading]').textContent = view === 'year' ? selected.getFullYear() : view === 'month' ? `${monthName(selected)} ${selected.getFullYear()}` : `${readableDate(dateKey(start))} – ${readableDate(dateKey(addDays(end, -1)))}`;
        find('[data-calendar-prev]').setAttribute('aria-label', `Previous ${view}`);
        find('[data-calendar-next]').setAttribute('aria-label', `Next ${view}`);
        find('[data-calendar-prev]').disabled = start.getFullYear() <= 1900;
        find('[data-calendar-next]').disabled = selected.getFullYear() >= 2100;
        root.querySelectorAll('[data-calendar-view]').forEach((node) => node.setAttribute('aria-pressed', String(node.dataset.calendarView === view)));
        if (view === 'year') {
            const year = element('div', 'calendar-year');
            for (let month = 0; month < 12; month++) {
                const date = new Date(selected.getFullYear(), month, 1, 12);
                const card = element('section', 'calendar-mini');
                card.append(button(monthName(date), 'calendar-mini-heading', () => { selected = date; view = 'month'; refresh(); }));
                card.append(monthGrid(date, true));
                const count = events.filter((event) => event.starts_at < dateKey(new Date(selected.getFullYear(), month + 1, 1, 12)) + 'T00:00' && event.ends_at > dateKey(date) + 'T00:00').length;
                card.append(element('p', 'mt-2 text-xs text-muted', `${count} entries · Select month to view events`));
                year.append(card);
            }
            grid.append(year);
        } else if (view === 'month') {
            const scroll = element('div', 'calendar-month-scroll');
            scroll.append(monthGrid(start, false));
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
        form.hidden = false;
        errors.hidden = true;
        find('[data-schedule-details]').replaceChildren();
        find('[data-schedule-details]').hidden = true;
        find('#schedule-dialog-title').textContent = 'Add schedule';
        form.elements.starts_at.value = `${day}T09:00`;
        form.elements.ends_at.value = `${day}T10:00`;
        form.elements.category.value = 'Meeting';
        find('[data-recurrence-fields]').hidden = false;
        find('[data-recurrence-fields]').disabled = false;
        find('[data-occurrence-notice]').hidden = true;
        updateRecurrence();
        showDialog();
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
        const actions = element('div', 'flex flex-wrap gap-2');
        if (event.edit_url) {
            actions.append(button('Edit / Reschedule', 'admin-button', () => editEvent()));
            if (event.status !== 'cancelled') actions.append(button('Cancel event', 'admin-secondary', () => { editEvent(); form.elements.status.value = 'cancelled'; }));
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
        errors.hidden = true;
        find('[data-schedule-details]').hidden = true;
        form.hidden = false;
        find('#schedule-dialog-title').textContent = 'Edit schedule';
        ['title', 'starts_at', 'ends_at', 'location', 'department', 'category', 'description', 'notes', 'personnel_id', 'status'].forEach((name) => { form.elements[name].value = currentEvent[name] ?? ''; });
        find('[data-recurrence-fields]').hidden = true;
        find('[data-recurrence-fields]').disabled = true;
        find('[data-occurrence-notice]').hidden = !currentEvent.series_id;
        form.elements.title.focus();
    }

    function updateRecurrence() {
        const repeats = form.elements.recurrence.value !== 'none';
        find('[data-recurrence-until]').hidden = !repeats;
        form.elements.recurrence_until.required = repeats;
        form.elements.recurrence_until.disabled = !repeats;
    }

    function setBusy(value) {
        busy = value;
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
        setBusy(true);
        errors.hidden = true;
        const payload = Object.fromEntries(new FormData(form));
        delete payload._token;
        if (currentEvent) payload.version = currentEvent.version;
        try {
            const result = await mutate(currentEvent?.edit_url || root.dataset.store, currentEvent ? 'PUT' : 'POST', payload);
            dialog.close();
            const category = payload.category;
            if (![...filters.elements.category.options].some((option) => option.value === category)) filters.elements.category.add(new Option(category, category));
            await refresh();
            message.textContent = result.message + ' ' + message.textContent;
        } catch (error) {
            errors.textContent = error.message;
            errors.hidden = false;
            errors.focus();
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
    dialog.addEventListener('close', () => lastTrigger?.isConnected && lastTrigger.focus());
    form.elements.recurrence.addEventListener('change', updateRecurrence);
    document.querySelector('[data-calendar-add]')?.addEventListener('click', () => openCreate(dateKey(selected)));
    root.querySelectorAll('[data-calendar-view]').forEach((node) => node.addEventListener('click', () => { view = node.dataset.calendarView; refresh(); }));
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
