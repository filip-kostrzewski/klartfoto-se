import { fileLooksLikeHeic, heicFileToJpegFile, jpegFileName } from './heic.js';
import { PHOTO_RESOLUTION, jobFormErrors } from './job-form.js';

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

if (focusStatus && (window.location.hash === '#signup' || focusStatus.dataset.focusOnLoad === 'true')) {
    const focusSummary = () => {
        window.requestAnimationFrame(() => focusStatus.focus({ preventScroll: true }));
    };

    const focusInvalidField = () => {
        const invalidField = document.querySelector([
            '.assignment-email-form [aria-invalid="true"]',
            '.assignment-team-form [aria-invalid="true"]',
            '.assignment-pair-notes-form [aria-invalid="true"]',
        ].join(', '));

        if (! (invalidField instanceof HTMLElement)) {
            focusSummary();

            return;
        }

        window.requestAnimationFrame(() => invalidField.focus());
    };

    const focusTarget = focusStatus.dataset.focusOnLoad === 'true' ? focusInvalidField : focusSummary;

    window.addEventListener('pageshow', focusTarget, { once: true });

    if (document.readyState === 'complete') {
        focusTarget();
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

const createUploadId = () => {
    if (typeof window.crypto?.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);

        return (character === 'x' ? random : (random & 0x3) | 0x8).toString(16);
    });
};

const photoUploadRecordsByForm = new WeakMap();
const photoPickerRenderersByInput = new WeakMap();

const restoreSubmitButton = (form) => {
    form.removeAttribute('aria-busy');
    form.querySelectorAll('button[data-original-content]').forEach((button) => {
        if (! (button instanceof HTMLButtonElement)) {
            return;
        }

        button.innerHTML = button.dataset.originalContent ?? button.innerHTML;
        button.disabled = button.dataset.originalDisabled === 'true';
        button.removeAttribute('aria-busy');
    });
};

const showFormErrors = (form, errors) => {
    form.querySelectorAll('[data-field-error]').forEach((field) => {
        const key = field.dataset.fieldError ?? '';
        const message = errors[key]?.[0] ?? '';
        field.hidden = message === '';
        field.textContent = message;
        const input = form.querySelector(`[name="${key}"]`);

        if (input instanceof HTMLInputElement) {
            input.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
        }
    });

    form.querySelectorAll('[data-photo-picker]').forEach((picker) => {
        const error = picker.querySelector('[data-photo-error]');
        const input = picker.querySelector('input[type="file"]');

        if (! error || ! (input instanceof HTMLInputElement)) {
            return;
        }

        const key = input.name.replace(/\[\]$/, '');
        const message = Object.entries(errors).find(([name]) => name === key || name.startsWith(`${key}.`))?.[1]?.[0] ?? '';
        error.hidden = message === '';
        error.textContent = message;
    });

    const messages = [...new Set(Object.values(errors).flat().filter((message) => typeof message === 'string' && message !== ''))];
    let box = form.querySelector('[data-form-errors]');

    if (! (box instanceof HTMLElement)) {
        box = document.createElement('div');
        box.className = 'form-status form-status-error';
        box.setAttribute('role', 'alert');
        box.tabIndex = -1;
        box.dataset.formErrors = '';
        form.prepend(box);
    }

    if (messages.length === 0) {
        box.hidden = true;
        box.replaceChildren();

        return;
    }

    const heading = document.createElement('strong');
    heading.textContent = 'Kontrollera uppgifterna';
    const list = document.createElement('ul');
    messages.forEach((message) => {
        const item = document.createElement('li');
        item.textContent = message;
        list.append(item);
    });
    box.hidden = false;
    box.replaceChildren(heading, list);
    box.focus();
};

