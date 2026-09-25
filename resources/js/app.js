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
