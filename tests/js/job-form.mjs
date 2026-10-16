import {
    END_AFTER_START,
    PHOTOS_NOT_ATTACHED,
    PHOTO_FORMAT,
    PHOTO_REQUIRED,
    PHOTO_TOO_LARGE,
    START_TIME_REQUIRED,
    jobFormErrors,
    selectionLimitMessage,
    tooManySelectedMessage,
} from '../../resources/js/job-form.js';

const fail = (message) => {
    console.error(message);
    process.exit(1);
};

const endOnly = jobFormErrors({
    start: '',
    end: '11:30',
    photosRequired: false,
    beforeFiles: [],
    afterFiles: [],
});

if (endOnly.performed_time?.[0] !== START_TIME_REQUIRED) {
    fail('end without start was accepted');
}

const backwards = jobFormErrors({
    start: '11:30',
    end: '09:00',
    beforeFiles: [],
    afterFiles: [],
});

if (backwards.performed_end_time?.[0] !== END_AFTER_START) {
    fail('an earlier end time was accepted');
}

const range = jobFormErrors({
    start: '09:00',
    end: '11:30',
    beforeFiles: [{ name: 'fore.jpg', type: 'image/jpeg', size: 1200 }],
    afterFiles: [],
});

if (Object.keys(range).length !== 0) {
    fail('a valid range was rejected');
}

const textFile = jobFormErrors({
    beforeFiles: [{ name: 'anteckning.txt', type: 'text/plain', size: 20 }],
    afterFiles: [],
});

if (textFile['before_photos.0']?.[0] !== PHOTO_FORMAT) {
    fail('a text file was accepted');
}

const oversized = jobFormErrors({
    beforeFiles: [{ name: 'stor.jpg', type: 'image/jpeg', size: (15 * 1024 * 1024) + 1 }],
    afterFiles: [],
});

if (oversized['before_photos.0']?.[0] !== PHOTO_TOO_LARGE) {
    fail('an oversized photo was accepted');
}

const missing = jobFormErrors({
    photosRequired: true,
    beforeFiles: [],
    afterFiles: [],
});

if (missing.before_photos?.[0] !== PHOTO_REQUIRED) {
    fail('an empty photo form was accepted');
}

const eleven = Array.from({ length: 11 }, () => ({ name: 'fore.jpg', type: 'image/jpeg', size: 1200 }));

if (tooManySelectedMessage(11) !== '11 bilder valda – max 10 per uppdrag. Ta bort 1.') {
    fail('eleven photos did not ask the user to remove one');
}

if (selectionLimitMessage(eleven) !== '11 bilder valda – max 10 per uppdrag. Ta bort 1.') {
    fail('selecting eleven photos did not explain the limit');
}

if (jobFormErrors({ beforeFiles: eleven, afterFiles: [] }).before_photos?.[0] !== '11 bilder valda – max 10 per uppdrag. Ta bort 1.') {
    fail('submitting eleven photos did not explain the limit');
}

const oversizedSelection = selectionLimitMessage([
    { name: 'stor.jpg', type: 'image/jpeg', size: (15 * 1024 * 1024) + 1 },
]);

if (oversizedSelection !== '1 bild är större än 15 MB. Ta bort den.') {
    fail('an oversized photo was not flagged on selection');
}

if (PHOTOS_NOT_ATTACHED !== 'De valda bilderna följde inte med. Spara igen utan att lämna sidan.') {
    fail('the missing-photo message changed');
}

console.log('ok');
