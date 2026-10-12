import './admin-calendar';

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
        section.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !active; });
    });
    select.addEventListener('change', update);
    update();
});
