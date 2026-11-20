// Records guided interactions on the local demo server frame by frame (60 fps of video time).
// Each frame: move the real mouse (hover states), draw a guide cursor into the page, screenshot.
// Output: out/rec/<segment>/00000.jpg … and out/rec/manifest.json (frame counts, cursor path, step marks).
//   node capture/record.mjs [segment …]     (no args = all segments)
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
import path from 'node:path';

const BASE = 'http://localhost:8123';
const ROOT = path.resolve(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'out/rec');
const VW = 1440, VH = 900, DSF = 1.6, FPS = 60; // DSF 1.6 keeps text sharp when the monitor zooms in
// LOG_ONLY=1 replays a session without screenshots and only logs click/keystroke frames to out/rec/events.json
// (frame counts are identical to a real recording, so the events line up with the existing frames).
const LOG_ONLY = process.env.LOG_ONLY === '1';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const ease = (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2);
// CSS-style cubic-bezier easing. Cursor uses a "fast in" curve: quick travel, long soft landing on the target.
function bezier(x1, y1, x2, y2) {
    const B = (t, a, b) => 3 * a * t * (1 - t) ** 2 + 3 * b * t * t * (1 - t) + t ** 3;
    return (x) => { let lo = 0, hi = 1; for (let i = 0; i < 30; i++) { const m = (lo + hi) / 2; if (B(m, x1, x2) < x) lo = m; else hi = m; } return B((lo + hi) / 2, y1, y2); };
}
const cursorEase = bezier(0.3, 0, 0.1, 1);

// Injected into every page: guide cursor + click ripple, and a virtual clock so on-page timers run at video speed.
const PAGE_SETUP = () => {
    const realNow = Date.now.bind(Date);
    const start = realNow();
    window.__vt = 0;
    Date.now = () => start + window.__vt;
    try { localStorage.setItem('ui-theme', 'light'); } catch {}
    const css = `#__cur{position:fixed;left:0;top:0;width:30px;height:36px;z-index:2147483647;pointer-events:none;filter:drop-shadow(0 4px 6px rgba(15,18,34,.35));transform-origin:3px 2px}
      #__rip{position:fixed;left:0;top:0;width:70px;height:70px;margin:-35px 0 0 -35px;border-radius:50%;z-index:2147483646;pointer-events:none;border:4px solid #7C3AED;background:rgba(124,58,237,.18);opacity:0}`;
    const add = () => {
        if (document.getElementById('__cur')) return;
        const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
        const c = document.createElement('div'); c.id = '__cur';
        c.innerHTML = '<svg width="30" height="36" viewBox="0 0 44 52"><path d="M4 3 L4 40 L14 31 L21 47 L28 44 L21 28 L35 28 Z" fill="#fff" stroke="#0F1222" stroke-width="3.2" stroke-linejoin="round"/></svg>';
        const r = document.createElement('div'); r.id = '__rip';
        document.body.append(c, r);
    };
    window.__cur = (x, y, press, rip, vt) => {
        add(); window.__vt = vt;
        const c = document.getElementById('__cur'), r = document.getElementById('__rip');
        c.style.transform = `translate(${x - 3}px, ${y - 2}px) scale(${1 - press * 0.2})`;
        if (rip > 0 && rip < 1) { r.style.opacity = String(1 - rip); r.style.transform = `translate(${x}px, ${y}px) scale(${0.3 + rip * 1.4})`; }
        else r.style.opacity = '0';
    };
    document.addEventListener('DOMContentLoaded', add);
};

