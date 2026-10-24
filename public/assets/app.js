document.addEventListener('DOMContentLoaded', () => {
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');

        startDate?.addEventListener('change', () => {
            endDate.value = startDate.value;
        });
    });

document.querySelectorAll('[data-mobile-menu-button]').forEach((button) => {
            const menu = document.querySelector('[data-mobile-menu]');

            button.addEventListener('click', () => {
                const isOpen = !menu.classList.contains('hidden');

                menu.classList.toggle('hidden', isOpen);
                button.setAttribute('aria-expanded', String(!isOpen));
            });
        });

document.querySelectorAll('[data-booking-import-form]').forEach((form) => {
            const input = form.querySelector('[data-booking-file-input]');
            const dropzone = form.querySelector('[data-booking-dropzone]');
            const fileName = form.querySelector('[data-booking-file-name]');
            let selectedFiles = [];
            const supportsAdditiveFiles = typeof DataTransfer === 'function';

            const fileKey = (file) => `${file.name}-${file.size}-${file.lastModified}`;

            const syncInput = () => {
                if (!supportsAdditiveFiles) {
                    return;
                }

                const transfer = new DataTransfer();

                selectedFiles.slice(0, 3).forEach((file) => {
                    transfer.items.add(file);
                });

                input.files = transfer.files;
            };

            const addFiles = (files) => {
                if (!supportsAdditiveFiles) {
                    selectedFiles = Array.from(files).slice(0, 3);
                    showFile();
                    return;
                }

                const existing = new Set(selectedFiles.map(fileKey));

                Array.from(files).forEach((file) => {
                    const key = fileKey(file);

                    if (!existing.has(key) && selectedFiles.length < 3) {
                        selectedFiles.push(file);
                        existing.add(key);
                    }
                });

                syncInput();
                showFile();
            };

            const showFile = () => {
                if (!selectedFiles.length) {
                    fileName.classList.add('hidden');
                    fileName.textContent = '';
                    return;
                }

                const names = selectedFiles.map((file) => file.name);

                fileName.textContent = selectedFiles.length === 1
                    ? names[0]
                    : `${selectedFiles.length} Dateien: ${names.slice(0, 3).join(', ')}${names.length > 3 ? ' ...' : ''}`;
                fileName.classList.remove('hidden');
            };

            input.addEventListener('change', () => {
                addFiles(input.files);
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.add('border-slate-900', 'bg-slate-50');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('border-slate-900', 'bg-slate-50');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                if (!event.dataTransfer.files.length) {
                    return;
                }

                addFiles(event.dataTransfer.files);
            });
        });

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});
