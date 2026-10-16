import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

const filesDir = '/tmp/klartfoto-e2e-files';
const photo = `${filesDir}/fore.jpg`;
const fakePhoto = `${filesDir}/fake.jpg`;
const oversized = `${filesDir}/oversized.jpg`;

const login = async (page) => {
    await page.goto('/login');
    await page.locator('#email').fill('e2e-foto@klartfoto.test');
    await page.locator('#password').fill('secret-password');
    await page.getByRole('button', { name: 'Logga in' }).click();
    await expect(page).toHaveURL(/\/uppdrag$/);
};

const createDraft = async (page) => {
    await page.goto('/uppdrag/nytt');
    await page.locator('#customer_name').fill('E2E Kund');
    await page.locator('#address').fill('Testgatan 1');
    await page.locator('#performed_end_time').fill('11:30');
    await page.getByRole('button', { name: 'Spara och lägg till bilder' }).click();
    await expect(page.getByRole('alert')).toContainText('Ange en starttid när sluttiden är ifylld.');
    await expect(page).toHaveURL(/\/uppdrag\/nytt$/);
    await page.locator('#performed_time').fill('09:00');
    await page.getByRole('button', { name: 'Spara och lägg till bilder' }).click();
    await expect(page).toHaveURL(/\/uppdrag\/\d+$/);
};

const beforePicker = (page) => page.locator('[data-photo-picker]').filter({ has: page.locator('#before_photos') });

const elevenPhotos = Array.from({ length: 11 }, (_, index) => `${filesDir}/fore-${index + 1}.jpg`);

test.beforeAll(() => {
    mkdirSync(filesDir, { recursive: true });
    execFileSync('php', ['-r', 'if (! extension_loaded("gd")) { fwrite(STDERR, "gd missing\\n"); exit(1); } $image = imagecreatetruecolor(40, 30); imagefill($image, 0, 0, imagecolorallocate($image, 220, 30, 40)); if (! imagejpeg($image, getenv("E2E_JPEG"), 90)) { exit(1); }'], {
        env: { ...process.env, E2E_JPEG: photo },
    });
    elevenPhotos.forEach((path) => copyFileSync(photo, path));
    writeFileSync(fakePhoto, 'this is not a jpeg');
    writeFileSync(oversized, Buffer.alloc((15 * 1024 * 1024) + 1));
});

test('eleven photos and a file over 15 MB are rejected before upload', async ({ page }) => {
    const uploads = [];
    page.on('request', (request) => {
        if (request.method() === 'POST' && new URL(request.url()).pathname.endsWith('/foton')) {
            uploads.push(request.url());
        }
    });

    await login(page);
    await createDraft(page);
    await page.locator('#before_photos').setInputFiles(elevenPhotos);
    await expect(beforePicker(page).locator('[data-photo-error]')).toBeVisible();
    await expect(beforePicker(page).locator('[data-photo-error]')).toHaveText('11 bilder valda – max 10 per uppdrag. Ta bort 1.');
    expect(uploads).toEqual([]);

    await page.reload();
    await page.locator('#before_photos').setInputFiles(oversized);
    await expect(beforePicker(page).locator('[data-photo-error]')).toBeVisible();
    await expect(beforePicker(page).locator('[data-photo-error]')).toHaveText('1 bild är större än 15 MB. Ta bort den.');
    expect(uploads).toEqual([]);
});

test('selected photos survive a validation error and the job saves them', async ({ page }) => {
    const documentUploads = [];
    page.on('request', (request) => {
        const url = new URL(request.url());

        if (request.method() === 'POST' && request.resourceType() === 'document' && url.pathname.endsWith('/foton')) {
            documentUploads.push(url.pathname);
        }
    });

    await login(page);
    await createDraft(page);
    await page.locator('#before_photos').setInputFiles(fakePhoto);
    await expect(beforePicker(page).locator('[data-photo-count]')).toHaveText('1 bild vald');

    const rejected = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/foton'));
    await page.getByRole('button', { name: 'Ladda upp nya bildpar' }).click();
    const rejection = await rejected;
    expect(rejection.status()).toBe(422);
    expect(rejection.headers()['content-type'] ?? '').toContain('application/json');
    await expect(page.locator('[data-form-errors]')).toContainText('Filen är inte en bild. Använd JPEG, PNG, WebP, HEIC eller HEIF.');
    await expect(beforePicker(page).locator('[data-photo-count]')).toHaveText('1 bild vald');
    await expect(page).toHaveURL(/\/uppdrag\/\d+$/);
    await expect.poll(() => page.locator('#before_photos').evaluate((input) => input.files.length)).toBe(1);
    expect(documentUploads).toEqual([]);

    await page.getByRole('button', { name: 'Ta bort bild 1' }).click();
    await page.locator('#before_photos').setInputFiles(photo);
    await expect(beforePicker(page).locator('[data-photo-count]')).toHaveText('1 bild vald');
    await page.locator('[data-upload-submit]').click();
    await expect(page.locator('.photo-pair-count')).toHaveText('1 bild');
    await expect(page.getByRole('heading', { name: 'Inga bilder ännu' })).toHaveCount(0);
    expect(documentUploads).toEqual([]);
});