class Recorder {
    constructor(page, name) {
        this.page = page; this.name = name; this.n = 0; this.cur = [VW * 0.62, VH * 0.82];
        this.press = 0; this.rip = -1; this.path = []; this.marks = {}; this.zoom = []; this.navs = []; this.events = [];
        this.dir = path.join(OUT, name);
        if (!LOG_ONLY) { fs.rmSync(this.dir, { recursive: true, force: true }); fs.mkdirSync(this.dir, { recursive: true }); }
    }
    async frame() {
        const [x, y] = this.cur;
        await this.page.mouse.move(x, y);
        await this.page.evaluate((x, y, p, r, vt) => window.__cur?.(x, y, p, r, vt), x, y, this.press, this.rip, this.n * 1000 / FPS).catch(() => {});
        if (!LOG_ONLY) await this.page.screenshot({ path: path.join(this.dir, String(this.n).padStart(5, '0') + '.jpg'), type: 'jpeg', quality: 86, optimizeForSpeed: true });
        this.path.push([+(x / VW).toFixed(4), +(y / VH).toFixed(4)]);
        this.n++;
    }
    async hold(sec) { for (let i = 0, n = Math.round(sec * FPS); i < n; i++) await this.frame(); }
    mark(name) { this.marks[name] = this.n; }
    setZoom(z) { this.zoom.push([this.n, z]); }
    async point(target) {
        if (Array.isArray(target)) return target;
        const el = typeof target === 'string' ? await this.page.$(target) : target;
        if (!el) throw new Error(`${this.name}: missing ${target}`);
        // Bring off-screen targets into view with a smooth scroll instead of a jump.
        const need = await el.evaluate((e) => { const b = e.getBoundingClientRect(); return b.top < 90 || b.bottom > innerHeight - 60 ? scrollY + b.top - innerHeight * 0.45 : null; });
        if (need !== null) await this.scroll(Math.max(0, need), 0.7);
        const b = await el.boundingBox();
        return [b.x + Math.min(b.width / 2, 60), b.y + b.height / 2];
    }
    // Cursor moves: quick (about 1300 px/s, at most 0.9s) with a soft landing on the target.
    async move(target, sec = 0.8) {
        const to = await this.point(target), from = [...this.cur];
        const dist = Math.hypot(to[0] - from[0], to[1] - from[1]);
        sec = Math.max(0.35, Math.min(0.9, Math.max(sec * 0.55, dist / 1300)));
        const n = Math.max(1, Math.round(sec * FPS)), arc = Math.min(60, dist * 0.12);
        for (let i = 1; i <= n; i++) {
            const k = cursorEase(i / n);
            this.cur = [from[0] + (to[0] - from[0]) * k, from[1] + (to[1] - from[1]) * k - Math.sin(Math.PI * k) * arc];
            await this.frame();
        }
    }
    // Ripple + press animation; `act` performs the real interaction in the middle of it.
    async click(act = 'click', { nav = false } = {}) {
        const n = Math.round(0.4 * FPS), hit = Math.round(0.1 * FPS);
        for (let i = 0; i < n; i++) {
            this.press = Math.exp(-Math.pow((i - hit) / (FPS / 15), 2)); this.rip = i / n;
            if (i === hit) {
                this.events.push(['click', this.n, +(this.cur[0] / VW).toFixed(3)]);
                const doIt = async () => {
                    if (act === 'click') await this.page.mouse.click(...this.cur);
                    else if (act === 'triple') await this.page.mouse.click(...this.cur, { clickCount: 3 });
                    else if (typeof act === 'function') await act();
                };
                if (nav) { await Promise.all([this.page.waitForNavigation({ waitUntil: 'networkidle0' }), doIt()]); this.navs.push(this.n); }
                else await doIt();
            }
            await this.frame();
        }
        this.press = 0; this.rip = -1;
    }
    async type(text, cps = 14) {
        const per = Math.max(1, Math.round(FPS / cps));
        for (const ch of text) { this.events.push(['key', this.n, ch === ' ' ? 1 : 0]); await this.page.keyboard.type(ch); for (let i = 0; i < per; i++) await this.frame(); }
    }
    // Smooth scroll of the window (or an element) to a y offset or so that a selector sits at `at` of the viewport.
    async scroll(to, sec = 1.2, { el = null, at = 0.3 } = {}) {
        const page = this.page;
        const get = () => page.evaluate((s) => (s ? document.querySelector(s).scrollTop : scrollY), el);
        const from = await get();
        let target = to;
        if (typeof to === 'string') target = await page.evaluate((s, at) => { const b = document.querySelector(s).getBoundingClientRect(); return scrollY + b.top - innerHeight * at; }, to, at);
        const n = Math.round(sec * FPS);
        for (let i = 1; i <= n; i++) {
            const y = from + (target - from) * ease(i / n);
            await page.evaluate((s, y) => (s ? document.querySelector(s).scrollTo({ top: y, behavior: 'instant' }) : scrollTo({ top: y, behavior: 'instant' })), el, y);
            await this.frame();
        }
    }
    save() { return { fps: FPS, frames: this.n, path: this.path, marks: this.marks, zoom: this.zoom, navs: this.navs }; }
}

