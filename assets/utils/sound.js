export const SOUND_PREF_KEY = 'chat.sounds';
export const MIN_GAP_MS = 1000;

export function soundsEnabled() {
    // 'off' disables sounds; anything else (or missing) enables
    return localStorage.getItem(SOUND_PREF_KEY) !== 'off';
}

export function enableChatSounds() {
    localStorage.setItem(SOUND_PREF_KEY, 'on');
    tryUnlockAudio();
}

export function disableChatSounds() {
    localStorage.setItem(SOUND_PREF_KEY, 'off');
}

export function toggleChatSounds() {
    soundsEnabled() ? disableChatSounds() : enableChatSounds();
}

// HMR-safe, tab-global rate limit
function shouldPlayNow() {
    const now = Date.now();
    const last = window.__lastSoundPlayAt ?? 0;
    if (now - last < MIN_GAP_MS) return false;
    window.__lastSoundPlayAt = now;
    return true;
}

export function playSound(src, volume = 0.7) {
    if (!src || !soundsEnabled() || !shouldPlayNow()) return;

    const audio = new Audio(src);
    const v = Number.isFinite(volume) ? Math.max(0, Math.min(1, volume)) : 0.7;
    audio.volume = v;

    audio.play().catch(() => {
        // Browser blocked it due to autoplay policy — mark for unlock
        window.__needsSoundUnlock = true;
    });
}

// ---- Autoplay unlock helpers ----

function tryUnlockAudio() {
    // Try resuming WebAudio (some browsers unlock this way)
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (Ctx) {
            const ctx = (window.__soundCtx = window.__soundCtx || new Ctx());
            if (ctx.state === 'suspended') ctx.resume().catch(() => { });
        }
    } catch { }

    // Try a no-op Audio() play; some engines count this as a gesture-following play
    try { new Audio().play().catch(() => { }); } catch { }

    window.__needsSoundUnlock = false;
}

/**
 * Attach click/touch listeners once. They only actually unlock if a prior
 * play() failed (i.e. __needsSoundUnlock = true). We remove the listeners
 * after a successful unlock to avoid overhead.
 */
export function registerSoundUnlockOnce() {
    if (window.__soundUnlockRegistered) return;
    window.__soundUnlockRegistered = true;

    const handler = () => {
        if (!window.__needsSoundUnlock) return;
        tryUnlockAudio();
        // remove after we did an unlock attempt
        window.removeEventListener('click', handler, { capture: true });
        window.removeEventListener('touchstart', handler, { capture: true });
    };

    // Use capture so we run early in the event phase
    window.addEventListener('click', handler, { capture: true });
    window.addEventListener('touchstart', handler, { capture: true });

    // Optional: if already flagged (SPA navigation), try immediately
    if (window.__needsSoundUnlock) tryUnlockAudio();

    // (Optional) expose toggles globally for quick testing / console use
    window.enableChatSounds = enableChatSounds;
    window.disableChatSounds = disableChatSounds;
    window.toggleChatSounds = toggleChatSounds;
}
