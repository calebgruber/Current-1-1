/**
 * Synthesised feedback tones (WebAudio) – no audio files required, works offline.
 * success: bright rising two-tone chirp · warning: double mid beep · error: low buzz.
 */
let ctx: AudioContext | null = null;
const KEY = 'stagetrack.sound';

function audio(): AudioContext | null {
    if (typeof window === 'undefined') return null;
    const Ctor = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!Ctor) return null;
    ctx ??= new Ctor();
    if (ctx.state === 'suspended') void ctx.resume();
    return ctx;
}

function tone(freq: number, start: number, duration: number, type: OscillatorType = 'sine', volume = 0.25) {
    const c = audio();
    if (!c) return;
    const osc = c.createOscillator();
    const gain = c.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, c.currentTime + start);
    gain.gain.setValueAtTime(0.0001, c.currentTime + start);
    gain.gain.exponentialRampToValueAtTime(volume, c.currentTime + start + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + start + duration);
    osc.connect(gain).connect(c.destination);
    osc.start(c.currentTime + start);
    osc.stop(c.currentTime + start + duration + 0.02);
}

export type SoundName = 'success' | 'warning' | 'error';

export function useSound() {
    const enabled = () => localStorage.getItem(KEY) !== 'off';
    const setEnabled = (on: boolean) => localStorage.setItem(KEY, on ? 'on' : 'off');

    const play = (name: SoundName) => {
        if (!enabled()) return;
        if (name === 'success') {
            tone(880, 0, 0.09, 'sine');
            tone(1320, 0.08, 0.14, 'sine');
        } else if (name === 'warning') {
            tone(660, 0, 0.12, 'triangle', 0.3);
            tone(660, 0.18, 0.12, 'triangle', 0.3);
        } else {
            tone(180, 0, 0.35, 'square', 0.18);
            tone(140, 0.05, 0.35, 'sawtooth', 0.12);
        }
    };

    /** Browsers require a user gesture before audio can start. */
    const unlock = () => audio();

    return { play, unlock, enabled, setEnabled };
}
