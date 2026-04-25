// ID card preprocessing + preview.
//
// After a file is selected, the browser center-crops it to 900x600, shows
// the final result, and replaces the file input with that generated JPEG.

(function () {
    const MAX_INPUT_MB = 3;
    const MAX_SOURCE_PIXELS = 40000000;
    const OUTPUT_WIDTH = 900;
    const OUTPUT_HEIGHT = 600;
    const OUTPUT_ASPECT = OUTPUT_WIDTH / OUTPUT_HEIGHT;
    const QUALITY = 0.85;

    const LABELS = {
        id_card_front: 'ID card front',
        id_card_back: 'ID card back',
    };

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            const url = URL.createObjectURL(file);
            const img = new Image();

            img.onload = function () {
                resolve({ img: img, url: url });
            };

            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Could not decode the image. Please choose a valid photo file.'));
            };

            img.src = url;
        });
    }

    function getCenteredCrop(origW, origH) {
        let cropW = origW;
        let cropH = origW / OUTPUT_ASPECT;

        if (cropH > origH) {
            cropH = origH;
            cropW = origH * OUTPUT_ASPECT;
        }

        return {
            srcX: Math.floor((origW - cropW) / 2),
            srcY: Math.floor((origH - cropH) / 2),
            cropW: Math.floor(cropW),
            cropH: Math.floor(cropH),
        };
    }

    function replaceInputFile(input, blob, originalName) {
        const outName = (originalName || input.name).replace(/\.[^.]+$/, '') + '.jpg';
        const dt = new DataTransfer();
        dt.items.add(new File([blob], outName, { type: 'image/jpeg' }));
        input.files = dt.files;
    }

    function getPreviewBox(input) {
        const parent = input.parentElement;
        let box = parent.querySelector('[data-id-card-preview]');
        if (box) {
            return box;
        }

        box = document.createElement('div');
        box.className = 'mt-3';
        box.setAttribute('data-id-card-preview', '');
        parent.appendChild(box);
        return box;
    }

    function getOrigDimsInputs(input) {
        const parent = input.parentElement;
        const key = input.name;
        let widthEl = parent.querySelector('input[name="' + key + '_orig_width"]');
        let heightEl = parent.querySelector('input[name="' + key + '_orig_height"]');

        if (!widthEl) {
            widthEl = document.createElement('input');
            widthEl.type = 'hidden';
            widthEl.name = key + '_orig_width';
            parent.appendChild(widthEl);
        }

        if (!heightEl) {
            heightEl = document.createElement('input');
            heightEl.type = 'hidden';
            heightEl.name = key + '_orig_height';
            parent.appendChild(heightEl);
        }

        return { widthEl: widthEl, heightEl: heightEl };
    }

    function renderLoading(box, label) {
        box.innerHTML =
            '<div class="small text-muted">' +
                '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' +
                'Preparing preview for ' + label + '...' +
            '</div>';
    }

    function renderError(box, msg) {
        box.innerHTML = '<div class="alert alert-danger small mb-0">' + msg + '</div>';
    }

    function renderPreview(box, result) {
        const kb = Math.round(result.blob.size / 1024);
        const cropStyle = [
            'left:' + ((result.crop.srcX / result.origW) * 100).toFixed(4) + '%',
            'top:' + ((result.crop.srcY / result.origH) * 100).toFixed(4) + '%',
            'width:' + ((result.crop.cropW / result.origW) * 100).toFixed(4) + '%',
            'height:' + ((result.crop.cropH / result.origH) * 100).toFixed(4) + '%',
        ].join(';');

        box.innerHTML =
            '<div class="card border-success shadow-sm id-card-final-preview">' +
                '<div class="card-body p-3">' +
                    '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">' +
                        '<div>' +
                            '<div class="text-success fw-semibold">' +
                                '<i class="bi bi-check-circle-fill me-1"></i>Ready to submit' +
                            '</div>' +
                            '<div class="small text-muted">The system will submit the center-cropped preview on the right.</div>' +
                        '</div>' +
                        '<div class="small text-muted text-end">' +
                            '<div><strong>Original:</strong> ' + result.origW + ' x ' + result.origH + ' px</div>' +
                            '<div><strong>Final:</strong> ' + OUTPUT_WIDTH + ' x ' + OUTPUT_HEIGHT + ' px</div>' +
                            '<div><strong>Size:</strong> ' + kb + ' KB JPEG</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="id-card-preview-pair">' +
                        '<div class="id-card-preview-pane">' +
                            '<div class="border rounded p-2 h-100">' +
                                '<div class="small fw-semibold text-muted mb-2">Original img</div>' +
                                '<div class="id-card-preview-original">' +
                                    '<span class="id-card-preview-original-frame">' +
                                        '<img src="' + result.originalUrl + '" alt="Original upload preview">' +
                                        '<span class="id-card-crop-suggested-area" style="' + cropStyle + '"></span>' +
                                    '</span>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="id-card-preview-pane">' +
                            '<div class="border rounded p-2 h-100">' +
                                '<div class="small fw-semibold text-muted mb-2">Cropped img by system</div>' +
                                '<img src="' + result.previewUrl + '" class="id-card-preview-final rounded border" alt="Final cropped preview">' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
    }

    function clearPreview(box) {
        box.innerHTML = '';
    }

    function revokePreviewUrls(input) {
        const previous = input._idCardPreviewUrls;
        if (!previous) {
            return;
        }

        if (previous.originalUrl) {
            URL.revokeObjectURL(previous.originalUrl);
        }

        if (previous.previewUrl) {
            URL.revokeObjectURL(previous.previewUrl);
        }

        input._idCardPreviewUrls = null;
    }

    async function processFile(file, label) {
        if (file.size > MAX_INPUT_MB * 1024 * 1024) {
            throw new Error(label + ' is larger than ' + MAX_INPUT_MB + ' MB. Please pick a smaller file.');
        }

        const loaded = await loadImage(file);
        const img = loaded.img;
        const origW = img.naturalWidth;
        const origH = img.naturalHeight;

        if (!origW || !origH) {
            URL.revokeObjectURL(loaded.url);
            throw new Error('Could not read the image dimensions.');
        }

        if (origW * origH > MAX_SOURCE_PIXELS) {
            URL.revokeObjectURL(loaded.url);
            throw new Error(label + ' is too large in resolution. Please choose a smaller image.');
        }

        if (origW < OUTPUT_WIDTH || origH < OUTPUT_HEIGHT) {
            URL.revokeObjectURL(loaded.url);
            throw new Error(label + ' is too small. Please upload an image at least ' + OUTPUT_WIDTH + ' x ' + OUTPUT_HEIGHT + ' px.');
        }

        const crop = getCenteredCrop(origW, origH);
        const canvas = document.createElement('canvas');
        canvas.width = OUTPUT_WIDTH;
        canvas.height = OUTPUT_HEIGHT;

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            URL.revokeObjectURL(loaded.url);
            throw new Error('Your browser could not prepare the image preview.');
        }

        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, crop.srcX, crop.srcY, crop.cropW, crop.cropH, 0, 0, OUTPUT_WIDTH, OUTPUT_HEIGHT);

        const blob = await new Promise(function (resolve, reject) {
            canvas.toBlob(function (result) {
                if (!result) {
                    reject(new Error('Could not encode ' + label + ' as JPEG.'));
                    return;
                }
                resolve(result);
            }, 'image/jpeg', QUALITY);
        });

        return {
            blob: blob,
            crop: crop,
            origW: origW,
            origH: origH,
            originalUrl: loaded.url,
            previewUrl: URL.createObjectURL(blob),
        };
    }

    function attachInput(input) {
        const label = LABELS[input.name] || input.name;
        const box = getPreviewBox(input);
        const dims = getOrigDimsInputs(input);

        input.addEventListener('change', async function () {
            const file = input.files[0];
            revokePreviewUrls(input);
            input.dataset.idCardProcessing = '0';

            if (!file) {
                clearPreview(box);
                dims.widthEl.value = '';
                dims.heightEl.value = '';
                return;
            }

            input.dataset.idCardProcessing = '1';
            renderLoading(box, label);

            try {
                const result = await processFile(file, label);
                replaceInputFile(input, result.blob, file.name);
                dims.widthEl.value = String(result.origW);
                dims.heightEl.value = String(result.origH);
                input._idCardPreviewUrls = {
                    originalUrl: result.originalUrl,
                    previewUrl: result.previewUrl,
                };
                renderPreview(box, result);
                input.dataset.idCardProcessing = '0';
            } catch (err) {
                renderError(box, err.message || 'Image processing failed.');
                input.value = '';
                dims.widthEl.value = '';
                dims.heightEl.value = '';
                input.dataset.idCardProcessing = '0';
            }
        });
    }

    function attachForm(form) {
        const frontInput = form.querySelector('input[name="id_card_front"]');
        const backInput = form.querySelector('input[name="id_card_back"]');

        if (!frontInput || !backInput) {
            return;
        }

        attachInput(frontInput);
        attachInput(backInput);

        form.addEventListener('submit', function (event) {
            if (frontInput.dataset.idCardProcessing === '1' || backInput.dataset.idCardProcessing === '1') {
                event.preventDefault();
                alert('Please wait for the ID card preview to finish preparing.');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(attachForm);
    });
})();
