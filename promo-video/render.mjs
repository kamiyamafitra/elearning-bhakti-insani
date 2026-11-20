// Renders the promo video.
//   node render.mjs stills 3 15 50        -> PNG stills at those seconds in out/stills/
//   node render.mjs video [fps]           -> out/promo-elearning.mp4 (1920x1080, H.264)
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import puppeteer from 'puppeteer-core';
import ffmpegPath from 'ffmpeg-static';

const ROOT = import.meta.dirname;
const OUT = path.join(ROOT, 'out');
const TYPES = { '.html': 'text/html', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.json': 'application/json' };

const server = http.createServer((req, res) => {
    const url = decodeURIComponent(new URL(req.url, 'http://x').pathname);
    const file = path.join(ROOT, url === '/' ? (process.env.SCENE ?? 'scene2d/index.html') : url); // SCENE=scene/index.html for the 3D version
    if (!file.startsWith(ROOT) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end(); }
    res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] ?? 'application/octet-stream' });
    fs.createReadStream(file).pipe(res);
}).listen(0);
const port = server.address().port;

const [mode = 'stills', ...args] = process.argv.slice(2);
const browser = await puppeteer.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true,
    args: ['--use-angle=d3d11', '--enable-gpu', '--ignore-gpu-blocklist', '--enable-unsafe-swiftshader'],
    defaultViewport: { width: 1920, height: 1080, deviceScaleFactor: 1 },
    protocolTimeout: 600000,
});
const page = await browser.newPage();
page.on('console', (m) => { if (m.type() === 'error' || m.type() === 'warn') console.log('[page]', m.text()); });
page.on('pageerror', (e) => console.log('[pageerror]', e.message));
await page.goto(`http://localhost:${port}/`, { waitUntil: 'networkidle0' });
await page.waitForFunction('window.__ready === true', { timeout: 120000 });
console.log('GPU:', await page.evaluate(() => {
    const gl = document.createElement('canvas').getContext('webgl2'); const ext = gl.getExtension('WEBGL_debug_renderer_info');
    return ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : 'unknown';
}));

fs.mkdirSync(OUT, { recursive: true });

console.log('TIMELINE', JSON.stringify(await page.evaluate(() => window.TIMELINE)));
if (await page.evaluate(() => typeof window.checkCrops === 'function')) {
    console.log('CROPS', JSON.stringify(await page.evaluate(() => window.checkCrops())));
    console.log('SHOTS', JSON.stringify(await page.evaluate(() => window.SHOTS()), null, 1));
}
if (mode === 'stills') {
    fs.mkdirSync(path.join(OUT, 'stills'), { recursive: true });
    for (const s of args) {
        await page.evaluate((t) => window.renderAt(t), Number(s));
        await page.screenshot({ path: path.join(OUT, 'stills', `t${String(s).padStart(5, '0')}.png`) });
        console.log('still', s);
    }
} else {
    const fps = Number(args[0] ?? 30);
    const duration = await page.evaluate(() => window.DURATION);
    const frames = Math.round(duration * fps);
    // optional frame range: node render.mjs video 30 <first> <last> -> out/part.mp4 (for splicing a fix)
    const first = Number(args[1] ?? 0), last = Number(args[2] ?? frames);
    const outFile = path.join(OUT, args[1] ? 'part.mp4' : (process.env.OUTNAME ?? (process.env.SCENE ? 'promo-elearning.mp4' : 'promo-elearning-2d.mp4')));
    const ff = spawn(ffmpegPath, ['-y', '-f', 'image2pipe', '-framerate', String(fps), '-c:v', 'mjpeg', '-i', '-',
        '-c:v', 'libx264', '-preset', 'slow', '-crf', '17', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', outFile], { stdio: ['pipe', 'ignore', 'inherit'] });
    const started = Date.now();
    for (let i = first; i < last; i++) {
        await page.evaluate((t) => window.renderAt(t), i / fps);
        const buf = await page.screenshot({ type: 'jpeg', quality: 95, optimizeForSpeed: true });
        if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r));
        if (i % 150 === 0) console.log(`frame ${i}/${frames}  ${((Date.now() - started) / 1000).toFixed(0)}s`);
    }
    ff.stdin.end();
    await new Promise((r) => ff.on('close', r));
    console.log('wrote', outFile);
}

await browser.close();
server.close();
