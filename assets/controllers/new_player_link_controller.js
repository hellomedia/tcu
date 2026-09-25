import { Controller } from "@hotwired/stimulus";

/**
 * Lien "Créer un nouveau joueur" sous l'autocomplete du formulaire "Nouvelle inscription"
 * (RegistrationCrudController) : reprend le nom tapé dans l'autocomplete
 * pour pré-remplir le formulaire du nouveau joueur (paramètre d'URL "nom").
 *
 * Le texte est mémorisé au fil de la frappe : TomSelect vide son champ de recherche
 * dès qu'il perd le focus, donc avant le clic sur le lien.
 */
export default class extends Controller {

    static values = { param: { type: String, default: 'nom' } };

    connect() {
        this.typed = '';
        this.form = this.element.closest('form');
        this.onInput = (event) => {
            if (event.target.matches('.ts-wrapper input')) {
                this.typed = event.target.value.trim();
            }
        };
        this.form?.addEventListener('input', this.onInput);
    }

    disconnect() {
        this.form?.removeEventListener('input', this.onInput);
    }

    follow(event) {
        if (!this.typed) {
            return;
        }

        event.preventDefault();
        const url = new URL(this.element.href, window.location.href);
        url.searchParams.set(this.paramValue, this.typed);
        window.location.assign(url.toString());
    }

}
