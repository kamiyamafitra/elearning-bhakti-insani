# Generates the Indonesian voiceover (Microsoft neural TTS via edge-tts), fits every line into its scene,
# mixes one voice track and muxes it into a COPY of the 2D video (the original stays silent).
#   python -I voice/make-voice.py [voice]      (default id-ID-GadisNeural)
# Output: voice/lines/*.mp3, out/voiceover-<voice>.wav, out/promo-elearning-2d-dubbed.mp4
import asyncio, json, math, re, subprocess, sys
from pathlib import Path

import edge_tts

ROOT = Path(__file__).resolve().parent.parent
FFMPEG = str(ROOT / 'node_modules' / 'ffmpeg-static' / 'ffmpeg.exe')
VOICE = sys.argv[1] if len(sys.argv) > 1 else 'id-ID-GadisNeural'
VIDEO = ROOT / 'out' / 'promo-elearning-2d.mp4'
OUT_VIDEO = ROOT / 'out' / 'promo-elearning-2d-dubbed.mp4'
LINES = ROOT / 'voice' / 'lines'
VIDEO_END = 133.4

# (start second, text) — starts line up with the on-screen action of the 2D video's timeline.
SCRIPT = [
    (0.3, 'Selamat datang di E-Learning SMK Bhakti Insani. Satu ruang belajar untuk guru dan siswa.'),
    (7.3, 'Dirancang khusus untuk guru dan siswa. Kita mulai dari sisi guru.'),
    (12.9, 'Dashboard guru merangkum semua kelas.'),
    (16.2, 'Buka pengumpulan terbaru, klik Beri Nilai, isi skor dan umpan balik, lalu simpan. Siswa langsung bisa melihatnya.'),
    (26.5, 'Lewat menu Diskusi Kelas, guru bisa menjawab pertanyaan siswa langsung di forum, lengkap dengan fitur balasan pesan.'),
    (35.6, 'Fitur unggulan: Rapor Otomatis.'),
    (39.25, 'Atur bobot tugas, bobot kuis ikut menyesuaikan. Simpan, dan rapor langsung dihitung ulang.'),
    (46.4, 'Hasilnya bisa diunduh ke Excel, tanpa input ulang.'),
    (52.2, 'Sekarang, mari beralih ke sisi siswa.'),
    (56.8, 'Dashboard siswa menampilkan jadwal dan deadline.'),
    (60.6, 'Fitur unggulan berikutnya: Pratinjau Tanpa Unduh.'),
    (64.6, 'Klik Lihat, dan modul PDF langsung tampil di browser.'),
    (72.3, 'Dokumen Word juga bisa dibaca langsung, tanpa perlu mengunduh.'),
    (78.6, 'Begitu pula Excel. Hemat kuota HP.'),
    (82.9, 'Lalu, Kuis dengan Timer.'),
    (85.6, 'Waktu berjalan otomatis, urutan soal diacak, dan jawaban tersimpan otomatis. Soal yang sudah dilewati tidak bisa dibuka lagi.'),
    (99.4, 'Setelah dikumpulkan, nilai langsung keluar.'),
    (104.0, 'Tugas di kertas? Cukup foto, pilih sekaligus, lalu ubah jadi satu file PDF yang rapi.'),
    (112.5, 'Di menu Nilai Saya, rapor dan umpan balik guru selalu transparan.'),
    (118.2, 'Pratinjau file, kuis yang adil, rapor otomatis, konversi PDF, dan diskusi kelas. Semua dalam satu platform.'),
    (127.7, 'Belajar lebih mudah, mengajar lebih ringan.'),
]


def duration(path):
    err = subprocess.run([FFMPEG, '-i', str(path)], capture_output=True, text=True).stderr
    h, m, s = re.search(r'Duration: (\d+):(\d+):([\d.]+)', err).groups()
    return int(h) * 3600 + int(m) * 60 + float(s)


TRIM = 'silenceremove=start_periods=1:start_threshold=-45dB,areverse,silenceremove=start_periods=1:start_threshold=-45dB,areverse,apad=pad_dur=0.08'


async def speak(text, path, rate_pct):
    """Synthesize to mp3, then trim leading/trailing silence into a wav next to it. Returns the wav path."""
    await edge_tts.Communicate(text, VOICE, rate=f'{rate_pct:+d}%').save(str(path))
    wav = path.with_suffix('.wav')
    subprocess.run([FFMPEG, '-y', '-loglevel', 'error', '-i', str(path), '-af', TRIM, '-ar', '48000', str(wav)], check=True)
    return wav


async def main():
    LINES.mkdir(parents=True, exist_ok=True)
    report = []
    for i, (start, text) in enumerate(SCRIPT):
        end = SCRIPT[i + 1][0] if i + 1 < len(SCRIPT) else VIDEO_END
        room = end - start - 0.2
        path = LINES / f'{i:02d}.mp3'
        rate = 0
        wav = await speak(text, path, rate)
        d = duration(wav)
        # Speed a line up a little (never more than +20%) only if it would run into the next scene.
        while d > room and rate < 20:
            rate = min(20, rate + max(3, math.ceil((d / room - 1) * 100) + 2))
            wav = await speak(text, path, rate)
            d = duration(wav)
        report.append({'i': i, 'start': start, 'dur': round(d, 2), 'room': round(room, 2), 'rate': rate, 'fits': d <= room})
    (ROOT / 'voice' / 'lines.json').write_text(json.dumps(report, indent=1))

    # One voice track: each line delayed to its start, mixed, gently levelled.
    inputs, filters = [], []
    for i, (start, _) in enumerate(SCRIPT):
        inputs += ['-i', str(LINES / f'{i:02d}.wav')]
        ms = int(start * 1000)
        filters.append(f'[{i}:a]aresample=48000,adelay={ms}|{ms},apad[a{i}]')
    mix = ''.join(f'[a{i}]' for i in range(len(SCRIPT)))
    graph = ';'.join(filters) + f';{mix}amix=inputs={len(SCRIPT)}:normalize=0:duration=longest,atrim=0:{VIDEO_END},loudnorm=I=-16:TP=-1.5:LRA=11[v]'
    wav = ROOT / 'out' / f'voiceover-{VOICE}.wav'
    subprocess.run([FFMPEG, '-y', '-loglevel', 'error', *inputs, '-filter_complex', graph, '-map', '[v]', '-ac', '2', '-ar', '48000', str(wav)], check=True)

    # Dubbed copy: same video stream (no re-encode) + the voice track.
    subprocess.run([FFMPEG, '-y', '-loglevel', 'error', '-i', str(VIDEO), '-i', str(wav), '-map', '0:v', '-map', '1:a',
                    '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-shortest', '-movflags', '+faststart', str(OUT_VIDEO)], check=True)
    for r in report:
        print(f"{r['start']:>6.1f}s  {r['dur']:>5.2f}s / {r['room']:>5.2f}s  rate {r['rate']:+d}%  {'ok' if r['fits'] else 'TOO LONG'}")
    print('wrote', wav.name, 'and', OUT_VIDEO.name)


asyncio.run(main())
