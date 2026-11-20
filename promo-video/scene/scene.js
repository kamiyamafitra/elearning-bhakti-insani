// ~2:13 3D promo for the SMK Bhakti Insani e-learning (teacher + student roles).
// The site plays as live, cursor-guided recordings (out/rec, made by capture/record.mjs) on floating 3D laptops.
// Every frame is a function of time t only, so rendering is deterministic: await window.renderAt(t).
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const W = 1920, H = 1080, FPS = 30;

// ---------- helpers ----------
const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
const lerp = (a, b, k) => a + (b - a) * k;
const smooth = (x) => { x = clamp(x); return x * x * x * (x * (x * 6 - 15) + 10); };
const easeOut = (x) => 1 - Math.pow(1 - clamp(x), 3);
const backOut = (x) => { x = clamp(x); const c = 1.7; return 1 + (c + 1) * Math.pow(x - 1, 3) + c * Math.pow(x - 1, 2); };
const range = (t, a, b) => clamp((t - a) / (b - a));
function rng(seed) { return () => { seed |= 0; seed = (seed + 0x6D2B79F5) | 0; let r = Math.imul(seed ^ (seed >>> 15), 1 | seed); r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r; return ((r ^ (r >>> 14)) >>> 0) / 4294967296; }; }
const V = (x = 0, y = 0, z = 0) => new THREE.Vector3(x, y, z);
const $ = (id) => document.getElementById(id);
const C = { indigo: 0x4F46E5, blue: 0x3B82F6, purple: 0x7C3AED, violet: 0x8B5CF6, lilac: 0xA5B4FC, lavender: 0xC4B5FD, sky: 0x93C5FD, white: 0xFFFFFF };

// ---------- recordings ----------
const REC = await (await fetch('/out/rec/manifest.json')).json();

// Smoothed cursor (camera follows it) and zoom curve per recording, computed once.
function prepare(name) {
    const r = REC[name];
    const sm = (arr, a) => { const o = arr.map((v) => [...v]); for (let p = 0; p < 2; p++) { const idx = p ? [...o.keys()].reverse() : [...o.keys()]; let prev = o[idx[0]]; for (const i of idx) { o[i] = [lerp(prev[0], o[i][0], a), lerp(prev[1], o[i][1], a)]; prev = o[i]; } } return o; };
    r.focus = sm(r.path, 0.05);
    const z = new Array(r.frames).fill(0.2); let cur = 0.2, ev = 0;
    for (let i = 0; i < r.frames; i++) { while (r.zoom[ev] && r.zoom[ev][0] <= i) cur = r.zoom[ev++][1]; z[i] = cur; }
    r.zoomCurve = sm(z.map((v) => [v, 0]), 0.04).map((v) => v[0]);
    return r;
}

// ---------- content ----------
// One continuous recording per role; chapters start at the recording's "c:<key>" marks, steps at "<key>.sN".
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

// ---------- timeline ----------
const LEAD = 0.8, TAIL = 0.6;
const T = { introEnd: 7, rolesEnd: 12 };
let clock = T.rolesEnd;
for (const R of ROLES) {
    if (R.role === 'siswa') { T.teacherEnd = clock; T.swapEnd = clock + 5; clock = T.swapEnd; }
    R.r = prepare(R.rec);
    R.t0 = clock; R.play = clock + LEAD; R.t1 = R.play + R.r.frames / FPS + TAIL; clock = R.t1;
    const at = (m) => R.play + (R.r.marks[m] ?? 0) / FPS;
    R.chapters.forEach((c, i) => {
        c.n = i + 1; c.of = R.chapters.length; c.role = R.role;
        c.start = at('c:' + c.key);
        c.end = R.chapters[i + 1] ? at('c:' + R.chapters[i + 1].key) : R.t1 - 0.2;
        if (c.steps) c.stepTimes = c.steps.map((_, j) => at(`${c.key}.s${j + 1}`));
    });
}
const [TEACH, STUD] = ROLES;
T.studentEnd = clock; T.uniqEnd = clock + 9.5; T.end = T.uniqEnd + 6;
export const DURATION = T.end;
const frameOf = (R, t) => clamp(Math.floor((t - R.play) * FPS), 0, R.r.frames - 1);
const markTime = (R, m, extra = 0) => R.play + ((R.r.marks[m] ?? 0) + extra) / FPS;
const stepOf = (c, t) => { let i = -1; c.stepTimes.forEach((s, j) => { if (t >= s) i = j; }); return i; };

// ---------- world layout ----------
const LAPTOP_Y = -5;
const TEACH_P = V(60, LAPTOP_Y, 0);
const SWAP_P = V(TEACH_P.x + 52, 0.5, -2);
const TURN_P = V(SWAP_P.x + 70, LAPTOP_Y, 0);
TEACH.home = TEACH_P; STUD.home = TURN_P;
const UNIQ_C = V(TURN_P.x + 90, 0, -4);

// ---------- renderer / scene ----------
const renderer = new THREE.WebGLRenderer({ canvas: $('c'), antialias: true, alpha: true, preserveDrawingBuffer: true });
renderer.setPixelRatio(1); renderer.setSize(W, H, false);
renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.toneMapping = THREE.NeutralToneMapping;
renderer.setClearColor(0x000000, 0);
const scene = new THREE.Scene();
const FOG_A = new THREE.Color(0xF2F3FF), FOG_B = new THREE.Color(0xF6F0FF);
scene.fog = new THREE.Fog(FOG_A.clone(), 50, 170);
scene.environment = new THREE.PMREMGenerator(renderer).fromScene(new RoomEnvironment(), 0.04).texture;
scene.environmentIntensity = 0.9;
const camera = new THREE.PerspectiveCamera(40, W / H, 0.1, 700);
const key = new THREE.DirectionalLight(0xffffff, 1.6); key.position.set(20, 40, 30); scene.add(key);
scene.add(new THREE.HemisphereLight(0xffffff, 0xE0E7FF, 0.8));
const grid = new THREE.GridHelper(1800, 360, 0xC7CCF5, 0xDDE0FA);
grid.position.set(320, -9, 0); grid.material.transparent = true; grid.material.opacity = 0.55; scene.add(grid);
const glossy = (color, opts = {}) => new THREE.MeshPhysicalMaterial({ color, roughness: 0.18, metalness: 0.05, clearcoat: 1, clearcoatRoughness: 0.12, ...opts });

