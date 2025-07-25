import React, { useEffect, useRef } from 'react';
import { Command } from '../types';

interface CommandListProps {
    commands: Command[];
    onCommandSelect: (command: Command) => void;
    selectedIndex: number;
    onHoverIndex?: (index: number) => void;
}

const CommandList: React.FC<CommandListProps> = ({ commands, onCommandSelect, selectedIndex, onHoverIndex }) => {
    const itemRefs = useRef<(HTMLLIElement | null)[]>([]);

    useEffect(() => {
        if (itemRefs.current[selectedIndex]) {
            itemRefs.current[selectedIndex]?.focus();
        }
    }, [selectedIndex]);

    if (!commands.length) {
        return <div className="aicp-no-results">No results found.</div>;
    }

    return (
        <>
            <ul className="aicp-command-list" role="listbox" aria-activedescendant={commands[selectedIndex]?.id}>
                {commands.map((command, idx) => {
                    const selected = idx === selectedIndex;
                    return (
                        <li
                            key={command.id}
                            id={command.id}
                            ref={el => itemRefs.current[idx] = el}
                            className={`aicp-command-item${selected ? ' aicp-command-item--selected' : ''}`}
                            onClick={() => onCommandSelect(command)}
                            onMouseEnter={() => onHoverIndex && onHoverIndex(idx)}
                            onMouseLeave={() => onHoverIndex && onHoverIndex(-1)}
                            role="option"
                            aria-selected={selected}
                            tabIndex={selected ? 0 : -1}
                        >
                            <span className={`dashicons ${command.icon || 'dashicons-admin-generic'}`}></span>
                            <span className="aicp-command-title" dangerouslySetInnerHTML={{ __html: command.title }}></span>
                            <span className="aicp-command-category">{command.category}</span>
                        </li>
                    );
                })}
            </ul>
            {/* Live region for screen readers announcing the selected command */}
            <div aria-live="polite" aria-atomic="true" className="screen-reader-text" id="aicp-commandlist-live">
                {commands[selectedIndex] && `${commands[selectedIndex].title}. ${commands[selectedIndex].description || ''}`}
            </div>
        </>
    );
};

export default CommandList;