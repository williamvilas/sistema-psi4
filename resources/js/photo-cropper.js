const photoCropState = {
    canvas: null,
    context: null,
    image: null,
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
    dragging: false,
    startX: 0,
    startY: 0,
    startOffsetX: 0,
    startOffsetY: 0,
    pressX: 0,
    pressY: 0,
    hasSavedPhoto: false,
    tainted: false,
};

function setupPhotoCropper(savedPhotoUrl = null) {
    const canvas = document.getElementById('photoCanvas');
    const wrap = document.getElementById('photoCanvasWrap');
    const input = document.getElementById('fotoPsicologo');
    const zoomInput = document.getElementById('photoZoom');

    if (!canvas || !wrap || !input || !zoomInput) return;

    photoCropState.canvas = canvas;
    photoCropState.context = canvas.getContext('2d');
    drawPhotoPlaceholder();

    if (savedPhotoUrl) {
        photoCropState.hasSavedPhoto = true;
        const savedPhoto = new Image();
        savedPhoto.addEventListener('load', () => {
            if (photoCropState.image) return;
            photoCropState.tainted = true;
            const scale = Math.max(canvas.width / savedPhoto.width, canvas.height / savedPhoto.height);
            photoCropState.context.drawImage(
                savedPhoto,
                (canvas.width - savedPhoto.width * scale) / 2,
                (canvas.height - savedPhoto.height * scale) / 2,
                savedPhoto.width * scale,
                savedPhoto.height * scale
            );
        });
        savedPhoto.src = savedPhotoUrl;
    }

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.addEventListener('load', () => {
            const image = new Image();
            image.addEventListener('load', () => {
                // Foto salva vem de outra origem (S3) e "suja" o canvas; um canvas novo permite exportar o recorte.
                if (photoCropState.tainted) replacePhotoCanvas();
                photoCropState.image = image;
                photoCropState.zoom = 1;
                photoCropState.offsetX = 0;
                photoCropState.offsetY = 0;
                zoomInput.value = '1';
                zoomInput.disabled = false;
                document.getElementById('photoZoomWrap')?.classList.remove('d-none');
                setRemovePhoto(false);
                drawPhoto();
            });
            image.src = reader.result;
        });
        reader.readAsDataURL(file);
    });

    zoomInput.addEventListener('input', () => {
        photoCropState.zoom = Number(zoomInput.value);
        drawPhoto();
    });

    wrap.addEventListener('pointerdown', (event) => {
        photoCropState.pressX = event.clientX;
        photoCropState.pressY = event.clientY;
        if (!photoCropState.image) return;
        photoCropState.dragging = true;
        photoCropState.startX = event.clientX;
        photoCropState.startY = event.clientY;
        photoCropState.startOffsetX = photoCropState.offsetX;
        photoCropState.startOffsetY = photoCropState.offsetY;
        wrap.setPointerCapture(event.pointerId);
    });

    wrap.addEventListener('pointermove', (event) => {
        if (!photoCropState.dragging) return;
        const ratio = photoCropState.canvas.width / wrap.clientWidth;
        photoCropState.offsetX = photoCropState.startOffsetX + (event.clientX - photoCropState.startX) * ratio;
        photoCropState.offsetY = photoCropState.startOffsetY + (event.clientY - photoCropState.startY) * ratio;
        drawPhoto();
    });

    ['pointerup', 'pointercancel'].forEach((eventName) => {
        wrap.addEventListener(eventName, (event) => {
            photoCropState.dragging = false;

            const moved = Math.hypot(event.clientX - photoCropState.pressX, event.clientY - photoCropState.pressY);
            if (eventName === 'pointerup' && moved < 5) input.click();
        });
    });

    document.getElementById('btn-remove-photo')?.addEventListener('click', confirmRemovePhoto);
    updateRemoveButton();
}

function prepareCroppedPhoto() {
    if (!photoCropState.image || !photoCropState.canvas) return;

    const croppedPhoto = document.getElementById('fotoPsicologoCortada');
    if (croppedPhoto) croppedPhoto.value = photoCropState.canvas.toDataURL('image/jpeg', .92);
}

function resetPhotoCropper() {
    const removed = document.getElementById('removerFoto')?.value === '1';
    if (removed) {
        photoCropState.hasSavedPhoto = false;
    } else if (photoCropState.image) {
        photoCropState.hasSavedPhoto = true;
    }

    photoCropState.image = null;
    photoCropState.dragging = false;

    const zoomInput = document.getElementById('photoZoom');
    if (zoomInput) {
        zoomInput.value = '1';
        zoomInput.disabled = true;
    }

    document.getElementById('photoZoomWrap')?.classList.add('d-none');

    const input = document.getElementById('fotoPsicologo');
    if (input) input.value = '';

    const croppedPhoto = document.getElementById('fotoPsicologoCortada');
    if (croppedPhoto) croppedPhoto.value = '';

    setRemovePhoto(false);
}

function setRemovePhoto(remove) {
    const field = document.getElementById('removerFoto');
    if (field) {
        field.value = remove ? '1' : '0';
        // Avisa o formulário (ex.: controle de alterações não salvas)
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    updateRemoveButton();
}

function updateRemoveButton() {
    const button = document.getElementById('btn-remove-photo');
    if (!button) return;

    const removed = document.getElementById('removerFoto')?.value === '1';
    button.classList.toggle('d-none', removed || !(photoCropState.hasSavedPhoto || photoCropState.image));
}

function confirmRemovePhoto() {
    confirmAction('Tem certeza que deseja <strong>remover</strong> a foto? A alteração será aplicada ao salvar as configurações.', function () {
        if (photoCropState.tainted) replacePhotoCanvas();
        resetPhotoCropper();
        setRemovePhoto(true);
        drawPhotoPlaceholder();
    });
}

function replacePhotoCanvas() {
    const fresh = photoCropState.canvas.cloneNode(false);
    photoCropState.canvas.replaceWith(fresh);
    photoCropState.canvas = fresh;
    photoCropState.context = fresh.getContext('2d');
    photoCropState.tainted = false;
}

function drawPhotoPlaceholder() {
    const { context, canvas } = photoCropState;
    context.fillStyle = '#e9ecef';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#6c757d';
    context.textAlign = 'center';
    context.font = '600 27px sans-serif';
    context.fillText('Selecione uma foto', canvas.width / 2, canvas.height / 2);
}

function drawPhoto() {
    const { context, canvas, image } = photoCropState;
    if (!image) return;

    const scale = Math.max(canvas.width / image.width, canvas.height / image.height) * photoCropState.zoom;
    const width = image.width * scale;
    const height = image.height * scale;
    const maxOffsetX = (width - canvas.width) / 2;
    const maxOffsetY = (height - canvas.height) / 2;
    photoCropState.offsetX = Math.min(maxOffsetX, Math.max(-maxOffsetX, photoCropState.offsetX));
    photoCropState.offsetY = Math.min(maxOffsetY, Math.max(-maxOffsetY, photoCropState.offsetY));

    const x = (canvas.width - width) / 2 + photoCropState.offsetX;
    const y = (canvas.height - height) / 2 + photoCropState.offsetY;

    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, x, y, width, height);
}