const browser = await puppeteer.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true, defaultViewport: { width: VW, height: VH, deviceScaleFactor: DSF },
});
async function session(user, pass) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.on('dialog', (d) => d.accept());
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
    await page.evaluateOnNewDocument(PAGE_SETUP);
    const cdp = await page.createCDPSession();
    await cdp.send('Browser.setDownloadBehavior', { behavior: 'deny' }).catch(() => {});
    await cdp.send('Animation.enable');
    await cdp.send('Animation.setPlaybackRate', { playbackRate: 0.12 });
    await page.goto(BASE + '/login', { waitUntil: 'networkidle0' });
    await page.type('input[name=username]', user);
    await page.type('input[name=password]', pass);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    return page;
}
const go = (page, url) => page.goto(BASE + url, { waitUntil: 'networkidle0' });
const byText = async (page, selector, re) => {
    for (const el of await page.$$(selector)) if (re.test(await el.evaluate((e) => e.textContent))) return el;
    throw new Error('no element ' + selector + ' ' + re);
};

// ---------------- sessions ----------------
// Clicks a sidebar menu entry (the left-most link to that page).
async function sidebar(page, r, href, sec = 0.9) {
    const links = await page.$$(`a.nav-link[href$="${href}"]`);
    if (!links.length) throw new Error('no sidebar link ' + href);
    r.setZoom(0.32);
    await r.move(links[0], sec); await r.click('click', { nav: true });
}
// Clicks an in-page link and waits for the next page.
async function follow(page, r, el, sec = 0.8) { await r.move(el, sec); await r.click('click', { nav: true }); }

