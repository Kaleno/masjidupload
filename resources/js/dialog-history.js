/*
 * Dialogs push a history entry while open so the phone's back button closes
 * them instead of leaving the page. The entry reuses Turbo's state. This
 * module must be imported before Turbo so its popstate listener runs first
 * and can hide these pops from Turbo.
 */
const layers = [];
let skipNextPopstate = false;

window.addEventListener('popstate', (event) => {
    if (skipNextPopstate) {
        skipNextPopstate = false;
        event.stopImmediatePropagation();

        return;
    }

    const layer = layers.pop();

    if (layer) {
        event.stopImmediatePropagation();
        layer.onBack();
    }
}, true);

export function hasOpenDialogs() {
    return layers.length > 0;
}

export function forgetDialogs() {
    layers.length = 0;
}

/**
 * @param {() => void} onBack
 * @returns {{ close: () => void, forget: () => void }}
 */
export function openDialogLayer(onBack) {
    const layer = { onBack };

    layers.push(layer);
    history.pushState(history.state, '', window.location.href);

    return {
        close() {
            const index = layers.indexOf(layer);

            if (index === -1) {
                return;
            }

            layers.splice(index, 1);
            skipNextPopstate = true;
            history.back();
        },

        forget() {
            const index = layers.indexOf(layer);

            if (index !== -1) {
                layers.splice(index, 1);
            }
        },
    };
}
