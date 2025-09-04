import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["menu", "buttonAnimation", "buttonPath"];

    openMenuPath = "M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"; // Open icon
    closeMenuPath = "M6 18L18 6M6 6l12 12"; // Close icon

    connect() {
        this.scrollableDiv = this.menuTarget;
    }

    disconnect() {
        if (this.isOpen()) {
            this.close();
        }
    }

    isOpen() {
        return this.menuTarget.classList.contains('open');
    }

    toggle() {
        if (this.isOpen()) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        // if we lock scrolling on the main window,
        // we need to scroll to top first to see the close button in top menu
        window.scrollTo({
            top: 0,
            behaviour: 'smooth' // -- or set in css: html {scroll-bahaviour:smooth}
        });

        // Button SVG animation
        this.buttonAnimationTarget.setAttribute('from', this.openMenuPath);
        this.buttonAnimationTarget.setAttribute('to', this.closeMenuPath);
        this.buttonAnimationTarget.beginElement(); // Start the animation

        // Button SVG animation
        this.buttonPathTarget.style.transform = 'rotate(360deg)';
        
        // Menu CSS transition
        this.menuTarget.classList.add("open");

        document.body.classList.add('scroll-lock');
        // document.documentElement = <html>
        document.documentElement.classList.add('scroll-lock');
    }

    close() {

        // Button SVG animation
        this.buttonAnimationTarget.setAttribute('from', this.closeMenuPath);
        this.buttonAnimationTarget.setAttribute('to', this.openMenuPath);
        this.buttonAnimationTarget.beginElement(); // Start the animation

        // Button SVG animation
        this.buttonPathTarget.style.transform = 'rotate(0deg)';

        // Menu CSS transition
        this.menuTarget.classList.remove("open");
        
        document.body.classList.remove('scroll-lock');
        // document.documentElement = <html>
        document.documentElement.classList.remove('scroll-lock');
    }

    /**
     * ===============================
     * DRAGGABLE / SWIPEABLE BEHAVIOUR
     * ===============================
     * 
     * Lets the user drag down and swipe the menu
     * 
     *  Usage:
     * 	<div id="mobile-menu" data-mobile-menu-target="menu" data-action="touchstart->mobile-menu#handleGestureStart"></div>
     */

    handleGestureStart(e) {

        // DO NOT prevent OS/browser's defaults to allow click on menu links
        // e.preventDefault();

        e.stopPropagation();

        if (e.touches.length > 1) {
            // do not support more than 1 currently active touch on screen (double fingers, etc.)
            return;
        }

        this.startScrollTop = this.scrollableDiv.scrollTop;

        // remove transition for smooth drag
        this.menuTarget.style.transition = 'none';

        this.startTouchPoint = this.getGesturePointFromEvent(e);
        this.resetStartTouchPoint = false;
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
        // Scrolling *outside* the menu is disabled by 'scroll-lock' class on <html> and <body>
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

        // ignore drag if scrolling
        if (this.scrollableDiv.scrollTop != 0) {
            // reset startTouchPoint when scrolled back to top
            this.resetStartTouchPoint = true;
            return;
        }

        if (this.resetStartTouchPoint) {
            this.startTouchPoint = this.getGesturePointFromEvent(e);
            this.resetStartTouchPoint = false;
        }
    
        const currentTouchPoint = this.getGesturePointFromEvent(e);

        this.deltaY = currentTouchPoint.y - this.startTouchPoint.y;

        // ignore drag outside of dragzone
        if (this.deltaY < 0) {
            return;
        }

        // Drag
        // Assumes menu translateY = O at open position
        this.menuTarget.style.transform = 'translateY(' + this.deltaY + 'px)';

    }

    handleGestureEnd(e) {

        // DO NOT prevent browser/OS default to allow for clicks on menu links
        // e.preventDefault();

        e.stopPropagation();

        // Ignore pending request animation frame
        // -- which would set inline style translateY and override ending animation
        this.ignorePendingRequestAnimationFrame = true;

        // restore transition for smooth snapping
        this.menuTarget.style.transition = 'all 0.3s ease-out';

        const endTime = performance.now();
        const elapsedTime = endTime - this.startTime;
        const endTouchPoint = this.getGesturePointFromEvent(e);

        this.deltaX = endTouchPoint.x - this.startTouchPoint.x;

        if (this.deltaY > 50 && elapsedTime < 500 && Math.abs(this.deltaY) > (Math.abs(this.deltaX) + 50)) {
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