// Soft, clean UI sound effects for the 2D promo, synthesized in code (no sample packs) and placed on the
// video's own timeline: cursor clicks + keystrokes (from out/rec/events.json), wipes, laptop slide-in/close,
// card flip, pops/bursts, pop-out "dings", caption chimes and the outro chord.
//   node sfx/make-sfx.mjs
// Output: out/sfx-track.wav (48 kHz stereo) and out/promo-elearning-2d-sfx.mp4 (copy of the 2D video + SFX).
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import ffmpegPath from 'ffmpeg-static';

const ROOT = path.resolve(import.meta.dirname, '..');
const SR = 48000;
const manifest = JSON.parse(fs.readFileSync(path.join(ROOT, 'out/rec/manifest.json'), 'utf8'));
const logged = JSON.parse(fs.readFileSync(path.join(ROOT, 'out/rec/events.json'), 'utf8'));

// ---------- timeline (same formulas as scene2d/scene2d.js) ----------
const REC_FPS = manifest.teacher.fps ?? 30, LEAD = 0.8, TAIL = 0.6;
const T = { introEnd: 7, rolesEnd: 12 };
const R = {};
let clock = T.rolesEnd;
for (const name of ['teacher', 'student']) {
    if (name === 'student') { T.teacherEnd = clock; T.swapEnd = clock + 5; clock = T.swapEnd; }
    const r = manifest[name];
    R[name] = { r, t0: clock, play: clock + LEAD, t1: clock + LEAD + r.frames / REC_FPS + TAIL };
    clock = R[name].t1;
}
T.studentEnd = clock; T.uniqEnd = clock + 9.5; T.end = T.uniqEnd + 6;
const markTime = (name, m, extra30 = 0) => R[name].play + ((R[name].r.marks[m] ?? 0) + extra30 * REC_FPS / 30) / REC_FPS;

// Logged (replayed) frames -> recorded frames: piecewise-linear through matching marks/page loads.
function frameMap(name) {
    const L = logged[name], rec = manifest[name];
    const pairs = [[0, 0], [L.frames, rec.frames]];
    for (const k of Object.keys(rec.marks)) if (L.marks[k] !== undefined) pairs.push([L.marks[k], rec.marks[k]]);
    rec.navs.forEach((n, i) => { if (L.navs[i] !== undefined) pairs.push([L.navs[i], n]); });
    pairs.sort((a, b) => a[0] - b[0]);
    return (f) => {
        let i = pairs.findIndex((p) => p[0] > f); if (i <= 0) i = pairs.length - 1;
        const [a0, b0] = pairs[i - 1], [a1, b1] = pairs[i];
        return a1 === a0 ? b0 : b0 + ((f - a0) / (a1 - a0)) * (b1 - b0);
    };
}

// ---------- tiny DSP ----------
let seed = 12345;
const rand = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296; };
const total = Math.ceil((T.end + 1) * SR);
const L = new Float32Array(total), Rt = new Float32Array(total);
function add(buf, at, pan = 0, gain = 1) {
    const s0 = Math.round(at * SR), gl = Math.cos((pan + 1) * Math.PI / 4) * gain, gr = Math.sin((pan + 1) * Math.PI / 4) * gain;
    for (let i = 0; i < buf.length; i++) { const j = s0 + i; if (j < 0 || j >= total) continue; L[j] += buf[i] * gl; Rt[j] += buf[i] * gr; }
}
const env = (t, a, d) => (t < a ? t / a : Math.exp(-(t - a) / d));
function synth(dur, fn) { const n = Math.round(dur * SR), b = new Float32Array(n); for (let i = 0; i < n; i++) b[i] = fn(i / SR, i); return b; }
function lowpassNoise(dur, cutoffAt, ampAt) {
    let y = 0;
    return synth(dur, (t) => { const fc = cutoffAt(t / dur), a = 1 - Math.exp(-2 * Math.PI * fc / SR); y += a * ((rand() * 2 - 1) - y); return y * ampAt(t / dur); });
}

