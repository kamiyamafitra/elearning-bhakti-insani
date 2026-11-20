// Renders a contact sheet of images (args: output, ...inputs) with headless Chrome.
import puppeteer from 'puppeteer-core'; import fs from 'node:fs'; import path from 'node:path';
const [out, ...files] = process.argv.slice(2);
const mime = (f) => f.endsWith('.png') ? 'image/png' : 'image/jpeg';
const html = `<body style="margin:0;display:grid;grid-template-columns:repeat(4,540px);gap:6px;font:14px sans-serif;background:#ccc">` +
  files.map(f => `<div><div>${path.basename(path.dirname(f))}/${path.basename(f)}</div><img width=540 src="data:${mime(f)};base64,${fs.readFileSync(f).toString('base64')}"></div>`).join('') + '</body>';
const b = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', defaultViewport: { width: 2190, height: 400 } });
const p = await b.newPage(); await p.setContent(html); await p.screenshot({ path: out, fullPage: true }); await b.close();
