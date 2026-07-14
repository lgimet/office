// AppState.js
class AppState {
    constructor() {
        this.currentUser = null;
        this.openWindows = new Map();
        this.activeWindowId = null;
        this.theme = 'light';
        this.loadedCss = new Set();
    }
}

export const appState = new AppState();
