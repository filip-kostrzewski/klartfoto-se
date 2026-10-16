const HEIC_EXTENSION = /\.(heic|heif)$/i;

const HEIC_TYPES = new Set([
    'image/heic',
    'image/heif',
    'image/heic-sequence',
    'image/heif-sequence',
]);

// Major and compatible brands. ftypheic and ftypmif1 are the iPhone cases.
const HEIC_BRANDS = new Set([
    'heic',
    'heix',
    'hevc',
    'hevx',
    'heim',
    'heis',
    'hevm',
    'hevs',
    'mif1',
    'msf1',
]);

let libheifPromise;

const ascii = (bytes, offset, length) => {
    let value = '';

    for (let index = 0; index < length; index += 1) {
        value += String.fromCharCode(bytes[offset + index]);
    }

    return value;
};

const readUint32 = (bytes, offset) => (
    ((bytes[offset] << 24) | (bytes[offset + 1] << 16) | (bytes[offset + 2] << 8) | bytes[offset + 3]) >>> 0
);

const bytesLookLikeBrowserImage = (bytes) => {
    if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) {
        return true;
    }

    if (bytes.length >= 4 && bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47) {
        return true;
    }

    return bytes.length >= 12 && ascii(bytes, 0, 4) === 'RIFF' && ascii(bytes, 8, 4) === 'WEBP';
};

const claimsHeic = (file) => {
    const type = String(file.type || '').toLowerCase();

    return HEIC_TYPES.has(type) || HEIC_EXTENSION.test(String(file.name || ''));
};

export const bytesLookLikeHeic = (bytes) => {
    if (! (bytes instanceof Uint8Array) || bytes.length < 12 || ascii(bytes, 4, 4) !== 'ftyp') {
        return false;
    }

    const boxSize = readUint32(bytes, 0);
    const limit = boxSize >= 16 && boxSize <= bytes.length ? boxSize : Math.min(bytes.length, 64);

    if (HEIC_BRANDS.has(ascii(bytes, 8, 4))) {
        return true;
    }

    for (let offset = 16; offset + 4 <= limit; offset += 4) {
        if (HEIC_BRANDS.has(ascii(bytes, offset, 4))) {
            return true;
        }
    }

    return false;
};

export const fileLooksLikeHeic = async (file) => {
    const head = new Uint8Array(await file.slice(0, 64).arrayBuffer());

    if (bytesLookLikeBrowserImage(head)) {
        return false;
    }

    return bytesLookLikeHeic(head) || claimsHeic(file);
};

export const jpegFileName = (name) => {
    const withoutHeic = String(name || '').replace(HEIC_EXTENSION, '');
    const base = withoutHeic.replace(/\.[^.]+$/, '') || withoutHeic || 'bild';

    return `${base || 'bild'}.jpg`;
};

const loadLibheif = () => {
    libheifPromise ??= import('libheif-js/libheif-wasm/libheif-bundle.mjs').then((loaded) => {
        const factory = loaded.default;

        return typeof factory === 'function' ? factory() : factory;
    });

    return libheifPromise;
};

export const decodeHeic = async (bytes) => {
    const libheif = await loadLibheif();
    const decoder = new libheif.HeifDecoder();
    const images = decoder.decode(bytes);
    const image = images?.[0];

    if (! image) {
        throw new Error('heic');
    }

    const width = image.get_width();
    const height = image.get_height();

    if (! width || ! height) {
        throw new Error('heic');
    }

    const displayed = await new Promise((resolve, reject) => {
        image.display({ data: new Uint8ClampedArray(width * height * 4), width, height }, (result) => {
            if (! result) {
                reject(new Error('heic'));

                return;
            }

            resolve(result);
        });
    });

    return {
        width,
        height,
        data: displayed.data,
    };
};

const rgbaToJpegBlob = (width, height, pixels) => new Promise((resolve, reject) => {
    const scale = Math.min(1, 2000 / Math.max(width, height, 1));
    const targetWidth = Math.max(1, Math.round(width * scale));
    const targetHeight = Math.max(1, Math.round(height * scale));
    const source = document.createElement('canvas');
    source.width = width;
    source.height = height;
    const sourceContext = source.getContext('2d');

    if (! sourceContext) {
        reject(new Error('heic'));

        return;
    }

    sourceContext.putImageData(new ImageData(new Uint8ClampedArray(pixels), width, height), 0, 0);

    const canvas = scale === 1 ? source : document.createElement('canvas');

    if (scale !== 1) {
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        const context = canvas.getContext('2d');

        if (! context) {
            reject(new Error('heic'));

            return;
        }

        context.drawImage(source, 0, 0, targetWidth, targetHeight);
    }

    canvas.toBlob((blob) => {
        if (! blob) {
            reject(new Error('heic'));

            return;
        }

        resolve(blob);
    }, 'image/jpeg', 0.9);
});

export const heicFileToJpegFile = async (file) => {
    const decoded = await decodeHeic(new Uint8Array(await file.arrayBuffer()));

    if (typeof document === 'undefined') {
        throw new Error('heic');
    }

    const blob = await rgbaToJpegBlob(decoded.width, decoded.height, decoded.data);

    return new File([blob], jpegFileName(file.name), {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
};