// ---------- textures ----------
function canvasTex(cv) { const t = new THREE.CanvasTexture(cv); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = renderer.capabilities.getMaxAnisotropy(); return t; }
const loadImage = (src) => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = src; });
function radialTex(inner, outer, size = 256) {
    const cv = document.createElement('canvas'); cv.width = cv.height = size; const c = cv.getContext('2d');
    const g = c.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2); g.addColorStop(0, inner); g.addColorStop(1, outer);
    c.fillStyle = g; c.fillRect(0, 0, size, size); return canvasTex(cv);
}
const shadowTex = radialTex('rgba(30,27,75,0.55)', 'rgba(30,27,75,0)');
const gradStops = (g, a = '#3B82F6', b = '#4F46E5', c = '#7C3AED') => { g.addColorStop(0, a); g.addColorStop(0.5, b); g.addColorStop(1, c); return g; };
const glowTex = (() => {
    const cv = document.createElement('canvas'); cv.width = 512; cv.height = 320; const c = cv.getContext('2d');
    c.filter = 'blur(28px)'; c.fillStyle = gradStops(c.createLinearGradient(0, 0, 512, 320)); c.beginPath(); c.roundRect(60, 50, 392, 220, 40); c.fill(); return canvasTex(cv);
})();

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
const EMBLEM = await emblemCanvas();
const emblemTex = canvasTex(EMBLEM);
function makeEmblem(size = 9) {
    const g = new THREE.Group();
    const s = size / EMBLEM.width, cx = EMBLEM.width / 2;
    const pts = [[506, 40], [975, 392], [940, 925], [508, 978], [72, 925], [37, 392]].map(([x, y]) => new THREE.Vector2((x - cx) * s, (cx - y) * s));
    const body = new THREE.Mesh(new THREE.ExtrudeGeometry(new THREE.Shape(pts), { depth: 0.7, bevelEnabled: true, bevelThickness: 0.18, bevelSize: 0.14, bevelSegments: 4 }), glossy(0x1E1B4B, { roughness: 0.3 }));
    body.position.z = -0.9; g.add(body);
    g.add(new THREE.Mesh(new THREE.PlaneGeometry(size, size), new THREE.MeshBasicMaterial({ map: emblemTex, transparent: true, alphaTest: 0.04, toneMapped: false })));
    return g;
}
const logo = makeEmblem(9); scene.add(logo);
const logo2 = makeEmblem(7.5); logo2.position.copy(UNIQ_C); scene.add(logo2);
function makeRing(r, color, tube = 0.08) { return new THREE.Mesh(new THREE.TorusGeometry(r, tube, 16, 160), glossy(color, { emissive: color, emissiveIntensity: 0.25 })); }
const rings = [makeRing(7.5, C.blue), makeRing(9.5, C.purple, 0.05), makeRing(11.5, C.lilac, 0.04)]; rings.forEach((r) => scene.add(r));
const rings2 = [makeRing(7.5, C.blue), makeRing(18, C.lilac, 0.05)]; rings2.forEach((r) => { r.position.copy(UNIQ_C); scene.add(r); });

// ---------- floating shapes ----------
const shapeGeos = [new THREE.SphereGeometry(1, 48, 32), new RoundedBoxGeometry(1.6, 1.6, 1.6, 6, 0.35), new THREE.TorusGeometry(1, 0.36, 24, 64),
    new THREE.IcosahedronGeometry(1.1, 0), new THREE.CapsuleGeometry(0.6, 1.2, 8, 20), new THREE.TorusKnotGeometry(0.8, 0.26, 120, 16), new THREE.ConeGeometry(0.9, 1.6, 40)];
const PALETTE = [[C.indigo, C.purple], [C.blue, C.violet], [C.purple, C.purple], [C.white, C.white], [C.white, C.white], [C.lilac, C.lavender], [C.sky, C.lavender]];
const shapeMats = PALETTE.map(([a]) => glossy(a));
const shapes = [];
{
    const r = rng(7);
    for (let i = 0; i < 260; i++) {
        const m = new THREE.Mesh(shapeGeos[Math.floor(r() * shapeGeos.length)], shapeMats[Math.floor(r() * shapeMats.length)]);
        const x = -40 + r() * (UNIQ_C.x + 80);
        let y = -6 + r() * 22, z = -16 - r() * 34;
        if (r() < 0.12) { z = 4 + r() * 10; y = 14 + r() * 6; }
        const s = 0.5 + r() * 1.6;
        if (x > UNIQ_C.x - 48 && x < UNIQ_C.x - 8) continue;
        if (Math.abs(x - SWAP_P.x) < 14 && z > -20) continue;
        if (Math.abs(x - TURN_P.x) < 20 && z > -24) continue;
        m.position.set(x, y, z); m.scale.setScalar(s);
        m.userData = { base: m.position.clone(), rot: V(r() * 6, r() * 6, r() * 6), spin: V(r() - 0.5, r() - 0.5, r() - 0.5).multiplyScalar(0.8), bob: 0.3 + r() * 0.9, ph: r() * 6.28 };
        scene.add(m); shapes.push(m);
    }
}

