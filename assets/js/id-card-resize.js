// Client-side ID card image preprocessing.
//
// Why: raw phone photos are 3-8 MB. Uploading them is slow, and the server
// then has to decode huge JPEGs in GD which pressures Fly's 256 MB machine.
// Doing the work in the browser shifts all that cost to the user's device
// and the server receives an already-small file.
//
// What this does on form submit:
//   1. Reject files > MAX_INPUT_MB (hard limit — matches server check).
//   2. Crop each image to a centered square (shorter edge × shorter edge).
//   3. Scale the square to TARGET_SIDE px.
//   4. Re-encode as JPEG at QUALITY.
//   5. Replace the file input's FileList with the processed Blob.
//   6. Let the form submit normally.
//
// TARGET_SIDE = 1200 → diagonal ≈ 1697 px. Reads ID numbers clearly,
// outputs ~150-300 KB per image at Q 0.85.

(function () {
    const MAX_INPUT_MB = 3;
    const TARGET_SIDE = 1200;
    const QUALITY = 0.85;

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Could not decode the image. Is the file a valid photo?'));
            };
            img.src = url;
        });
    }

    async function processImage(file, label) {
        if (file.size > MAX_INPUT_MB * 1024 * 1024) {
            throw new Error(label + ' is larger than ' + MAX_INPUT_MB + ' MB. Please pick a smaller file.');
        }

        const img = await loadImage(file);

        // Center-square crop: take the shorter edge as the side length,
        // offset the longer edge so the square is centered.
        const side = Math.min(img.naturalWidth, img.naturalHeight);
        const srcX = Math.floor((img.naturalWidth - side) / 2);
        const srcY = Math.floor((img.naturalHeight - side) / 2);

        // Never upscale — if the source square is smaller than TARGET_SIDE,
        // keep the original resolution (re-encoding still shrinks file size).
        const outSide = Math.min(side, TARGET_SIDE);

        const canvas = document.createElement('canvas');
        canvas.width = outSide;
        canvas.height = outSide;
        const ctx = canvas.getContext('2d');
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, srcX, srcY, side, side, 0, 0, outSide, outSide);

        return await new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('Could not encode ' + label + ' as JPEG.'));
                    return;
                }
                resolve(blob);
            }, 'image/jpeg', QUALITY);
        });
    }

    function replaceInputFile(input, blob, originalName) {
        // File inputs are read-only, but assigning to .files via DataTransfer
        // works in all modern browsers — the server sees the processed blob
        // under the same field name.
        const outName = (originalName || input.name).replace(/\.[^.]+$/, '') + '.jpg';
        const dt = new DataTransfer();
        dt.items.add(new File([blob], outName, { type: 'image/jpeg' }));
        input.files = dt.files;
    }

    function findErrorBox(form) {
        // Reuse an existing alert-danger if present, otherwise inject one.
        let box = form.querySelector('[data-id-card-error]');
        if (box) return box;
        box = document.createElement('div');
        box.className = 'alert alert-danger';
        box.setAttribute('data-id-card-error', '');
        form.prepend(box);
        return box;
    }

    function attach(form) {
        const frontInput = form.querySelector('input[name="id_card_front"]');
        const backInput = form.querySelector('input[name="id_card_back"]');
        if (!frontInput || !backInput) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        let processed = false;

        form.addEventListener('submit', async function (e) {
            if (processed) return; // already rewrote the files, let it go
            if (!frontInput.files[0] || !backInput.files[0]) return; // native validation handles

            e.preventDefault();
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.origHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing images...';
            }

            try {
                const frontFile = frontInput.files[0];
                const backFile = backInput.files[0];
                const [frontBlob, backBlob] = await Promise.all([
                    processImage(frontFile, 'ID card front'),
                    processImage(backFile, 'ID card back'),
                ]);
                replaceInputFile(frontInput, frontBlob, frontFile.name);
                replaceInputFile(backInput, backBlob, backFile.name);
                processed = true;
                form.submit();
            } catch (err) {
                const box = findErrorBox(form);
                box.textContent = err.message || 'Image processing failed.';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.origHtml;
                }
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(attach);
    });
})();
