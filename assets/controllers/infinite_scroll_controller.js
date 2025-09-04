import { Controller } from "@hotwired/stimulus"

/** 
 * Infinite scroll WITH TURBO
 */
export default class extends Controller {

    static values = {
        nextPageUrl: String 
    }

    connect() {
        // we can already be at the end of scroll if:
        //   - only 1 page
        //   - come from back button with scroll position at the "last page" part of loaded items
        this.endInfiniteScroll = !this.hasNextPageUrlValue || this.nextPageUrlValue == '';

        // debounce scroll
        this.scrollTimer = null
        this.loading = false

        window.addEventListener('scroll', this.onScroll)
        document.addEventListener('turbo:before-stream-render', this._turboStreamListener)

        this.onScroll(); // initial check for loading next content
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll)
        document.removeEventListener('turbo:before-stream-render', this._turboStreamListener)
    }

    onScroll = () => {
        if (this.scrollTimer || this.loading || this.endInfiniteScroll) return

        // Added a debounce / timer in addition to the loading flag
        // Loading flag by itself wasn't preventing some duplicate calls to loadMore()
        this.scrollTimer = setTimeout(() => {
            this.scrollTimer = null

            // NB: this logic only works for a turbo setup
            const scrollPosition = window.innerHeight + window.scrollY
            // with Turbo, document.bodyoffsetHeight is increased
            // at every ajax call.
            // Without Turbo, it stays constant
            const threshold = document.body.offsetHeight - 1000

            if (scrollPosition >= threshold) {
                this.loadMore()
            }
        }, 150) // ms delay between triggers
    }

    async loadMore() {
        this.loading = true
        
        const url = new URL(this.nextPageUrlValue, window.location.origin);

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'text/vnd.turbo-stream.html',
                    'X-Turbo-Stream-Request': 'true' // custom marquor for stream response detection in controller
                }
            })

            const html = await response.text()
            Turbo.renderStreamMessage(html)
        
            // this.endInfiniteScroll was just updated by 'before-turbo-stream' listener
            if (this.endInfiniteScroll) {
                this.nextPageUrlValue = undefined // stimulus way to remove the value and data attribute
            } else {
                this._updateNextPageUrlValue(url)
            }

        } catch (error) {
            console.error('Infinite scroll failed:', error)
        } finally {
            this.loading = false
        }
    }

    _updateNextPageUrlValue(url) {
        const currentPath = url.pathname; // e.g. '/foo/bar' or '/foo/bar/2'

        // Split the path into segments:
        const segments = currentPath.split('/').filter(Boolean); // removes empty segments

        // Check if the last segment is a number (page):
        const lastSegment = segments[segments.length - 1];
        const pageNumber = parseInt(lastSegment, 10);

        if (!isNaN(pageNumber)) {
            // Last segment is a page number — increment it:
            segments[segments.length - 1] = (pageNumber + 1).toString();
        } else {
            // No page number segment yet — assume we want to go to page 2:
            segments.push('2');
        }

        // Rebuild the path:
        url.pathname = '/' + segments.join('/');

        // Preserve query parameters:
        this.nextPageUrlValue = url.toString();
    }

    _turboStreamListener = (event) => {

        const template = event.target.querySelector('template')
        const marker = template?.content?.querySelector('[data-infinite-scroll-end]')
        
        if (marker) {
            this.endInfiniteScroll = true
        }
    }
}
