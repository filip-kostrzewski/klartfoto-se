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

document.querySelectorAll('[data-share-url]').forEach((button) => {
    button.addEventListener('click', async () => {
        const url = button.dataset.shareUrl;
        const original = button.textContent;

        if (navigator.share) {
            try {
                await navigator.share({ title: 'Städrapport', url });
                return;
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        try {
            await navigator.clipboard.writeText(url);
            button.textContent = 'Länken är kopierad';
            window.setTimeout(() => {
                button.textContent = original;
            }, 2000);
        } catch (error) {
            const input = document.querySelector('#share-link');

            if (input instanceof HTMLInputElement) {
                input.focus();
                input.select();
            }
        }
    });
});
