/** Copies the app's own scripts into public/assets/js. */
import { cp, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

for (const name of ['app.js', 'admin.js', 'invoice.js', 'line-editor.js', 'analytics.js']) {
    const target = resolve(root, 'public/assets/js', name);
    await mkdir(dirname(target), { recursive: true });
    await cp(resolve(root, 'resources/js', name), target);
    console.log(`copied resources/js/${name} -> public/assets/js/${name}`);
}