const S = {
    click: (pitch = 1) => synth(0.06, (t) => 0.55 * Math.sin(2 * Math.PI * 1150 * pitch * t) * env(t, 0.0008, 0.012) + 0.35 * Math.sin(2 * Math.PI * 3100 * pitch * t) * env(t, 0.0004, 0.004) + 0.12 * (rand() * 2 - 1) * env(t, 0.0002, 0.0025)),
    key: (pitch = 1) => synth(0.035, (t) => 0.45 * Math.sin(2 * Math.PI * 2600 * pitch * t) * env(t, 0.0004, 0.0035) + 0.25 * (rand() * 2 - 1) * env(t, 0.0002, 0.002)),
    pop: (f0 = 900) => { let ph = 0; return synth(0.14, (t) => { const f = 340 + (f0 - 340) * Math.exp(-t / 0.025); ph += 2 * Math.PI * f / SR; return Math.sin(ph) * env(t, 0.0015, 0.045); }); },
    bell: (f, dur = 1.0) => synth(dur, (t) => (Math.sin(2 * Math.PI * f * t) * env(t, 0.002, dur * 0.35) + 0.4 * Math.sin(2 * Math.PI * f * 2.01 * t) * env(t, 0.002, dur * 0.16) + 0.15 * Math.sin(2 * Math.PI * f * 3.0 * t) * env(t, 0.002, dur * 0.08))),
    chime: () => synth(0.6, (t) => 0.7 * Math.sin(2 * Math.PI * 1760 * t) * env(t, 0.003, 0.14) + 0.3 * Math.sin(2 * Math.PI * 2640 * t) * env(t, 0.003, 0.08)),
    whoosh: (dur = 0.75) => lowpassNoise(dur, (x) => 250 + 5200 * Math.sin(Math.PI * Math.min(1, x * 1.1)) ** 2, (x) => Math.sin(Math.PI * x) ** 2),
    swish: (dur = 0.38) => lowpassNoise(dur, (x) => 1400 + 6500 * Math.sin(Math.PI * x), (x) => Math.sin(Math.PI * x) ** 3),
    thud: () => { let ph = 0; return synth(0.32, (t) => { const f = 70 + 60 * Math.exp(-t / 0.04); ph += 2 * Math.PI * f / SR; return Math.sin(ph) * env(t, 0.003, 0.09) + 0.08 * (rand() * 2 - 1) * env(t, 0.001, 0.01); }); },
};
const ding = (at, gain = 0.32) => { add(S.bell(1318.5, 0.9), at, 0.05, gain); add(S.bell(1975.5, 0.9), at + 0.09, -0.05, gain * 0.8); };
const poppy = (at, n, gain, spread = 0.35) => { for (let i = 0; i < n; i++) add(S.pop(700 + rand() * 600), at + rand() * spread, rand() * 1.2 - 0.6, gain * (0.6 + rand() * 0.4)); };
const whoosh = (center, gain = 0.5, dur = 0.75) => { const b = S.whoosh(dur); add(b, center - dur * 0.45, -0.4, gain * 0.75); add(S.whoosh(dur), center - dur * 0.4, 0.4, gain * 0.75); };

// ---------- events ----------
const counts = {};
const count = (k, n = 1) => { counts[k] = (counts[k] ?? 0) + n; };

// intro: shapes sprinkle in, rings draw on, emblem pops
for (let i = 0; i < 7; i++) add(S.pop(800 + rand() * 500), 0.25 + rand() * 1.4, rand() * 1.6 - 0.8, 0.07);
add(S.swish(0.9), 0.35, 0, 0.18);
add(S.pop(620), 0.36, 0, 0.32); add(S.thud(), 0.38, 0, 0.25);
poppy(0.55, 6, 0.12); count('intro', 1);
// roles
whoosh(T.introEnd - 0.05, 0.5); count('wipe');
add(S.pop(900), T.introEnd + 0.75, -0.3, 0.22); add(S.pop(1050), T.introEnd + 0.95, 0.3, 0.22);
add(S.chime(), T.introEnd + 3.05, 0, 0.14); count('chime');