// ---------- 3D laptop ----------
const SW = 20, SH = 12.5, LID_TILT = 0.24, HINGE = V(0, 0.32, -7.1), CHIN = 0.8, SCREEN_Z = 0.19;
const LID_UP = V(0, Math.cos(LID_TILT), -Math.sin(LID_TILT)), LID_N = V(0, Math.sin(LID_TILT), Math.cos(LID_TILT));
const alu = new THREE.MeshPhysicalMaterial({ color: 0xE4E6F2, metalness: 0.65, roughness: 0.3, clearcoat: 0.6, clearcoatRoughness: 0.2 });
function keyboardTexture() {
    const cv = document.createElement('canvas'); cv.width = 1600; cv.height = 1060; const c = cv.getContext('2d');
    c.fillStyle = '#D9DCEA'; c.fillRect(0, 0, 1600, 1060);
    c.fillStyle = '#C9CDE0'; c.beginPath(); c.roundRect(110, 70, 1380, 520, 26); c.fill();
    const rows = [14, 14, 13, 12, 11];
    rows.forEach((n, r) => { const kw = (1340 - (n - 1) * 10) / n; for (let k = 0; k < n; k++) { c.fillStyle = '#2A2D3E'; c.beginPath(); c.roundRect(130 + k * (kw + 10), 90 + r * 98, kw, 86, 12); c.fill(); } });
    c.fillStyle = '#2A2D3E'; c.beginPath(); c.roundRect(470, 90 + 5 * 98 - 6, 660, 0, 12); c.fill();
    c.fillStyle = '#CDD1E3'; c.strokeStyle = '#BEC2D6'; c.lineWidth = 4; c.beginPath(); c.roundRect(560, 650, 480, 330, 24); c.fill(); c.stroke();
    return canvasTex(cv);
}
const kbTex = keyboardTexture();
function makeLaptop() {
    const g = new THREE.Group();
    const base = new THREE.Mesh(new RoundedBoxGeometry(21.6, 0.62, 14.6, 4, 0.28), alu); g.add(base);
    const kb = new THREE.Mesh(new THREE.PlaneGeometry(21, 13.9), new THREE.MeshStandardMaterial({ map: kbTex, roughness: 0.6 }));
    kb.rotation.x = -Math.PI / 2; kb.position.y = 0.315; g.add(kb);
    const lid = new THREE.Group(); lid.position.copy(HINGE); lid.rotation.x = -LID_TILT; g.add(lid);
    const shell = new THREE.Mesh(new RoundedBoxGeometry(21.6, 14.2, 0.34, 4, 0.16), alu); shell.position.y = 7.1; lid.add(shell);
    const bezel = new THREE.Mesh(new THREE.PlaneGeometry(21.2, 13.8), new THREE.MeshPhysicalMaterial({ color: 0x0F1222, roughness: 0.15, clearcoat: 1 }));
    bezel.position.set(0, 7.1, 0.172); lid.add(bezel);
    const screenTex = new THREE.Texture(); screenTex.colorSpace = THREE.SRGBColorSpace; screenTex.flipY = false; screenTex.anisotropy = renderer.capabilities.getMaxAnisotropy();
    screenTex.minFilter = THREE.LinearMipmapLinearFilter;
    const screen = new THREE.Mesh(new THREE.PlaneGeometry(SW, SH), new THREE.MeshBasicMaterial({ map: screenTex, toneMapped: false, color: 0xffffff }));
    screen.position.set(0, CHIN + SH / 2, SCREEN_Z); lid.add(screen);
    const back = new THREE.Mesh(new THREE.PlaneGeometry(4.2, 4.2), new THREE.MeshBasicMaterial({ map: emblemTex, transparent: true, alphaTest: 0.04, toneMapped: false }));
    back.position.set(0, 7.3, -0.18); back.rotation.y = Math.PI; lid.add(back);
    const glow = new THREE.Mesh(new THREE.PlaneGeometry(32, 22), new THREE.MeshBasicMaterial({ map: glowTex, transparent: true, opacity: 0.5, depthWrite: false, fog: false }));
    glow.position.set(0, 7.2, -1.4); lid.add(glow);
    g.userData = { lid, screen, screenTex, frameKey: null };
    return g;
}
// World point on a resting laptop's screen (u,v in 0..1 from top-left).
const screenPoint = (home, [u, v], z = 0.25) => home.clone().add(HINGE).addScaledVector(LID_UP, CHIN + (1 - v) * SH).addScaledVector(LID_N, SCREEN_Z + z).add(V((u - 0.5) * SW, 0, 0));

// Frame loading: one ImageBitmap per displayed frame, decoded on demand.
async function showFrame(laptop, rec, idx) {
    const k = rec + '/' + idx;
    if (laptop.userData.frameKey === k) return;
    const blob = await (await fetch(`/out/rec/${rec}/${String(idx).padStart(5, '0')}.jpg`)).blob();
    const bmp = await createImageBitmap(blob, { imageOrientation: 'flipY' });
    const tex = laptop.userData.screenTex;
    tex.image?.close?.();
    tex.image = bmp; tex.needsUpdate = true;
    laptop.userData.frameKey = k;
}

const teacherLaptop = makeLaptop(); teacherLaptop.position.copy(TEACH_P); scene.add(teacherLaptop); TEACH.laptop = teacherLaptop;
{
    const sh = new THREE.Mesh(new THREE.PlaneGeometry(30, 20), new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, opacity: 0.35, depthWrite: false }));
    sh.rotation.x = -Math.PI / 2; sh.position.set(TEACH_P.x, -8.95, TEACH_P.z); scene.add(sh);
}
const turntable = new THREE.Group(); turntable.position.copy(TURN_P); scene.add(turntable);
const disc = new THREE.Mesh(new THREE.CylinderGeometry(15, 15.6, 0.8, 96), glossy(C.white, { roughness: 0.22 }));
disc.position.y = -0.75; turntable.add(disc);
const discRing = makeRing(15.2, C.purple, 0.12); discRing.rotation.x = Math.PI / 2; discRing.position.y = -0.3; turntable.add(discRing);
const studentLaptop = makeLaptop(); turntable.add(studentLaptop); STUD.laptop = studentLaptop;
const turnShadow = new THREE.Mesh(new THREE.PlaneGeometry(44, 44), new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, opacity: 0.4, depthWrite: false }));
turnShadow.rotation.x = -Math.PI / 2; turnShadow.position.set(TURN_P.x, -8.95, TURN_P.z); scene.add(turnShadow);

