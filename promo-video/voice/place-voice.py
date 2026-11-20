# Cuts the narrator recording into the 21 script lines and places each line at its scene time in the video.
# Line boundaries are the measured pauses between lines (silencedetect), checked against a local Whisper
# transcript (voice/align.py). Also writes a per-frame loudness envelope that drives the avatar's talking bounce.
#   python voice/place-voice.py
# Output: out/voice/voice-track.wav (48 kHz stereo, -16 LUFS), out/voice/timing.json (bubble text + times, envelope)
import ast, json, re, subprocess
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parent.parent
FFMPEG = str(ROOT / 'node_modules' / 'ffmpeg-static' / 'ffmpeg.exe')
SR, FPS, VIDEO_END = 48000, 60, 133.4
src = (ROOT / 'voice' / 'make-voice.py').read_text(encoding='utf-8')
SCRIPT = ast.literal_eval(re.search(r'SCRIPT = (\[.*?\n\])', src, re.S).group(1))

# Where each line sits in out/voice/source.mp3 (seconds), cut in the middle of the pause before/after it.
CUTS = [(0.00, 6.44), (6.81, 11.21), (11.63, 14.16), (14.56, 22.33), (22.75, 30.59), (30.70, 33.32),
        (33.32, 39.68), (40.09, 43.52), (43.89, 46.18), (46.59, 49.97), (50.39, 53.92), (53.92, 57.76),
        (57.76, 62.25), (62.25, 64.82), (65.23, 66.99), (66.99, 76.12), (76.53, 79.49), (79.90, 85.52),
        (85.88, 90.18), (90.55, 98.16), (98.51, 101.42)]
# Avatar expressions, anchored to phrases in the recording: (line, phrase start in source, phrase end or None = line end, face).
# Times come from the Whisper transcript (out/voice/transcript.json).
EXPR_ANCHORS = [
    (6, 37.10, None, 'loading'),      # "Simpan, dan rapor langsung dihitung ulang"
    (13, 63.76, None, 'lowbat'),      # "hemat kuota HP"
    (15, 73.24, None, 'speechless'),  # "Soal yang sudah dilewati tidak bisa dibuka lagi"
    (17, 79.88, 80.64, 'speechless'), # "Tugas di kertas?"
    (17, 83.32, None, 'loading'),     # "lalu ubah jadi satu file PDF"
]
SLEEP_AFTER = 1.2   # avatar dozes off when the voice has been silent this long, if the gap is long enough
SLEEP_MIN_GAP = 3.5
assert len(CUTS) == len(SCRIPT)

pcm = subprocess.run([FFMPEG, '-loglevel', 'error', '-i', str(ROOT / 'out' / 'voice' / 'source.mp3'), '-f', 'f32le', '-ac', '1', '-ar', str(SR), '-'],
                     capture_output=True, check=True).stdout
x = np.frombuffer(pcm, dtype=np.float32)

def trim(seg, thr_db=-42):
    """Drop leading/trailing near-silence, keeping a short natural margin."""
    win = int(0.01 * SR)
    rms = np.sqrt(np.convolve(seg ** 2, np.ones(win) / win, mode='same'))
    loud = np.where(20 * np.log10(rms + 1e-9) > thr_db)[0]
    if not len(loud): return seg, 0.0
    a, b = max(0, loud[0] - int(0.03 * SR)), min(len(seg), loud[-1] + int(0.08 * SR))
    return seg[a:b], a / SR

total = int(VIDEO_END * SR)
track = np.zeros(total, dtype=np.float32)
lines = []
for i, ((s, e), (start, text)) in enumerate(zip(CUTS, SCRIPT)):
    seg, lead = trim(x[int(s * SR):int(e * SR)])
    nxt = SCRIPT[i + 1][0] if i + 1 < len(SCRIPT) else VIDEO_END
    dur = len(seg) / SR
    # fade the edges a touch so cuts never click
    f = int(0.008 * SR); seg = seg.copy(); seg[:f] *= np.linspace(0, 1, f); seg[-f:] *= np.linspace(1, 0, f)
    a = int(start * SR); track[a:a + len(seg)] += seg[:max(0, total - a)]
    lines.append({'i': i, 'text': text, 'start': round(start, 3), 'end': round(start + dur, 3), 'fits': start + dur <= nxt - 0.1, 'src0': s + lead})

# expression schedule in video time
expr = []
for li, a, b, face in EXPR_ANCHORS:
    L = lines[li]
    t0 = L['start'] + (a - L['src0'])
    t1 = L['end'] + 0.25 if b is None else L['start'] + (b - L['src0']) + 0.15
    expr.append([round(t0 - 0.05, 2), round(t1, 2), face])
for A, B in zip(lines, lines[1:]):
    if B['start'] - A['end'] >= SLEEP_MIN_GAP:
        expr.append([round(A['end'] + SLEEP_AFTER, 2), round(B['start'] - 0.15, 2), 'sleepy'])
expr.sort()

# per-video-frame loudness (0..1) for the talking bounce
hop = SR // FPS
env = np.sqrt(np.add.reduceat(track ** 2, np.arange(0, total, hop)) / hop)
env = np.clip(env / (np.percentile(env[env > 1e-4], 95) + 1e-9), 0, 1)

raw = ROOT / 'out' / 'voice' / 'voice-raw.f32'
raw.write_bytes(track.tobytes())
subprocess.run([FFMPEG, '-y', '-loglevel', 'error', '-f', 'f32le', '-ar', str(SR), '-ac', '1', '-i', str(raw),
                '-af', 'highpass=f=70,loudnorm=I=-16:TP=-1.5:LRA=11', '-ar', str(SR), '-ac', '2', str(ROOT / 'out' / 'voice' / 'voice-track.wav')], check=True)
raw.unlink()
(ROOT / 'out' / 'voice' / 'timing.json').write_text(json.dumps({'fps': FPS, 'lines': lines, 'expr': expr, 'env': [round(float(v), 3) for v in env]}, ensure_ascii=False))
print('expressions:', expr)
for l in lines:
    print(f"{l['i']:2d} {l['start']:7.2f}-{l['end']:7.2f} {'ok ' if l['fits'] else 'LONG'} {l['text'][:55]}")
