import { fileLooksLikeHeic, heicFileToJpegFile, jpegFileName } from './heic.js';

document.documentElement.classList.add('js-enhanced');

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const field = button.closest('.password-field')?.querySelector('input');
    const label = button.querySelector('span');

    if (! (field instanceof HTMLInputElement) || ! label) {
        return;
    }

    button.hidden = false;
    button.addEventListener('click', () => {
        const isVisible = field.type === 'text';
        field.type = isVisible ? 'password' : 'text';
        label.textContent = isVisible ? button.dataset.showLabel : button.dataset.hideLabel;
        button.setAttribute('aria-label', isVisible ? button.dataset.showLabel : button.dataset.hideLabel);
        button.setAttribute('aria-pressed', String(! isVisible));
    });
});

document.querySelectorAll('form[data-submit-feedback]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;

        if (! (submitter instanceof HTMLButtonElement) || ! submitter.dataset.loadingText) {
            return;
        }

        submitter.dataset.originalContent ??= submitter.innerHTML;
        submitter.dataset.originalDisabled ??= String(submitter.disabled);
        form.setAttribute('aria-busy', 'true');
        submitter.disabled = true;
        submitter.textContent = submitter.dataset.loadingText;
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submit-feedback]').forEach((form) => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[data-original-content]').forEach((button) => {
            button.innerHTML = button.dataset.originalContent;
            button.disabled = button.dataset.originalDisabled === 'true';
        });
    });
});

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
            description.setAttribute('aria-label', control.dataset.descriptionTemplate.replace(':percent', split));
            description.replaceChildren();
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

document.querySelectorAll('[data-toast-close]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('[data-toast]')?.remove();
    });
});

document.querySelectorAll('[data-onboarding-done]').forEach((section) => {
    const hide = () => {
        section.remove();
    };
    const timer = window.setTimeout(hide, 6000);

    section.querySelectorAll('[data-onboarding-close]').forEach((button) => {
        button.addEventListener('click', () => {
            window.clearTimeout(timer);
            hide();
        });
    });
});

const HEIC_CONVERSION_ERROR = 'HEIC-bilden kunde inte läsas. Spara den som JPEG och försök igen.';

const bitmapToJpeg = (bitmap) => new Promise((resolve) => {
    const scale = Math.min(1, 2000 / Math.max(bitmap.width, bitmap.height, 1));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    const context = canvas.getContext('2d');

    if (! context) {
        resolve(null);

        return;
    }

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.9);
});

const preparePhoto = async (file) => {
    if (! await fileLooksLikeHeic(file)) {
        return file;
    }

    try {
        if (typeof createImageBitmap === 'function') {
            const bitmap = await createImageBitmap(file);

            try {
                if (bitmap.width > 0 && bitmap.height > 0) {
                    const blob = await bitmapToJpeg(bitmap);

                    if (blob) {
                        return new File([blob], jpegFileName(file.name), {
                            type: 'image/jpeg',
                            lastModified: Date.now(),
                        });
                    }
                }
            } finally {
                bitmap.close?.();
            }
        }
    } catch (error) {
        // This browser cannot decode HEIC natively. The wasm decoder runs next.
    }

    return heicFileToJpegFile(file);
};

document.querySelectorAll('[data-photo-picker]').forEach((picker) => {
    const input = picker.querySelector('input[type="file"]');
    const list = picker.querySelector('[data-photo-list]');
    const count = picker.querySelector('[data-photo-count]');
    const error = picker.querySelector('[data-photo-error]');

    if (! (input instanceof HTMLInputElement) || ! list || ! count || typeof DataTransfer !== 'function') {
        return;
    }

    /** @type {File[]} */
    const files = [];
    /** @type {string[]} */
    let previewUrls = [];
    let writing = false;
    let converting = false;
    let submitAfter = false;

    const render = () => {
        previewUrls.forEach((url) => URL.revokeObjectURL(url));
        previewUrls = [];
        list.replaceChildren();

        files.forEach((file, index) => {
            const item = document.createElement('li');
            const image = document.createElement('img');
            const url = URL.createObjectURL(file);
            previewUrls.push(url);
            image.src = url;
            image.alt = 'Bild ' + (index + 1);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Ta bort';
            remove.setAttribute('aria-label', 'Ta bort bild ' + (index + 1));
            remove.addEventListener('click', () => {
                files.splice(index, 1);
                writeFiles();
            });

            item.append(image, remove);
            list.append(item);
        });

        if (files.length === 0) {
            count.hidden = true;
            count.textContent = '';

            return;
        }

        count.hidden = false;
        count.textContent = files.length === 1 ? '1 bild vald' : files.length + ' bilder valda';
    };

    const writeFiles = () => {
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        writing = true;
        input.files = transfer.files;
        writing = false;
        render();
    };

    const showError = (message) => {
        if (! error) {
            return;
        }

        error.hidden = message === '';
        error.textContent = message;
    };

    input.addEventListener('change', () => {
        if (writing) {
            return;
        }

        const selected = Array.from(input.files ?? []);
        converting = true;
        count.hidden = false;
        count.textContent = 'Läser bilder…';

        void (async () => {
            let failed = false;

            try {
                for (const file of selected) {
                    try {
                        files.push(await preparePhoto(file));
                    } catch (conversionError) {
                        failed = true;
                    }
                }
            } finally {
                converting = false;
                showError(failed ? HEIC_CONVERSION_ERROR : '');
                writeFiles();

                if (submitAfter) {
                    submitAfter = false;
                    input.form?.requestSubmit();
                }
            }
        })();
    });

    input.form?.addEventListener('submit', (event) => {
        if (! converting) {
            return;
        }

        event.preventDefault();
        submitAfter = true;
    });
});

const copyText = async (value) => {
    if (navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(value);

            return true;
        } catch (error) {
            // The page can still fall back to a hidden field.
        }
    }

    const field = document.createElement('textarea');
    field.value = value;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.left = '-9999px';
    document.body.append(field);
    field.select();

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch (error) {
        copied = false;
    }

    field.remove();

    return copied;
};

document.querySelectorAll('[data-copy-report]').forEach((button) => {
    button.addEventListener('click', async (event) => {
        const form = button.closest('form');
        const url = button.dataset.copyReport;

        if (! form || ! url) {
            return;
        }

        event.preventDefault();

        if (await copyText(url)) {
            form.submit();
        }
    });
});

document.querySelectorAll('[data-share-url]').forEach((button) => {
    button.addEventListener('click', async (event) => {
        event.preventDefault();

        const url = button.dataset.shareUrl;
        const original = button.textContent;
        const form = button.closest('form');
        const recordShare = () => {
            if (! form) {
                return;
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                keepalive: true,
            }).catch(() => {});
        };

        if (navigator.share) {
            try {
                await navigator.share({ title: 'Städrapport', url });
                recordShare();

                return;
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        try {
            await navigator.clipboard.writeText(url);
            recordShare();
            button.textContent = 'Länken är kopierad';
            window.setTimeout(() => {
                button.textContent = original;
            }, 2000);
        } catch (error) {
            recordShare();
            const input = document.querySelector('#share-link');

            if (input instanceof HTMLInputElement) {
                input.focus();
                input.select();
            }
        }
    });
});