// ---------- pop-outs (Excel export, photos -> PDF) ----------
function makePanel(img, w, h) {
    const g = new THREE.Group();
    const glow = new THREE.Mesh(new THREE.PlaneGeometry(w * 1.45, h * 1.6), new THREE.MeshBasicMaterial({ map: glowTex, transparent: true, opacity: 0.5, depthWrite: false, fog: false }));
    glow.position.z = -0.9; g.add(glow);
    g.add(new THREE.Mesh(new RoundedBoxGeometry(w + 0.5, h + 0.5, 0.5, 6, 0.24), glossy(C.white, { roughness: 0.25 })));
    const cv = document.createElement('canvas'); cv.width = img.width; cv.height = img.height;
    const c = cv.getContext('2d'); c.beginPath(); c.roundRect(0, 0, cv.width, cv.height, 34); c.clip(); c.drawImage(img, 0, 0);
    const face = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ map: canvasTex(cv), transparent: true, toneMapped: false }));
    face.position.z = 0.26; g.add(face);
    return g;
}
const sheetPanel = makePanel(await loadImage('/assets/shots/rp-4.png'), 16, 10); scene.add(sheetPanel);
const pdfGroup = new THREE.Group(); scene.add(pdfGroup);
const photos = [];
for (let i = 1; i <= 3; i++) {
    const img = await loadImage(`/capture/foto-tugas-${i}.jpg`);
    const m = new THREE.Mesh(new RoundedBoxGeometry(3.6, 4.8, 0.08, 2, 0.04), [glossy(C.white), glossy(C.white), glossy(C.white), glossy(C.white),
        new THREE.MeshBasicMaterial({ map: canvasTex(img), toneMapped: false }), glossy(C.white)]);
    pdfGroup.add(m); photos.push(m);
}
function pdfDocTexture() {
    const cv = document.createElement('canvas'); cv.width = 768; cv.height = 1024; const c = cv.getContext('2d');
    c.fillStyle = '#fff'; c.beginPath(); c.roundRect(0, 0, 768, 1024, 40); c.fill();
    c.fillStyle = '#F4F5FB'; c.fillRect(60, 300, 648, 520);
    [0, 1, 2].forEach((i) => { c.save(); c.translate(110 + i * 190, 340); c.rotate((i - 1) * 0.06); c.fillStyle = '#fffdf6'; c.fillRect(0, 0, 170, 226); c.strokeStyle = '#d8dbe8'; c.strokeRect(0, 0, 170, 226);
        c.strokeStyle = '#b9d3f0'; for (let y = 30; y < 226; y += 22) { c.beginPath(); c.moveTo(8, y); c.lineTo(162, y); c.stroke(); } c.restore(); });
    c.fillStyle = '#E11D48'; c.beginPath(); c.roundRect(60, 64, 180, 96, 22); c.fill();
    c.fillStyle = '#fff'; c.font = '800 54px "Plus Jakarta Sans"'; c.textAlign = 'center'; c.fillText('PDF', 150, 132);
    c.textAlign = 'left'; c.fillStyle = '#0F1222'; c.font = '800 50px "Plus Jakarta Sans"'; c.fillText('Tugas-Subnetting.pdf', 60, 236);
    c.fillStyle = '#454A63'; c.font = '600 36px "Plus Jakarta Sans"'; c.fillText('3 halaman · 1 file', 60, 650);
    c.fillStyle = gradStops(c.createLinearGradient(60, 0, 708, 0)); c.beginPath(); c.roundRect(60, 880, 648, 84, 42); c.fill();
    c.fillStyle = '#fff'; c.font = '700 34px "Plus Jakarta Sans"'; c.textAlign = 'center'; c.fillText('Siap dikumpulkan', 384, 934);
    return canvasTex(cv);
}
const pdfDoc = new THREE.Group();
pdfDoc.add(new THREE.Mesh(new RoundedBoxGeometry(6.2, 8.2, 0.35, 4, 0.2), glossy(C.white)));
{ const face = new THREE.Mesh(new THREE.PlaneGeometry(6, 8), new THREE.MeshBasicMaterial({ map: pdfDocTexture(), transparent: true, toneMapped: false })); face.position.z = 0.18; pdfDoc.add(face); }
pdfGroup.add(pdfDoc);

