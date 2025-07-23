import { createRoot, createElement } from '@wordpress/element';
import CommandPalette from '../assets/js/src/components/CommandPalette';
import '../assets/js/src/styles/command-palette.css';

declare global {
    interface Window {
        aicpData: {
            apiUrl: string;
            nonce: string;
            currentUser: {
                id: number;
                role: string;
                capabilities: Record<string, boolean>;
            };
            context: any;
            staticCommands: any[];
            settings: {
                api_provider: string;
                enable_frontend: boolean;
                enable_personalization: boolean;
                keyboard_shortcut: string;
            };
        };
        AICP: {
            init: () => void;
        };
        openAICPPalette?: () => void;
        wp?: any;
        aicpPaletteShouldOpen?: boolean;
    }
}

// Initialize the command palette
const init = () => {
    console.log('init AICP');
    const container = document.getElementById('aicp-command-palette-root');
    if (container) {
        console.log('AICP has container', container);
        const root = (window.wp?.element?.createRoot ?? createRoot)(container);
        // Always mount CommandPalette
        root.render(createElement(CommandPalette));
        console.log('AICP has root', root);
    }
};

// Always set global openAICPPalette function and state
window.aicpPaletteShouldOpen = false;
window.openAICPPalette = () => {
    window.aicpPaletteShouldOpen = true;
    // Dispatch a custom event that CommandPalette listens for
    window.dispatchEvent(new Event('openAICPPalette'));
    console.log('openAICPPalette');
};

window.AICP = window.AICP || {};
window.AICP.init = init;

// Export for global access
export { init };