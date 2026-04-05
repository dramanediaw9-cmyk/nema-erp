<div class="notice signature-card">
    <strong>Signature graphique</strong>
    <div class="muted" style="margin-top:8px;">Trace la signature du client au doigt ou a la souris. Si elle n est pas dessinee, la signature nominative textuelle reste utilisable.</div>
    <noscript><div class="muted" style="margin-top:8px;">La signature graphique demande JavaScript. Tu peux quand meme valider avec la signature nominative textuelle.</div></noscript>

    <div class="signature-pad" data-signature-pad data-target="signature_data_url">
        <canvas width="560" height="180" aria-label="Zone de signature client"></canvas>
        <input type="hidden" name="signature_data_url" value="{{ old('signature_data_url') }}">
        <div class="signature-toolbar">
            <button type="button" class="button button-secondary" data-signature-clear>Effacer</button>
            <span class="muted" data-signature-state>Aucune signature dessinee</span>
        </div>
    </div>
    @error('signature_data_url')<div class="field-error">{{ $message }}</div>@enderror
</div>

@push('scripts')
<script>
(function () {
    if (window.__nemaSignaturePadsBooted) {
        return;
    }

    window.__nemaSignaturePadsBooted = true;

    const pads = document.querySelectorAll('[data-signature-pad]');
    pads.forEach((pad) => {
        if (pad.dataset.ready === '1') {
            return;
        }
        pad.dataset.ready = '1';

        const canvas = pad.querySelector('canvas');
        const input = pad.querySelector('input[type="hidden"]');
        const clearButton = pad.querySelector('[data-signature-clear]');
        const state = pad.querySelector('[data-signature-state]');
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        let drawing = false;
        let hasStroke = false;

        const resizeCanvas = () => {
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            const width = Math.max(rect.width, 320);
            const height = Math.max(rect.height, 160);
            const snapshot = hasStroke ? canvas.toDataURL('image/png') : input.value;

            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.lineWidth = 2.6;
            ctx.strokeStyle = '#0f766e';
            ctx.fillStyle = '#fffdfa';
            ctx.fillRect(0, 0, width, height);
            ctx.strokeStyle = '#0f766e';

            if (snapshot) {
                const image = new Image();
                image.onload = () => {
                    ctx.fillRect(0, 0, width, height);
                    ctx.drawImage(image, 0, 0, width, height);
                    hasStroke = true;
                    input.value = canvas.toDataURL('image/png');
                    state.textContent = 'Signature capturee';
                };
                image.src = snapshot;
            }
        };

        const point = (event) => {
            const rect = canvas.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;
            return {
                x: source.clientX - rect.left,
                y: source.clientY - rect.top,
            };
        };

        const start = (event) => {
            drawing = true;
            const p = point(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            event.preventDefault();
        };

        const move = (event) => {
            if (!drawing) {
                return;
            }
            const p = point(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            hasStroke = true;
            state.textContent = 'Signature capturee';
            event.preventDefault();
        };

        const end = () => {
            if (!drawing) {
                return;
            }
            drawing = false;
            if (hasStroke) {
                input.value = canvas.toDataURL('image/png');
            }
        };

        const clear = () => {
            const width = canvas.width / (window.devicePixelRatio || 1);
            const height = canvas.height / (window.devicePixelRatio || 1);
            ctx.fillStyle = '#fffdfa';
            ctx.fillRect(0, 0, width, height);
            ctx.strokeStyle = '#0f766e';
            hasStroke = false;
            input.value = '';
            state.textContent = 'Aucune signature dessinee';
        };

        resizeCanvas();
        if (!input.value) {
            clear();
        }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);
        clearButton.addEventListener('click', clear);
        window.addEventListener('resize', resizeCanvas);
    });
})();
</script>
@endpush