// ---------- role-swap flip card ----------
function roleCardTexture(role) {
    const cv = document.createElement('canvas'); cv.width = 1600; cv.height = 1000; const c = cv.getContext('2d');
    c.beginPath(); c.roundRect(0, 0, 1600, 1000, 70); c.clip();
    c.fillStyle = role === 'guru' ? gradStops(c.createLinearGradient(0, 0, 1600, 1000), '#2563EB', '#3B82F6', '#4F46E5') : gradStops(c.createLinearGradient(0, 0, 1600, 1000), '#4F46E5', '#7C3AED', '#A855F7');
    c.fillRect(0, 0, 1600, 1000);
    c.globalAlpha = 0.12; c.fillStyle = '#fff';
    for (let i = 0; i < 6; i++) { c.beginPath(); c.arc(1300 - i * 40, 200 + i * 30, 260 + i * 70, 0, Math.PI * 2); c.fill(); }
    c.globalAlpha = 1; c.drawImage(EMBLEM, 110, 110, 190, 190);
    c.fillStyle = 'rgba(255,255,255,.85)'; c.font = '700 44px "Plus Jakarta Sans"'; c.fillText(role === 'guru' ? 'BAGIAN 1  ·  PERAN' : 'BAGIAN 2  ·  PERAN', 340, 190);
    c.font = '600 40px "Plus Jakarta Sans"'; c.fillText('SMK Bhakti Insani', 340, 250);
    c.fillStyle = '#fff'; c.font = '800 300px "Plus Jakarta Sans"'; c.fillText(role === 'guru' ? 'Guru' : 'Siswa', 100, 660);
    c.font = '500 64px "Plus Jakarta Sans"'; c.fillStyle = 'rgba(255,255,255,.92)'; c.fillText(role === 'guru' ? 'Mengajar lebih ringan.' : 'Belajar lebih fokus.', 110, 820);
    return canvasTex(cv);
}
const swapCard = new THREE.Group();
swapCard.add(new THREE.Mesh(new RoundedBoxGeometry(16.4, 10.4, 0.6, 6, 0.3), glossy(C.white)));
{
    const front = new THREE.Mesh(new THREE.PlaneGeometry(16, 10), new THREE.MeshBasicMaterial({ map: roleCardTexture('guru'), transparent: true, toneMapped: false })); front.position.z = 0.31; swapCard.add(front);
    const back = new THREE.Mesh(new THREE.PlaneGeometry(16, 10), new THREE.MeshBasicMaterial({ map: roleCardTexture('siswa'), transparent: true, toneMapped: false })); back.position.z = -0.31; back.rotation.y = Math.PI; swapCard.add(back);
}
swapCard.position.copy(SWAP_P); scene.add(swapCard);
const burst = [];
{
    const r = rng(11);
    for (let i = 0; i < 36; i++) {
        const m = new THREE.Mesh(shapeGeos[i % 4], shapeMats[[0, 1, 2, 5, 6][i % 5]]);
        const a = r() * Math.PI * 2, e = (r() - 0.5) * 1.6;
        // keep the burst behind/around the card, not across its text
        m.userData = { dir: V(Math.cos(a) * Math.cos(e), Math.sin(e), -0.35 - r() * 0.5).normalize(), speed: 9 + r() * 9, size: 0.25 + r() * 0.45, spin: r() * 6 };
        scene.add(m); burst.push(m);
    }
}

// ---------- unique-feature cards ----------
const RING_R = 12.5;
function cardTexture(i, title, desc) {
    const cv = document.createElement('canvas'); cv.width = 1024; cv.height = 640; const c = cv.getContext('2d');
    c.fillStyle = '#FFFFFF'; c.beginPath(); c.roundRect(0, 0, 1024, 640, 56); c.fill();
    const g = gradStops(c.createLinearGradient(0, 0, 1024, 0));
    c.save(); c.beginPath(); c.roundRect(0, 0, 1024, 640, 56); c.clip(); c.fillStyle = g; c.fillRect(0, 0, 1024, 18); c.restore();
    c.fillStyle = g; c.beginPath(); c.roundRect(64, 76, 108, 108, 30); c.fill();
    c.fillStyle = '#fff'; c.font = '800 56px "Plus Jakarta Sans"'; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillText(String(i + 1), 118, 132);
    c.textAlign = 'left'; c.textBaseline = 'alphabetic'; c.fillStyle = '#0F1222'; c.font = '800 64px "Plus Jakarta Sans"'; c.fillText(title, 64, 290);
    c.fillStyle = '#454A63'; c.font = '500 40px "Plus Jakarta Sans"';
    let line = '', y = 372;
    for (const w of desc.split(' ')) { const test = line ? line + ' ' + w : w; if (c.measureText(test).width > 880) { c.fillText(line, 64, y); line = w; y += 56; } else line = test; }
    c.fillText(line, 64, y);
    return canvasTex(cv);
}
await document.fonts.load('800 66px "Plus Jakarta Sans"');
await document.fonts.load('500 40px "Plus Jakarta Sans"');
await document.fonts.ready;
const uniqCards = UNIQUE.map(([title, desc], i) => {
    const g = new THREE.Group();
    g.add(new THREE.Mesh(new RoundedBoxGeometry(10.4, 6.6, 0.45, 6, 0.3), glossy(C.white, { roughness: 0.25 })));
    const face = new THREE.Mesh(new THREE.PlaneGeometry(10, 6.25), new THREE.MeshBasicMaterial({ map: cardTexture(i, title, desc), transparent: true, toneMapped: false })); face.position.z = 0.24; g.add(face);
    const glow = new THREE.Mesh(new THREE.PlaneGeometry(15, 10), new THREE.MeshBasicMaterial({ map: glowTex, transparent: true, opacity: 0.45, depthWrite: false, fog: false })); glow.position.z = -0.6; g.add(glow);
    scene.add(g); return g;
});

