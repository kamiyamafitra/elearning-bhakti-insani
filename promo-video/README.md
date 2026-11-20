# Promo video (Guru & Siswa)

A ~2:13, 1920×1080, 30 fps 3D motion-graphics promo of the e-learning site for the teacher and student roles. The site plays as live, cursor-guided recordings on floating 3D laptops. It is silent so music can be added afterwards.

Two versions share the same recordings and timeline:

- **2D mograph** (default): `out/promo-elearning-2d.mp4` — flat vector laptop, bouncy flat shapes, circle wipes, flat card flip. Scene: `scene2d/`.
- **3D**: `out/promo-elearning.mp4` — floating 3D laptops and turntable. Scene: `scene/`. Render with `SCENE=scene/index.html`.

## Storyboard

Each role is one continuous session on one laptop; the cursor moves between features by clicking the sidebar menu.

| Time | Scene |
|---|---|
| 0–7s | School emblem as a 3D badge: "SMK Bhakti Insani · Satu ruang belajar untuk Guru & Siswa" |
| 7–12s | Roles (Guru / Siswa), then "Untuk Guru" |
| 12–53s | **Guru laptop**: Dashboard → open a submission from "Pengumpulan Terbaru", type score + feedback, save → sidebar *Diskusi Kelas*, type a reply → sidebar *Penilaian* → ★ Rapor Otomatis (70% → Simpan → recalculated → Unduh Excel, the real export pops out) |
| 53–58s | Role swap: "Guru" card flips to "Siswa"; the world turns purple; the student turntable rises |
| 58–118s | **Siswa laptop**: Dashboard → sidebar *Mata Pelajaran Saya* → ★ Pratinjau (PDF, Word, Excel) → sidebar *Kuis* → ★ Kuis dengan Timer (score 100) → sidebar *Konversi PDF* → ★ photos fold into one PDF → sidebar *Nilai Saya* |
| 118–127s | "Yang membuatnya berbeda": 5 unique features on a rotating 3D ring |
| 127–133s | Outro: "Belajar lebih mudah. Mengajar lebih ringan." |

## How it's made

1. **Demo data** — `capture/demo-data.php` fills a throwaway SQLite DB (never the real MySQL). `capture/make-files.mjs` makes the demo Word/Excel files and homework photos.
2. **Recording** — start the `promo-demo` server from `.claude/launch.json`, then `node capture/record.mjs teacher student`. It drives the real site frame by frame (mouse moves with hover states, typing, smooth scrolling, a guide cursor and click ripple drawn into the page) and writes `out/rec/<teacher|student>/*.jpg` plus `out/rec/manifest.json` (cursor path, zoom cues, chapter marks `c:<key>` and step marks `<key>.sN`). Delete `storage/app/private/demo-promo` afterwards.
3. **Scene** — `scene/scene.js` (Three.js) plays each recording on a 3D laptop screen; the camera follows the smoothed cursor. Chapter captions and step chips live in `ROLES` there.
4. **Render** — `node render.mjs stills 20 70` for preview frames, `node render.mjs video` for the 2D MP4; prefix with `SCENE=scene/index.html` for the 3D one (~25 min each).

`capture/capture.mjs` and `capture/render-xlsx.mjs` produce the still screenshots in `assets/shots/` (the Excel export image `rp-4.png` is still used for the pop-out).

## Sound effects

`node sfx/make-sfx.mjs` builds a soft UI sound-effect bed (clicks, keystrokes, wipes, flip, pops, dings, chimes), all synthesized in code, and writes:

- `out/sfx-track.wav` — the SFX alone (48 kHz stereo, -20 LUFS) to mix under your own voiceover
- `out/promo-elearning-2d-sfx.mp4` — copy of the 2D video with the SFX (the original stays silent)

Click/keystroke times come from `out/rec/events.json`, made by replaying the sessions without screenshots:
`LOG_ONLY=1 node capture/record.mjs teacher student` (needs the demo server, like recording).