// a role's laptop session
function session(name, pan) {
    const s = R[name], map = frameMap(name);
    add(S.swish(0.5), s.t0 - 0.4, 0, 0.25); add(S.thud(), s.t0 + 0.25, 0, 0.32); count('laptop');
    poppy(s.t0 - 0.05, 5, 0.1, 0.5);
    for (const [type, f, x] of logged[name].events) {
        const t = s.play + map(f) / REC_FPS, p = ((x ?? 0.5) - 0.5) * 0.6;
        if (type === 'click') { add(S.click(0.95 + rand() * 0.1), t, p, 0.42); count('click'); }
        else { add(S.key(x ? 0.75 : 0.9 + rand() * 0.25), t, rand() * 0.2 - 0.1, 0.16); count('key'); }
    }
    // caption change chime at each chapter
    for (const k of Object.keys(s.r.marks).filter((m) => m.startsWith('c:')).sort((a, b) => s.r.marks[a] - s.r.marks[b])) {
        add(S.chime(), s.play + s.r.marks[k] / REC_FPS + 0.12, pan, 0.1); count('chime');
    }
}
session('teacher', -0.1);
// Excel pops out of "Unduh Excel"
{ const sx = markTime('teacher', 'rapor.pop', 3); add(S.pop(700), sx + 0.05, 0.2, 0.28); ding(sx + 0.18); poppy(sx + 0.3, 5, 0.1, 0.5); count('ding'); }
// teacher laptop folds shut, then wipe
add(S.swish(0.35), R.teacher.t1 - 0.75, 0, 0.18); add(S.thud(), R.teacher.t1 - 0.12, 0, 0.3);
whoosh(T.teacherEnd, 0.55); count('wipe');
// role swap card
{ const ts = T.teacherEnd; add(S.pop(560), ts + 0.3, 0, 0.3); add(S.swish(0.6), ts + 1.55, -0.2, 0.42); add(S.swish(0.5), ts + 1.75, 0.2, 0.3); poppy(ts + 1.95, 8, 0.13, 0.6); add(S.chime(), ts + 2.0, 0, 0.12); count('flip'); }
whoosh(T.swapEnd, 0.55); count('wipe');
session('student', 0.1);
// photos fly out and fold into a PDF
{ const ps = markTime('student', 'pdf.pop', 10); for (let i = 0; i < 3; i++) add(S.pop(800 + i * 120), ps + i * 0.15 + 0.05, (i - 1) * 0.5, 0.22);
  add(S.swish(0.45), ps + 1.85, 0, 0.25); add(S.pop(560), ps + 2.62, 0, 0.3); ding(ps + 2.72); poppy(ps + 2.75, 6, 0.1, 0.5); count('ding'); }
whoosh(T.studentEnd, 0.55); count('wipe');
// unique cards pop in and slide
for (let i = 0; i < 5; i++) add(S.pop(750 + i * 60), T.studentEnd + 0.15 + i * 0.08, 0.2 + i * 0.1, 0.16);
for (let k = 0; k < 4; k++) { add(S.swish(0.4), T.studentEnd + 0.4 + 1.8 * k + 1.4, 0.3, 0.22); add(S.chime(), T.studentEnd + 0.4 + 1.8 * (k + 1), -0.3, 0.07); }
whoosh(T.uniqEnd, 0.5); count('wipe');
// outro: emblem pops, chord
add(S.pop(600), T.uniqEnd + 0.3, 0, 0.3); poppy(T.uniqEnd + 0.4, 7, 0.11, 0.5);
[659.25, 987.77, 1318.5].forEach((f, i) => add(S.bell(f, 2.2), T.uniqEnd + 0.85 + i * 0.07, (i - 1) * 0.3, 0.16));

// ---------- write ----------
let peak = 0; for (let i = 0; i < total; i++) peak = Math.max(peak, Math.abs(L[i]), Math.abs(Rt[i]));
const g = peak > 0.95 ? 0.95 / peak : 1;
const n = Math.round(T.end * SR), wav = Buffer.alloc(44 + n * 4);
wav.write('RIFF', 0); wav.writeUInt32LE(36 + n * 4, 4); wav.write('WAVEfmt ', 8); wav.writeUInt32LE(16, 16); wav.writeUInt16LE(1, 20); wav.writeUInt16LE(2, 22);
wav.writeUInt32LE(SR, 24); wav.writeUInt32LE(SR * 4, 28); wav.writeUInt16LE(4, 32); wav.writeUInt16LE(16, 34); wav.write('data', 36); wav.writeUInt32LE(n * 4, 40);
for (let i = 0; i < n; i++) { wav.writeInt16LE(Math.round(Math.max(-1, Math.min(1, L[i] * g)) * 32767), 44 + i * 4); wav.writeInt16LE(Math.round(Math.max(-1, Math.min(1, Rt[i] * g)) * 32767), 46 + i * 4); }
const raw = path.join(ROOT, 'out/sfx-raw.wav'), track = path.join(ROOT, 'out/sfx-track.wav');
fs.writeFileSync(raw, wav);
// level the SFX bed to sit under a voiceover (-20 LUFS, peaks below -2 dBTP)
const run = (args) => { const p = spawnSync(ffmpegPath, args, { encoding: 'utf8' }); if (p.status) throw new Error(p.stderr); return p.stderr; };
run(['-y', '-loglevel', 'error', '-i', raw, '-af', 'loudnorm=I=-20:TP=-2:LRA=15', '-ar', String(SR), '-ac', '2', track]);
fs.unlinkSync(raw);
run(['-y', '-loglevel', 'error', '-i', path.join(ROOT, 'out/promo-elearning-2d.mp4'), '-i', track, '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-shortest', '-movflags', '+faststart', path.join(ROOT, 'out/promo-elearning-2d-sfx.mp4')]);
console.log('events', JSON.stringify(counts), '| end', T.end.toFixed(2) + 's');
console.log('wrote out/sfx-track.wav and out/promo-elearning-2d-sfx.mp4');
