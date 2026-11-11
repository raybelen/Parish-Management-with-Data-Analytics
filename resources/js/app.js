import './appointment-availability';
import './group-baptisms';
import './appointment-details';
import './psgc-address';

document.querySelectorAll('[data-print-billing]').forEach((button) => {
    button.addEventListener('click', () => {
        const selectedDocument = button.closest('[data-billing-document]');
        document.querySelectorAll('[data-billing-document]').forEach((documentSection) => {
            documentSection.toggleAttribute('data-print-selected', documentSection === selectedDocument);
        });
        document.body.toggleAttribute('data-printing-document', selectedDocument !== null);
        window.print();
    });
});
window.addEventListener('afterprint', () => {
    document.body.removeAttribute('data-printing-document');
    document.querySelectorAll('[data-print-selected]').forEach((section) => section.removeAttribute('data-print-selected'));
});

const menuToggle = document.getElementById('menu-toggle');
const mobileNavigation = document.getElementById('mobile-navigation');
const oneTimeAppointmentAccess = document.querySelector('[data-one-time-appointment-access]');
if (oneTimeAppointmentAccess) {
    window.history.replaceState(null, '', oneTimeAppointmentAccess.dataset.manageUrl);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.replace(oneTimeAppointmentAccess.dataset.manageUrl);
        }
    });
}
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
    .map((button) => ({ src: button.querySelector('img').src, alt: button.querySelector('img').alt }));
let currentPhoto = 0;
function showPhoto(index) {
    currentPhoto = (index + galleryPhotos.length) % galleryPhotos.length;
    const photo = galleryPhotos[currentPhoto];
    const image = document.getElementById('gallery-image');
    image.src = photo.src;
    image.alt = photo.alt;
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
    const feeLabel = document.querySelector('[data-service-fee]');
    const groupSession = document.querySelector('[name="group_baptism_session_id"]');
    const groupFee = groupSession && !groupSession.disabled ? groupSession.selectedOptions[0]?.dataset.feeCents : '';
    if (feeLabel) {
        feeLabel.textContent = selectedService
            ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(groupFee || serviceSelect.selectedOptions[0].dataset.feeCents || 0) / 100)
            : 'Select a service to see the fee.';
    }

    serviceSections.forEach((section) => {
        const isActive = section.dataset.serviceFields === selectedService;
        section.hidden = !isActive;

        section.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
            field.disabled = !isActive;
            field.required = isActive && field.dataset.required === 'true';
        });
        section.querySelectorAll('[data-psgc-address-controls]').forEach((fieldset) => {
            fieldset.disabled = !isActive;
        });
    });
}
serviceSelect?.addEventListener('change', updateServiceFields);
serviceSelect?.closest('form')?.addEventListener('group-session-changed', updateServiceFields);
if (serviceSelect) updateServiceFields();

const preferredContactMethod = document.querySelector('[data-contact-method]');
const contactEmail = document.querySelector('[data-contact-email]');
function updateContactEmailRequirement() {
    if (contactEmail) {
        contactEmail.required = preferredContactMethod?.value === 'email';
        const marker = document.querySelector('label[for="client_email"] span:last-child');
        if (marker) {
            marker.textContent = contactEmail.required ? '*' : 'Optional';
            marker.className = contactEmail.required ? 'font-semibold text-red-700' : 'text-[10px] font-medium tracking-[0.12em] uppercase text-muted';
            marker.setAttribute('aria-hidden', String(contactEmail.required));
        }
    }
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
const suggestedTimeButtons = [...document.querySelectorAll('[data-time-value]')];

function updateScheduleSelection() {
    suggestedTimeButtons.forEach((button) => {
        const isSelected = preferredTime?.value === button.dataset.timeValue;
        button.hidden = serviceSelect?.value === 'wedding' && !['08:00', '08:30', '09:00', '09:30', '10:00', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'].includes(button.dataset.timeValue);

        button.setAttribute('aria-pressed', String(isSelected));
    });
}

suggestedTimeButtons.forEach((button) => {
    button.addEventListener('click', () => {
        if (button.disabled) return;
        preferredTime.value = button.dataset.timeValue;
        preferredTime.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { availabilityChecked: true } }));
    });
});

