// ~2:13 flat 2D motion-graphics promo for the SMK Bhakti Insani e-learning (teacher + student roles).
// Same live recordings and timeline as the 3D version (out/rec), drawn with Canvas 2D:
// a flat vector laptop, bouncy shapes, circle wipes and a flat card flip. Deterministic: await window.renderAt(t).

const W = 1920, H = 1080;
const canvas = document.getElementById('c');
const ctx = canvas.getContext('2d');
const $ = (id) => document.getElementById(id);

// ---------- helpers ----------
const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
const lerp = (a, b, k) => a + (b - a) * k;
const smooth = (x) => { x = clamp(x); return x * x * x * (x * (x * 6 - 15) + 10); };
const easeOut = (x) => 1 - Math.pow(1 - clamp(x), 3);
const easeIn = (x) => Math.pow(clamp(x), 3);
const backOut = (x) => { x = clamp(x); const c = 1.7; return 1 + (c + 1) * Math.pow(x - 1, 3) + c * Math.pow(x - 1, 2); };
const elastic = (x) => { x = clamp(x); if (x === 0 || x === 1) return x; return Math.pow(2, -10 * x) * Math.sin((x * 10 - 0.75) * (2 * Math.PI / 3)) + 1; };
const range = (t, a, b) => clamp((t - a) / (b - a));
function rng(seed) { return () => { seed |= 0; seed = (seed + 0x6D2B79F5) | 0; let r = Math.imul(seed ^ (seed >>> 15), 1 | seed); r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r; return ((r ^ (r >>> 14)) >>> 0) / 4294967296; }; }
const hex = (h) => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)];
const mix = (a, b, k) => { const A = hex(a), B = hex(b); return `rgb(${A.map((v, i) => Math.round(lerp(v, B[i], k))).join(',')})`; };
const loadImage = (src) => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = src; });

const COL = { indigo: '#4F46E5', blue: '#3B82F6', purple: '#7C3AED', violet: '#8B5CF6', lilac: '#A5B4FC', lavender: '#C4B5FD', sky: '#93C5FD', navy: '#1E1B4B', ink: '#0F1222' };
const FONT = '"Plus Jakarta Sans", sans-serif';
const grad = (c, x0, y0, x1, y1, a = COL.blue, b = COL.indigo, d = COL.purple) => { const g = c.createLinearGradient(x0, y0, x1, y1); g.addColorStop(0, a); g.addColorStop(0.5, b); g.addColorStop(1, d); return g; };

