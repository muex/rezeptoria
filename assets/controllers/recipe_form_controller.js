import { Controller } from '@hotwired/stimulus';

/*
 * Handles the dynamic "add/remove section" and "add/remove ingredient"
 * behaviour of the recipe form.
 *
 * This lives in a Stimulus controller (rather than an inline DOMContentLoaded
 * script) because the app uses Turbo Drive: on Turbo navigations the body is
 * swapped without a full reload, so DOMContentLoaded never fires again and the
 * buttons would silently stop working. connect() runs on every Turbo render.
 */
export default class extends Controller {
    connect() {
        this.sectionsWrapper = this.element;
        this.sectionsList = this.element.querySelector('#sections-list');

        const addSectionBtn = this.element.querySelector('#add-section-btn');
        if (addSectionBtn) {
            addSectionBtn.addEventListener('click', this.addSection);
        }

        // Wire up sections rendered server-side (edit mode).
        this.sectionsList.querySelectorAll('.section-item').forEach((sectionEl) => {
            sectionEl.querySelector('.remove-section-btn')
                .addEventListener('click', () => sectionEl.remove());
            this.setupIngredientButtons(sectionEl);
        });
    }

    setupIngredientButtons(sectionEl) {
        const ingredientsWrapper = sectionEl.querySelector('.ingredients-wrapper');
        if (!ingredientsWrapper) return;

        sectionEl.querySelector('.add-ingredient-btn')
            .addEventListener('click', () => this.addIngredient(sectionEl, ingredientsWrapper));

        sectionEl.querySelectorAll('.remove-ingredient-btn').forEach((btn) => {
            btn.addEventListener('click', () => btn.closest('.ingredient-item').remove());
        });
    }

    addIngredient(sectionEl, ingredientsWrapper) {
        const sectionIndex = sectionEl.dataset.sectionIndex;
        const ingIndex = parseInt(ingredientsWrapper.dataset.index || '0');
        ingredientsWrapper.dataset.index = ingIndex + 1;

        const tmpl = document.getElementById('ingredient-template');
        const clone = document.importNode(tmpl.content, true);
        const itemEl = clone.querySelector('.ingredient-item');

        itemEl.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name
                .replace(/__section__/g, sectionIndex)
                .replace(/__ingredient__/g, ingIndex);
        });
        itemEl.querySelectorAll('[id]').forEach((el) => {
            el.id = el.id
                .replace(/__section__/g, sectionIndex)
                .replace(/__ingredient__/g, ingIndex);
        });

        itemEl.querySelector('.remove-ingredient-btn')
            .addEventListener('click', () => itemEl.remove());

        ingredientsWrapper.querySelector('.ingredients-list').appendChild(itemEl);
    }

    addSection = () => {
        const sectionIndex = parseInt(this.sectionsWrapper.dataset.index || '0');
        this.sectionsWrapper.dataset.index = sectionIndex + 1;

        const tmpl = document.getElementById('section-template');
        const clone = document.importNode(tmpl.content, true);
        const sectionEl = clone.querySelector('.section-item');

        // Store section index for ingredient creation later.
        sectionEl.dataset.sectionIndex = sectionIndex;

        sectionEl.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name.replace(/__section__/g, sectionIndex);
        });
        sectionEl.querySelectorAll('[id]').forEach((el) => {
            el.id = el.id.replace(/__section__/g, sectionIndex);
        });
        sectionEl.querySelectorAll('[for]').forEach((el) => {
            el.htmlFor = el.htmlFor.replace(/__section__/g, sectionIndex);
        });

        sectionEl.querySelector('.remove-section-btn')
            .addEventListener('click', () => sectionEl.remove());

        this.setupIngredientButtons(sectionEl);
        this.sectionsList.appendChild(sectionEl);
    };
}
