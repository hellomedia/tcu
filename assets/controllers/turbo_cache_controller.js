import { Controller } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";

export default class extends Controller {

    clearCache(event) {
        Turbo.cache.clear();
    }
}
