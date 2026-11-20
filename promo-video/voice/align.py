# Transcribes the narrator recording locally (faster-whisper) and finds where each of the 21 script lines
# starts/ends in it, by matching Whisper's timed words against the known script text.
#   python voice/align.py [audio]      (default out/voice/source.mp3)
# Output: out/voice/lines.json  [{i, text, start, end, matched}] (+ out/voice/transcript.json)
import ast, difflib, json, re, sys
from pathlib import Path

from faster_whisper import WhisperModel

ROOT = Path(__file__).resolve().parent.parent
AUDIO = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / 'out' / 'voice' / 'source.mp3'

# The script (with its on-screen start times) lives in make-voice.py as SCRIPT = [(start, text), ...].
src = (ROOT / 'voice' / 'make-voice.py').read_text(encoding='utf-8')
SCRIPT = ast.literal_eval(re.search(r'SCRIPT = (\[.*?\n\])', src, re.S).group(1))

norm = lambda w: re.sub(r'[^0-9a-z]', '', w.lower())

model = WhisperModel('base', device='cpu', compute_type='int8')
segments, info = model.transcribe(str(AUDIO), language='id', word_timestamps=True, vad_filter=False, beam_size=5,
                                  initial_prompt=' '.join(t for _, t in SCRIPT))
words = [(norm(w.word), w.start, w.end, w.word.strip()) for s in segments for w in s.words if norm(w.word)]
(ROOT / 'out' / 'voice' / 'transcript.json').write_text(json.dumps([[w[3], round(w[1], 2), round(w[2], 2)] for w in words], ensure_ascii=False))

script_tokens = [(norm(w), li) for li, (_, text) in enumerate(SCRIPT) for w in text.split() if norm(w)]
sm = difflib.SequenceMatcher(None, [t for t, _ in script_tokens], [w[0] for w in words], autojunk=False)
times = {}
for a, b, n in sm.get_matching_blocks():
    for k in range(n):
        times[a + k] = (words[b + k][1], words[b + k][2])

out = []
for li, (_, text) in enumerate(SCRIPT):
    idx = [k for k, (_, l) in enumerate(script_tokens) if l == li]
    hit = [times[k] for k in idx if k in times]
    out.append({'i': li, 'text': text, 'start': round(min(h[0] for h in hit), 3) if hit else None,
                'end': round(max(h[1] for h in hit), 3) if hit else None, 'matched': round(len(hit) / len(idx), 2)})
# Lines with no matched words: place them between their neighbours.
for k, o in enumerate(out):
    if o['start'] is None:
        prev = next((x['end'] for x in reversed(out[:k]) if x['end'] is not None), 0)
        nxt = next((x['start'] for x in out[k + 1:] if x['start'] is not None), prev + 2)
        o['start'], o['end'] = prev + 0.05, nxt - 0.05
(ROOT / 'out' / 'voice' / 'lines.json').write_text(json.dumps(out, ensure_ascii=False, indent=1))
print(f"language={info.language} p={info.language_probability:.2f} words={len(words)}")
for o in out:
    print(f"{o['i']:2d} {o['start']:7.2f}-{o['end']:7.2f}  match {o['matched']:.0%}  {o['text'][:60]}")
