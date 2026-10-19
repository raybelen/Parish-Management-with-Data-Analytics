document.querySelectorAll('[data-death-year]').forEach((yearField) => {
    const form = yearField.closest('form');
    const deathDate = form?.querySelector('[name="service[date_of_death]"]');
    const preferredDate = form?.querySelector('[name="preferred_date"]');
    const burialDate = form?.querySelector('[data-burial-date]');

    const updateDates = () => {
        yearField.value = deathDate?.value.slice(0, 4) ?? '';
        if (burialDate) burialDate.value = preferredDate?.value ?? '';
    };

    [deathDate, preferredDate].forEach((field) => {
        field?.addEventListener('input', updateDates);
        field?.addEventListener('change', updateDates);
    });
    updateDates();
});
