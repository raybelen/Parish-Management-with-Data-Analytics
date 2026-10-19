import './admin-calendar';
import './appointment-details';

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
