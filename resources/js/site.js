document.querySelectorAll('[data-comparison]').forEach((comparison) => {
    const control = comparison.querySelector('.comparison-control');
    const description = comparison.querySelector('[data-comparison-description]');

    if (!control) {
        return;
    }

    const updateComparison = () => {
        const split = Number(control.value);

        comparison.style.setProperty('--split', split + '%');
        control.setAttribute(
            'aria-valuetext',
            split + ' ' + control.dataset.beforeText + ', ' + control.dataset.afterText,
        );

        if (description) {
            description.textContent = control.dataset.descriptionTemplate.replace(':percent', split);
        }
    };

    control.addEventListener('input', updateComparison);
    updateComparison();
});

document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

const focusStatus = document.querySelector('[data-focus-status]');

if (focusStatus && window.location.hash === '#signup') {
    const focusSummary = () => {
        window.requestAnimationFrame(() => focusStatus.focus({ preventScroll: true }));
    };

    window.addEventListener('pageshow', focusSummary, { once: true });

    if (document.readyState === 'complete') {
        focusSummary();
    }
}