// ---------- camera ----------
const RIGHT = V(1, 0, 0), UP = V(0, 1, 0);
// Frames a laptop screen: zoom 0 shows the whole laptop, zoom 1 is close on the focus point.
// The screen sits in the upper part of the frame; the caption bar sits over the keyboard.
function frameLaptop(home, focusUV, zoom, yaw, lift) {
    const centre = screenPoint(home, [0.5, 0.5]);
    const focus = screenPoint(home, focusUV);
    const fk = clamp(zoom * 1.5);
    const target = centre.clone().lerp(focus, fk).addScaledVector(UP, -lerp(3.0, 1.4, zoom));
    const dist = lerp(38, 24, zoom);
    const dir = LID_N.clone().setY(LID_N.y * 0.6).normalize().applyAxisAngle(UP, yaw);
    return { pos: target.clone().addScaledVector(dir, dist).addScaledVector(UP, lift), target };
}
// One continuous shot per role: the camera follows the smoothed cursor and slowly swings around the laptop.
function rolePose(R, t) {
    const k = range(t, R.t0, R.t1);
    const fr = frameOf(R, t);
    const live = smooth(range(t, R.play - 0.2, R.play + 0.8)) * (1 - smooth(range(t, R.t1 - 0.8, R.t1 + 0.4)) * 0.7);
    const zoom = R.r.zoomCurve[fr] * live;
    const foc = R.r.focus[fr];
    const uv = [lerp(0.5, foc[0], live * 0.85), lerp(0.5, foc[1], live * 0.85)];
    const yaw = R.role === 'guru' ? -0.2 + Math.sin(k * Math.PI * 2) * 0.12 : 0.16 - Math.sin(k * Math.PI * 2) * 0.12;
    return frameLaptop(R.home, uv, zoom, yaw, lerp(2.2, 1.2, smooth(k)));
}
const SHOTS = [
    { t0: 0, t1: T.introEnd, pose(t) {
        const k = range(t, 0, T.introEnd);
        const a = lerp(1.1, 0.18, easeOut(k * 1.05)), r = lerp(12, 31, easeOut(k));
        return { pos: V(Math.sin(a) * r, lerp(-1.5, 2.5, easeOut(k)), Math.cos(a) * r), target: V(0, lerp(0.5, -5.2, smooth(range(k, 0.3, 1))), 0) };
    } },
    { t0: T.introEnd, t1: T.rolesEnd, pose(t) {
        const k = range(t, T.introEnd, T.rolesEnd);
        return { pos: V(lerp(8, 22, k), lerp(5, 3, k), 30), target: V(lerp(20, 32, k), 1.5, -6) };
    } },
    { t0: TEACH.t0, t1: TEACH.t1, pose: (t) => rolePose(TEACH, t) },
    { t0: T.teacherEnd, t1: T.swapEnd, arc: 4, pose(t) {
        const k = range(t, T.teacherEnd, T.swapEnd);
        return { pos: SWAP_P.clone().add(V(lerp(-3, 3, smooth(k)), lerp(2.5, 0.5, smooth(k)), lerp(27, 21, easeOut(k)))), target: SWAP_P.clone() };
    } },
    { t0: STUD.t0, t1: STUD.t1, arc: 6, pose: (t) => rolePose(STUD, t) },
    { t0: T.studentEnd, t1: T.uniqEnd, arc: 7, pose(t) {
        const k = range(t, T.studentEnd, T.uniqEnd);
        const a = lerp(-0.32, -0.12, smooth(k)), r = lerp(46, 40, smooth(k));
        const target = UNIQ_C.clone().add(V(-9.5, 0.5, 0));
        return { pos: target.clone().add(V(Math.sin(a) * r, lerp(7, 5, k), Math.cos(a) * r)), target };
    } },
    { t0: T.uniqEnd, t1: T.end, pose(t) {
        const k = range(t, T.uniqEnd, T.end);
        return { pos: UNIQ_C.clone().add(V(lerp(-6, 0, smooth(k)), lerp(5, 9, smooth(k)), lerp(40, 58, easeOut(k)))), target: UNIQ_C.clone().add(V(lerp(-9.5, 0, smooth(k)), lerp(0.5, -9, smooth(k)), 0)) };
    } },
];
function cameraAt(t) {
    let i = SHOTS.findIndex((s) => t < s.t1); if (i < 0) i = SHOTS.length - 1;
    let { pos, target } = SHOTS[i].pose(t);
    const mix = (a, b, nextShot, boundary) => {
        const bl = nextShot.blend ?? 1.9, arcAmt = nextShot.arc ?? 7, lift = nextShot.lift ?? 0.35;
        const w = smooth((t - (boundary - bl / 2)) / bl), arc = Math.sin(Math.PI * w);
        return { pos: a.pos.clone().lerp(b.pos, w).add(V(0, arc * arcAmt * lift, arc * arcAmt)), target: a.target.clone().lerp(b.target, w) };
    };
    const s = SHOTS[i], next = SHOTS[i + 1], prev = SHOTS[i - 1];
    if (next && t > s.t1 - (next.blend ?? 1.9) / 2) ({ pos, target } = mix({ pos, target }, next.pose(t), next, s.t1));
    else if (prev && t < s.t0 + (s.blend ?? 1.9) / 2) ({ pos, target } = mix(prev.pose(t), { pos, target }, s, s.t0));
    pos.add(V(Math.sin(t * 0.7) * 0.16, Math.sin(t * 0.9 + 1) * 0.12, 0));
    camera.position.copy(pos); camera.lookAt(target); camera.updateMatrixWorld();
}

// ---------- DOM captions ----------
// Lower-third caption bar per chapter, plus a role menu strip showing where we are in the session.
const CHAPTERS = ROLES.flatMap((R) => R.chapters);
for (const c of CHAPTERS) {
    const el = document.createElement('div'); el.className = 'layer';
    const tag = `<div class="tag"><b>0${c.n}/0${c.of}</b>${c.role === 'guru' ? 'Fitur Guru' : 'Fitur Siswa'}${c.star ? '<span class="star">&#9733; Fitur Unggulan</span>' : ''}</div>`;
    const body = c.steps
        ? `<ol class="chips">${c.steps.map(([t], i) => `<li><b>${i + 1}</b>${t}</li>`).join('')}</ol><p class="sd"></p>`
        : `<p>${c.text}</p>`;
    el.innerHTML = `<div class="bar3">${tag}<h2>${c.title}</h2>${body}</div>`;
    $('caps').appendChild(el); c.el = el;
    if (c.steps) { c.chipEls = [...el.querySelectorAll('.chips li')]; c.descEl = el.querySelector('.sd'); }
}
for (const R of ROLES) {
    const el = document.createElement('div'); el.className = 'layer';
    el.innerHTML = `<div class="menu3"><span class="who">${R.role === 'guru' ? 'Guru' : 'Siswa'}</span>${R.chapters.map((c) => `<span class="it">${c.star ? '&#9733; ' : ''}${c.title}</span>`).join('')}</div>`;
    $('caps').appendChild(el); R.menuEl = el; R.menuItems = [...el.querySelectorAll('.it')];
}
const uniqItems = UNIQUE.map(([t, d], i) => {
    const li = document.createElement('li');
    li.innerHTML = `<div class="n">${i + 1}</div><div><div class="t">${t}</div><div class="d">${d}</div></div>`;
    $('uniq-list').appendChild(li); return li;
});
function showLayer(el, t, a, b, { dy = 40, fin = 0.55, fout = 0.4 } = {}) {
    const vin = easeOut(range(t, a, a + fin)), vout = 1 - smooth(range(t, b - fout, b));
    const v = Math.min(vin, vout);
    el.style.opacity = v.toFixed(3);
    el.style.transform = `translateY(${((1 - vin) * dy - (1 - vout) * dy * 0.5).toFixed(1)}px)`;
    el.style.filter = v < 0.999 ? `blur(${((1 - v) * 8).toFixed(1)}px)` : 'none';
}

