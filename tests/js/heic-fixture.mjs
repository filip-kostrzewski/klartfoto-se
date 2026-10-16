import { readFileSync } from 'node:fs';
import { bytesLookLikeHeic, decodeHeic, fileLooksLikeHeic } from '../../resources/js/heic.js';

const fail = (message) => {
    console.error(message);
    process.exit(1);
};

const brandBox = (major, compatible = []) => {
    const size = 16 + (compatible.length * 4);
    const bytes = new Uint8Array(size);
    const view = new DataView(bytes.buffer);

    view.setUint32(0, size);
    bytes.set([0x66, 0x74, 0x79, 0x70], 4);
    bytes.set(Array.from(major, (character) => character.charCodeAt(0)), 8);
    compatible.forEach((brand, index) => {
        bytes.set(Array.from(brand, (character) => character.charCodeAt(0)), 16 + (index * 4));
    });

    return bytes;
};

const fixturePath = new URL('../Fixtures/room.heic', import.meta.url);
const bytes = new Uint8Array(readFileSync(fixturePath));
const head = Buffer.from(bytes.subarray(0, 32)).toString('latin1');

if (! head.includes('ftypheic') && ! head.includes('ftypmif1')) {
    fail('fixture is missing ftypheic/ftypmif1');
}

if (! bytesLookLikeHeic(bytes)) {
    fail('fixture magic was not detected');
}

if (! bytesLookLikeHeic(brandBox('mif1', ['heic']))) {
    fail('ftypmif1 was not detected');
}

if (! bytesLookLikeHeic(brandBox('heic'))) {
    fail('ftypheic was not detected');
}

const jpeg = new Uint8Array([0xff, 0xd8, 0xff, 0xe0, 0x00, 0x10]);

if (bytesLookLikeHeic(jpeg)) {
    fail('a jpeg was treated as HEIC');
}

const misnamedJpeg = new File([jpeg], 'photo.heic', { type: 'image/heic' });

if (await fileLooksLikeHeic(misnamedJpeg)) {
    fail('a jpeg named .heic was treated as HEIC');
}

const unnamed = new File([bytes], 'image', { type: 'application/octet-stream' });

if (! await fileLooksLikeHeic(unnamed)) {
    fail('HEIC magic was ignored when the name and MIME type were generic');
}

const image = await decodeHeic(bytes);

if (image.width !== 40 || image.height !== 30) {
    fail(`unexpected size ${image.width}x${image.height}`);
}

const [red, green, blue, alpha] = image.data;

if (red !== 220 || green !== 30 || blue !== 40 || alpha !== 255) {
    fail(`unexpected pixel ${red},${green},${blue},${alpha}`);
}

console.log('ok');
