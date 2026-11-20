// Creates the demo Word/Excel materials and three "homework photo" images for the promo capture.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import puppeteer from 'puppeteer-core';

const require = createRequire(path.resolve(import.meta.dirname, '../../package.json'));
const JSZip = require('jszip');
const XLSX = require('xlsx');
const OUT = import.meta.dirname;

// ---- Word document ----
const p = (text, opts = '') => `<w:p><w:pPr>${opts}</w:pPr><w:r>${opts.includes('Heading') ? '<w:rPr><w:b/><w:sz w:val="36"/><w:color w:val="4F46E5"/></w:rPr>' : '<w:rPr><w:sz w:val="24"/></w:rPr>'}<w:t xml:space="preserve">${text}</w:t></w:r></w:p>`;
const body = [
    p('Lembar Kerja Praktikum: Instalasi DHCP Server', '<w:pStyle w:val="Heading1"/>'),
    p('Mata Pelajaran: Teknologi Layanan Jaringan — Kelas X TKJ 1'),
    p(''),
    p('Tujuan', '<w:pStyle w:val="Heading1"/>'),
    p('1. Siswa dapat memasang paket isc-dhcp-server pada Debian.'),
    p('2. Siswa dapat mengatur rentang IP yang dibagikan ke klien.'),
    p(''),
    p('Langkah Kerja', '<w:pStyle w:val="Heading1"/>'),
    p('1. Jalankan perintah: apt install isc-dhcp-server'),
    p('2. Buka file /etc/dhcp/dhcpd.conf dan tambahkan subnet 192.168.10.0/24.'),
    p('3. Atur range 192.168.10.100 sampai 192.168.10.200.'),
    p('4. Restart layanan lalu uji dari komputer klien.'),
    p(''),
    p('Pertanyaan', '<w:pStyle w:val="Heading1"/>'),
    p('Jelaskan fungsi option routers dan option domain-name-servers!'),
].join('');
const zip = new JSZip();
zip.file('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
zip.file('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
zip.file('word/document.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>${body}<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1200" w:right="1200" w:bottom="1200" w:left="1200"/></w:sectPr></w:body></w:document>`);
fs.writeFileSync(path.join(OUT, 'lks-dhcp.docx'), await zip.generateAsync({ type: 'nodebuffer' }));

// ---- Excel workbook ----
const rows = [['Prefix', 'Subnet Mask', 'Jumlah Host', 'Blok Subnet', 'Contoh Network', 'Contoh Broadcast']];
for (let pr = 24; pr <= 30; pr++) {
    const block = 2 ** (32 - pr);
    rows.push([`/${pr}`, `255.255.255.${256 - block}`, block - 2, block, '192.168.10.0', `192.168.10.${block - 1}`]);
}
const ws = XLSX.utils.aoa_to_sheet(rows);
ws['!cols'] = [{ wch: 8 }, { wch: 18 }, { wch: 12 }, { wch: 12 }, { wch: 16 }, { wch: 18 }];
const wb = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(wb, ws, 'Tabel Subnet');
XLSX.writeFile(wb, path.join(OUT, 'tabel-subnet.xlsx'));

// ---- Homework "photos" ----
const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', defaultViewport: { width: 900, height: 1200 } });
const page = await browser.newPage();
const notes = [
    ['Tugas Subnetting', ['192.168.10.0 /26', 'mask = 255.255.255.192', 'host = 2^6 - 2 = 62', 'broadcast = .63']],
    ['Lanjutan', ['/27 -> 32 blok', 'host = 30', 'network: .0 .32 .64 .96', 'broadcast: .31 .63 .95']],
    ['Kesimpulan', ['makin besar prefix,', 'makin sedikit host', 'Raka Pratama - X TKJ 1']],
];
for (const [i, [title, lines]] of notes.entries()) {
    await page.setContent(`<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&display=block" rel="stylesheet">
    <body style="margin:0;background:#d9cbb3"><div style="margin:40px;height:1120px;background:#fffdf6 repeating-linear-gradient(#fffdf6 0 58px,#b9d3f0 58px 60px);transform:rotate(${[-1.2, 0.8, -0.5][i]}deg);box-shadow:0 10px 30px rgba(0,0,0,.25);padding:70px 60px;box-sizing:border-box;font:600 52px/60px Caveat;color:#1e3a8a;border-left:4px solid #f87171">
    <div style="font-size:66px;margin-bottom:20px;text-decoration:underline">${title}</div>${lines.map((l) => `<div>${l}</div>`).join('')}</div></body>`, { waitUntil: 'load' }); await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(OUT, `foto-tugas-${i + 1}.jpg`), type: 'jpeg', quality: 88 });
}
await browser.close();
console.log('files ready');
