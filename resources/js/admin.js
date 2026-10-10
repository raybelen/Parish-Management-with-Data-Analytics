document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('[data-walkin-service]').forEach((select) => {
    const update = () => document.querySelectorAll('[data-walkin-fields]').forEach((section) => {
        const active = section.dataset.walkinFields === select.value;
        section.hidden = !active;
        section.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !active; });
    });
    select.addEventListener('change', update);
    update();
});