// ---------- recordings + timeline (same as the 3D version) ----------
const REC = await (await fetch('/out/rec/manifest.json')).json();
// The recordings carry their own frame rate (60 fps now); the video can render at any rate.
const REC_FPS = REC.teacher.fps ?? 30;
// Zoom "shots" from the recording's zoom cues: each shot is a held framing of the monitor's content (wide, or
// zoomed on the area the cursor works in). Short cues are merged so the zoom never twitches.
const SCR = { x: 385, y: 112, w: 1150, h: 719 };
function prepare(name) {
    const r = REC[name];
    r.navs ??= [];
    const minShot = 1.7 * REC_FPS;
    let shots = r.zoom.map(([f, z], i) => ({ f0: f, f1: r.zoom[i + 1]?.[0] ?? r.frames, z }));
    for (let i = 1; i < shots.length;) {
        const s = shots[i], p = shots[i - 1];
        const wide = (x) => x.z < 0.34;
        if (s.f1 - s.f0 < minShot || (wide(s) && wide(p))) { p.f1 = s.f1; if (s.f1 - s.f0 >= minShot && !wide(s)) p.z = s.z; shots.splice(i, 1); } else i++;
    }
    // Zoomed cues are split into sub-shots so that every point the cursor visits, plus a margin for the size of
    // the button/field it clicks, fits inside the visible part of the page. A group that cannot fit at a
    // meaningful zoom stays wide instead of being cropped.
    const PAD_U = 0.14, PAD_V = 0.1, S_MAX = 1.45, S_MIN = 1.15;
    const fitOf = (b) => Math.min(1 / (b.u1 - b.u0 + 2 * PAD_U), 1 / (b.v1 - b.v0 + 2 * PAD_V));
    const out = [];
    for (const s of shots) {
        if (s.z < 0.34) { out.push({ ...s, S: 1, F: [0.5, 0.5] }); continue; }
        const want = Math.min(S_MAX, 1 + s.z * 1.0);
        let cur = null;
        const close = (f1) => { if (cur) { cur.f1 = f1; out.push(cur); } };
        const start = Math.min(s.f0 + Math.round(0.2 * REC_FPS), s.f1 - 1);
        for (let f = start; f < s.f1; f++) {
            const [u, v] = r.path[f];
            if (!cur) { cur = { f0: s.f0, z: s.z, b: { u0: u, u1: u, v0: v, v1: v } }; continue; }
            const nb = { u0: Math.min(cur.b.u0, u), u1: Math.max(cur.b.u1, u), v0: Math.min(cur.b.v0, v), v1: Math.max(cur.b.v1, v) };
            if (fitOf(nb) < Math.min(want, 1.3) && f - cur.f0 >= minShot) { close(f); cur = { f0: f, z: s.z, b: { u0: u, u1: u, v0: v, v1: v } }; }
            else cur.b = nb;
        }
        close(s.f1);
    }
    function want(s) { return Math.min(S_MAX, 1 + s.z * 1.0); }
    // Frames a shot from its bounding box (wide when the box needs less than S_MIN).
    r.frameShot = (s) => {
        if (!s.b) return;
        const S = Math.min(want(s), fitOf(s.b));
        s.S = S < S_MIN ? 1 : S;
        s.F = s.S === 1 ? [0.5, 0.5] : [(s.b.u0 + s.b.u1) / 2, (s.b.v0 + s.b.v1) / 2];
    };
    out.forEach(r.frameShot);
    shots = out;
    r.shots = shots;
    return r;
}
const ROLES = [
    { role: 'guru', rec: 'teacher', chapters: [
        { key: 'dashboard', title: 'Dashboard Guru', text: 'Kelas, tugas aktif, dan tugas yang perlu dinilai, semuanya dalam satu layar.' },
        { key: 'grading', title: 'Beri Nilai & Umpan Balik', text: 'Buka pengumpulan siswa, isi nilai dan umpan balik, lalu simpan. Siswa langsung bisa melihatnya.' },
        { key: 'discussion', title: 'Diskusi Kelas', text: 'Dari menu samping, buka diskusi dan balas pertanyaan siswa langsung di forum kelas.' },
        { key: 'rapor', title: 'Rapor Otomatis', star: true, steps: [
            ['Atur bobot', 'Ketik 70% untuk tugas, bobot kuis langsung menyesuaikan menjadi 30%.'],
            ['Simpan', 'Pengaturan disimpan untuk mata pelajaran dan kelas ini.'],
            ['Dihitung ulang', 'Nilai rapor, predikat, dan status tuntas satu kelas langsung diperbarui.'],
            ['Unduh Excel', 'Rapor siap diunduh dan dibagikan tanpa input ulang.'],
        ] },
    ] },
    { role: 'siswa', rec: 'student', chapters: [
        { key: 'dashboard', title: 'Dashboard Siswa', text: 'Jadwal, pengumuman, dan deadline 7 hari ke depan dalam sekali lihat.' },
        { key: 'preview', title: 'Pratinjau Tanpa Unduh', star: true, steps: [
            ['Klik "Lihat"', 'Materi dari guru bisa dibuka tanpa diunduh.'],
            ['PDF', 'Modul PDF tampil dan bisa digulir langsung di pop-up.'],
            ['Word', 'Dokumen Word (.docx) juga bisa dibaca di browser.'],
            ['Excel', 'Spreadsheet terbuka lengkap dengan tabelnya. Hemat kuota dan memori HP.'],
        ] },
        { key: 'quiz', title: 'Kuis dengan Timer', star: true, steps: [
            ['Mulai', 'Waktu langsung berjalan begitu kuis dimulai.'],
            ['Pilih jawaban', 'Urutan soal diacak untuk setiap siswa.'],
            ['Tersimpan otomatis', 'Jawaban langsung tersimpan. Soal yang sudah dilewati terkunci.'],
            ['Kumpulkan', 'Di soal terakhir, kumpulkan sebelum waktu habis.'],
            ['Nilai keluar', 'Skor dihitung otomatis, tanpa koreksi manual.'],
        ] },
        { key: 'pdf', title: 'Konversi Foto ke PDF', star: true, steps: [
            ['Pilih foto', 'Beberapa foto dari kamera HP bisa dipilih sekaligus.'],
            ['Konversi', 'Semua foto digabung menjadi satu dokumen.'],
            ['Satu file PDF', 'Rapi dan siap dikumpulkan, tanpa aplikasi tambahan.'],
        ] },
        { key: 'grades', title: 'Nilai Saya', text: 'Nilai rapor, rata-rata tugas, dan umpan balik guru selalu transparan.' },
    ] },
];
const UNIQUE = [
    ['Pratinjau File Langsung', 'PDF, Word, Excel, dan gambar terbuka di browser tanpa perlu diunduh.'],
    ['Kuis Aman & Adil', 'Soal diacak, waktu berjalan otomatis, soal yang dilewati tidak bisa dibuka lagi.'],
    ['Rapor Otomatis', 'Bobot tugas & kuis dihitung sendiri, siap ekspor ke Excel.'],
    ['Konversi Foto ke PDF', 'Foto tugas jadi satu file PDF langsung di aplikasi.'],
    ['Diskusi Kelas', 'Tanya-jawab bertingkat, guru menandai jawaban terbaik.'],
];
const LEAD = 0.8, TAIL = 0.6;
const T = { introEnd: 7, rolesEnd: 12 };
let clock = T.rolesEnd;
for (const R of ROLES) {
    if (R.role === 'siswa') { T.teacherEnd = clock; T.swapEnd = clock + 5; clock = T.swapEnd; }
    R.r = prepare(R.rec);
    R.t0 = clock; R.play = clock + LEAD; R.t1 = R.play + R.r.frames / REC_FPS + TAIL; clock = R.t1;
    const at = (m) => R.play + (R.r.marks[m] ?? 0) / REC_FPS;
    R.chapters.forEach((c, i) => {
        c.n = i + 1; c.of = R.chapters.length; c.role = R.role;
        c.start = at('c:' + c.key);
        c.end = R.chapters[i + 1] ? at('c:' + R.chapters[i + 1].key) : R.t1 - 0.2;
        if (c.steps) c.stepTimes = c.steps.map((_, j) => at(`${c.key}.s${j + 1}`));
    });
}
const [TEACH, STUD] = ROLES;
T.studentEnd = clock; T.uniqEnd = clock + 9.5; T.end = T.uniqEnd + 6;
const DURATION = T.end;
const frameOf = (R, t) => clamp(Math.floor((t - R.play) * REC_FPS + 1e-6), 0, R.r.frames - 1);
const markTime = (R, m, extra = 0) => R.play + ((R.r.marks[m] ?? 0) + extra * REC_FPS / 30) / REC_FPS;
const stepOf = (c, t) => { let i = -1; c.stepTimes.forEach((s, j) => { if (t >= s) i = j; }); return i; };

// ---------- assets ----------
async function emblemCanvas() {
    const img = await loadImage('/assets/logo.png');
    const cv = document.createElement('canvas'); cv.width = img.width; cv.height = img.height;
    const c = cv.getContext('2d'); c.drawImage(img, 0, 0);
    const d = c.getImageData(0, 0, cv.width, cv.height), px = d.data, w = cv.width, h = cv.height;
    const seen = new Uint8Array(w * h), stack = [0, w - 1, (h - 1) * w, h * w - 1];
    const outside = (i) => px[i * 4 + 3] < 20 || (px[i * 4] > 225 && px[i * 4 + 1] > 225 && px[i * 4 + 2] > 225);
    while (stack.length) {
        const i = stack.pop(); if (seen[i] || !outside(i)) continue; seen[i] = 1; px[i * 4 + 3] = 0;
        const x = i % w, y = (i / w) | 0;
        if (x > 0) stack.push(i - 1); if (x < w - 1) stack.push(i + 1); if (y > 0) stack.push(i - w); if (y < h - 1) stack.push(i + w);
    }
    c.putImageData(d, 0, 0); return cv;
}
await document.fonts.load(`800 66px ${FONT}`); await document.fonts.load(`500 40px ${FONT}`); await document.fonts.ready;
const EMBLEM = await emblemCanvas();
const SHEET = await loadImage('/assets/shots/rp-4.png');
const PHOTOS = await Promise.all([1, 2, 3].map((i) => loadImage(`/capture/foto-tugas-${i}.jpg`)));