// Two continuous sessions, one per role. Chapters start at marks "c:<key>", deep-dive steps at "<key>.sN".
const SEGMENTS = {
    async teacher(page, r) {
        await go(page, '/guru/dashboard');
        r.mark('c:dashboard'); r.setZoom(0.12);
        await r.hold(0.6); await r.move(await byText(page, '.card', /Perlu Dinilai/), 0.9); r.setZoom(0.3); await r.hold(0.5);
        await r.scroll(520, 1.4); await r.hold(0.3);

        // Beri nilai: open an ungraded submission straight from "Pengumpulan Terbaru"
        r.mark('c:grading');
        const row = await page.evaluateHandle(() => [...document.querySelectorAll('a.link-row')].find((a) => /Dikumpulkan/.test(a.textContent) && !/Sudah Dinilai/.test(a.textContent)) ?? document.querySelector('a.link-row[href*="/assignments/"]'));
        await follow(page, r, row, 0.9);
        await r.hold(0.3);
        const btn = await page.evaluateHandle(() => {
            const bs = [...document.querySelectorAll('[data-bs-target^="#grade-"]')];
            return bs.find((b) => !/\d+\s*\/\s*100/.test(b.closest('tr')?.textContent ?? '')) ?? bs.at(-1);
        });
        const id = await btn.evaluate((b) => b.getAttribute('data-bs-target'));
        await r.scroll(await btn.evaluate((b) => scrollY + b.getBoundingClientRect().top - innerHeight * 0.32), 1.1);
        r.setZoom(0.5);
        await r.move(btn, 0.7); await r.click(); await r.hold(0.6);
        await r.move(`${id} input[name=score]`, 0.6); await r.click(); await r.type('88', 8);
        await r.move(`${id} textarea[name=feedback]`, 0.5); await r.click('triple'); await r.type('Perhitungan subnet sudah tepat. Mantap!', 24);
        await r.move(`${id} button[type=submit]`, 0.6); await r.click('click', { nav: true });
        r.setZoom(0.2); await r.hold(1.0);

        // Diskusi
        r.mark('c:discussion');
        await sidebar(page, r, '/guru/discussions');
        r.setZoom(0.3); await r.hold(0.4);
        await follow(page, r, 'a[href$="/guru/discussions/1"]', 0.8);
        await r.hold(0.3); await r.scroll('#body', 1.3, { at: 0.45 });
        r.setZoom(0.48);
        await r.move('#body', 0.7); await r.click();
        await r.type('Betul! Ingat rumusnya 2^h − 2, h = jumlah bit host.', 26);
        await r.move(await byText(page, 'button', /Kirim Balasan/), 0.6); await r.click('click', { nav: true });
        r.setZoom(0.25);
        await r.hold(0.3); await r.scroll(await page.evaluate(() => document.body.scrollHeight - innerHeight - 260), 1.2); await r.hold(0.6);

        // Rapor otomatis
        r.mark('c:rapor');
        await sidebar(page, r, '/guru/grades');
        r.setZoom(0.3); await r.hold(0.4);
        await follow(page, r, 'a[href$="/guru/grades/2/1"]', 0.8);
        await r.hold(0.3);
        r.mark('rapor.s1'); r.setZoom(0.5);
        await r.move('#assignment_weight', 0.9); await r.click('triple'); await r.type('70', 6); await r.hold(0.6);
        r.mark('rapor.s2');
        await r.move('form[data-report-weights] button[type=submit]', 0.7); await r.click('click', { nav: true });
        r.mark('rapor.s3'); r.setZoom(0.25);
        await r.hold(0.4); await r.scroll('table', 1.3, { at: 0.25 });
        r.setZoom(0.45);
        await r.move(await byText(page, 'th', /Nilai Rapor/i), 0.7); await r.hold(0.5);
        await r.scroll(0, 1.0); r.setZoom(0.5);
        r.mark('rapor.s4');
        await r.move('a[href$="/export"]', 0.8); await r.click(() => {}); r.setZoom(0.15);
        r.mark('rapor.pop');
        await r.hold(4.2);
    },
    async student(page, r) {
        await go(page, '/siswa/dashboard');
        r.mark('c:dashboard'); r.setZoom(0.12);
        await r.hold(0.6); await r.move(await byText(page, '.card', /Deadline 7 Hari/), 0.9); r.setZoom(0.3); await r.hold(0.4);
        await r.scroll(480, 1.3); await r.hold(0.3);

        // Pratinjau tanpa unduh
        r.mark('c:preview');
        await sidebar(page, r, '/siswa/subjects');
        await r.hold(0.3);
        r.mark('preview.s1');
        await follow(page, r, 'a[href$="/siswa/subjects/2"]', 0.8);
        await r.hold(0.3);
        const open = async (id, tag) => {
            const sel = `[data-preview-url*="materials/${id}/download"]`;
            await r.scroll(sel, 0.9, { at: 0.45 });
            r.setZoom(0.45);
            await r.move(sel, 0.8); await r.click();
            r.mark(tag); r.setZoom(0.2);
            await page.waitForFunction(() => { const b = document.querySelector('#filePreviewModal [data-preview-body]'); return b && b.querySelector('canvas, .docx, table, img'); }, { timeout: 20000 }).catch(() => {});
            await r.hold(0.7);
        };
        const close = async () => { await r.move('#filePreviewModal .btn-close', 0.6); await r.click(); await r.hold(0.4); };
        await open(1, 'preview.s2');
        await r.scroll(420, 1.3, { el: '#filePreviewModal .modal-body' }); await r.hold(0.3);
        await close();
        await open(6, 'preview.s3');
        await r.scroll(300, 1.1, { el: '#filePreviewModal .modal-body' }); await r.hold(0.3);
        await close();
        await open(7, 'preview.s4');
        await r.move(await byText(page, '#filePreviewModal td', /255\.255\.255\.192/), 0.9); await r.hold(0.9);
        await close();

        // Kuis
        r.mark('c:quiz');
        await sidebar(page, r, '/siswa/quizzes');
        await r.hold(0.3);
        await follow(page, r, 'a[href$="/siswa/quizzes/1"]', 0.8);
        r.mark('quiz.s1'); r.setZoom(0.4);
        await r.hold(0.4); await r.move('form[action$="/start"] button[type=submit]', 0.8); await r.click('click', { nav: true });
        r.mark('quiz.s2');
        const correct = ['62', '255.255.255.224', '192.168.10.15', 'B', 'DHCP'];
        for (let q = 0; q < 5; q++) {
            await r.hold(q === 0 ? 0.5 : 0.15);
            let pick;
            for (const l of await page.$$('#quizForm .form-check')) {
                const txt = (await l.evaluate((e) => e.textContent)).replace(/^\s*[A-E]\.\s*/, '').trim();
                if (correct.includes(txt)) pick = l;
            }
            const label = (await pick.$('label')) ?? pick;
            await r.move(label, q === 0 ? 0.8 : 0.45); await r.click(); await r.hold(q === 0 ? 0.7 : 0.25);
            if (q === 0) r.mark('quiz.s3');
            if (await page.$('#quizNext')) { await r.move('#quizNext', q === 0 ? 0.6 : 0.4); await r.click('click', { nav: true }); }
            else { r.mark('quiz.s4'); await r.move('#quizSubmit', 0.6); await r.click('click', { nav: true }); }
        }
        r.mark('quiz.s5'); r.setZoom(0.35);
        await r.hold(0.4); await r.move(await byText(page, '.card', /Nilai/), 0.7); await r.hold(1.3);

        // Konversi PDF
        r.mark('c:pdf');
        await sidebar(page, r, '/siswa/pdf-converter');
        r.mark('pdf.s1'); r.setZoom(0.45);
        await r.hold(0.4); await r.move('#files', 0.8);
        await r.click(async () => { await (await page.$('#files')).uploadFile(...[1, 2, 3].map((i) => path.join(import.meta.dirname, `foto-tugas-${i}.jpg`))); });
        await r.hold(0.8);
        r.mark('pdf.s2');
        await r.move('#convertButton', 0.6); await r.click();
        r.mark('pdf.s3'); r.mark('pdf.pop'); r.setZoom(0.15);
        await r.hold(4.8);

        // Nilai saya
        r.mark('c:grades');
        await sidebar(page, r, '/siswa/grades');
        r.setZoom(0.3);
        await r.hold(0.4); await r.move(await byText(page, '.card', /Nilai Rapor|Rata-rata/), 0.8); await r.hold(0.3);
        await r.scroll(460, 1.4); await r.move(await byText(page, 'td', /Pertahankan|Alur logika/), 0.8); await r.hold(1.0);
    },
};
const ROLE = { t: ['guru', 'guru123'], s: ['siswa', 'siswa123'] };

