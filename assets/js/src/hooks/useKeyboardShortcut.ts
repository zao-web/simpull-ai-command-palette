import { useEffect, DependencyList } from 'react';

export const useKeyboardShortcut = (shortcut: string, callback: () => void, deps: DependencyList = []) => {
    useEffect(() => {
        if (!shortcut) return;
        const shortcuts = shortcut.split(',').map(s => s.trim());

        const handleKeyDown = (event: KeyboardEvent) => {
            // Don't trigger if user is typing in an input/textarea
            const target = event.target as HTMLElement;
            if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.contentEditable === 'true') {
                return;
            }

            for (const shortcutKey of shortcuts) {
                const parts = shortcutKey.toLowerCase().split('+');
                const key = parts[parts.length - 1];
                const modifiers = parts.slice(0, -1);

                const isCtrl = modifiers.includes('ctrl');
                const isCmd = modifiers.includes('cmd');
                const isShift = modifiers.includes('shift');
                const isAlt = modifiers.includes('alt');

                const ctrlOrCmd = (event.ctrlKey || event.metaKey);

                if (
                    event.key.toLowerCase() === key &&
                    ((isCtrl && event.ctrlKey) || (isCmd && event.metaKey) || (!isCtrl && !isCmd && ctrlOrCmd)) &&
                    (!isShift || event.shiftKey) &&
                    (!isAlt || event.altKey)
                ) {
                    event.preventDefault();
                    callback();
                    break;
                }
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [shortcut, callback, ...deps]);
};