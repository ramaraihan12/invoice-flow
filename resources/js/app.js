const pdfContainers = document.querySelectorAll('[data-pdf]');

if (pdfContainers.length > 0) {
    import('./pdf-viewer').then(({ renderPdf }) => {
        pdfContainers.forEach((container) => renderPdf(container, container.dataset.pdf));
    });
}

// Line items on the review form.
const items = document.querySelector('[data-items]');
const template = document.querySelector('[data-item-template]');

if (items && template) {
    let next = items.querySelectorAll('[data-item]').length;

    document.querySelector('[data-add-item]')?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(next++));
        items.insertAdjacentHTML('beforeend', html);
        items.lastElementChild.querySelector('input')?.focus();
    });

    items.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-item]');

        if (button) {
            button.closest('[data-item]').remove();
        }
    });
}

// Upload form: show what was picked and accept drag and drop.
const upload = document.querySelector('[data-upload]');

if (upload) {
    const input = upload.querySelector('input[type=file]');
    const zone = upload.querySelector('[data-dropzone]');
    const list = upload.querySelector('[data-file-list]');

    const showFiles = () => {
        list.replaceChildren(
            ...Array.from(input.files).map((file) => {
                const li = document.createElement('li');
                li.textContent = `${file.name} (${Math.ceil(file.size / 1024)} KB)`;
                return li;
            }),
        );
    };

    input.addEventListener('change', showFiles);

    ['dragenter', 'dragover'].forEach((type) =>
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.add('border-slate-500', 'bg-slate-50');
        }),
    );

    ['dragleave', 'drop'].forEach((type) =>
        zone.addEventListener(type, () => zone.classList.remove('border-slate-500', 'bg-slate-50')),
    );

    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        input.files = event.dataTransfer.files;
        showFiles();
    });

    // Past the host's own body limit a visitor would get a bare error page.
    const maxBytes = Number(upload.dataset.maxBytes);

    upload.addEventListener('submit', (event) => {
        if (maxBytes && Array.from(input.files).some((file) => file.size > maxBytes)) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const li = document.createElement('li');
            li.className = 'text-rose-700';
            li.textContent = `Files can be ${maxBytes / 1024 / 1024} MB at most.`;
            list.replaceChildren(li);
        }
    });
}

// On the demo an invoice is read inside the request, so say so while the browser waits.
document.querySelectorAll('form[data-reading]').forEach((form) => {
    form.addEventListener('submit', () => {
        document.querySelectorAll('form[data-reading] button').forEach((button) => {
            button.disabled = true;
        });

        form.querySelector('button').textContent = 'Reading, this takes a few seconds...';
    });
});
