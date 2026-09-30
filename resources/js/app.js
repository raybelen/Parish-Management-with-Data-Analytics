const menuToggle = document.getElementById('menu-toggle');
const mobileNavigation = document.getElementById('mobile-navigation');
function closeMenu() {
    mobileNavigation.hidden = true;
    menuToggle.setAttribute('aria-expanded', 'false');
    menuToggle.setAttribute('aria-label', 'Open navigation');
}
menuToggle?.addEventListener('click', () => {
    const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
    mobileNavigation.hidden = isExpanded;
    menuToggle.setAttribute('aria-expanded', String(!isExpanded));
    menuToggle.setAttribute('aria-label', isExpanded ? 'Open navigation' : 'Close navigation');
});
mobileNavigation?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && mobileNavigation && !mobileNavigation.hidden) {
        closeMenu();
        menuToggle.focus();
    }
});
window.matchMedia('(min-width: 1280px)').addEventListener('change', (event) => {
    if (event.matches && mobileNavigation) closeMenu();
});

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
document.querySelectorAll('.parish-accordion').forEach((details) => {
    const summary = details.querySelector('summary');
    let animation;

    summary.addEventListener('click', (event) => {
        if (reducedMotion.matches || !details.animate) return;

        event.preventDefault();
        if (animation) return;

        const opening = !details.open;
        const startHeight = details.getBoundingClientRect().height;

        if (opening) details.open = true;

        const endHeight = opening ? details.scrollHeight : summary.getBoundingClientRect().height;
        details.style.overflow = 'hidden';
        animation = details.animate(
            [{ height: `${startHeight}px` }, { height: `${endHeight}px` }],
            { duration: 220, easing: 'ease-in-out' },
        );
        animation.onfinish = () => {
            details.open = opening;
            details.style.overflow = '';
            animation = null;
        };
    });
});

let dialogTrigger;
function openDialog(dialog, trigger) {
    if (!dialog) return;
    dialogTrigger = mobileNavigation?.contains(trigger) ? menuToggle : trigger;
    if (mobileNavigation && !mobileNavigation.hidden) closeMenu();
    dialog.showModal();
    document.body.style.overflow = 'hidden';
}
document.querySelectorAll('[data-dialog-target]').forEach((trigger) => {
    trigger.addEventListener('click', () => openDialog(document.getElementById(trigger.dataset.dialogTarget), trigger));
});
document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.body.style.overflow = '';
        if (dialogTrigger?.isConnected) dialogTrigger.focus();
    });
});
const galleryDialog = document.getElementById('gallery-dialog');
const galleryPhotos = [...document.querySelectorAll('#gallery [data-gallery-index]')]
    .filter((button) => button.querySelector('img'))
    .map((button) => ({ src: button.querySelector('img').src, alt: button.querySelector('img').alt, title: button.querySelector('span').textContent }));
let currentPhoto = 0;
function showPhoto(index) {
    currentPhoto = (index + galleryPhotos.length) % galleryPhotos.length;
    const photo = galleryPhotos[currentPhoto];
    const image = document.getElementById('gallery-image');
    image.src = photo.src;
    image.alt = photo.alt;
    document.getElementById('gallery-caption').textContent = photo.title;
    document.getElementById('gallery-count').textContent = `${currentPhoto + 1} / ${galleryPhotos.length}`;
}
document.querySelectorAll('[data-gallery-index]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        showPhoto(Number(trigger.dataset.galleryIndex));
        openDialog(galleryDialog, trigger);
    });
});
document.getElementById('gallery-previous')?.addEventListener('click', () => showPhoto(currentPhoto - 1));
document.getElementById('gallery-next')?.addEventListener('click', () => showPhoto(currentPhoto + 1));
galleryDialog?.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        showPhoto(currentPhoto + (event.key === 'ArrowLeft' ? -1 : 1));
    }
});

const serviceSelect = document.querySelector('[data-service-select]');
const serviceSections = [...document.querySelectorAll('[data-service-fields]')];
function updateServiceFields() {
    const selectedService = serviceSelect?.value ?? '';

    serviceSections.forEach((section) => {
        const isActive = section.dataset.serviceFields === selectedService;
        section.hidden = !isActive;

        section.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !isActive;
            field.required = isActive && field.dataset.required === 'true';
        });
    });
}
serviceSelect?.addEventListener('change', updateServiceFields);
if (serviceSelect) updateServiceFields();

const preferredContactMethod = document.querySelector('[data-contact-method]');
const contactEmail = document.querySelector('[data-contact-email]');
function updateContactEmailRequirement() {
    if (contactEmail) contactEmail.required = preferredContactMethod?.value === 'email';
}
preferredContactMethod?.addEventListener('change', updateContactEmailRequirement);
if (preferredContactMethod) updateContactEmailRequirement();

document.querySelector('[data-validation-summary]')?.focus();

