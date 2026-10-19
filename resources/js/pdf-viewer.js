import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist';

GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

/**
 * Draws every page of the PDF into the container as canvases. The browser's
 * own viewer would work too, but it ignores page width, can't be styled and
 * isn't there at all in some embedded browsers.
 */
export async function renderPdf(container, url) {
    const status = container.querySelector('[data-pdf-status]');

    try {
        const pdf = await getDocument({ url }).promise;
        const width = container.clientWidth;
        const ratio = window.devicePixelRatio || 1;

        for (let number = 1; number <= pdf.numPages; number++) {
            const page = await pdf.getPage(number);
            const viewport = page.getViewport({ scale: width / page.getViewport({ scale: 1 }).width });

            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width * ratio);
            canvas.height = Math.floor(viewport.height * ratio);
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.className = 'rounded bg-white shadow';

            container.appendChild(canvas);

            await page.render({
                canvas,
                viewport,
                transform: ratio === 1 ? undefined : [ratio, 0, 0, ratio, 0, 0],
            }).promise;
        }

        status?.remove();
    } catch (error) {
        if (status) {
            status.textContent = `Couldn't show this PDF here (${error.message}). Use "Open in new tab".`;
        }
    }
}
