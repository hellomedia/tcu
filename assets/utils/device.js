// For more comprehensive user agent parsing, use 'ua-parser-js'
// import { UAParser } from 'ua-parser-js';
// const { device } = UAParser(window.navigator.userAgent);
// console.log('is mobile check : ', device.is('mobile'));

/**
 * Checks if the device supports touch input.
 * Runs dynamically each time, so if hardware changes it stays accurate.
 */
export function isTouchDevice() {
    return navigator.maxTouchPoints > 0;
}

/**
 * Checks if device is likely a mobile device.
 * Combines touch capability, screen size, and user agent.
 */
export function isMobileDevice() {
    const touch = navigator.maxTouchPoints > 0;
    const smallScreen = window.innerWidth < 768; // adjust breakpoint if needed
    const userAgentMobile = /Mobi|Android/i.test(navigator.userAgent);

    return touch && (smallScreen || userAgentMobile);
}
