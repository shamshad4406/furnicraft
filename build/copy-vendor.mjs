/**
 * Copies the vendor runtime files the Blade layouts link to.
 *
 * The build does not use a bundler: Application Control on the build machine
 * blocks Rollup's native binary, so styles are compiled with the pure-JS Sass
 * CLI and vendor JS is copied as-is. See package.json scripts.
 */
import { cp, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const jobs = [
    ['node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'public/assets/js/bootstrap.bundle.min.js'],
    ['node_modules/bootstrap-icons/font/fonts', 'public/assets/css/fonts'],
];

for (const [from, to] of jobs) {
    const target = resolve(root, to);
    await mkdir(dirname(target), { recursive: true });
    await cp(resolve(root, from), target, { recursive: true });
    console.log(`copied ${from} -> ${to}`);
}