function roleCard(role) {
    const cv = document.createElement('canvas'); cv.width = 1600; cv.height = 1000; const c = cv.getContext('2d');
    c.beginPath(); c.roundRect(0, 0, 1600, 1000, 70); c.clip();
    c.fillStyle = role === 'guru' ? grad(c, 0, 0, 1600, 1000, '#2563EB', COL.blue, COL.indigo) : grad(c, 0, 0, 1600, 1000, COL.indigo, COL.purple, '#A855F7');
    c.fillRect(0, 0, 1600, 1000);
    c.fillStyle = 'rgba(255,255,255,.12)';
    for (let i = 0; i < 5; i++) { c.beginPath(); c.arc(1320, 210, 220 + i * 110, 0, Math.PI * 2); c.lineWidth = 34; c.strokeStyle = 'rgba(255,255,255,.08)'; c.stroke(); }
    c.fillStyle = '#fff'; c.beginPath(); c.arc(205, 205, 115, 0, Math.PI * 2); c.fill();
    c.drawImage(EMBLEM, 115, 115, 180, 180);
    c.fillStyle = 'rgba(255,255,255,.85)'; c.font = `700 44px ${FONT}`; c.fillText(role === 'guru' ? 'BAGIAN 1  ·  PERAN' : 'BAGIAN 2  ·  PERAN', 360, 190);
    c.font = `600 40px ${FONT}`; c.fillText('SMK Bhakti Insani', 360, 250);
    c.fillStyle = '#fff'; c.font = `800 300px ${FONT}`; c.fillText(role === 'guru' ? 'Guru' : 'Siswa', 100, 660);
    c.font = `500 64px ${FONT}`; c.fillStyle = 'rgba(255,255,255,.92)'; c.fillText(role === 'guru' ? 'Mengajar lebih ringan.' : 'Belajar lebih fokus.', 110, 820);
    return cv;
}
const CARD_GURU = roleCard('guru'), CARD_SISWA = roleCard('siswa');
function uniqueCard(i, title, desc) {
    const cv = document.createElement('canvas'); cv.width = 1024; cv.height = 640; const c = cv.getContext('2d');
    c.fillStyle = '#fff'; c.beginPath(); c.roundRect(0, 0, 1024, 640, 56); c.fill();
    const g = grad(c, 0, 0, 1024, 0);
    c.save(); c.beginPath(); c.roundRect(0, 0, 1024, 640, 56); c.clip(); c.fillStyle = g; c.fillRect(0, 0, 1024, 18); c.restore();
    c.fillStyle = g; c.beginPath(); c.roundRect(64, 76, 108, 108, 30); c.fill();
    c.fillStyle = '#fff'; c.font = `800 56px ${FONT}`; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillText(String(i + 1), 118, 132);
    c.textAlign = 'left'; c.textBaseline = 'alphabetic'; c.fillStyle = COL.ink; c.font = `800 62px ${FONT}`; c.fillText(title, 64, 290);
    c.fillStyle = '#454A63'; c.font = `500 40px ${FONT}`;
    let line = '', y = 372;
    for (const w of desc.split(' ')) { const test = line ? line + ' ' + w : w; if (c.measureText(test).width > 880) { c.fillText(line, 64, y); line = w; y += 56; } else line = test; }
    c.fillText(line, 64, y);
    return cv;
}
const UCARDS = UNIQUE.map(([t, d], i) => uniqueCard(i, t, d));
function pdfDoc() {
    const cv = document.createElement('canvas'); cv.width = 768; cv.height = 1024; const c = cv.getContext('2d');
    c.fillStyle = '#fff'; c.beginPath(); c.roundRect(0, 0, 768, 1024, 40); c.fill();
    c.fillStyle = '#F4F5FB'; c.fillRect(60, 300, 648, 520);
    PHOTOS.forEach((p, i) => { c.save(); c.translate(110 + i * 190, 340); c.rotate((i - 1) * 0.06); c.drawImage(p, 0, 0, 170, 226); c.strokeStyle = '#d8dbe8'; c.lineWidth = 3; c.strokeRect(0, 0, 170, 226); c.restore(); });
    c.fillStyle = '#E11D48'; c.beginPath(); c.roundRect(60, 64, 180, 96, 22); c.fill();
    c.fillStyle = '#fff'; c.font = `800 54px ${FONT}`; c.textAlign = 'center'; c.fillText('PDF', 150, 132);
    c.textAlign = 'left'; c.fillStyle = COL.ink; c.font = `800 50px ${FONT}`; c.fillText('Tugas-Subnetting.pdf', 60, 236);
    c.fillStyle = '#454A63'; c.font = `600 36px ${FONT}`; c.fillText('3 halaman · 1 file', 60, 650);
    c.fillStyle = grad(c, 60, 0, 708, 0); c.beginPath(); c.roundRect(60, 880, 648, 84, 42); c.fill();
    c.fillStyle = '#fff'; c.font = `700 34px ${FONT}`; c.textAlign = 'center'; c.fillText('Siap dikumpulkan', 384, 934);
    return cv;
}
const PDFDOC = pdfDoc();

// ---------- drawing primitives ----------
// Flat "block" shadow: the same shape offset down-right in a soft indigo.
function card(img, x, y, w, h, { r = 26, s = 1, rot = 0, alpha = 1, border = 10, shadow = true } = {}) {
    if (s <= 0.001 || alpha <= 0.001) return;
    ctx.save(); ctx.globalAlpha = alpha; ctx.translate(x, y); ctx.rotate(rot); ctx.scale(s, s);
    if (shadow) { ctx.fillStyle = 'rgba(79,70,229,.22)'; ctx.beginPath(); ctx.roundRect(-w / 2 + 18, -h / 2 + 22, w, h, r); ctx.fill(); }
    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.roundRect(-w / 2, -h / 2, w, h, r); ctx.fill();
    ctx.save(); ctx.beginPath(); ctx.roundRect(-w / 2 + border, -h / 2 + border, w - border * 2, h - border * 2, Math.max(4, r - border)); ctx.clip();
    ctx.drawImage(img, -w / 2 + border, -h / 2 + border, w - border * 2, h - border * 2); ctx.restore();
    ctx.restore();
}
function shape(type, x, y, size, color, rot, s, alpha = 1, t = 0) {
    if (s <= 0.001) return;
    ctx.save(); ctx.globalAlpha = alpha; ctx.translate(x, y); ctx.rotate(rot); ctx.scale(s, s);
    ctx.fillStyle = color; ctx.strokeStyle = color; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    const r = size / 2;
    switch (type) {
        case 'circle': ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.fill(); break;
        case 'ring': ctx.lineWidth = size * 0.16; ctx.beginPath(); ctx.arc(0, 0, r * 0.85, 0, Math.PI * 2); ctx.stroke(); break;
        case 'pill': ctx.beginPath(); ctx.roundRect(-r * 1.4, -r * 0.42, r * 2.8, r * 0.84, r * 0.42); ctx.fill(); break;
        case 'plus': ctx.lineWidth = size * 0.22; ctx.beginPath(); ctx.moveTo(-r * 0.7, 0); ctx.lineTo(r * 0.7, 0); ctx.moveTo(0, -r * 0.7); ctx.lineTo(0, r * 0.7); ctx.stroke(); break;
        case 'tri': ctx.beginPath(); ctx.moveTo(0, -r); ctx.lineTo(r * 0.9, r * 0.6); ctx.lineTo(-r * 0.9, r * 0.6); ctx.closePath(); ctx.lineWidth = size * 0.12; ctx.stroke(); break;
        case 'half': ctx.beginPath(); ctx.arc(0, 0, r, Math.PI, 0); ctx.closePath(); ctx.fill(); break;
        case 'dots': for (let i = 0; i < 3; i++) for (let j = 0; j < 3; j++) { ctx.beginPath(); ctx.arc((i - 1) * r * 0.7, (j - 1) * r * 0.7, r * 0.13, 0, Math.PI * 2); ctx.fill(); } break;
        case 'squiggle': {
            ctx.lineWidth = size * 0.13; ctx.beginPath();
            for (let i = 0; i <= 40; i++) { const u = i / 40; const px = (u - 0.5) * size * 1.6, py = Math.sin(u * Math.PI * 3 + t * 3) * r * 0.35; i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); }
            ctx.stroke(); break;
        }
    }
    ctx.restore();
}

