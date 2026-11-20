// Logs into the local demo server as the demo teacher and student and screenshots the pages used in the video.
// Click-through flows also record where each clicked element sits (as 0..1 coordinates) in assets/shots/flows.json.
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
import path from 'node:path';

const BASE = 'http://localhost:8123';
const OUT = path.resolve(import.meta.dirname, '../assets/shots');
const VW = 1440, VH = 900;
fs.mkdirSync(OUT, { recursive: true });
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await puppeteer.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true,
    defaultViewport: { width: VW, height: VH, deviceScaleFactor: 1.5 },
});

async function login(username, password) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.on('dialog', (d) => d.accept());
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
    await page.evaluateOnNewDocument(() => localStorage.setItem('ui-theme', 'light'));
    await page.goto(BASE + '/login', { waitUntil: 'networkidle0' });
    await page.type('input[name=username]', username);
    await page.type('input[name=password]', password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    return { ctx, page };
}

async function shot(page, name) {
    await wait(400);
    await page.screenshot({ path: path.join(OUT, name + '.png') });
    console.log('saved', name, page.url());
}

// Centre of an element in 0..1 page coordinates.
async function uv(page, selectorOrHandle) {
    const el = typeof selectorOrHandle === 'string' ? await page.$(selectorOrHandle) : selectorOrHandle;
    if (!el) throw new Error('missing ' + selectorOrHandle);
    const b = await el.boundingBox();
    return [+((b.x + b.width / 2) / VW).toFixed(4), +((b.y + b.height / 2) / VH).toFixed(4)];
}

const flows = {};

// ---------------- Teacher ----------------
{
    const { ctx, page } = await login('guru', 'guru123');
    for (const [name, url] of [
        ['t-dashboard', '/guru/dashboard'], ['t-subject', '/guru/subjects/2'], ['t-assignment', '/guru/assignments/1'],
        ['t-quiz', '/guru/quizzes/1'], ['t-discussion', '/guru/discussions/1'],
    ]) { await page.goto(BASE + url, { waitUntil: 'networkidle0' }); await shot(page, name); }

    // Rapor Otomatis: change the weight, save, then the Excel export.
    const f = [];
    await page.goto(BASE + '/guru/grades/2/1', { waitUntil: 'networkidle0' });
    await shot(page, 't-grades');
    await shot(page, 'rp-1');
    f.push({ img: 'rp-1', target: await uv(page, '#assignment_weight') });
    await page.click('#assignment_weight', { clickCount: 3 });
    await page.type('#assignment_weight', '70');
    await shot(page, 'rp-2');
    f.push({ img: 'rp-2', target: await uv(page, 'form[data-report-weights] button[type=submit]') });
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('form[data-report-weights] button[type=submit]')]);
    await shot(page, 'rp-3');
    f.push({ img: 'rp-3', target: await uv(page, 'a[href$="/export"]') });
    // Fetch the real export and render it like a spreadsheet window.
    const xlsxB64 = await page.evaluate(async () => {
        const r = await fetch(document.querySelector('a[href$="/export"]').href, { credentials: 'same-origin' });
        const buf = new Uint8Array(await r.arrayBuffer()); let s = ''; for (const b of buf) s += String.fromCharCode(b); return btoa(s);
    });
    fs.writeFileSync(path.join(import.meta.dirname, 'export-rapor.xlsx'), Buffer.from(xlsxB64, 'base64'));
    flows.rapor = f;
    await ctx.close();
}

// ---------------- Student ----------------
{
    const { ctx, page } = await login('siswa', 'siswa123');
    for (const [name, url] of [
        ['s-dashboard', '/siswa/dashboard'], ['s-subjects', '/siswa/subjects'], ['s-grades', '/siswa/grades'],
        ['s-discussion', '/siswa/discussions/1'], ['s-announcements', '/siswa/announcements'],
    ]) { await page.goto(BASE + url, { waitUntil: 'networkidle0' }); await shot(page, name); }

    // Pratinjau tanpa unduh: PDF, Word and Excel in the preview pop-up.
    const pv = [];
    const openPreview = async (url, name, withPage) => {
        await page.goto(BASE + url, { waitUntil: 'networkidle0' });
        const btn = await page.$('[data-bs-target="#filePreviewModal"]');
        if (withPage) { await shot(page, withPage); pv.push({ img: withPage, target: await uv(page, btn) }); }
        await btn.click(); await wait(3000);
        await shot(page, name);
        pv.push({ img: name, target: null });
    };
    await openPreview('/siswa/subjects/2/materials/1', 'pv-pdf', 'pv-page');
    await openPreview('/siswa/subjects/3/materials/6', 'pv-docx');
    await openPreview('/siswa/subjects/2/materials/7', 'pv-xlsx');
    await shot(page, 's-preview');
    flows.preview = pv;

    // Kuis: start, answer, next, submit, result.
    const qz = [];
    const correct = ['62', '255.255.255.224', '192.168.10.15', 'B', 'DHCP'];
    await page.goto(BASE + '/siswa/quizzes/1', { waitUntil: 'networkidle0' });
    await shot(page, 'qz-1');
    const startSel = 'form[action$="/start"] button[type=submit]';
    qz.push({ img: 'qz-1', target: await uv(page, startSel) });
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click(startSel)]);
    for (let q = 0; q < 5; q++) {
        const labels = await page.$$('#quizForm .form-check');
        let pick = labels[0];
        for (const l of labels) {
            const txt = (await l.evaluate((e) => e.textContent)).replace(/^\s*[A-E]\.\s*/, '').trim();
            if (correct.includes(txt)) pick = l;
        }
        const radio = await pick.$('input[type=radio]');
        const label = (await pick.$('label')) ?? radio;
        if (q === 0) { await shot(page, 'qz-2'); qz.push({ img: 'qz-2', target: await uv(page, label) }); }
        await radio.click(); await wait(900);
        const isLast = (await page.$('#quizNext')) === null;
        if (q === 0) { await shot(page, 'qz-3'); qz.push({ img: 'qz-3', target: await uv(page, '#quizNext') }); }
        if (isLast) {
            await shot(page, 'qz-4'); qz.push({ img: 'qz-4', target: await uv(page, '#quizSubmit') });
            await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('#quizSubmit')]);
            break;
        }
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('#quizNext')]);
    }
    await shot(page, 'qz-5'); qz.push({ img: 'qz-5', target: null });
    await shot(page, 's-quiz-attempt');
    flows.quiz = qz;

    // Konversi PDF: choose photos, convert, check the PDF really comes back.
    const pd = [];
    await page.goto(BASE + '/siswa/pdf-converter', { waitUntil: 'networkidle0' });
    await shot(page, 'pd-1'); pd.push({ img: 'pd-1', target: await uv(page, '#files') });
    const input = await page.$('#files');
    await input.uploadFile(...[1, 2, 3].map((i) => path.join(import.meta.dirname, `foto-tugas-${i}.jpg`)));
    await wait(300);
    await shot(page, 'pd-2'); pd.push({ img: 'pd-2', target: await uv(page, '#convertButton') });
    const pdfInfo = await page.evaluate(async () => {
        const form = document.getElementById('pdfConverterForm');
        const r = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
        return { status: r.status, type: r.headers.get('content-type'), size: (await r.arrayBuffer()).byteLength };
    });
    console.log('pdf converter response', pdfInfo);
    flows.pdf = pd;
    flows.pdfCheck = pdfInfo;
    await ctx.close();
}

fs.writeFileSync(path.join(OUT, 'flows.json'), JSON.stringify(flows, null, 2));
await browser.close();
