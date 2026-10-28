import './admin-calendar';
import './appointment-details';

document.querySelectorAll('[data-forecast-form]').forEach((form) => {
    const button = form.querySelector('button');
    const progress = form.querySelector('[data-forecast-progress]');
    const originalLabel = button.textContent;
    form.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = 'Updating…';
        progress.classList.remove('hidden');
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = originalLabel;
        progress.classList.add('hidden');
        form.removeAttribute('aria-busy');
    });
});

document.querySelectorAll('[data-forecast-chart]').forEach((chart) => {
    const svg = chart.querySelector('[data-forecast-chart-svg]');
    const tooltip = chart.querySelector('[data-chart-tooltip]');
    const guide = chart.querySelector('[data-chart-guide]');
    const marker = chart.querySelector('[data-chart-marker]');
    const label = tooltip.querySelector('[data-tooltip-label]');
    const kind = tooltip.querySelector('[data-tooltip-kind]');
    const value = tooltip.querySelector('[data-tooltip-value]');
    const range = tooltip.querySelector('[data-tooltip-range]');

    const showTooltip = (point) => {
        const pointBounds = point.getBoundingClientRect();
        const chartBounds = chart.getBoundingClientRect();
        const centerX = pointBounds.left + pointBounds.width / 2 - chartBounds.left;
        const centerY = pointBounds.top + pointBounds.height / 2 - chartBounds.top;

        label.textContent = point.dataset.label;
        kind.textContent = point.dataset.kind;
        value.textContent = point.dataset.value;
        range.textContent = point.dataset.range ? `Possible range: ${point.dataset.range}` : '';
        range.classList.toggle('hidden', !point.dataset.range);
        tooltip.classList.remove('hidden');

        const viewportStart = chart.scrollLeft + 8;
        const viewportEnd = chart.scrollLeft + chart.clientWidth - tooltip.offsetWidth - 8;
        const left = Math.min(Math.max(centerX + chart.scrollLeft + 12, viewportStart), viewportEnd);
        const top = Math.max(centerY - tooltip.offsetHeight - 12, 8);
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;

        guide.setAttribute('x1', point.dataset.x);
        guide.setAttribute('x2', point.dataset.x);
        guide.setAttribute('visibility', 'visible');
        marker.setAttribute('cx', point.dataset.x);
        marker.setAttribute('cy', point.dataset.y);
        marker.setAttribute('stroke', point.dataset.kind === 'Expected total' ? '#967020' : '#13233f');
        marker.setAttribute('visibility', 'visible');
    };

    const hideTooltip = () => {
        tooltip.classList.add('hidden');
        guide.setAttribute('visibility', 'hidden');
        marker.setAttribute('visibility', 'hidden');
    };

    svg.querySelectorAll('[data-chart-point]').forEach((point) => {
        point.addEventListener('pointerenter', () => showTooltip(point));
        point.addEventListener('pointerleave', hideTooltip);
        point.addEventListener('focus', () => showTooltip(point));
        point.addEventListener('blur', hideTooltip);
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('.admin-mobile-menu').forEach((menu) => {
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            menu.open = false;
        }
    });
});

let activeDialogTrigger;
document.querySelectorAll('[data-dialog-target]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        const dialog = document.getElementById(trigger.dataset.dialogTarget);
        if (!dialog) return;

        activeDialogTrigger = trigger;
        dialog.showModal();
        document.body.style.overflow = 'hidden';
    });
});
document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        const outside = event.clientX < bounds.left
            || event.clientX > bounds.right
            || event.clientY < bounds.top
            || event.clientY > bounds.bottom;

        if (event.target === dialog && outside) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.body.style.overflow = '';
        activeDialogTrigger?.focus();
    });
});

const revealSettingsSection = () => {
    const section = document.getElementById(window.location.hash.slice(1));
    if (section?.matches('details.admin-card')) {
        section.open = true;
    }
};
window.addEventListener('hashchange', revealSettingsSection);
revealSettingsSection();

document.addEventListener('invalid', (event) => {
    let section = event.target.closest('details');
    while (section) {
        section.open = true;
        section = section.parentElement?.closest('details');
    }
}, true);

document.querySelectorAll('[data-walkin-service]').forEach((select) => {
    const update = () => document.querySelectorAll('[data-walkin-fields]').forEach((section) => {
        const active = section.dataset.walkinFields === select.value;
        section.hidden = !active;
        section.querySelectorAll('input, select, textarea').forEach((input) => {
            input.disabled = !active;
            if (input.dataset.required !== undefined) input.required = active && input.dataset.required === 'true';
        });
    });
    select.addEventListener('change', update);
    update();
});

document.querySelectorAll('[data-admin-group-session]').forEach((select) => {
    select.addEventListener('change', () => {
        const option = select.selectedOptions[0];
        select.form.elements.starts_at.value = option.dataset.start;
        select.form.elements.confirmed_church.value = option.dataset.venue;
    });
});