fs.mkdirSync(OUT, { recursive: true });
const manifestPath = path.join(OUT, 'manifest.json');
const manifest = fs.existsSync(manifestPath) ? JSON.parse(fs.readFileSync(manifestPath, 'utf8')) : {};
const wanted = process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(SEGMENTS);
const pages = {};
for (const name of wanted) {
    const role = name[0];
    pages[role] ??= await session(...ROLE[role]);
    const r = new Recorder(pages[role], name);
    const t0 = Date.now();
    await SEGMENTS[name](pages[role], r);
    if (LOG_ONLY) {
        const evPath = path.join(OUT, 'events.json');
        const ev = fs.existsSync(evPath) ? JSON.parse(fs.readFileSync(evPath, 'utf8')) : {};
        ev[name] = { frames: r.n, matchesRecording: manifest[name]?.frames === r.n, marks: r.marks, navs: r.navs, events: r.events };
        fs.writeFileSync(evPath, JSON.stringify(ev));
    } else {
        manifest[name] = r.save();
        fs.writeFileSync(manifestPath, JSON.stringify(manifest));
    }
    console.log(`${name}: ${r.n} frames (${(r.n / FPS).toFixed(1)}s) in ${((Date.now() - t0) / 1000).toFixed(0)}s`, r.marks);
}
await browser.close();
