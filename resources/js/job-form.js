export const START_TIME_REQUIRED = 'Ange en starttid när sluttiden är ifylld.';

export const END_AFTER_START = 'Sluttiden måste vara senare än starttiden.';

export const PHOTO_FORMAT = 'Filen är inte en bild. Använd JPEG, PNG, WebP, HEIC eller HEIF.';

export const PHOTO_TOO_LARGE = 'Varje bild får vara högst 15 MB.';

export const PHOTO_RESOLUTION = 'En bild har för hög upplösning. Prova en mindre bild.';

export const PHOTO_REQUIRED = 'Lägg till minst en före- eller efterbild.';

export const PHOTOS_NOT_ATTACHED = 'De valda bilderna följde inte med. Spara igen utan att lämna sidan.';

export const tooManySelectedMessage = (count) => {
    const selected = Number(count) || 0;

    if (selected <= 10) {
        return '';
    }

    const extra = selected - 10;

    return `${selected} bilder valda – max 10 per uppdrag. Ta bort ${extra}.`;
};

export const oversizedSelectionMessage = (count) => {
    const oversized = Number(count) || 0;

    if (oversized <= 0) {
        return '';
    }

    if (oversized === 1) {
        return '1 bild är större än 15 MB. Ta bort den.';
    }

    return `${oversized} bilder är större än 15 MB. Ta bort dem.`;
};

export const selectionLimitMessage = (files) => {
    const list = Array.isArray(files) ? files : [];
    const messages = [
        tooManySelectedMessage(list.length),
        oversizedSelectionMessage(list.filter((file) => Number(file?.size || 0) > MAX_BYTES).length),
    ].filter((message) => message !== '');

    return messages.join(' ');
};

const IMAGE_EXTENSION = /\.(jpe?g|png|webp|heic|heif)$/i;

const IMAGE_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/heic',
    'image/heif',
    'image/heic-sequence',
    'image/heif-sequence',
]);

const MAX_BYTES = 15 * 1024 * 1024;

export const photoFileError = (file) => {
    const size = Number(file?.size || 0);
    const type = String(file?.type || '').toLowerCase();
    const name = String(file?.name || '');

    if (size > MAX_BYTES) {
        return PHOTO_TOO_LARGE;
    }

    if (IMAGE_TYPES.has(type) || type.startsWith('image/') || IMAGE_EXTENSION.test(name)) {
        return null;
    }

    if (type !== '' || name.includes('.')) {
        return PHOTO_FORMAT;
    }

    return null;
};

const photoListErrors = (files, key) => {
    if (files.length > 10) {
        return { [key]: [tooManySelectedMessage(files.length)] };
    }

    /** @type {Record<string, string[]>} */
    const errors = {};

    files.forEach((file, index) => {
        const message = photoFileError(file);

        if (message) {
            errors[`${key}.${index}`] = [message];
        }
    });

    return errors;
};

/**
 * @param {{ start?: string, end?: string, photosRequired?: boolean, beforeFiles?: Array<{size?: number, type?: string, name?: string}>, afterFiles?: Array<{size?: number, type?: string, name?: string}> }} input
 * @return {Record<string, string[]>}
 */
export const jobFormErrors = (input) => {
    const start = String(input.start || '');
    const end = String(input.end || '');
    const beforeFiles = input.beforeFiles ?? [];
    const afterFiles = input.afterFiles ?? [];
    /** @type {Record<string, string[]>} */
    const errors = {};

    if (end !== '' && start === '') {
        errors.performed_time = [START_TIME_REQUIRED];
    }

    if (start !== '' && end !== '' && /^\d{2}:\d{2}$/.test(start) && /^\d{2}:\d{2}$/.test(end) && end <= start) {
        errors.performed_end_time = [END_AFTER_START];
    }

    Object.assign(errors, photoListErrors(beforeFiles, 'before_photos'));
    Object.assign(errors, photoListErrors(afterFiles, 'after_photos'));

    if (input.photosRequired && beforeFiles.length === 0 && afterFiles.length === 0 && ! errors.before_photos) {
        errors.before_photos = [PHOTO_REQUIRED];
    }

    return errors;
};
