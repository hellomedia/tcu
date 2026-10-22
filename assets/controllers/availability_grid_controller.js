import { Controller } from "@hotwired/stimulus";

/**
 * Availability grid (templates/availability/_grid.html.twig), actions on all the dates at once.
 *
 * - "Pas dispo sur les nouveaux créneaux": every date card answers its unanswered new slots
 *   (availability_date_controller#setNewNotAvailable, listening on window)
 */
export default class extends Controller {
    newNotAvailable() {
        this.dispatch("new-not-available", { target: window });
    }
}
