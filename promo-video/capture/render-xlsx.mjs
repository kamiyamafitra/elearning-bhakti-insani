// Draws the real rapor export (capture/export-rapor.xlsx) as a spreadsheet window: assets/shots/rp-4.png
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import puppeteer from 'puppeteer-core';

const require = createRequire(path.resolve(import.meta.dirname, '../../package.json'));
const XLSX = require('xlsx');
const wb = XLSX.read(fs.readFileSync(path.join(import.meta.dirname, 'export-rapor.xlsx')));
const ws = wb.Sheets[wb.SheetNames[0]];
const rows = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false, defval: '' }).slice(0, 22);
const cols = Math.max(...rows.map((r) => r.length));
const letters = [...Array(cols)].map((_, i) => String.fromCharCode(65 + i));
const esc = (v) => String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;');

const html = `<!doctype html><html><head><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=block" rel="stylesheet">
<style>
body{margin:0;background:#EEF0FA;font-family:'Plus Jakarta Sans',sans-serif;display:grid;place-items:center;height:900px}
.win{width:1360px;height:820px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 30px 80px -30px rgba(30,27,75,.45);display:flex;flex-direction:column}
.top{height:52px;background:#1F7A4D;color:#fff;display:flex;align-items:center;gap:14px;padding:0 20px;font-weight:700;font-size:16px}
.top .ic{width:28px;height:28px;border-radius:6px;background:#fff;color:#1F7A4D;display:grid;place-items:center;font-weight:800}
.top span{opacity:.8;font-weight:400}
.fx{height:36px;border-bottom:1px solid #E3E5EE;display:flex;align-items:center;padding:0 16px;font-size:13px;color:#555;gap:12px}
.fx b{border:1px solid #ddd;padding:2px 10px;border-radius:4px;font-weight:600}
.grid{flex:1;overflow:hidden}
table{border-collapse:collapse;font-size:13px;width:100%}
td,th{border:1px solid #E3E5EE;padding:7px 10px;white-space:nowrap;max-width:260px;overflow:hidden;text-overflow:ellipsis}
th{background:#F5F6FA;color:#777;font-weight:600;font-size:12px}
th.r{width:36px}
tr.h td{background:#E8F3EC;font-weight:700;color:#14532D}
.tabs{height:34px;border-top:1px solid #E3E5EE;display:flex;align-items:center;padding-left:46px;font-size:13px}
.tabs div{padding:6px 18px;border-bottom:3px solid #1F7A4D;color:#1F7A4D;font-weight:700;background:#fff}
</style></head><body><div class="win">
<div class="top"><div class="ic">X</div>Rapor-ASJ-X-TKJ-1.xlsx <span>— diunduh dari E-Learning</span></div>
<div class="fx"><b>A1</b> ${esc(rows[0]?.[0] ?? '')}</div>
<div class="grid"><table><tr><th class="r"></th>${letters.map((l) => `<th>${l}</th>`).join('')}</tr>
${rows.map((r, i) => r.slice(1).every((c) => c === '') ? `<tr><th class="r">${i + 1}</th><td colspan="${cols}" style="max-width:none;font-weight:${i ? 400 : 700};font-size:${i ? 13 : 16}px">${esc(r[0])}</td></tr>` : `<tr class="${r.length > 3 && r.some((c) => /nama/i.test(String(c))) ? 'h' : ''}"><th class="r">${i + 1}</th>${letters.map((_, j) => `<td>${esc(r[j] ?? '')}</td>`).join('')}</tr>`).join('')}
</table></div><div class="tabs"><div>${esc(wb.SheetNames[0])}</div></div></div></body></html>`;

const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', defaultViewport: { width: 1440, height: 900, deviceScaleFactor: 1.5 } });
const page = await browser.newPage();
await page.setContent(html, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
await page.screenshot({ path: path.resolve(import.meta.dirname, '../assets/shots/rp-4.png') });
await browser.close();
console.log('rows', rows.length, 'cols', cols, rows.slice(0, 6));