function toTitleCase(value) {
    return value
        .trim()
        .replace(/\s+/g, ' ')
        .toLocaleLowerCase('en-PH')
        .replace(/(^|[\s'’-])\p{L}/gu, (letter) => letter.toLocaleUpperCase('en-PH'));
}

document.querySelectorAll('[data-title-case]').forEach((field) => {
    field.addEventListener('blur', () => {
        field.value = toTitleCase(field.value);
    });
});

function formatPhoneNumber(value) {
    const trimmedValue = value.trim();

    if (!/^\+?[0-9()\-\s.]+$/.test(trimmedValue)) {
        return trimmedValue;
    }

    const digits = trimmedValue.replace(/\D/g, '');

    if (trimmedValue.startsWith('+') && digits.startsWith('63') && digits.length === 12) {
        return `+63 ${digits.slice(2, 5)} ${digits.slice(5, 8)} ${digits.slice(8)}`;
    }

    if (digits.startsWith('0') && digits.length === 11) {
        return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
    }

    return `${trimmedValue.startsWith('+') ? '+' : ''}${digits}`;
}

document.querySelectorAll('[data-phone-input]').forEach((field) => {
    field.addEventListener('blur', () => {
        field.value = formatPhoneNumber(field.value);
    });
});

document.querySelectorAll('[data-email-input]').forEach((field) => {
    field.addEventListener('blur', () => {
        field.value = field.value.trim().toLocaleLowerCase('en-PH');
    });
});

const preferredDate = document.querySelector('[data-preferred-date]');
const preferredTime = document.querySelector('[data-preferred-time]');
const scheduleSummary = document.querySelector('[data-schedule-summary]');
const suggestedTimeButtons = [...document.querySelectorAll('[data-time-value]')];

function updateScheduleSelection() {
    suggestedTimeButtons.forEach((button) => {
        const isSelected = preferredTime?.value === button.dataset.timeValue;

        button.setAttribute('aria-pressed', String(isSelected));
        button.classList.toggle('border-gold', isSelected);
        button.classList.toggle('bg-gold/20', isSelected);
    });

    if (!scheduleSummary) return;

    if (!preferredDate?.value && !preferredTime?.value) {
        scheduleSummary.textContent = 'Choose a preferred date and time.';

        return;
    }

    const formattedDate = preferredDate?.value
        ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'full' }).format(new Date(`${preferredDate.value}T12:00:00`))
        : 'a date to be selected';
    const formattedTime = preferredTime?.value
        ? new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit' }).format(new Date(`2000-01-01T${preferredTime.value}:00`))
        : 'a time to be selected';

    scheduleSummary.textContent = `Preferred schedule: ${formattedDate} at ${formattedTime}. Final availability is subject to parish confirmation.`;
}

suggestedTimeButtons.forEach((button) => {
    button.addEventListener('click', () => {
        preferredTime.value = button.dataset.timeValue;
        preferredTime.dispatchEvent(new Event('change', { bubbles: true }));
    });
});

preferredDate?.addEventListener('change', updateScheduleSelection);
preferredTime?.addEventListener('change', updateScheduleSelection);
updateScheduleSelection();

const appointmentForm = document.querySelector('[data-appointment-form]');
const requiredAlert = document.querySelector('[data-required-alert]');
const requiredAlertList = document.querySelector('[data-required-alert-list]');
let requiredAlertFrame;
let hasAttemptedAppointmentSubmission = false;

function missingRequiredFields() {
    return [...(appointmentForm?.querySelectorAll('[required]:not(:disabled)') ?? [])].filter((field) => {
        if (field.type === 'file') return field.files.length === 0;

        return field.value.trim() === '';
    });
}

function requiredFieldLabel(field) {
    return field.labels?.[0]?.querySelector('span')?.textContent?.trim() || field.name;
}

function updateRequiredAlert({ focus = false } = {}) {
    if (!requiredAlert || !requiredAlertList) return;

    const missingFields = missingRequiredFields();
    requiredAlertList.replaceChildren(...missingFields.map((field) => {
        const item = document.createElement('li');
        item.textContent = requiredFieldLabel(field);
        field.setAttribute('aria-invalid', 'true');

        return item;
    }));

    appointmentForm?.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
        if (!missingFields.includes(field)) field.removeAttribute('aria-invalid');
    });

    requiredAlert.hidden = missingFields.length === 0;

    if (focus && missingFields.length > 0) {
        requiredAlert.focus({ preventScroll: true });
        requiredAlert.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'center' });
    }
}

appointmentForm?.addEventListener('invalid', () => {
    hasAttemptedAppointmentSubmission = true;
    cancelAnimationFrame(requiredAlertFrame);
    requiredAlertFrame = requestAnimationFrame(() => updateRequiredAlert({ focus: true }));
}, true);

appointmentForm?.addEventListener('input', () => {
    if (hasAttemptedAppointmentSubmission) updateRequiredAlert();
});
appointmentForm?.addEventListener('change', () => {
    if (hasAttemptedAppointmentSubmission) updateRequiredAlert();
});