serviceSelect?.addEventListener('change', updateScheduleSelection);
preferredDate?.addEventListener('change', updateScheduleSelection);
preferredTime?.addEventListener('change', updateScheduleSelection);
updateScheduleSelection();

const appointmentForm = document.querySelector('[data-appointment-form]');
const requiredAlert = document.querySelector('[data-required-alert]');
const requiredAlertList = document.querySelector('[data-required-alert-list]');
const appointmentConfirmationDialog = document.getElementById('confirm-appointment-dialog');
const appointmentConfirmationButton = document.querySelector('[data-confirm-appointment]');
const appointmentReviewButton = document.querySelector('[data-review-appointment]');
let requiredAlertFrame;
let hasAttemptedAppointmentSubmission = false;
let isAppointmentSubmissionConfirmed = false;

function missingRequiredFields() {
    return [...(appointmentForm?.querySelectorAll('[required]:not(:disabled), [data-required="true"]:not(:disabled)') ?? [])].filter((field) => {
        if (field.type === 'file') return field.files.length === 0;

        return field.value.trim() === '';
    });
}

function requiredFieldLabel(field) {
    return field.dataset.requiredLabel || field.labels?.[0]?.querySelector('span')?.textContent?.trim() || field.name;
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

function selectedOptionText(fieldName) {
    return appointmentForm?.elements.namedItem(fieldName)?.selectedOptions?.[0]?.textContent?.trim() || 'Not provided';
}

function fieldValue(fieldName) {
    return appointmentForm?.querySelector(`[name="${fieldName}"]`)?.value?.trim() || 'Not provided';
}

function updateAppointmentReview() {
    if (!appointmentForm || !appointmentConfirmationDialog) return;

    const reviewValues = {
        client: fieldValue('client_full_name'),
        contact: `${selectedOptionText('preferred_contact_method')}: ${fieldValue(fieldValue('preferred_contact_method') === 'email' ? 'client_email' : 'client_contact_number')}`,
        address: fieldValue('client_address'),
        service: fieldValue('baptism_mode') === 'shared' && serviceSelect?.value === 'baptism' ? 'Group baptism' : selectedOptionText('service_type'),
        schedule: `${preferredDate?.value ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'long' }).format(new Date(`${preferredDate.value}T12:00:00`)) : 'Not provided'} at ${preferredTime?.value ? new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit' }).format(new Date(`2000-01-01T${preferredTime.value}:00`)) : 'Not provided'}`,
        church: selectedOptionText('preferred_church'),
        payment: appointmentForm?.querySelector('[name="payment_option"]:checked')?.dataset.paymentLabel ?? 'Not provided',
        fee: document.querySelector('[data-service-fee]')?.textContent ?? 'Not provided',
    };

    Object.entries(reviewValues).forEach(([name, value]) => {
        const output = appointmentConfirmationDialog.querySelector(`[data-review-${name}]`);
        if (output) output.textContent = value;
    });
}

appointmentForm?.addEventListener('submit', (event) => {
    if (missingRequiredFields().length > 0) {
        event.preventDefault();
        hasAttemptedAppointmentSubmission = true;
        updateRequiredAlert({ focus: true });

        return;
    }

    if (isAppointmentSubmissionConfirmed || !appointmentConfirmationDialog) return;

    event.preventDefault();
    updateAppointmentReview();
    openDialog(appointmentConfirmationDialog, appointmentReviewButton);
});

appointmentConfirmationButton?.addEventListener('click', () => {
    isAppointmentSubmissionConfirmed = true;
    appointmentConfirmationButton.disabled = true;
    appointmentConfirmationButton.textContent = 'Submitting request...';
    appointmentConfirmationDialog.close();
    appointmentForm.requestSubmit();
});
