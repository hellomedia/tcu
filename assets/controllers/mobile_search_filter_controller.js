import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["container", "menu", "backdrop"];

    connect() {
        this.containerTarget.style.zIndex = -100;

        // when turbo restore page from cache (and runs connect() ),
        // it seems that the snapshot has the drawer open
        this.resetCachedDrawer();

        this.scrollableDiv = this.menuTarget;

        this.DRAG_THRESHOLD = 10;
        this.SWIPE_THRESHOLD = 50;
    }

    disconnect() {
        if (this.isOpen()) {
            this.close();
        }
    }

    resetCachedDrawer() {
        if (this.isOpen()) {
            this.close();
        }        
    }

    isOpen() {
        return this.containerTarget.classList.contains('open');
    }

    toggle() {
        if (this.isOpen()) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {

        // move filter form inside mobile menu
        const form = document.getElementById("filter-form");
        const mobileContainer = document.getElementById("filter-form-mobile-container");

        if (form && mobileContainer) {
            mobileContainer.appendChild(form);
        }

        // CSS transitions
        this.containerTarget.classList.add("open");

        // change container z-index at beginning of the open transition
        this.containerTarget.style.zIndex = 500;

        document.body.classList.add('scroll-lock');
        // document.documentElement = <html>
        document.documentElement.classList.add('scroll-lock');
    }

    close() {

        // CSS transitions
        this.containerTarget.classList.remove("open");

        // change container z-index at the end of the close transition
        setTimeout(() => {
            this.containerTarget.style.zIndex = -100;

            // move filter form back to sidebar
            const form = document.getElementById("filter-form");
            const sidebarContainer = document.getElementById("filter-form-sidebar-container");

            if (form && sidebarContainer) {
                sidebarContainer.appendChild(form);
            }
        }, 300);
        
        document.body.classList.remove('scroll-lock');
        // document.documentElement = <html>
        document.documentElement.classList.remove('scroll-lock');
    }

    closeOnBackdropClick(e) {
        // only process click events on the backdrop - not propagated to it
        if (e.target == this.backdropTarget) {
            this.close();
        }
    }

    /**
     * ===============================
     * DRAGGABLE / SWIPEABLE BEHAVIOUR
     * ===============================
     * 
     * Menu sliding in-out from side of screen.
     * 
     * Allows user to drag right and swipe to close the menu.
     * Allows user to click on backdrop to close the menu.
     * Allows user to click on items inside the menu.
     * Allows user to scroll vertically inside the menu.
     * If scrolling, locks drag until the end of gesture.
     * If dragging/swiping, locks scroll until the end of gesture.
     * Swipe can take effect at the end of gesture or before (optionally), when SWIPE_THRESHOLD is reached
     */

    handleGestureStart(e) {

        // DO NOT prevent OS/browser's defaults like clicking on form element
        // https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
        // e.preventDefault();

        e.stopPropagation();

        if (e.touches.length > 1) {
            // do not support more than 1 currently active touch on screen (double fingers, etc.)
            return;
        }

        this.isScrolling = false; /* y axis gesture */
        this.isDragging = false; /* x axis gesture */
        this.startScroll = this.scrollableDiv.scrollTop;

        // remove transition for smooth drag
        this.menuTarget.style.transition = 'none';

        this.startTouchPoint = this.getGesturePointFromEvent(e);
        this.startTime = performance.now();

        // only declare one animation frame request at a time
        this.animationFrameRequested = false;
        // At gesture end, ignore pending request animation frame
        this.ignorePendingRequestAnimationFrame = false;
        // initialize and/or reset
        this.deltaX = 0;
        this.deltaY = 0;

        // Events must be linked to the methods with the 'this' binding
        this.boundHandleGestureMove = this.handleGestureMove.bind(this);
        this.boundHandleGestureEnd = this.handleGestureEnd.bind(this);

        // For cross-browser compatibility, set 'passive' option to false if you call e.preventDefault() in listener
        // https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
        this.menuTarget.addEventListener('touchmove', this.boundHandleGestureMove, { capture: true, passive: false });
        this.menuTarget.addEventListener('touchend', this.boundHandleGestureEnd, true);
        this.menuTarget.addEventListener('touchcancel', this.boundHandleGestureEnd, true);
    }

    /**
     * For performance, keep this method as lean as possible 
     * (it is called on every touchmove event!)
     * and defer logic to onAnimationFrame(), 
     * which runs at intervals - and not on the main thread
     */
    handleGestureMove(e) {

        // DO NOT prevent OS/browser's defaults to allow scrolling *inside* the menu
        // e.preventDefault();

        e.stopPropagation();

        // only declare one animation frame request at a time
        if (!this.animationFrameRequested) {
            // arrow fonctions bind 'this' to the class automatically
            window.requestAnimationFrame(() => {
                this.onAnimationFrame(e);
                this.animationFrameRequested = false;
            });
            this.animationFrameRequested = true;
        }
    }

    onAnimationFrame(e) {

        if (this.ignorePendingRequestAnimationFrame) {
            return;
        }

        const currentTouchPoint = this.getGesturePointFromEvent(e);

        this.deltaX = currentTouchPoint.x - this.startTouchPoint.x; // keep sign for dragzone boundary test
        this.deltaY = currentTouchPoint.y - this.startTouchPoint.y;

        if (this.isDragging == false && this.isScrolling == false && this.scrollableDiv.scrollTop != 0 && Math.abs(this.deltaY) > Math.abs(this.deltaX)) {
            // isScrolling locks x-axis dragging until the end of gesture
            this.isScrolling = true;
        }

        // if scrolling, ignore drag until end of scrolling
        if (this.isScrolling) {
            return;
        }

        // if not a scrolling scenario, force no scrolling
        // NB: browser handles this natively BUT ...
        // since we use DRAG_THRESHOLD below, we might have a gesture close to 45% a bit more towards the x-axis
        // that does not trigger drag directly, nor scroll
        // and if we let it trigger scroll later, jumps to the x-value of the threshold
        this.scrollableDiv.scrollTop = this.startScroll;

        // ignore dragging outside of dragzone
        if (this.deltaX < 0) {
            return;
        }

        if (this.isDragging == false && Math.abs(this.deltaX) > Math.abs(this.deltaY) + this.DRAG_THRESHOLD) {
            // isDragging locks y-axis scrolling until the end of gesture
            this.isDragging = true;
        }

        if (this.isDragging) {
            // Assumes translateX = 0 for menu open position
            this.menuTarget.style.transform = 'translateX(' + this.deltaX + 'px)';

            // Optional -- not necessarily the best UX
            // if (this.deltaX > this.SWIPE_THRESHOLD) {
            //     this.handleGestureEnd(e);
            // }
        }

    }

    handleGestureEnd(e) {

        // DO NOT prevent browser/OS default to allow for clicks on menu links
        // e.preventDefault();

        e.stopPropagation();

        // Ignore pending request animation frame
        // which would set inline style translateY and override ending animation
        this.ignorePendingRequestAnimationFrame = true;

        if (this.isScrolling) {
            return;
        }

        // restore transition for smooth snapping
        this.menuTarget.style.transition = 'all 0.2s ease-out';

        const endTime = performance.now();
        const elapsedTime = endTime - this.startTime;
        const endTouchPoint = this.getGesturePointFromEvent(e);

        this.deltaY = endTouchPoint.y - this.startTouchPoint.y;

        if (this.deltaX > this.SWIPE_THRESHOLD && elapsedTime < 500 && this.isDragging) {
            // remove transform inline style to let class styles handle transform
            this.menuTarget.style.transform = '';
            // close menu
            this.close();
        } else {
            // snap back to open position
            this.menuTarget.style.transform = '';
        }

        // after ending animation,
        // remove inline transition style to let class styles handle transition
        setTimeout(() => {
            this.menuTarget.style.transition = '';
        }, 300);

        this.menuTarget.removeEventListener('touchmove', this.boundHandleGestureMove, true);
        this.menuTarget.removeEventListener('touchend', this.boundHandleGestureEnd, true);
        this.menuTarget.removeEventListener('touchcancel', this.boundHandleGestureEnd, true);

    }

    getGesturePointFromEvent(e) {
        var point = {};

        if (e.type == 'touchend') {
            point.x = e.changedTouches[0].clientX;
            point.y = e.changedTouches[0].clientY;

            return point;
        }

        point.x = e.targetTouches[0].clientX;
        point.y = e.targetTouches[0].clientY;

        return point;
    }

}