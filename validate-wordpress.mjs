import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));
const required = [
    'commerciale-ai-theme/style.css',
    'commerciale-ai-theme/functions.php',
    'commerciale-ai-theme/header.php',
    'commerciale-ai-theme/footer.php',
    'commerciale-ai-theme/front-page.php',
    'commerciale-ai-theme/page.php',
    'commerciale-ai-theme/inc/site-structure.php',
    'commerciale-ai-client/commerciale-ai-client.php',
    'commerciale-ai-client/assets/client-area.css',
    'standalone/wp-content/mu-plugins/commerciale-ai-bootstrap.php',
];

let failed = false;
for (const relative of required) {
    const filename = path.join(root, relative);
    if (!fs.existsSync(filename) || fs.statSync(filename).size === 0) {
        console.error(`Manca il file richiesto: ${relative}`);
        failed = true;
    }
}

const phpFiles = [];
function collect(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const filename = path.join(directory, entry.name);
        if (entry.isDirectory() && !['dist', 'standalone'].includes(entry.name)) collect(filename);
        else if (entry.isFile() && entry.name.endsWith('.php')) phpFiles.push(filename);
    }
}
collect(root);
phpFiles.push(path.join(root, 'standalone/wp-content/mu-plugins/commerciale-ai-bootstrap.php'));

function validateDelimiters(filename) {
    const source = fs.readFileSync(filename, 'utf8');
    const stack = [];
    const pairs = { ')': '(', ']': '[', '}': '{' };
    let state = 'code';
    for (let index = 0; index < source.length; index += 1) {
        const char = source[index];
        const next = source[index + 1];
        if (state === 'line') { if (char === '\n') state = 'code'; continue; }
        if (state === 'block') { if (char === '*' && next === '/') { state = 'code'; index += 1; } continue; }
        if (state === 'single') { if (char === '\\') index += 1; else if (char === "'") state = 'code'; continue; }
        if (state === 'double') { if (char === '\\') index += 1; else if (char === '"') state = 'code'; continue; }
        if (char === '/' && next === '/') { state = 'line'; index += 1; continue; }
        if (char === '#') { state = 'line'; continue; }
        if (char === '/' && next === '*') { state = 'block'; index += 1; continue; }
        if (char === "'") { state = 'single'; continue; }
        if (char === '"') { state = 'double'; continue; }
        if ('([{'.includes(char)) stack.push(char);
        if (')]}'.includes(char) && stack.pop() !== pairs[char]) return false;
    }
    return stack.length === 0 && !['single', 'double', 'block'].includes(state);
}

for (const filename of phpFiles) {
    if (!validateDelimiters(filename)) {
        console.error(`Delimitatori PHP non bilanciati: ${path.relative(root, filename)}`);
        failed = true;
    }
}

const structure = fs.readFileSync(path.join(root, 'commerciale-ai-theme/inc/site-structure.php'), 'utf8');
for (const marker of [
    "'prodotto' =>", "'acquisizione-lead' =>", "'qualificazione-ai' =>", "'risposte-conversazioni' =>",
    "'pipeline-follow-up' =>", "'knowledge-base' =>", "'team-sicurezza-consumi' =>", "'soluzioni' =>",
    "'professionisti' =>", "'team-commerciali' =>", "'agenzie-b2b' =>", "'come-funziona' =>",
    "'prezzi' =>", "'faq' =>", "'contatti' =>",
]) {
    if (!structure.includes(marker)) {
        console.error(`Alberatura incompleta: ${marker}`);
        failed = true;
    }
}

for (const [source, installed] of [
    ['commerciale-ai-theme', 'standalone/wp-content/themes/commerciale-ai-theme'],
    ['commerciale-ai-client', 'standalone/wp-content/plugins/commerciale-ai-client'],
]) {
    const sourceRoot = path.join(root, source);
    const installedRoot = path.join(root, installed);
    const compare = directory => {
        for (const entry of fs.readdirSync(directory, {withFileTypes: true})) {
            const filename = path.join(directory, entry.name);
            if (entry.isDirectory()) compare(filename);
            else {
                const relative = path.relative(sourceRoot, filename);
                const installedFile = path.join(installedRoot, relative);
                if (!fs.existsSync(installedFile) || !fs.readFileSync(filename).equals(fs.readFileSync(installedFile))) {
                    console.error(`Copia standalone non sincronizzata: ${relative}`);
                    failed = true;
                }
            }
        }
    };
    compare(sourceRoot);
}

for (const forbidden of ['wp-config.php', 'standalone/wp-config.php']) {
    if (fs.existsSync(path.join(root, forbidden))) {
        console.error(`Configurazione reale da non versionare: ${forbidden}`);
        failed = true;
    }
}

const plugin = fs.readFileSync(path.join(root, 'commerciale-ai-client/commerciale-ai-client.php'), 'utf8');
for (const marker of ['register_activation_hook', 'valid_stripe_signature', 'Idempotency-Key', 'customer.subscription.', 'commerciale_ai_pricing', 'commerciale_ai_account']) {
    if (!plugin.includes(marker)) {
        console.error(`Controllo plugin non superato: ${marker}`);
        failed = true;
    }
}

if (failed) process.exit(1);
console.log(`Validati ${phpFiles.length} file PHP e ${required.length} artefatti obbligatori.`);
