<style>
    .product-media-layout {
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr);
        margin-bottom: 20px;
        align-items: start;
    }
    .product-upload-card,
    .product-camera-card {
        border: 1px solid var(--line);
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        padding: 18px;
    }
    .product-dropzone {
        border: 2px dashed #bfd3ee;
        border-radius: 22px;
        padding: 16px;
        background: #f8fbff;
        transition: border-color .15s ease, background .15s ease, transform .15s ease;
        cursor: pointer;
    }
    .product-dropzone.is-dragover {
        border-color: #2e6cf6;
        background: #eef4ff;
        transform: translateY(-1px);
    }
    .product-dropzone input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }
    .product-preview {
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 22px;
        overflow: hidden;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 1px solid #dbe3ef;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #17304f;
    }
    .product-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .product-preview-placeholder {
        font-size: 72px;
        font-weight: 900;
        letter-spacing: .04em;
    }
    .product-upload-meta {
        display: grid;
        gap: 10px;
        margin-top: 16px;
    }
    .product-upload-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .product-upload-note {
        padding: 12px 14px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid #dbe3ef;
        color: #4b5e75;
        font-size: 14px;
    }
    .product-camera-card[hidden] {
        display: none !important;
    }
    .product-camera-frame {
        width: 100%;
        aspect-ratio: 4 / 3;
        background: #0f172a;
        border-radius: 22px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #c9d5e4;
    }
    .product-camera-frame video,
    .product-camera-frame canvas {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .product-camera-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 14px;
    }
    .product-current-photo {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 18px;
        background: #fff;
        border: 1px solid #dbe3ef;
    }
    .product-current-photo img {
        width: 74px;
        height: 74px;
        object-fit: cover;
        border-radius: 18px;
        border: 1px solid #dbe3ef;
        background: #fff;
    }
    @media (max-width: 980px) {
        .product-media-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card">
    <div class="product-media-layout">
        <section class="product-upload-card">
            <label style="margin-bottom:12px;">Photo produit</label>
            <div class="product-dropzone" id="product-dropzone" tabindex="0">
                <input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" capture="environment">
                <div class="product-preview" id="product-preview">
                    @if ($product->image_url && ! old('remove_image'))
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                    @else
                        <div class="product-preview-placeholder" id="product-preview-placeholder">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(old('name', $product->name ?: 'PR'), 0, 2)) }}</div>
                    @endif
                </div>
                <div class="product-upload-meta">
                    <strong id="product-upload-title">{{ $product->image_url && ! old('remove_image') ? 'Photo actuelle du produit' : 'Ajoute une photo produit' }}</strong>
                    <div class="muted" id="product-upload-filename">{{ $product->image_url && ! old('remove_image') ? 'Image deja enregistree sur ce produit.' : 'Glisse-depose une image ici ou utilise les boutons ci-dessous.' }}</div>
                    <div class="product-upload-actions">
                        <button type="button" class="button button-secondary" id="product-pick-image">Choisir une photo</button>
                        <button type="button" class="button button-secondary" id="product-open-camera">Prendre une photo</button>
                    </div>
                    <div class="product-upload-note">Formats acceptes : JPG, PNG, WEBP. La photo sera visible dans le catalogue, la fiche produit et le point de vente.</div>
                    @error('image')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="product-camera-card" id="product-camera-card" hidden>
            <label style="margin-bottom:12px;">Capture camera</label>
            <div class="product-camera-frame" id="product-camera-frame">
                <video id="product-camera-video" autoplay playsinline muted></video>
                <canvas id="product-camera-canvas" hidden></canvas>
            </div>
            <div class="product-camera-actions">
                <button type="button" class="button button-primary" id="product-capture-image">Capturer</button>
                <button type="button" class="button button-secondary" id="product-stop-camera">Fermer la camera</button>
            </div>
            <div class="help" style="margin-top:10px;">Sur mobile, utilise la camera arriere quand elle est disponible.</div>
        </section>
    </div>

    @if ($product->image_url)
        <div class="product-current-photo" style="margin-bottom:20px;">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
            <div style="display:grid; gap:8px;">
                <strong>Photo actuellement enregistree</strong>
                <label style="display:flex; align-items:center; gap:10px; margin:0;">
                    <input type="checkbox" name="remove_image" id="remove_image" value="1" @checked(old('remove_image'))>
                    Supprimer la photo actuelle lors de l enregistrement
                </label>
            </div>
        </div>
    @else
        <input type="hidden" name="remove_image" value="0">
    @endif

    <div class="form-grid">
        <div>
            <label for="sku">Reference interne</label>
            <input id="sku" type="text" name="sku" value="{{ old('sku', $product->sku) }}">
            <div class="help">Laisser vide pour generation automatique.</div>
        </div>
        <div>
            <label for="barcode">Code-barres</label>
            <input id="barcode" type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}" placeholder="Ex: 3700000000012">
            <div class="help">Utilise pour la recherche rapide et le scan au point de vente.</div>
        </div>
        <div>
            <label for="name">Nom</label>
            <input id="name" type="text" name="name" value="{{ old('name', $product->name) }}" required>
        </div>
        <div>
            <label for="category_id">Categorie</label>
            <select id="category_id" name="category_id">
                <option value="">Sans categorie</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type">Type</label>
            <select id="type" name="type" required>
                <option value="stockable" @selected(old('type', $product->type) === 'stockable')>Article stockable</option>
                <option value="service" @selected(old('type', $product->type) === 'service')>Service</option>
            </select>
        </div>
        <div>
            <label for="unit">Unite</label>
            <input id="unit" type="text" name="unit" value="{{ old('unit', $product->unit ?: 'unite') }}" required>
        </div>
        <div>
            <label for="min_stock">Stock minimum</label>
            <input id="min_stock" type="number" step="0.001" min="0" name="min_stock" value="{{ old('min_stock', $product->min_stock ?: 0) }}" required>
        </div>
        <div>
            <label for="purchase_price">Prix d'achat</label>
            <input id="purchase_price" type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price ?: 0) }}" required>
        </div>
        <div>
            <label for="sale_price">Prix de vente</label>
            <input id="sale_price" type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $product->sale_price ?: 0) }}" required>
        </div>
        <div>
            <label for="sale_tax_rule_id">Taxe vente</label>
            <select id="sale_tax_rule_id" name="sale_tax_rule_id">
                <option value="">Aucune taxe</option>
                @foreach ($taxRules as $taxRule)
                    <option value="{{ $taxRule->id }}" @selected((string) old('sale_tax_rule_id', $product->sale_tax_rule_id) === (string) $taxRule->id)>{{ $taxRule->name }} · {{ number_format((float) $taxRule->rate, 2, ',', ' ') }}%</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="purchase_tax_rule_id">Taxe achat</label>
            <select id="purchase_tax_rule_id" name="purchase_tax_rule_id">
                <option value="">Aucune taxe</option>
                @foreach ($taxRules as $taxRule)
                    <option value="{{ $taxRule->id }}" @selected((string) old('purchase_tax_rule_id', $product->purchase_tax_rule_id) === (string) $taxRule->id)>{{ $taxRule->name }} · {{ number_format((float) $taxRule->rate, 2, ',', ' ') }}%</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="is_active">Statut</label>
            <select id="is_active" name="is_active">
                <option value="1" @selected(old('is_active', $product->is_active ?? true))>Actif</option>
                <option value="0" @selected((string) old('is_active', $product->is_active ?? true) === '0')>Inactif</option>
            </select>
        </div>
        <div class="full">
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $product->description) }}</textarea>
        </div>
    </div>

    <div class="actions">
        <a href="{{ route('products.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>