// ---------- background shape field ----------
const TYPES = ['circle', 'ring', 'pill', 'plus', 'tri', 'half', 'dots', 'squiggle'];
const BG = [];
{
    const r = rng(21);
    for (let i = 0; i < 46; i++) {
        let x, y;
        do { x = r() * W; y = r() * H; } while (x > 360 && x < 1560 && y > 90 && y < 860 && r() < 0.85); // mostly around the laptop
        BG.push({ x, y, type: TYPES[Math.floor(r() * TYPES.length)], size: 26 + r() * 70, c: Math.floor(r() * 5), rot: r() * 6, spin: (r() - 0.5) * 0.8, amp: 8 + r() * 18, ph: r() * 6.28, delay: r() * 1.4, depth: 0.3 + r() * 0.7 });
    }
}
const PAL_A = [COL.indigo, COL.blue, COL.sky, COL.lilac, COL.purple];
const PAL_B = [COL.purple, COL.violet, COL.lavender, COL.lavender, COL.purple];
function drawBackground(t, swapK, parallax = [0, 0]) { // parallax unused now that the laptop never moves
    // soft dot grid drifting
    ctx.save(); ctx.fillStyle = mix('#C7CCF5', '#D8CCFA', swapK); ctx.globalAlpha = 0.55;
    const off = (t * 18) % 48;
    for (let y = -48; y < H + 48; y += 48) for (let x = -48; x < W + 48; x += 48) { ctx.beginPath(); ctx.arc(x + off + parallax[0] * 0.1, y + off * 0.5 + parallax[1] * 0.1, 2.2, 0, Math.PI * 2); ctx.fill(); }
    ctx.restore();
    for (const b of BG) {
        const pop = elastic(range(t, 0.2 + b.delay, 1.4 + b.delay));
        const x = b.x + Math.sin(t * 0.6 + b.ph) * b.amp + parallax[0] * b.depth * 0.25;
        const y = b.y + Math.cos(t * 0.5 + b.ph) * b.amp + parallax[1] * b.depth * 0.25;
        shape(b.type, x, y, b.size, mix(PAL_A[b.c], PAL_B[b.c], swapK), b.rot + t * b.spin, pop, b.type === 'circle' ? 0.9 : 1, t + b.ph);
    }
}
// Burst of small shapes popping out from a point.
function burst(cx, cy, t0, t, seed, n = 16, dist = 260) {
    const k = range(t, t0, t0 + 1.4); if (k <= 0 || k >= 1) return;
    const r = rng(seed);
    for (let i = 0; i < n; i++) {
        const a = (i / n) * Math.PI * 2 + r() * 0.4, d = dist * (0.6 + r() * 0.6) * easeOut(k);
        const s = Math.sin(Math.PI * Math.min(1, k * 1.3));
        shape(TYPES[i % TYPES.length], cx + Math.cos(a) * d, cy + Math.sin(a) * d, 18 + r() * 26, PAL_A[i % 5], a + k * 3, s, 1, t);
    }
}