// ---------- per-frame update ----------
const col = new THREE.Color();

async function update(t) {
    const swapK = smooth(range(t, T.teacherEnd + 1.8, T.teacherEnd + 3.6));
    shapeMats.forEach((m, i) => m.color.copy(col.set(PALETTE[i][0]).lerp(new THREE.Color(PALETTE[i][1]), swapK)));
    scene.fog.color.copy(FOG_A).lerp(FOG_B, swapK);
    $('tint').style.opacity = swapK.toFixed(3);

    for (const m of shapes) {
        const u = m.userData;
        m.position.y = u.base.y + Math.sin(t * u.bob + u.ph) * 0.8;
        m.rotation.set(u.rot.x + t * u.spin.x, u.rot.y + t * u.spin.y, u.rot.z + t * u.spin.z);
    }
    const ki = easeOut(range(t, 0, 3.2));
    logo.rotation.set(Math.sin(t * 0.8) * 0.05, lerp(-Math.PI * 1.5, 0, ki) + Math.sin(t * 0.6) * 0.14, 0);
    logo.position.y = Math.sin(t * 1.1) * 0.35; logo.scale.setScalar(lerp(0.4, 1, ki));
    rings.forEach((r, i) => {
        r.scale.setScalar(Math.max(0.001, easeOut(range(t, 0.4 + i * 0.35, 2.4 + i * 0.35))));
        r.rotation.set(Math.PI / 2 + Math.sin(t * 0.5 + i) * 0.35, t * (0.2 + i * 0.1), Math.cos(t * 0.4 + i) * 0.3);
    });

    // teacher laptop: flies in and opens as the camera arrives, closes when the teacher part ends
    {
        const e = easeOut(range(t, TEACH.t0 - 2.2, TEACH.t0 + 0.5));
        const open = smooth(range(t, TEACH.t0 - 1.4, TEACH.t0 + 0.6)) * (1 - smooth(range(t, TEACH.t1 - 0.7, TEACH.t1 + 0.5)));
        teacherLaptop.position.set(TEACH_P.x, TEACH_P.y + (1 - e) * -6, TEACH_P.z);
        teacherLaptop.rotation.set((1 - e) * 0.4, (1 - e) * -0.8, 0);
        teacherLaptop.scale.setScalar(lerp(0.75, 1, e));
        teacherLaptop.userData.lid.rotation.x = lerp(1.45, -LID_TILT, open);
    }

    // role swap
    const ts = T.teacherEnd;
    const flip = backOut(range(t, ts + 1.6, ts + 3.0));
    const inK = easeOut(range(t, ts - 1.2, ts + 0.6)), outK = smooth(range(t, T.swapEnd - 0.9, T.swapEnd + 0.6));
    swapCard.position.copy(SWAP_P).add(V(0, (1 - inK) * -8 + Math.sin(Math.PI * range(t, ts + 1.6, ts + 3.0)) * 1.6 + outK * 14, -outK * 10));
    swapCard.rotation.set((1 - inK) * 0.6 + Math.sin(t * 0.8) * 0.03, flip * Math.PI + (1 - inK) * -0.5, Math.sin(Math.PI * range(t, ts + 1.6, ts + 3.0)) * 0.12);
    swapCard.scale.setScalar(Math.max(0.001, lerp(0.6, 1, inK) * (1 - outK * 0.6)));
    const bk = range(t, ts + 2.1, ts + 4.4);
    burst.forEach((m) => {
        const u = m.userData;
        m.position.copy(SWAP_P).addScaledVector(u.dir, 6 + easeOut(bk) * u.speed);
        m.scale.setScalar(bk > 0 && bk < 1 ? u.size * Math.sin(Math.PI * Math.min(1, bk * 1.4)) + 0.001 : 0.001);
        m.rotation.set(t * u.spin, t * u.spin * 0.7, 0);
    });

    // student turntable: rises in during the swap with the lid opening; the disc ring keeps turning
    const rise = easeOut(range(t, T.swapEnd - 2.0, T.swapEnd + 0.3));
    turntable.position.set(TURN_P.x, TURN_P.y + (1 - rise) * -14, TURN_P.z);
    turntable.scale.setScalar(Math.max(0.001, lerp(0.5, 1, rise)));
    turntable.rotation.y = (1 - rise) * Math.PI * 1.5;
    studentLaptop.userData.lid.rotation.x = lerp(1.45, -LID_TILT, smooth(range(t, T.swapEnd - 1.4, T.swapEnd + 0.4)));
    discRing.rotation.z = t * 0.3;

    // live screens: each role's laptop plays its one continuous session
    await Promise.all(ROLES.map((R) => showFrame(R.laptop, R.rec, frameOf(R, t))));

    // rapor -> Excel pop-out from the "Unduh Excel" button
    const sx = markTime(TEACH, 'rapor.pop', 3);
    const btnUV = TEACH.r.path[Math.min(TEACH.r.frames - 1, TEACH.r.marks['rapor.pop'] ?? 0)];
    const pk = easeOut(range(t, sx, sx + 1.2)), pOut = smooth(range(t, TEACH.t1 - 0.9, TEACH.t1 - 0.1));
    sheetPanel.position.copy(screenPoint(TEACH_P, btnUV, 0.5)).lerp(screenPoint(TEACH_P, [0.56, 0.5], 7.5), pk);
    sheetPanel.rotation.set(0.1 * pk, lerp(-0.9, -0.08, pk), lerp(0.4, 0, pk));
    sheetPanel.scale.setScalar(Math.max(0.001, lerp(0.02, 0.85, pk) * (1 - pOut)));

    // photos -> PDF, cleared away before the cursor moves on to "Nilai Saya"
    const ps = markTime(STUD, 'pdf.pop', 10);
    const base = screenPoint(TURN_P, [0.55, 0.5], 7);
    const listP = screenPoint(TURN_P, [0.11, 0.49], 0.4);
    const fly = range(t, ps, ps + 1.6), fold = smooth(range(t, ps + 1.9, ps + 3.0)), docK = backOut(range(t, ps + 2.6, ps + 3.6));
    const nextC = markTime(STUD, 'c:grades');
    const gone = smooth(range(t, nextC - 0.6, nextC + 0.2));
    photos.forEach((m, i) => {
        const k = easeOut(range(fly, i * 0.15, 0.7 + i * 0.15));
        const fan = base.clone().add(V((i - 1) * 4.4, 0.4 + (i === 1 ? 0.5 : 0), (i - 1) * -0.2));
        const stack = base.clone().add(V(0.8 + (i - 1) * 0.25, (i - 1) * -0.18, -1.2 - i * 0.12));
        m.position.copy(listP).lerp(fan, k).lerp(stack, fold);
        m.rotation.set(0, lerp(-0.6, (i - 1) * -0.18, k) * (1 - fold), lerp(0.5, (i - 1) * 0.07, k) * (1 - fold) + (i - 1) * 0.05 * fold);
        m.scale.setScalar(Math.max(0.001, k * lerp(1, 0.75, fold) * (1 - gone)));
    });
    pdfDoc.position.copy(base).add(V(-0.8, 0, 0.6));
    pdfDoc.rotation.set(0, lerp(0.8, 0.14, clamp(docK)), 0);
    pdfDoc.scale.setScalar(Math.max(0.001, docK * (1 - gone)));

    // unique ring
    const kU = range(t, T.studentEnd - 1.2, T.studentEnd + 0.8);
    const steps = (t - T.studentEnd - 0.4) / 1.8;
    const stepIdx = clamp(Math.floor(steps), 0, 4), frac = steps - Math.floor(steps);
    let step = stepIdx + (steps >= 0 && stepIdx < 4 ? smooth(range(frac, 0.78, 1)) : 0);
    if (t > T.uniqEnd) step = 4 + (t - T.uniqEnd) * 0.18;
    uniqCards.forEach((g, i) => {
        const a = i * (Math.PI * 2 / 5) - step * (Math.PI * 2 / 5);
        const rr = RING_R * lerp(0.2, 1, easeOut(kU));
        g.position.set(UNIQ_C.x + Math.sin(a) * rr, UNIQ_C.y + Math.sin(t * 0.9 + i) * 0.3, UNIQ_C.z + Math.cos(a) * rr);
        g.rotation.set(0, a, 0);
        g.scale.setScalar(Math.max(0.001, easeOut(range(t, T.studentEnd - 1 + i * 0.12, T.studentEnd + 0.6 + i * 0.12))));
    });
    logo2.rotation.set(0, Math.sin(t * 0.5) * 0.35, 0); logo2.position.y = UNIQ_C.y + Math.sin(t * 1.1) * 0.35;
    rings2.forEach((r, i) => r.rotation.set(Math.PI / 2 + Math.sin(t * 0.4 + i) * 0.25, t * 0.25, 0));

    scene.updateMatrixWorld();
    cameraAt(t);

    // DOM
    showLayer($('L-intro'), t, 2.2, T.introEnd - 0.2);
    showLayer($('L-roles'), t, T.introEnd + 0.5, T.introEnd + 3.0);
    document.querySelectorAll('#L-roles .role').forEach((r, i) => { r.style.transform = `scale(${lerp(0.7, 1, easeOut(range(t, T.introEnd + 0.8 + i * 0.25, T.introEnd + 1.5 + i * 0.25))).toFixed(3)})`; });
    showLayer($('L-teacher'), t, T.introEnd + 3.0, T.rolesEnd + 0.15);
    showLayer($('L-swap'), t, T.teacherEnd + 0.4, T.swapEnd - 0.3, { dy: 24 });
    $('swap-word').textContent = t < T.teacherEnd + 1.88 ? 'Guru' : 'Siswa'; // the card is edge-on here
    for (const R of ROLES) {
        showLayer(R.menuEl, t, R.play - 0.2, R.t1 - 0.3, { dy: -20 });
        R.chapters.forEach((c, i) => R.menuItems[i].classList.toggle('on', t >= c.start - 0.05 && t < c.end - 0.05));
    }
    for (const c of CHAPTERS) {
        showLayer(c.el, t, c.start + 0.1, c.end + 0.05, { dy: 36, fin: 0.45, fout: 0.3 });
        if (c.steps) {
            const i = stepOf(c, t);
            c.chipEls.forEach((li, j) => { li.classList.toggle('on', j === i); li.classList.toggle('done', j < i); });
            c.descEl.textContent = c.steps[Math.max(0, i)][1];
            c.descEl.style.opacity = i < 0 ? '0.55' : '1';
        }
    }
    showLayer($('L-uniq'), t, T.studentEnd + 0.1, T.uniqEnd - 0.1);
    const act = clamp(Math.floor((t - T.studentEnd - 0.4) / 1.8), 0, 4);
    uniqItems.forEach((li, i) => li.classList.toggle('on', i === act));
    showLayer($('L-outro'), t, T.uniqEnd + 0.6, T.end + 5);
    $('flash').style.opacity = Math.max(1 - range(t, 0, 0.6), smooth(range(t, T.end - 0.5, T.end))).toFixed(3);
}

window.renderAt = async (t) => { await update(t); renderer.render(scene, camera); };
window.DURATION = DURATION;
window.TIMELINE = { T, chapters: CHAPTERS.map((c) => `${c.role}:${c.key} ${c.start.toFixed(1)}-${c.end.toFixed(1)}`) };

renderer.compile(scene, camera);
await window.renderAt(Number(new URLSearchParams(location.search).get('t') ?? 3));
window.__ready = true;
