import { Controller } from '@hotwired/stimulus';

/*
 * Opens the recipe gallery full screen.
 *
 * The slider on the page shows the images small and side by side; this is the
 * way to actually look at one. Sources and captions are read from the slider
 * markup, so the gallery is described in one place only.
 *
 * The overlay is appended to <body> rather than to the slider: the slider is a
 * horizontally scrolling box, and a child of it would be trapped inside that
 * scroll area.
 */
export default class extends Controller {
    connect() {
        this.images = Array.from(this.element.querySelectorAll('.gallery-image'))
            .map((el) => ({ src: el.src, caption: el.dataset.caption || '' }));

        this.onKeydown = this.onKeydown.bind(this);
    }

    // Turbo swaps the body without unloading the page, so an overlay left
    // behind would survive onto the next one.
    disconnect() {
        this.close();
    }

    open(event) {
        this.index = parseInt(event.params.index || '0');

        if (!this.overlay) {
            this.overlay = this.buildOverlay();
            document.body.appendChild(this.overlay);
            document.addEventListener('keydown', this.onKeydown);
            // Keeps the page behind from scrolling away under the overlay.
            document.body.classList.add('overflow-hidden');
        }

        this.render();
    }

    close() {
        if (!this.overlay) return;

        document.removeEventListener('keydown', this.onKeydown);
        document.body.classList.remove('overflow-hidden');
        this.overlay.remove();
        this.overlay = null;
    }

    step(offset) {
        this.index = (this.index + offset + this.images.length) % this.images.length;
        this.render();
    }

    render() {
        const image = this.images[this.index];

        this.overlayImage.src = image.src;
        this.overlayImage.alt = image.caption;
        this.overlayCaption.textContent = image.caption;
        this.overlayCounter.textContent = `${this.index + 1} / ${this.images.length}`;
    }

    onKeydown(event) {
        if (event.key === 'Escape') this.close();
        if (event.key === 'ArrowRight') this.step(1);
        if (event.key === 'ArrowLeft') this.step(-1);
    }

    buildOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/90 p-4 backdrop-blur-sm';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <button type="button" data-role="close" aria-label="Schließen"
                    class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-2xl leading-none text-white transition-colors hover:bg-white/20">&times;</button>
            <img data-role="image" class="max-h-[80vh] max-w-full rounded-2xl object-contain" alt="">
            <p data-role="caption" class="mt-4 max-w-2xl text-center text-sm text-white/80"></p>
            <p data-role="counter" class="mt-1 text-xs text-white/50"></p>
            <button type="button" data-role="prev" aria-label="Vorheriges Bild"
                    class="absolute left-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-xl text-white transition-colors hover:bg-white/20">&#8249;</button>
            <button type="button" data-role="next" aria-label="Nächstes Bild"
                    class="absolute right-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-xl text-white transition-colors hover:bg-white/20">&#8250;</button>
        `;

        this.overlayImage = overlay.querySelector('[data-role="image"]');
        this.overlayCaption = overlay.querySelector('[data-role="caption"]');
        this.overlayCounter = overlay.querySelector('[data-role="counter"]');

        overlay.querySelector('[data-role="close"]').addEventListener('click', () => this.close());
        overlay.querySelector('[data-role="prev"]').addEventListener('click', () => this.step(-1));
        overlay.querySelector('[data-role="next"]').addEventListener('click', () => this.step(1));

        // A click that lands next to the image, not on it or on a button.
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) this.close();
        });

        const singleImage = this.images.length < 2;
        overlay.querySelector('[data-role="prev"]').hidden = singleImage;
        overlay.querySelector('[data-role="next"]').hidden = singleImage;
        this.overlayCounter.hidden = singleImage;

        return overlay;
    }
}