// ---------- flat laptop ----------
const BEZ ={ side: 22, top: 26, bottom: 34 };
const frameCache = new Map();
async function frameBitmap(rec, idx) {
    const k = rec + '/' + idx;
    if (!frameCache.has(k)) {
        if (frameCache.size > 6) { const [oldK, oldV] = frameCache.entries().next().value; oldV.close?.(); frameCache.delete(oldK); }
        const blob = await (await fetch(`/out/rec/${rec}/${String(idx).padStart(5, '0')}.jpg`)).blob();
        frameCache.set(k, await createImageBitmap(blob));
    }
    return frameCache.get(k);
}
// Source rectangle of a recorded frame for an in-monitor zoom {S, F:[u,v]}; it never leaves the page.
function cropOf(img, z) {
    const sw = img.width / z.S, sh = img.height / z.S;
    return [clamp(z.F[0] * img.width - sw / 2, 0, img.width - sw), clamp(z.F[1] * img.height - sh / 2, 0, img.height - sh), sw, sh];
}
// Where a point of the page (u,v) currently appears on screen, given the in-monitor zoom.
function zoomPt(z, [u, v]) {
    const [sx, sy, sw, sh] = cropOf({ width: 1, height: 1 }, z);
    return [SCR.x + ((u - sx) / sw) * SCR.w, SCR.y + ((v - sy) / sh) * SCR.h];
}
function drawLaptop(bmp, dx, dy, s = 1, closed = 0, under = null, alpha = 1, zoom = { S: 1, F: [0.5, 0.5] }) {
    ctx.save();
    const cx = SCR.x + SCR.w / 2, base = SCR.y + SCR.h + BEZ.bottom;
    ctx.translate(cx + dx, base + dy); ctx.scale(s, s); ctx.translate(-cx, -base);
    const lx = SCR.x - BEZ.side, ly = SCR.y - BEZ.top, lw = SCR.w + BEZ.side * 2, lh = SCR.h + BEZ.top + BEZ.bottom;
    // lid closing = flat squash toward the hinge
    const sq = 1 - closed * 0.97;
    ctx.save(); ctx.translate(0, base); ctx.scale(1, sq); ctx.translate(0, -base);
    ctx.fillStyle = 'rgba(79,70,229,.22)'; ctx.beginPath(); ctx.roundRect(lx + 22, ly + 26, lw, lh, 30); ctx.fill();
    ctx.fillStyle = COL.navy; ctx.beginPath(); ctx.roundRect(lx, ly, lw, lh, 30); ctx.fill();
    ctx.fillStyle = '#3B3770'; ctx.beginPath(); ctx.arc(cx, ly + BEZ.top / 2, 4, 0, Math.PI * 2); ctx.fill();
    ctx.save(); ctx.beginPath(); ctx.roundRect(SCR.x, SCR.y, SCR.w, SCR.h, 8); ctx.clip();
    ctx.fillStyle = '#fff'; ctx.fillRect(SCR.x, SCR.y, SCR.w, SCR.h);
    ctx.imageSmoothingQuality = 'high';
    if (under) ctx.drawImage(under, ...cropOf(under, zoom), SCR.x, SCR.y, SCR.w, SCR.h);
    if (bmp) { ctx.globalAlpha = alpha; ctx.drawImage(bmp, ...cropOf(bmp, zoom), SCR.x, SCR.y, SCR.w, SCR.h); ctx.globalAlpha = 1; }
    ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.beginPath(); ctx.moveTo(SCR.x, SCR.y); ctx.lineTo(SCR.x + SCR.w * 0.42, SCR.y); ctx.lineTo(SCR.x + SCR.w * 0.18, SCR.y + SCR.h); ctx.lineTo(SCR.x, SCR.y + SCR.h); ctx.fill();
    ctx.restore(); ctx.restore();
    // base
    const bw = lw + 150, bx = cx - bw / 2, by = base - 2;
    ctx.fillStyle = 'rgba(79,70,229,.22)'; ctx.beginPath(); ctx.roundRect(bx + 22, by + 12, bw, 34, [6, 6, 22, 22]); ctx.fill();
    ctx.fillStyle = '#E4E6F2'; ctx.beginPath(); ctx.roundRect(bx, by, bw, 34, [6, 6, 22, 22]); ctx.fill();
    ctx.fillStyle = '#F6F7FC'; ctx.fillRect(bx + 8, by, bw - 16, 7);
    ctx.fillStyle = '#C9CDE0'; ctx.beginPath(); ctx.roundRect(cx - 90, by, 180, 12, [0, 0, 10, 10]); ctx.fill();
    ctx.restore();
}
// In-monitor zoom for a role: the laptop never moves. Each shot is held still; between shots the page inside
// the monitor eases (zoom + pan) over 1.6s. Each move's start time is chosen per shot change (see tuneLeads)
// so the cursor stays inside the visible part of the page before, during and after the move.
const MOVE_S = 1.6;
const gentle = (x) => { x = clamp(x); return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2; };
const lerpZ = (a, b, w) => ({ S: lerp(a.S, b.S, w), F: [lerp(a.F[0], b.F[0], w), lerp(a.F[1], b.F[1], w)] });
function outside(z, [u, v], m = 0.06) {
    if (z.S <= 1.001) return false;
    const [sx, sy, sw, sh] = cropOf({ width: 1, height: 1 }, z);
    // the margin only matters on edges that cut into the page (at the page's own edge there is nothing more to show)
    const e = 1e-6, mL = sx > e ? m : 0, mR = sx + sw < 1 - e ? m : 0, mT = sy > e ? m : 0, mB = sy + sh < 1 - e ? m : 0;
    return u < sx + mL || u > sx + sw - mR || v < sy + mT || v > sy + sh - mB;
}
function tuneLeads(r) {
    pickLeads(r);
    // Where no timing keeps the cursor in view between two zoomed shots, merge them into one framing.
    for (let k = 1; k < r.shots.length; k++) {
        const A = r.shots[k - 1], B = r.shots[k];
        if (B.bad > 0 && A.b && B.b) {
            A.b = { u0: Math.min(A.b.u0, B.b.u0), u1: Math.max(A.b.u1, B.b.u1), v0: Math.min(A.b.v0, B.b.v0), v1: Math.max(A.b.v1, B.b.v1) };
            A.f1 = B.f1; A.z = Math.max(A.z, B.z); r.frameShot(A);
            r.shots.splice(k, 1); pickLeads(r); k = 0;
        }
    }
    r.tuned = true;
}
function pickLeads(r) {
    const move = MOVE_S * REC_FPS;
    r.shots.forEach((B, k) => {
        const A = r.shots[k - 1];
        if (!A) { B.lead = 0.6 * REC_FPS; return; }
        let best = null;
        for (let L = -0.3 * REC_FPS; L <= 2.2 * REC_FPS; L += 2) {
            const s0 = B.f0 - L;
            let bad = 0;
            for (let f = Math.max(A.f0, Math.floor(s0 - 0.4 * REC_FPS)); f < Math.min(B.f1, s0 + move + 0.4 * REC_FPS); f++) {
                if (outside(lerpZ(A, B, gentle((f - s0) / move)), r.path[f])) bad++;
            }
            const score = bad * 1000 + Math.abs(L - 0.7 * REC_FPS);
            if (!best || score < best.score) best = { L, score, bad };
        }
        B.lead = best.L; B.bad = best.bad;
    });
}
function screenZoom(R, t) {
    if (!R.r.tuned) tuneLeads(R.r);
    const f = (t - R.play) * REC_FPS, move = MOVE_S * REC_FPS;
    const shots = R.r.shots;
    let k = shots.findIndex((s) => f < s.f1); if (k < 0) k = shots.length - 1;
    const cur = shots[k], prev = shots[k - 1] ?? cur, next = shots[k + 1];
    let z = lerpZ(prev, cur, gentle((f - (cur.f0 - cur.lead)) / move));
    if (next && f > next.f0 - next.lead) z = lerpZ(z, next, gentle((f - (next.f0 - next.lead)) / move));
    let { S, F } = z;
    // wide at the start and end of the session
    const live = gentle(range(t, R.play - 0.2, R.play + 1.0)) * (1 - gentle(range(t, R.t1 - 1.6, R.t1 - 0.2)));
    return { S: lerp(1, S, live), F: [lerp(0.5, F[0], live), lerp(0.5, F[1], live)] };
}