<script>
    (() => {
        const fileInput = document.getElementById('image');
        const dropzone = document.getElementById('product-dropzone');
        const preview = document.getElementById('product-preview');
        const title = document.getElementById('product-upload-title');
        const fileName = document.getElementById('product-upload-filename');
        const pickButton = document.getElementById('product-pick-image');
        const cameraButton = document.getElementById('product-open-camera');
        const removeCheckbox = document.getElementById('remove_image');
        const nameInput = document.getElementById('name');
        const cameraCard = document.getElementById('product-camera-card');
        const cameraVideo = document.getElementById('product-camera-video');
        const cameraCanvas = document.getElementById('product-camera-canvas');
        const captureButton = document.getElementById('product-capture-image');
        const stopCameraButton = document.getElementById('product-stop-camera');
        const existingImageUrl = @json(old('remove_image') ? null : $product->image_url);
        let cameraStream = null;

        if (!fileInput || !dropzone || !preview || !title || !fileName || !pickButton || !cameraButton) {
            return;
        }

        const initials = (value) => {
            const parts = String(value || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
            return (parts.map((part) => part.charAt(0).toUpperCase()).join('') || 'PR');
        };

        const humanSize = (bytes) => {
            if (!bytes) {
                return '0 Ko';
            }
            const kb = bytes / 1024;
            if (kb < 1024) {
                return `${kb.toFixed(0)} Ko`;
            }
            return `${(kb / 1024).toFixed(1)} Mo`;
        };

        const setPlaceholder = (message) => {
            preview.innerHTML = `<div class="product-preview-placeholder">${initials(nameInput?.value)}</div>`;
            title.textContent = 'Ajoute une photo produit';
            fileName.textContent = message || 'Glisse-depose une image ici ou utilise les boutons ci-dessous.';
        };

        const setImagePreview = (url, label, detail) => {
            preview.innerHTML = `<img src="${url}" alt="Apercu produit">`;
            title.textContent = label;
            fileName.textContent = detail;
        };

        const assignFile = (file) => {
            if (!file) {
                return;
            }
            const transfer = new DataTransfer();
            transfer.items.add(file);
            fileInput.files = transfer.files;
            if (removeCheckbox) {
                removeCheckbox.checked = false;
            }
            const reader = new FileReader();
            reader.onload = (event) => {
                setImagePreview(event.target?.result || '', 'Nouvelle photo selectionnee', `${file.name} · ${humanSize(file.size)}`);
            };
            reader.readAsDataURL(file);
        };

        const stopCamera = () => {
            if (cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                cameraStream = null;
            }
            if (cameraCard) {
                cameraCard.hidden = true;
            }
        };

        const openCamera = async () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !cameraCard || !cameraVideo) {
                fileInput.click();
                return;
            }
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false,
                });
                cameraVideo.srcObject = cameraStream;
                cameraCard.hidden = false;
            } catch (error) {
                fileName.textContent = 'Camera non accessible dans ce navigateur. Utilise le choix de fichier classique.';
                fileInput.click();
            }
        };

        pickButton.addEventListener('click', () => fileInput.click());
        cameraButton.addEventListener('click', () => {
            openCamera();
        });
        stopCameraButton?.addEventListener('click', stopCamera);

        captureButton?.addEventListener('click', () => {
            if (!cameraVideo || !cameraCanvas) {
                return;
            }
            const width = cameraVideo.videoWidth || 1280;
            const height = cameraVideo.videoHeight || 960;
            cameraCanvas.width = width;
            cameraCanvas.height = height;
            const context = cameraCanvas.getContext('2d');
            if (!context) {
                return;
            }
            context.drawImage(cameraVideo, 0, 0, width, height);
            cameraCanvas.toBlob((blob) => {
                if (!blob) {
                    return;
                }
                const capturedFile = new File([blob], `produit-${Date.now()}.jpg`, { type: 'image/jpeg' });
                assignFile(capturedFile);
                stopCamera();
            }, 'image/jpeg', 0.92);
        });

        fileInput.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            if (file) {
                assignFile(file);
            }
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', (event) => {
            const file = event.dataTransfer?.files?.[0];
            if (file && file.type.startsWith('image/')) {
                assignFile(file);
            }
        });
        dropzone.addEventListener('click', (event) => {
            if (event.target.closest('button') || event.target.closest('input[type="checkbox"]')) {
                return;
            }
            fileInput.click();
        });
        dropzone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });

        removeCheckbox?.addEventListener('change', () => {
            if (removeCheckbox.checked) {
                fileInput.value = '';
                setPlaceholder('La photo actuelle sera supprimee a l enregistrement.');
                stopCamera();
                return;
            }
            if (fileInput.files?.[0]) {
                assignFile(fileInput.files[0]);
                return;
            }
            if (existingImageUrl) {
                setImagePreview(existingImageUrl, 'Photo actuelle du produit', 'Image deja enregistree sur ce produit.');
                return;
            }
            setPlaceholder();
        });

        nameInput?.addEventListener('input', () => {
            if (!preview.querySelector('img')) {
                setPlaceholder(fileName.textContent);
            }
        });

        window.addEventListener('beforeunload', stopCamera);

        if (existingImageUrl && !removeCheckbox?.checked) {
            setImagePreview(existingImageUrl, 'Photo actuelle du produit', 'Image deja enregistree sur ce produit.');
        } else {
            setPlaceholder();
        }
    })();
</script>

