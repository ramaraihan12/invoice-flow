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
}