async function drawRole(R, t, swapK) {
    const zoom = screenZoom(R, t);
    drawBackground(t, swapK);
    const inK = backOut(range(t, R.t0 - 0.4, R.t0 + 0.7));
    const closeK = R.role === 'guru' ? smooth(range(t, R.t1 - 0.8, R.t1 - 0.1)) : 0;
    const fr = frameOf(R, t);
    const bmp = await frameBitmap(R.rec, fr);
    // page loads crossfade over 0.2s instead of cutting
    const fadeN = Math.round(0.2 * REC_FPS);
    const nav = R.r.navs.find((n) => fr >= n && fr < n + fadeN);
    const under = nav ? await frameBitmap(R.rec, nav - 1) : null;
    drawLaptop(bmp, 0, (1 - inK) * 900, lerp(0.85, 1, inK), closeK, under, nav ? smooth((fr - nav + 1) / fadeN) : 1, zoom);
    burst(960, 520, R.t0 - 0.1, t, R.role === 'guru' ? 5 : 6, 18, 620);

    if (R === TEACH) {
        // Excel export pops out of the "Unduh Excel" button
        const sx = markTime(TEACH, 'rapor.pop', 3);
        const k = backOut(range(t, sx, sx + 0.9)), out = smooth(range(t, TEACH.t1 - 1.0, TEACH.t1 - 0.35));
        if (k > 0) {
            const from = zoomPt(zoom, TEACH.r.path[Math.min(TEACH.r.frames - 1, TEACH.r.marks['rapor.pop'] ?? 0)]);
            const to = [1080, 450];
            card(SHEET, lerp(from[0], to[0], clamp(k)), lerp(from[1], to[1], clamp(k)), 960, 600, { s: lerp(0.05, 1, k) * (1 - out), rot: lerp(0.4, -0.03, clamp(k)) });
            burst(to[0], to[1], sx + 0.3, t, 9, 14, 520);
        }
    } else {
        // Photos fly out of the file list, fan, stack, and become one PDF
        const ps = markTime(STUD, 'pdf.pop', 10), next = markTime(STUD, 'c:grades');
        const gone = smooth(range(t, next - 0.6, next));
        const listP = zoomPt(zoom, [0.11, 0.49]);
        const fold = smooth(range(t, ps + 1.9, ps + 2.9)), docK = backOut(range(t, ps + 2.6, ps + 3.4));
        PHOTOS.forEach((p, i) => {
            const k = backOut(range(t, ps + i * 0.15, ps + 0.9 + i * 0.15));
            if (k <= 0) return;
            const fan = [960 + (i - 1) * 330, 430 + (i === 1 ? -20 : 10)], stack = [1150 + (i - 1) * 18, 450 + (i - 1) * 10];
            const x = lerp(lerp(listP[0], fan[0], clamp(k)), stack[0], fold), y = lerp(lerp(listP[1], fan[1], clamp(k)), stack[1], fold);
            card(p, x, y, 300, 400, { s: k * lerp(1, 0.8, fold) * (1 - gone), rot: lerp((i - 1) * 0.12, (i - 1) * 0.05, fold), r: 18, border: 8 });
        });
        if (docK > 0) { card(PDFDOC, 900, 470, 420, 560, { s: docK * (1 - gone), rot: -0.03, r: 26, border: 0 }); burst(900, 470, ps + 2.7, t, 12, 16, 420); }
    }
}