const dimensionErrors = async (files, key, existing) => {
    /** @type {Record<string, string[]>} */
    const errors = {};

    if (typeof createImageBitmap !== 'function') {
        return errors;
    }

    for (let index = 0; index < files.length; index += 1) {
        const field = `${key}.${index}`;

        if (existing[field]) {
            continue;
        }

        let bitmap;

        try {
            bitmap = await createImageBitmap(files[index]);
            const tooBig = bitmap.width > 8000 || bitmap.height > 8000 || bitmap.width < 1 || bitmap.height < 1;

            if (tooBig) {
                errors[field] = [PHOTO_RESOLUTION];
            }
        } catch (error) {
            // The server checks files this browser cannot measure.
        } finally {
            bitmap?.close?.();
        }
    }

    return errors;
};

document.querySelectorAll('[data-photo-picker]').forEach((picker) => {
    const input = picker.querySelector('input[type="file"]');
    const list = picker.querySelector('[data-photo-list]');
    const count = picker.querySelector('[data-photo-count]');
    const error = picker.querySelector('[data-photo-error]');
    const uploadIdContainer = picker.querySelector('[data-upload-id-container]');

    if (! (input instanceof HTMLInputElement) || ! list || ! count) {
        return;
    }

    /** @type {File[]} */
    const files = [];
    /** @type {string[]} */
    const uploadIds = [];
    /** @type {number[]} */
    const slotIndices = [];
    /** @type {number[]} */
    const availableSlotIndices = [];
    let nextSlotIndex = 0;
    /** @type {string[]} */
    let previewUrls = [];
    let writing = false;
    let converting = false;
    let submitAfter = false;

    const form = input.form;

    const markConverting = (active) => {
        if (! form) {
            return;
        }

        const current = Number(form.dataset.photoConverting || '0');
        const next = Math.max(0, current + (active ? 1 : -1));

        if (next === 0) {
            delete form.dataset.photoConverting;
        } else {
            form.dataset.photoConverting = String(next);
        }
    };

    const renderUploadIds = () => {
        if (! uploadIdContainer || ! input.dataset.uploadIdName) {
            return;
        }

        uploadIdContainer.replaceChildren();
        uploadIds.forEach((uploadId) => {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = input.dataset.uploadIdName;
            field.value = uploadId;
            uploadIdContainer.append(field);
        });
    };

    const renderUploadSlots = () => {
        const slotContainer = picker.querySelector('[data-upload-slot-container]');

        if (! slotContainer) {
            return;
        }

        slotContainer.replaceChildren();
        slotIndices.forEach((slotIndex) => {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.dataset.uploadSlot = String(slotIndex);
            slotContainer.append(field);
        });
    };

    const render = () => {
        previewUrls.forEach((url) => URL.revokeObjectURL(url));
        previewUrls = [];
        list.replaceChildren();
        renderUploadIds();
        renderUploadSlots();

        files.forEach((file, index) => {
            const item = document.createElement('li');
            const image = document.createElement('img');
            const url = URL.createObjectURL(file);
            previewUrls.push(url);
            image.src = url;
            image.alt = 'Bild ' + (index + 1);

            const remove = document.createElement('button');
            remove.type = 'button';
            const wasAccepted = photoUploadRecordsByForm.get(input.form)?.has(uploadIds[index]) ?? false;
            remove.textContent = wasAccepted ? 'Uppladdad' : 'Ta bort';
            remove.disabled = wasAccepted;
            remove.setAttribute('aria-label', wasAccepted ? 'Redan uppladdad bild ' + (index + 1) : 'Ta bort bild ' + (index + 1));
            remove.addEventListener('click', () => {
                if (wasAccepted) {
                    return;
                }

                files.splice(index, 1);
                uploadIds.splice(index, 1);
                const removedSlotIndex = slotIndices.splice(index, 1)[0];
                if (Number.isInteger(removedSlotIndex)) {
                    availableSlotIndices.push(removedSlotIndex);
                    availableSlotIndices.sort((left, right) => left - right);
                }
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

    photoPickerRenderersByInput.set(input, render);

    const writeFiles = () => {
        if (typeof DataTransfer !== 'function') {
            render();

            return;
        }

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

        const selectedFiles = Array.from(input.files ?? []);

        if (! input.multiple || typeof DataTransfer !== 'function') {
            files.splice(0, files.length);
            uploadIds.splice(0, uploadIds.length);
            slotIndices.splice(0, slotIndices.length);
            availableSlotIndices.splice(0, availableSlotIndices.length);
            nextSlotIndex = 0;
        }

        converting = true;
        markConverting(true);
        count.hidden = false;
        count.textContent = 'Läser bilder…';
        const wasDisabled = input.disabled;
        input.disabled = true;

        void (async () => {
            let failed = false;

            try {
                for (const file of selectedFiles) {
                    try {
                        files.push(await preparePhoto(file));

                        if (input.dataset.uploadIdName) {
                            uploadIds.push(createUploadId());
                            slotIndices.push(availableSlotIndices.shift() ?? nextSlotIndex++);
                        }
                    } catch (conversionError) {
                        failed = true;
                    }
                }
            } finally {
                converting = false;
                markConverting(false);
                showError(failed ? HEIC_CONVERSION_ERROR : '');
                input.disabled = wasDisabled;
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

document.querySelectorAll('form[data-photo-upload-mode]').forEach((form) => {
    const submitButton = form.querySelector('[data-upload-submit]');
    const progressContainer = form.querySelector('[data-upload-progress-list]');
    const feedback = form.querySelector('[data-upload-feedback]');
    const mode = form.dataset.photoUploadMode;

    if (! (form instanceof HTMLFormElement) || ! (submitButton instanceof HTMLButtonElement) || ! progressContainer || ! feedback) {
        return;
    }

    const fileInputs = Array.from(form.querySelectorAll('input[type="file"][name="before_photos[]"], input[type="file"][name="after_photos[]"]'));
    const uploadRecords = new Map();
    photoUploadRecordsByForm.set(form, uploadRecords);

    const uploadIdsFor = (input) => {
        const container = input.closest('[data-photo-picker]')?.querySelector('[data-upload-id-container]');
        const name = input.dataset.uploadIdName;
        const files = Array.from(input.files ?? []);

        if (! container || ! name) {
            return files.map(() => createUploadId());
        }

        let fields = Array.from(container.querySelectorAll('input[type="hidden"]'));

        while (fields.length < files.length) {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            field.value = createUploadId();
            container.append(field);
            fields.push(field);
        }

        while (fields.length > files.length) {
            fields.pop()?.remove();
        }

        return fields.map((field) => field.value);
    };

    const describeFiles = () => fileInputs.flatMap((input) => {
        const phaseKey = input.name.startsWith('before') ? 'before' : 'after';
        const phase = phaseKey === 'before' ? 'Före' : 'Efter';
        const files = Array.from(input.files ?? []);
        const uploadIds = uploadIdsFor(input);
        const slotContainer = input.closest('[data-photo-picker]')?.querySelector('[data-upload-slot-container]');
        const slotFields = slotContainer ? Array.from(slotContainer.querySelectorAll('[data-upload-slot]')) : [];
        const slotIndices = slotFields.length === files.length
            ? slotFields.map((field) => Number(field.dataset.uploadSlot))
            : files.map((file, index) => index);

        return files.map((file, index) => ({
            file,
            input,
            slotIndex: slotIndices[index],
            phase,
            phaseKey,
            uploadId: uploadIds[index],
            record: uploadRecords.get(uploadIds[index]) ?? null,
            accepted: uploadRecords.has(uploadIds[index]),
            state: null,
            progress: null,
        }));
    });

    const sendBatch = (payload, batchItems) => new Promise((resolve) => {
        const request = new XMLHttpRequest();

        request.open('POST', form.action);
        request.setRequestHeader('Accept', 'application/json');
        request.timeout = 120000;
        request.upload.addEventListener('progress', (event) => {
            if (! event.lengthComputable) {
                return;
            }

            let batchOffset = 0;
            batchItems.forEach((item) => {
                const uploaded = Math.max(0, Math.min(item.file.size, event.loaded - batchOffset));
                const percent = item.file.size === 0 ? 100 : Math.round((uploaded / item.file.size) * 100);
                item.progress.value = percent;
                item.state.textContent = percent >= 100 ? 'Bearbetar…' : `Laddar upp · ${percent}%`;
                batchOffset += item.file.size;
            });
        });
        request.addEventListener('load', () => {
            let data = null;
            try {
                data = JSON.parse(request.responseText);
            } catch (error) {
                data = null;
            }

            resolve({
                ok: request.status >= 200 && request.status < 300,
                message: data?.message ?? null,
                errors: data?.errors ?? null,
                uploads: data?.uploads ?? null,
                responseText: request.responseText,
                status: request.status,
            });
        });
        request.addEventListener('error', () => resolve({ ok: false, message: null, errors: null, status: 0 }));
        request.addEventListener('timeout', () => resolve({ ok: false, message: null, errors: null, status: 0 }));
        request.send(payload);
    });

    form.addEventListener('submit', async (event) => {
        if (form.dataset.photoConverting) {
            return;
        }

        event.preventDefault();
        feedback.hidden = true;
        feedback.textContent = '';

        const beforeInput = form.querySelector('[name="before_photos[]"]');
        const afterInput = form.querySelector('[name="after_photos[]"]');
        const beforeFiles = beforeInput instanceof HTMLInputElement ? Array.from(beforeInput.files ?? []) : [];
        const afterFiles = afterInput instanceof HTMLInputElement ? Array.from(afterInput.files ?? []) : [];
        const start = form.querySelector('[name="performed_time"]');
        const end = form.querySelector('[name="performed_end_time"]');
        const errors = jobFormErrors({
            start: start instanceof HTMLInputElement ? start.value : '',
            end: end instanceof HTMLInputElement ? end.value : '',
            photosRequired: true,
            beforeFiles,
            afterFiles,
        });

        Object.assign(errors, await dimensionErrors(beforeFiles, 'before_photos', errors));
        Object.assign(errors, await dimensionErrors(afterFiles, 'after_photos', errors));

        if (Object.keys(errors).length > 0) {
            showFormErrors(form, errors);
            progressContainer.hidden = true;
            restoreSubmitButton(form);

            return;
        }

        showFormErrors(form, {});
        const items = describeFiles();

        if (items.length === 0) {
            showFormErrors(form, { before_photos: ['Lägg till minst en före- eller efterbild.'] });
            restoreSubmitButton(form);

            return;
        }

        progressContainer.hidden = false;
        progressContainer.replaceChildren();

        const itemList = document.createElement('ul');
        itemList.className = 'photo-upload-items';
        items.forEach((item) => {
            const row = document.createElement('li');
            const description = document.createElement('span');
            description.textContent = `${item.phase} · ${item.file.name}`;
            const state = document.createElement('span');
            state.textContent = item.accepted ? 'Redan uppladdad' : 'Väntar';
            const progress = document.createElement('progress');
            progress.max = 100;
            progress.value = item.accepted ? 100 : 0;
            progress.setAttribute('aria-label', `${item.phase}: ${item.file.name}`);
            row.append(description, state, progress);
            itemList.append(row);
            item.state = state;
            item.progress = progress;
        });

        progressContainer.append(itemList);
        submitButton.dataset.originalContent ??= submitButton.innerHTML;
        submitButton.dataset.originalDisabled ??= String(submitButton.disabled);
        submitButton.disabled = true;
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.textContent = submitButton.dataset.loadingText ?? 'Laddar upp…';
        form.setAttribute('aria-busy', 'true');

        const showFailure = (message) => {
            feedback.textContent = message;
            feedback.hidden = false;
            feedback.focus();
            submitButton.disabled = false;
            submitButton.innerHTML = 'Försök igen';
            submitButton.removeAttribute('aria-busy');
            form.removeAttribute('aria-busy');
        };

        const csrf = form.querySelector('input[name="_token"]')?.value ?? '';
        const placeField = form.querySelector('[name="place_name"]');
        const placeName = placeField instanceof HTMLInputElement ? placeField.value : 'Övrigt';
        const pendingItems = items.filter((item) => ! item.accepted);

        const failItem = (item, result) => {
            item.state.textContent = result.status === 0 ? 'Resultatet kunde inte bekräftas' : 'Kontrollera bilden';
            const validationMessages = result.errors
                ? Object.values(result.errors).flat().join(' ')
                : null;

            showFailure(validationMessages || result.message || 'Uppladdningen kunde inte bekräftas. Försök igen med samma fil innan du ändrar den.');

            if (result.status >= 400 && result.status < 500 && uploadRecords.size === 0 && placeField instanceof HTMLInputElement) {
                placeField.readOnly = false;
            }
        };

        const sendItem = async (item, pairId) => {
            item.state.textContent = 'Laddar upp…';
            item.progress.value = 0;

            const payload = new FormData();
            payload.append('_token', csrf);
            payload.append(item.input.name, item.file, item.file.name);
            payload.append(item.input.dataset.uploadIdName, item.uploadId);
            if (pairId !== null && pairId !== undefined && pairId !== '') {
                payload.append('assignment_photo_pair_id', String(pairId));
            } else if (mode !== 'existing-pair') {
                payload.append('place_name', placeName);
            }

            if (mode !== 'existing-pair' && placeField instanceof HTMLInputElement) {
                placeField.readOnly = true;
            }

            const result = await sendBatch(payload, [item]);

            if (! result.ok) {
                failItem(item, result);

                return null;
            }

            const mapping = Array.isArray(result.uploads)
                ? result.uploads.find((upload) => upload.uploadId === item.uploadId)
                : null;
            const photoId = Number(mapping?.photoId);
            const mappedPairId = Number(mapping?.pairId);

            if (! mapping || ! Number.isInteger(photoId) || ! Number.isInteger(mappedPairId) || mapping.phase !== item.phaseKey) {
                failItem(item, {
                    status: 0,
                    message: 'Servern kunde inte bekräfta resultatet för den här bilden.',
                    errors: null,
                });

                return null;
            }

            const record = {
                photoId,
                pairId: mappedPairId,
                phase: mapping.phase,
                result: mapping.result,
            };
            uploadRecords.set(item.uploadId, record);
            item.record = record;
            item.accepted = true;
            item.progress.value = 100;
            item.state.textContent = 'Uppladdad — behålls vid försök igen';
            photoPickerRenderersByInput.get(item.input)?.();

            return record.pairId;
        };

        if (pendingItems.length === 0) {
            feedback.hidden = false;
            feedback.setAttribute('role', 'status');
            feedback.textContent = 'Alla valda bilder är redan uppladdade. Uppdaterar rapporten…';
            window.location.reload();

            return;
        }

        if (mode === 'existing-pair') {
            const pairId = form.querySelector('[name="assignment_photo_pair_id"]')?.value ?? null;

            for (const item of pendingItems) {
                if (await sendItem(item, pairId) === null) {
                    return;
                }
            }
        } else {
            const slots = new Map();
            items.forEach((item) => {
                if (! slots.has(item.slotIndex)) {
                    slots.set(item.slotIndex, []);
                }
                slots.get(item.slotIndex).push(item);
            });

            const orderedSlots = Array.from(slots.keys()).sort((left, right) => left - right);
            for (const slotIndex of orderedSlots) {
                const slotItems = slots.get(slotIndex);
                const beforeItem = slotItems.find((item) => item.phaseKey === 'before');
                const afterItem = slotItems.find((item) => item.phaseKey === 'after');
                const priorUpload = slotItems.find((item) => item.record);
                let pairId = priorUpload?.record?.pairId ?? null;

                for (const item of [beforeItem, afterItem]) {
                    if (! item || item.accepted) {
                        continue;
                    }

                    pairId = await sendItem(item, pairId);
                    if (pairId === null) {
                        return;
                    }
                }
            }
        }

        feedback.hidden = false;
        feedback.setAttribute('role', 'status');
        feedback.textContent = 'Bilderna är uppladdade. Uppdaterar rapporten…';
        window.location.reload();
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-photo-upload-mode]').forEach((form) => {
        form.removeAttribute('aria-busy');
        const button = form.querySelector('[data-upload-submit]');

        if (button instanceof HTMLButtonElement) {
            button.removeAttribute('aria-busy');
            button.disabled = false;
            if (button.dataset.originalContent) {
                button.innerHTML = button.dataset.originalContent;
            }
        }
    });
});

document.querySelectorAll('[data-customer-picker]').forEach((picker) => {
    const customerSelect = picker.querySelector('[data-customer-select]');
    const propertySelect = picker.querySelector('[data-property-select]');
    const customerName = document.getElementById('customer_name');
    const customerEmail = document.getElementById('customer_email');
    const address = document.getElementById('address');

    if (
        ! (customerSelect instanceof HTMLSelectElement)
        || ! (propertySelect instanceof HTMLSelectElement)
        || ! (customerName instanceof HTMLInputElement)
        || ! (customerEmail instanceof HTMLInputElement)
        || ! (address instanceof HTMLInputElement)
    ) {
        return;
    }

    const selectedOption = (select) => select.selectedOptions.item(0);

    const syncPropertyOptions = ({ chooseSingleProperty = false } = {}) => {
        const customerId = customerSelect.value;
        const availableProperties = [];

        Array.from(propertySelect.options).forEach((option) => {
            const optionCustomerId = option.dataset.customerId;
            const isAvailable = ! optionCustomerId || (customerId !== '' && optionCustomerId === customerId);

            option.hidden = ! isAvailable;
            option.disabled = ! isAvailable;

            if (optionCustomerId && isAvailable) {
                availableProperties.push(option);
            }
        });

        const currentProperty = selectedOption(propertySelect);

        if (currentProperty?.disabled) {
            propertySelect.value = '';
        } else if (chooseSingleProperty && propertySelect.value === '' && availableProperties.length === 1) {
            propertySelect.value = availableProperties[0].value;
        }
    };

    const applyCustomer = ({ chooseSingleProperty = false } = {}) => {
        const customer = selectedOption(customerSelect);

        syncPropertyOptions({ chooseSingleProperty });

        if (customerSelect.value === '') {
            customerName.value = '';
            customerEmail.value = '';
            address.value = '';

            return;
        }

        customerName.value = customer?.dataset.customerName ?? '';
        customerEmail.value = customer?.dataset.customerEmail ?? '';
        const property = selectedOption(propertySelect);

        address.value = property?.dataset.propertyAddress ?? '';
    };

    customerSelect.addEventListener('change', () => applyCustomer({ chooseSingleProperty: true }));
    propertySelect.addEventListener('change', () => {
        const property = selectedOption(propertySelect);

        address.value = property?.dataset.propertyAddress ?? '';
    });
    syncPropertyOptions();
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

document.querySelectorAll('[data-copy-url]').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', async () => {
        const url = button.dataset.copyUrl;
        const original = button.textContent;
        const linkTools = button.closest('.share-link-tools');
        const input = linkTools?.querySelector('#share-link');
        const feedback = button.dataset.copyFeedback
            ? document.getElementById(button.dataset.copyFeedback)
            : null;

        try {
            if (! url || ! await copyText(url)) {
                throw new Error('Clipboard is unavailable.');
            }

            if (feedback) {
                feedback.textContent = 'Länken är kopierad.';
            }

            button.textContent = 'Kopierad';
            const recordForm = linkTools?.querySelector('form[data-record-copy]');

            if (recordForm instanceof HTMLFormElement) {
                fetch(recordForm.action, {
                    method: 'POST',
                    body: new FormData(recordForm),
                    keepalive: true,
                }).catch(() => {});
            }

            window.setTimeout(() => {
                button.textContent = original;
            }, 2000);
        } catch (error) {
            if (input instanceof HTMLInputElement) {
                input.focus();
                input.select();
            }

            if (feedback) {
                feedback.textContent = 'Markera länken och kopiera den manuellt.';
            }
        }
    });
});
