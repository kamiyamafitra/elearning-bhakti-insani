// Shared pop-up that previews PDFs, Word files, spreadsheets, images and text files without downloading them.
// The viewer libraries are bundled with the app (no outside CDN) and each is loaded only when its file type is opened.

const MAX_SHEET_ROWS = 500;
const MAX_SHEET_COLUMNS = 50;

export function initFilePreview() {
    const modal = document.getElementById('filePreviewModal');

    if (! modal) {
        return;
    }

    const body = modal.querySelector('[data-preview-body]');
    let pending = null;
    let renderId = 0;

    function showMessage(text) {
        const message = document.createElement('p');
        message.className = 'file-preview__message';
        message.textContent = text;
        body.replaceChildren(message);
    }

    async function fetchFile(url) {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (! response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        return response.arrayBuffer();
    }

    // Mozilla's PDF.js. Phones (Android Chrome) can't show PDFs on their own.
    async function renderPdf(url, id) {
        const [pdfjs, worker] = await Promise.all([
            import('pdfjs-dist'),
            import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
        ]);
        pdfjs.GlobalWorkerOptions.workerSrc = worker.default;

        const pdf = await pdfjs.getDocument({ url: url, withCredentials: true }).promise;
        const ratio = window.devicePixelRatio || 1;
        const available = body.clientWidth - 32;

        body.replaceChildren();

        for (let number = 1; number <= pdf.numPages; number++) {
            const page = await pdf.getPage(number);

            if (id !== renderId) {
                return;
            }

            const scale = Math.min(available / page.getViewport({ scale: 1 }).width, 2);
            const viewport = page.getViewport({ scale: scale * ratio });
            const canvas = document.createElement('canvas');

            canvas.className = 'file-preview__page';
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.width = (viewport.width / ratio) + 'px';
            canvas.setAttribute('aria-label', 'Halaman ' + number + ' dari ' + pdf.numPages);
            body.append(canvas);

            await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
        }
    }

    async function renderText(url, id) {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (! response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const text = await response.text();

        if (id === renderId) {
            const block = document.createElement('pre');
            block.className = 'file-preview__text';
            block.textContent = text;
            body.replaceChildren(block);
        }
    }

    async function renderWord(url, id) {
        const [{ renderAsync }, data] = await Promise.all([import('docx-preview'), fetchFile(url)]);

        if (id !== renderId) {
            return;
        }

        const container = document.createElement('div');
        container.className = 'file-preview__word';
        body.replaceChildren(container);
        await renderAsync(data, container, container, {
            ignoreWidth: true,
            ignoreLastRenderedPageBreak: true,
        });

        // Links inside a document only open real web pages, in a new tab; anything else (such as javascript:) is disabled.
        container.querySelectorAll('a[href]').forEach(function (link) {
            if (/^(https?:|mailto:)/i.test(link.getAttribute('href'))) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            } else if (! link.getAttribute('href').startsWith('#')) {
                link.removeAttribute('href');
            }
        });
    }

    async function renderSheet(url, id) {
        const [XLSX, data] = await Promise.all([import('xlsx'), fetchFile(url)]);

        if (id !== renderId) {
            return;
        }

        // Only the rows that are shown get read (plus one, to know when there are more), so big files stay fast on phones.
        const workbook = XLSX.read(data, { type: 'array', sheetRows: MAX_SHEET_ROWS + 1 });
        const tabs = document.createElement('div');
        const holder = document.createElement('div');

        tabs.className = 'file-preview__tabs';
        tabs.setAttribute('role', 'tablist');
        holder.className = 'file-preview__sheet';

        function showSheet(index) {
            const rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[index]], { header: 1, raw: false, defval: '', blankrows: true });
            const widest = rows.reduce(function (most, row) { return Math.max(most, row.length); }, 0);
            const columnCount = Math.min(widest, MAX_SHEET_COLUMNS);
            const table = document.createElement('table');
            const head = table.createTHead().insertRow();
            const tbody = table.createTBody();

            table.className = 'table table-sm table-bordered mb-0';
            head.append(document.createElement('th'));

            for (let column = 0; column < columnCount; column++) {
                const cell = document.createElement('th');
                cell.textContent = XLSX.utils.encode_col(column);
                head.append(cell);
            }

            rows.slice(0, MAX_SHEET_ROWS).forEach(function (row, index) {
                const line = tbody.insertRow();
                const number = document.createElement('th');
                number.textContent = index + 1;
                line.append(number);

                for (let column = 0; column < columnCount; column++) {
                    line.insertCell().textContent = row[column] ?? '';
                }
            });

            holder.replaceChildren(table);

            if (rows.length === 0) {
                holder.replaceChildren(Object.assign(document.createElement('p'), { className: 'file-preview__message', textContent: 'Lembar ini kosong.' }));
            } else if (rows.length > MAX_SHEET_ROWS || widest > MAX_SHEET_COLUMNS) {
                holder.append(Object.assign(document.createElement('p'), {
                    className: 'small text-muted mt-2 mb-0',
                    textContent: 'Hanya ' + MAX_SHEET_ROWS + ' baris dan ' + MAX_SHEET_COLUMNS + ' kolom pertama yang ditampilkan. Unduh berkas untuk melihat semuanya.',
                }));
            }

            tabs.querySelectorAll('button').forEach(function (button, buttonIndex) {
                button.classList.toggle('active', buttonIndex === index);
                button.setAttribute('aria-selected', buttonIndex === index ? 'true' : 'false');
            });
        }

        workbook.SheetNames.forEach(function (name, index) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm';
            button.setAttribute('role', 'tab');
            button.textContent = name;
            button.addEventListener('click', function () {
                showSheet(index);
            });
            tabs.append(button);
        });

        body.replaceChildren(...(workbook.SheetNames.length > 1 ? [tabs, holder] : [holder]));
        showSheet(0);
    }

    function renderImage(url) {
        return new Promise(function (resolve, reject) {
            const image = new Image();
            image.className = 'file-preview__image';
            image.alt = 'Pratinjau gambar';
            image.onload = function () {
                body.replaceChildren(image);
                resolve();
            };
            image.onerror = reject;
            image.src = url;
        });
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-file-preview]');

        if (! button) {
            return;
        }

        pending = {
            type: button.dataset.filePreview,
            url: button.dataset.previewUrl,
        };
        modal.querySelector('[data-preview-title]').textContent = button.dataset.fileName;
        modal.querySelector('[data-preview-download]').href = button.dataset.downloadUrl;
        showMessage('Memuat pratinjau…');
    });

    // Rendering starts once the pop-up is visible, so PDF pages can be sized to its width.
    modal.addEventListener('shown.bs.modal', function () {
        if (! pending) {
            return;
        }

        const id = ++renderId;
        const { type, url } = pending;
        const renderers = { pdf: renderPdf, text: renderText, word: renderWord, sheet: renderSheet };
        const render = renderers[type] ? renderers[type](url, id) : renderImage(url);

        render.catch(function () {
            if (id === renderId) {
                showMessage('Pratinjau tidak dapat dimuat. Silakan unduh berkasnya.');
            }
        });
    });

    modal.addEventListener('hidden.bs.modal', function () {
        renderId++;
        pending = null;
        body.replaceChildren();
    });
}