// ---------- scenes ----------
function drawIntro(t) {
    drawBackground(t, 0);
    const k = elastic(range(t, 0.3, 1.8));
    // rings drawing on around the emblem
    [[260, COL.blue, 0.4], [320, COL.purple, 0.6], [380, COL.lilac, 0.8]].forEach(([rad, c, d], i) => {
        const p = easeOut(range(t, d, d + 1.2));
        ctx.save(); ctx.strokeStyle = c; ctx.lineWidth = 10 - i * 2; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.arc(960, 400, rad * lerp(0.8, 1, p), -Math.PI / 2 + t * (0.3 + i * 0.15), -Math.PI / 2 + t * (0.3 + i * 0.15) + Math.PI * 2 * p * 0.82); ctx.stroke(); ctx.restore();
    });
    ctx.save(); ctx.translate(960, 400); ctx.rotate((1 - k) * -0.6); ctx.scale(k, k);
    ctx.fillStyle = 'rgba(79,70,229,.22)'; ctx.beginPath(); ctx.arc(14, 18, 210, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(0, 0, 210, 0, Math.PI * 2); ctx.fill();
    ctx.drawImage(EMBLEM, -165, -165, 330, 330);
    ctx.restore();
    burst(960, 400, 0.5, t, 3, 20, 520);
}
function drawRoles(t) {
    drawBackground(t, 0);
    // big flat circles sliding across behind the text
    const k = easeOut(range(t, T.introEnd, T.introEnd + 1.2));
    shape('circle', lerp(2300, 1500, k), 540, 760, COL.indigo, 0, 1, 0.12);
    shape('ring', lerp(2400, 1560, k), 560, 980, COL.purple, 0, 1, 0.18);
    shape('circle', lerp(-300, 260, easeOut(range(t, T.introEnd + 3, T.rolesEnd))), 900, 300, COL.blue, 0, 1, 0.12);
}
function drawSwap(t) {
    const ts = T.teacherEnd, swapK = smooth(range(t, ts + 1.6, ts + 3.2));
    drawBackground(t, swapK);
    const inK = backOut(range(t, ts + 0.1, ts + 0.9)), outK = easeIn(range(t, T.swapEnd - 0.6, T.swapEnd));
    const fk = range(t, ts + 1.5, ts + 2.4), ang = smooth(fk) * Math.PI;
    const sx = Math.abs(Math.cos(ang)) * lerp(1, 1.06, Math.sin(ang)), front = ang < Math.PI / 2;
    ctx.save(); ctx.translate(960, 470 - outK * 900); ctx.rotate(lerp(-0.12, 0, clamp(inK)) + Math.sin(ang) * 0.06); ctx.scale(inK * sx, inK);
    const img = front ? CARD_GURU : CARD_SISWA, w = 900, h = 562;
    ctx.fillStyle = 'rgba(79,70,229,.25)'; ctx.beginPath(); ctx.roundRect(-w / 2 + 22, -h / 2 + 26, w, h, 40); ctx.fill();
    ctx.drawImage(img, -w / 2, -h / 2, w, h);
    ctx.restore();
    burst(960, 470, ts + 1.9, t, 7, 22, 640);
}
function drawUnique(t) {
    drawBackground(t, 1);
    const steps = (t - T.studentEnd - 0.4) / 1.8;
    const i0 = clamp(Math.floor(steps), 0, 4), frac = steps - Math.floor(steps);
    const pos = steps < 0 ? 0 : i0 + (i0 < 4 ? smooth(range(frac, 0.78, 1)) : 0);
    UCARDS.forEach((img, i) => {
        const d = i - pos;
        const enter = backOut(range(t, T.studentEnd + i * 0.08, T.studentEnd + 0.8 + i * 0.08));
        const x = 1330 + d * 330, y = 520 + Math.abs(d) * 40, s = lerp(0.62, 1, clamp(1 - Math.abs(d))) * enter;
        if (Math.abs(d) > 2.2) return;
        card(img, x, y, 620, 388, { s, rot: d * 0.06, alpha: clamp(1.4 - Math.abs(d) * 0.45), border: 0, r: 34 });
    });
}
function drawOutro(t) {
    drawBackground(t, 1);
    const k = elastic(range(t, T.uniqEnd + 0.2, T.uniqEnd + 1.6));
    [[230, COL.blue], [290, COL.purple]].forEach(([rad, c], i) => {
        const p = easeOut(range(t, T.uniqEnd + 0.4 + i * 0.2, T.uniqEnd + 1.6 + i * 0.2));
        ctx.save(); ctx.strokeStyle = c; ctx.lineWidth = 9 - i * 2; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.arc(960, 330, rad, -Math.PI / 2 - t * 0.4, -Math.PI / 2 - t * 0.4 + Math.PI * 2 * p * 0.85); ctx.stroke(); ctx.restore();
    });
    ctx.save(); ctx.translate(960, 330); ctx.scale(k, k);
    ctx.fillStyle = 'rgba(79,70,229,.22)'; ctx.beginPath(); ctx.arc(12, 16, 175, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(0, 0, 175, 0, Math.PI * 2); ctx.fill();
    ctx.drawImage(EMBLEM, -138, -138, 276, 276);
    ctx.restore();
    burst(960, 330, T.uniqEnd + 0.4, t, 4, 20, 460);
}

const SCENES = [
    { t0: 0, t1: T.introEnd, draw: (t) => drawIntro(t) },
    { t0: T.introEnd, t1: T.rolesEnd, draw: (t) => drawRoles(t) },
    { t0: T.rolesEnd, t1: T.teacherEnd, draw: (t) => drawRole(TEACH, t, 0) },
    { t0: T.teacherEnd, t1: T.swapEnd, draw: (t) => drawSwap(t) },
    { t0: T.swapEnd, t1: T.studentEnd, draw: (t) => drawRole(STUD, t, 1) },
    { t0: T.studentEnd, t1: T.uniqEnd, draw: (t) => drawUnique(t) },
    { t0: T.uniqEnd, t1: T.end, draw: (t) => drawOutro(t) },
];
// Circle-wipe transitions: two coloured circles grow from a point, then the next scene is revealed inside a third.
const WIPES = [
    { at: T.introEnd, from: [960, 400], cols: [COL.blue, COL.indigo] },
    { at: T.teacherEnd, from: [1500, 220], cols: [COL.blue, COL.purple] },
    { at: T.swapEnd, from: [960, 470], cols: [COL.purple, COL.violet] },
    { at: T.studentEnd, from: [300, 900], cols: [COL.indigo, COL.purple] },
    { at: T.uniqEnd, from: [1330, 520], cols: [COL.violet, COL.blue] },
];
const WIPE = 0.9, RMAX = Math.hypot(W, H);
async function drawAt(t) {
    const idx = SCENES.findIndex((s) => t < s.t1);
    const i = idx < 0 ? SCENES.length - 1 : idx;
    const wipe = WIPES.find((w) => Math.abs(t - w.at) < WIPE / 2 + 0.25);
    if (!wipe) { await SCENES[i].draw(t); return; }
    const k = range(t, wipe.at - WIPE / 2, wipe.at + WIPE / 2 + 0.25);
    const prev = SCENES.findIndex((s) => s.t1 === wipe.at), next = prev + 1;
    await SCENES[prev].draw(t);
    const r1 = easeOut(range(k, 0, 0.55)) * RMAX, r2 = easeOut(range(k, 0.12, 0.7)) * RMAX, r3 = easeOut(range(k, 0.3, 1)) * RMAX;
    ctx.save(); ctx.fillStyle = wipe.cols[0]; ctx.beginPath(); ctx.arc(...wipe.from, r1, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = wipe.cols[1]; ctx.beginPath(); ctx.arc(...wipe.from, r2, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    if (r3 > 1) {
        ctx.save(); ctx.beginPath(); ctx.arc(...wipe.from, r3, 0, Math.PI * 2); ctx.clip(); ctx.clearRect(0, 0, W, H);
        await SCENES[next].draw(t); ctx.restore();
    }
}

// ---------- DOM captions (shared markup with the 3D page) ----------
const CHAPTERS = ROLES.flatMap((R) => R.chapters);
for (const c of CHAPTERS) {
    const el = document.createElement('div'); el.className = 'layer';
    const tag = `<div class="tag"><b>0${c.n}/0${c.of}</b>${c.role === 'guru' ? 'Fitur Guru' : 'Fitur Siswa'}${c.star ? '<span class="star">&#9733; Fitur Unggulan</span>' : ''}</div>`;
    const body = c.steps ? `<ol class="chips">${c.steps.map(([s], i) => `<li><b>${i + 1}</b>${s}</li>`).join('')}</ol><p class="sd"></p>` : `<p>${c.text}</p>`;
    el.innerHTML = `<div class="bar3">${tag}<h2>${c.title}</h2>${body}</div>`;
    $('caps').appendChild(el); c.el = el;
    if (c.steps) { c.chipEls = [...el.querySelectorAll('.chips li')]; c.descEl = el.querySelector('.sd'); }
}
for (const R of ROLES) {
    const el = document.createElement('div'); el.className = 'layer';
    el.innerHTML = `<div class="menu3"><span class="who">${R.role === 'guru' ? 'Guru' : 'Siswa'}</span>${R.chapters.map((c) => `<span class="it">${c.star ? '&#9733; ' : ''}${c.title}</span>`).join('')}</div>`;
    $('caps').appendChild(el); R.menuEl = el; R.menuItems = [...el.querySelectorAll('.it')];
}
const uniqItems = UNIQUE.map(([tt, d], i) => {
    const li = document.createElement('li');
    li.innerHTML = `<div class="n">${i + 1}</div><div><div class="t">${tt}</div><div class="d">${d}</div></div>`;
    $('uniq-list').appendChild(li); return li;
});
// 2D-style text motion: slide + slight overshoot, no blur
function showLayer(el, t, a, b, { dy = 40, fin = 0.5, fout = 0.3 } = {}) {
    const vin = backOut(range(t, a, a + fin)), vout = 1 - easeIn(range(t, b - fout, b));
    const v = clamp(Math.min(range(t, a, a + fin * 0.6), vout));
    el.style.opacity = v.toFixed(3);
    el.style.transform = `translateY(${((1 - vin) * dy + (1 - vout) * dy).toFixed(1)}px)`;
    el.style.filter = 'none';
}
// ---------- narrator avatar + chat-bubble subtitles ----------
// Voice lines + per-frame loudness come from voice/place-voice.py (out/voice/timing.json).
const VO = await fetch('/out/voice/timing.json').then((r) => (r.ok ? r.json() : null)).catch(() => null);
// Expression changes [from, to, face] in video seconds, computed by voice/place-voice.py from where each phrase
// falls in the recording (plus "sleepy" during long silences). Anything else = smile.
const EXPR = VO?.expr ?? [];
const voEl = $('vo'), avEl = $('vo-av'), bubbleEl = $('vo-bubble'), textEl = $('vo-text');
const faces = Object.fromEntries([...avEl.querySelectorAll('img')].map((i) => [i.dataset.e, i]));
await Promise.all(Object.values(faces).map((i) => i.decode().catch(() => {})));
let shownLine = -1, shownFace = '';
function updateNarrator(t) {
    if (!VO) { voEl.style.display = 'none'; return; }
    voEl.classList.toggle('up', t < T.introEnd || t >= T.uniqEnd);
    const appear = elastic(range(t, 0.15, 1.0)), leave = 1 - easeIn(range(t, T.end - 0.6, T.end - 0.1));
    // expression and the moment it changed (for a little pop)
    const seg = EXPR.find(([a, b]) => t >= a && t < b);
    const face = seg ? seg[2] : 'smile';
    const prevEnd = EXPR.filter(([, b]) => b <= t).map(([, b]) => b).pop() ?? 0.15;
    const changedAt = seg ? seg[0] : prevEnd;
    if (face !== shownFace) { Object.entries(faces).forEach(([k, img]) => img.classList.toggle('on', k === face)); shownFace = face; }
    const pop = lerp(0.82, 1, elastic(range(t, changedAt, changedAt + 0.5)));
    // talking bounce from the voice loudness
    const e = VO.env[Math.min(VO.env.length - 1, Math.max(0, Math.floor(t * VO.fps)))] ?? 0;
    const idle = Math.sin(t * 2.2) * 3;
    avEl.style.transform = `translateY(${(-e * 12 + idle * (1 - e)).toFixed(1)}px) rotate(${(Math.sin(t * 9) * e * 2.5).toFixed(2)}deg) scale(${(appear * leave * pop * (1 + e * 0.04)).toFixed(3)})`;
    // bubble: current line, words lighting up through the line
    const i = VO.lines.findIndex((l) => t >= l.start - 0.08 && t < l.end + 0.45);
    if (i !== shownLine) {
        shownLine = i;
        textEl.innerHTML = i < 0 ? '' : VO.lines[i].text.split(' ').map((w) => `<span class="w">${w}</span> `).join('');
    }
    if (i < 0) { bubbleEl.style.opacity = '0'; return; }
    const L = VO.lines[i], words = textEl.querySelectorAll('.w');
    const k = range(t, L.start, L.end - 0.15);
    words.forEach((w, j) => w.classList.toggle('on', k >= j / words.length));
    const bin = backOut(range(t, L.start - 0.08, L.start + 0.22)), bout = 1 - easeIn(range(t, L.end + 0.2, L.end + 0.45));
    bubbleEl.style.opacity = Math.min(1, bin * 1.4, bout).toFixed(3);
    bubbleEl.style.transform = `translateY(${((1 - bin) * 18).toFixed(1)}px) scale(${(lerp(0.85, 1, bin) * lerp(0.92, 1, bout)).toFixed(3)})`;
}

function updateDom(t) {
    updateNarrator(t);
    showLayer($('L-intro'), t, 2.0, T.introEnd - 0.5);
    showLayer($('L-roles'), t, T.introEnd + 0.5, T.introEnd + 3.0);
    document.querySelectorAll('#L-roles .role').forEach((r, i) => { r.style.transform = `scale(${elastic(range(t, T.introEnd + 0.7 + i * 0.2, T.introEnd + 1.7 + i * 0.2)).toFixed(3)})`; });
    showLayer($('L-teacher'), t, T.introEnd + 3.0, T.rolesEnd + 0.1);
    showLayer($('L-swap'), t, T.teacherEnd + 0.5, T.swapEnd - 0.5, { dy: 24 });
    $('swap-word').textContent = t < T.teacherEnd + 1.95 ? 'Guru' : 'Siswa';
    for (const R of ROLES) {
        showLayer(R.menuEl, t, R.play - 0.2, R.t1 - 0.5, { dy: -24 });
        R.chapters.forEach((c, i) => R.menuItems[i].classList.toggle('on', t >= c.start - 0.05 && t < c.end - 0.05));
    }
    for (const c of CHAPTERS) {
        showLayer(c.el, t, c.start + 0.1, c.end + 0.05, { dy: 40, fin: 0.45, fout: 0.25 });
        if (c.steps) {
            const i = stepOf(c, t);
            c.chipEls.forEach((li, j) => { li.classList.toggle('on', j === i); li.classList.toggle('done', j < i); });
            c.descEl.textContent = c.steps[Math.max(0, i)][1];
            c.descEl.style.opacity = i < 0 ? '0.55' : '1';
        }
    }
    showLayer($('L-uniq'), t, T.studentEnd + 0.3, T.uniqEnd - 0.4);
    const act = clamp(Math.floor((t - T.studentEnd - 0.4) / 1.8), 0, 4);
    uniqItems.forEach((li, i) => li.classList.toggle('on', i === act));
    showLayer($('L-outro'), t, T.uniqEnd + 0.8, T.end + 5);
    $('tint').style.opacity = smooth(range(t, T.teacherEnd + 1.6, T.teacherEnd + 3.2)).toFixed(3);
    $('flash').style.opacity = Math.max(1 - range(t, 0, 0.5), smooth(range(t, T.end - 0.5, T.end))).toFixed(3);
}

window.renderAt = async (t) => {
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, W, H);
    await drawAt(t);
    updateDom(t);
};
window.DURATION = DURATION;
// Self-check: every frame where the cursor would sit outside the visible (zoomed) part of the page, with a margin.
window.checkCrops = (margin = 0.06) => ROLES.flatMap((R) => {
    const bad = [];
    for (let f = 0; f < R.r.frames; f += 2) {
        const t = R.play + f / REC_FPS, z = screenZoom(R, t);
        if (z.S <= 1.001) continue;
        if (outside(z, R.r.path[f], margin)) bad.push(+t.toFixed(2));
    }
    return bad.length ? [`${R.role}: ${bad.length} frames, e.g. ${bad.slice(0, 12).join(', ')}`] : [`${R.role}: ok`];
});
window.SHOTS = () => ROLES.map((R) => `${R.role}: ` + R.r.shots.map((s) => `${(R.play + s.f0 / REC_FPS).toFixed(1)}s x${s.S.toFixed(2)}`).join(' | '));
window.TIMELINE = { T };
await window.renderAt(Number(new URLSearchParams(location.search).get('t') ?? 3));
window.__ready = true;
