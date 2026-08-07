import { Controller } from '@hotwired/stimulus';

/*
 * Handles the dynamic "add/remove gallery image" rows of the recipe form.
 *
 * A row removed here is simply not submitted, which is how Symfony's
 * allow_delete recognises that the image is gone — see RecipeImageUpdater for
 * what happens to the file behind it.
 *
 * Lives in a Stimulus controller for the same reason as recipe_form_controller:
 * Turbo swaps the body without a reload, so DOMContentLoaded never fires again.
 */
export default class extends Controller {
    connect() {
        this.list = this.element.querySelector('#gallery-list');

        const addImageBtn = this.element.querySelector('#add-image-btn');
        if (addImageBtn) {
            addImageBtn.addEventListener('click', this.addImage);
        }

        // Wire up the rows rendered server-side (edit mode).
        this.list.querySelectorAll('.gallery-item').forEach((itemEl) => this.wireRemoveButton(itemEl));
    }

    wireRemoveButton(itemEl) {
        itemEl.querySelector('.remove-image-btn')
            .addEventListener('click', () => itemEl.remove());
    }

    addImage = () => {
        const index = parseInt(this.element.dataset.index || '0');
        this.element.dataset.index = index + 1;

        const tmpl = document.getElementById('image-template');
        const clone = document.importNode(tmpl.content, true);
        const itemEl = clone.querySelector('.gallery-item');

        itemEl.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name.replace(/__image__/g, index);
        });
        itemEl.querySelectorAll('[id]').forEach((el) => {
            el.id = el.id.replace(/__image__/g, index);
        });

        this.wireRemoveButton(itemEl);
        this.list.appendChild(itemEl);
    };
}
