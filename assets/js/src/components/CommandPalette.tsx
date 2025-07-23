import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Modal, TextControl, Spinner, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { debounce } from 'lodash';
import classNames from 'classnames';
import Fuse from 'fuse.js';
import CommandList from './CommandList'; // FIX: use default import
import { useKeyboardShortcut } from '../hooks/useKeyboardShortcut';
import { Command, CommandResult } from '../types';
import VisualizationEngine, { ChartType } from './VisualizationEngine';
import WooCommerceReports from './WooCommerceReports';
import { ClientSideAI } from '../utils/ClientSideAI';
import { AIAbstraction } from '../utils/AIAbstraction';

const REPORT_TYPES = [
  { label: 'Sales', value: 'sales' },
  { label: 'Orders', value: 'orders' },
  { label: 'Top Sellers', value: 'top_sellers' },
];

const DATE_RANGES = [
  { label: 'Last 7 days', value: '7d' },
  { label: 'Last 30 days', value: '30d' },
  { label: 'Custom', value: 'custom' },
];

// Add type for window.openAICPPalette
declare global {
    interface Window {
        openAICPPalette?: () => void;
        aicpPaletteShouldOpen?: boolean;
    }
}

export const CommandPalette: React.FC = () => {
    console.log('[AICP] CommandPalette component rendered');

    // --- LOGGING: Mount ---
    useEffect(() => {
        console.log('[AICP] CommandPalette mounted');
        return () => {
            console.log('[AICP] CommandPalette unmounted');
        };
    }, []);

    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [commands, setCommands] = useState<Command[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const [aiLoading, setAiLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [dynamicCommands, setDynamicCommands] = useState<any[]>([]);
    const [dynamicLoading, setDynamicLoading] = useState(false);
    const [selectedCommand, setSelectedCommand] = useState<any | null>(null);
    const [paramValues, setParamValues] = useState<Record<string, any>>({});
    const [execResult, setExecResult] = useState<any>(null);
    const [execLoading, setExecLoading] = useState(false);
    const [execError, setExecError] = useState<string | null>(null);
    const [workflowPlan, setWorkflowPlan] = useState<any | null>(null);
    const [workflowSteps, setWorkflowSteps] = useState<any[]>([]);
    const [workflowRunning, setWorkflowRunning] = useState(false);
    const [workflowResult, setWorkflowResult] = useState<any | null>(null);
    const [workflowError, setWorkflowError] = useState<string | null>(null);
    const [contextualSuggestions, setContextualSuggestions] = useState<any[]>([]);
    const [contextualLoading, setContextualLoading] = useState(false);
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [shortcut, setShortcut] = useState<string>(() => {
        return localStorage.getItem('aicp_palette_shortcut') || (window.aicpData?.settings?.keyboard_shortcut || 'cmd+k,ctrl+k');
    });
    const [shortcutInput, setShortcutInput] = useState(shortcut);
    const [shortcutError, setShortcutError] = useState<string | null>(null);
    const [recording, setRecording] = useState(false);
    const [recordedKeys, setRecordedKeys] = useState<string[]>([]);
    const [isLoggedIn, setIsLoggedIn] = useState(!!window.aicpData?.currentUser?.id);
    const [aiPreferences, setAiPreferences] = useState(() => {
        const stored = localStorage.getItem('aicp_ai_preferences');
        return stored ? JSON.parse(stored) : { preferClientSide: true };
    });
    const [conversationHistory, setConversationHistory] = useState<any[]>([]);

    // Enhanced accessibility: Focus management
    const [focusableElements, setFocusableElements] = useState<HTMLElement[]>([]);

    // Trap focus within modal when open
    useEffect(() => {
        if (isOpen) {
            const modal = document.querySelector('.aicp-modal') as HTMLElement;
            if (modal) {
                const focusable = modal.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                ) as NodeListOf<HTMLElement>;
                setFocusableElements(Array.from(focusable));

                // Focus first element
                if (focusable.length > 0) {
                    focusable[0].focus();
                }
            }
        }
    }, [isOpen]);

    // Initialize with static commands
    useEffect(() => {
        if (window.aicpData?.staticCommands) {
            setCommands(window.aicpData.staticCommands);
        }
    }, []);

    // Load contextual suggestions only when modal is opened
    useEffect(() => {
        if (isOpen) {
            loadContextualSuggestions();
        }
    }, [isOpen]);

    // Load contextual suggestions using AI abstraction
    const loadContextualSuggestions = async () => {
        if (!window.aicpData?.context) return;

        setContextualLoading(true);
        try {
            const aiAbstraction = AIAbstraction.getInstance();
            aiAbstraction.updatePreferences(aiPreferences);

            const response = await aiAbstraction.process({
                type: 'suggestions',
                context: {
                    ...(window.aicpData?.context || {}),
                    isAdmin: window.aicpData?.context?.isAdmin,
                    currentUser: window.aicpData?.currentUser || null,
                    conversation_history: conversationHistory,
                }
            });

            if (response.success && response.data) {
                // Convert suggestions to command format
                const suggestions = response.data.map((suggestion: string, index: number) => ({
                    id: `suggestion-${index}`,
                    title: suggestion,
                    description: '',
                    category: 'suggestion',
                    icon: 'dashicons-star-filled',
                    priority: 10 - index,
                    reason: response.source === 'client' ? 'AI suggestion (client-side)' :
                           response.source === 'server' ? 'AI suggestion (server-side)' :
                           'Rule-based suggestion'
                }));
                setContextualSuggestions(suggestions);
            }
        } catch (error) {
            console.warn('Failed to load contextual suggestions:', error);
        } finally {
            setContextualLoading(false);
        }
    };

    // Helper to normalize shortcut input
    const normalizeShortcut = (input: string) => {
        return input
            .toLowerCase()
            .replace(/command/g, 'cmd')
            .replace(/control/g, 'ctrl')
            .replace(/option/g, 'alt')
            .replace(/\s+/g, '') // remove spaces
            .replace(/\++/g, '+'); // collapse multiple pluses
    };

    // Set up keyboard shortcut (normalized, settings-aware)
    useKeyboardShortcut(normalizeShortcut(shortcut), () => {
        setIsOpen(true);
    });

    // Focus input when opened
    useEffect(() => {
        if (isOpen && inputRef.current) {
            inputRef.current.focus();
        }
    }, [isOpen]);

    // --- LOGGING: query changes ---
    useEffect(() => {
        console.log('[AICP] query changed:', query);
    }, [query]);


    // --- LOGGING: selectedCommand changes ---
    useEffect(() => {
        console.log('[AICP] selectedCommand changed:', selectedCommand);
    }, [selectedCommand]);

    // --- LOGGING: errors ---
    useEffect(() => {
        if (error) console.error('[AICP] Error:', error);
    }, [error]);
    useEffect(() => {
        if (execError) console.error('[AICP] ExecError:', execError);
    }, [execError]);
    useEffect(() => {
        if (workflowError) console.error('[AICP] WorkflowError:', workflowError);
    }, [workflowError]);

    // --- LOGGING: dynamicCommands changes ---
    useEffect(() => {
        console.log('[AICP] dynamicCommands updated:', dynamicCommands);
    }, [dynamicCommands]);

    // --- LOGGING: contextualSuggestions changes ---
    useEffect(() => {
        console.log('[AICP] contextualSuggestions updated:', contextualSuggestions);
    }, [contextualSuggestions]);

    // --- LOGGING: palette open triggers ---
    useEffect(() => {
        // Query string trigger
        const params = new URLSearchParams(window.location.search);
        if (params.get('openPalette') === '1') {
            console.log('[AICP] Opening palette via query string');
            setIsOpen(true);
        }
        // On mount, check global flag
        if (window.aicpPaletteShouldOpen) {
            console.log('[AICP] Opening palette via global flag');
            setIsOpen(true);
            window.aicpPaletteShouldOpen = false;
        }
        // Custom event trigger
        const handler = () => {
            console.log('[AICP] Opening palette via custom event');
            setIsOpen(true);
            window.aicpPaletteShouldOpen = false;
        };
        window.addEventListener('openAICPPalette', handler);
        return () => {
            window.removeEventListener('openAICPPalette', handler);
        };
    }, []);

    // AI-assisted search handler using unified abstraction layer
    const handleSearch = useCallback(
        debounce(async (searchQuery: string) => {
            if (!searchQuery) {
                setCommands(window.aicpData?.staticCommands || []);
                setAiLoading(false);
                setSelectedCommand(null);
                return;
            }

            setLoading(true);
            setError(null);
            setSelectedCommand(null);

            try {
                // Local fuzzy search
                const fuse = new Fuse([
                    ...(window.aicpData?.staticCommands || []),
                    ...dynamicCommands,
                ], {
                    keys: ['title', 'description', 'keywords'],
                    threshold: 0.4
                });
                const localResults = fuse.search(searchQuery).map(result => result.item);

                // Use unified AI abstraction layer
                const aiAbstraction = AIAbstraction.getInstance();
                let aiSelected = false;
                let aiSource = 'none';

                // Only use AI for natural language queries
                if (searchQuery.split(' ').length > 2 && searchQuery.length > 10) {
                    setAiLoading(true);
                    try {
                        // Update AI preferences if they've changed
                        aiAbstraction.updatePreferences(aiPreferences);

                        // Try intent classification first
                        const intentResponse = await aiAbstraction.process({
                            type: 'intent_classification',
                            query: searchQuery,
                            context: {
                                ...(window.aicpData?.context || {}),
                                isAdmin: window.aicpData?.context?.isAdmin,
                                currentUser: window.aicpData?.currentUser || null,
                                conversation_history: conversationHistory,
                            }
                        });

                        // Save to conversation history
                        if (intentResponse.success) {
                            setConversationHistory(prev => [
                                ...prev,
                                {
                                    query: searchQuery,
                                    plan: intentResponse.data,
                                    timestamp: Date.now(),
                                }
                            ]);
                        }

                        // In handleSearch, after intent classification, try workflow planning for all queries (not just complex ones)
                        if (intentResponse.success) {
                            aiSource = intentResponse.source;
                            const intent = intentResponse.data;
                            // Try to find a command whose category or id matches the intent
                            const match = commands.find(cmd =>
                                (cmd.category && cmd.category.toLowerCase() === intent.toLowerCase()) ||
                                (cmd.id && cmd.id.toLowerCase() === intent.toLowerCase())
                            );
                            // Always try to get a workflow plan from the backend for any query
                            try {
                                const workflowResponse = await apiFetch({
                                    path: '/ai-command-palette/v1/ai-process',
                                    method: 'POST',
                                    data: {
                                        type: 'workflow_plan',
                                        query: searchQuery,
                                        context: {
                                            ...(window.aicpData?.context || {}),
                                            isAdmin: window.aicpData?.context?.isAdmin,
                                            currentUser: window.aicpData?.currentUser || null,
                                            conversation_history: conversationHistory,
                                        },
                                    },
                                });
                                if (
                                    workflowResponse &&
                                    typeof workflowResponse === 'object' &&
                                    'success' in workflowResponse &&
                                    workflowResponse.success &&
                                    'data' in workflowResponse &&
                                    workflowResponse.data &&
                                    typeof workflowResponse.data === 'object' &&
                                    'steps' in workflowResponse.data &&
                                    Array.isArray(workflowResponse.data.steps) &&
                                    workflowResponse.data.steps.length > 0
                                ) {
                                    // Save workflow plan to conversation history
                                    setConversationHistory(prev => [
                                        ...prev,
                                        {
                                            query: searchQuery,
                                            plan: workflowResponse.data,
                                            timestamp: Date.now(),
                                        }
                                    ]);
                                    // If a full schema is embedded, use it for the form
                                    if (
                                        workflowResponse.data &&
                                        typeof workflowResponse.data === 'object' &&
                                        'full_function_schema' in workflowResponse.data
                                    ) {
                                        setFullFunctionSchema((workflowResponse.data as any).full_function_schema);
                                        const initialArgs = workflowResponse.data.steps[0].arguments || {};
                                        setParamValues(initialArgs);
                                    }

                                    // Show the workflow UI
                                    setSelectedCommand({
                                        id: 'ai-workflow',
                                        title: __('AI Workflow Plan', 'ai-command-palette'),
                                        action: {
                                            type: 'ai_execute',
                                            plan: workflowResponse.data
                                        },
                                        category: 'ai',
                                        icon: 'dashicons-lightbulb',
                                    });
                                    aiSelected = true;
                                } else {
                                    // AI didn't return a valid workflow plan
                                    console.log('AI workflow planning returned no valid plan:', workflowResponse);
                                    setError(__('AI couldn\'t create a workflow for your request. Try a more specific command or use the search results below.', 'ai-command-palette'));
                                }
                            } catch (err) {
                                console.warn('AI workflow planning failed:', err);
                                setError(__('AI workflow planning is temporarily unavailable. Showing best matches below.', 'ai-command-palette'));
                            }
                            if (!aiSelected) {
                                if (match) {
                                    setSelectedCommand(match);
                                    aiSelected = true;
                                } else {
                                    // If no actionable command, show intent as a dummy command
                                    const intentCommand = {
                                        id: 'ai-intent',
                                        title: `${__('AI Intent:', 'ai-command-palette')} ${intent}`,
                                        description: `${__('Category:', 'ai-command-palette')} ${intent}`,
                                        category: 'ai',
                                        icon: 'dashicons-lightbulb',
                                        priority: 1
                                    };
                                    setCommands([intentCommand, ...localResults]);
                                    aiSelected = true;
                                }
                            }
                        }
                    } catch (aiError) {
                        console.warn('AI processing failed:', aiError);
                    } finally {
                        setAiLoading(false);
                    }
                }

                if (!aiSelected) {
                    // Fallback: show search results
                    setCommands(localResults);
                    if (aiSource === 'fallback' && searchQuery.split(' ').length > 2) {
                        setError(__('AI unavailable - showing best matches', 'ai-command-palette'));
                    }
                }
            } catch (err) {
                setError(__('Failed to search commands', 'ai-command-palette'));
            } finally {
                setLoading(false);
            }
        }, 1000), // <-- updated debounce delay to 1000ms
        [dynamicCommands, aiPreferences, conversationHistory]
    );

    // Handle query change
    const handleQueryChange = (value: string) => {
        setQuery(value);
        setSelectedIndex(0);
        handleSearch(value);
    };

    // Enhanced keyboard navigation with focus trap
    const handleKeyDown = (e: React.KeyboardEvent) => {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setSelectedIndex(prev =>
                    prev < commands.length - 1 ? prev + 1 : prev
                );
                break;
            case 'ArrowUp':
                e.preventDefault();
                setSelectedIndex(prev => prev > 0 ? prev - 1 : 0);
                break;
            case 'Enter':
                e.preventDefault();
                if (commands[selectedIndex]) {
                    handleExecute(commands[selectedIndex]);
                }
                break;
            case 'Escape':
                e.preventDefault();
                setIsOpen(false);
                break;
            case 'Tab':
                // Trap focus within modal
                if (focusableElements.length > 0) {
                    const currentIndex = focusableElements.indexOf(document.activeElement as HTMLElement);
                    if (e.shiftKey) {
                        // Shift+Tab: move backwards
                        if (currentIndex <= 0) {
                            e.preventDefault();
                            focusableElements[focusableElements.length - 1].focus();
                        }
                    } else {
                        // Tab: move forwards
                        if (currentIndex >= focusableElements.length - 1) {
                            e.preventDefault();
                            focusableElements[0].focus();
                        }
                    }
                }
                break;
        }
    };

    // Announce changes to screen readers
    const announceToScreenReader = (message: string) => {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = message;
        document.body.appendChild(announcement);

        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    };

    // Enhanced command execution with accessibility
    const handleExecute = async (command: Command) => {
        setLoading(true);
        setError(null);

        // Announce execution to screen reader
        announceToScreenReader(`Executing command: ${command.title}`);

        try {
            if (command.action?.type === 'navigate' && command.action.url) {
                announceToScreenReader('Navigating to new page');
                window.location.href = command.action.url;
            } else if (command.action?.type === 'ai_execute' && command.action.plan) {
                const response = await apiFetch({
                    path: '/ai-command-palette/v1/execute',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.aicpData?.nonce },
                    data: {
                        command: 'ai_execute',
                        params: {
                            ai_plan: command.action.plan
                        }
                    }
                });

                const result = response as CommandResult;
                if (result.success) {
                    if (result.redirect) {
                        announceToScreenReader('Command successful, redirecting');
                        window.location.href = result.redirect;
                    } else {
                        const message = result.message || __('Command executed successfully', 'ai-command-palette');
                        setError(message);
                        announceToScreenReader(message);
                        setTimeout(() => setIsOpen(false), 1500);
                    }
                } else {
                    const message = result.message || __('Command failed', 'ai-command-palette');
                    setError(message);
                    announceToScreenReader(`Error: ${message}`);
                }
            } else if (command.id) {
                const response = await apiFetch({
                    path: '/ai-command-palette/v1/execute',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.aicpData?.nonce },
                    data: {
                        command: command.id,
                        params: {}
                    }
                });

                const result = response as CommandResult;
                if (result.success) {
                    if (result.redirect) {
                        announceToScreenReader('Command successful, redirecting');
                        window.location.href = result.redirect;
                    } else {
                        const message = result.message || __('Command executed successfully', 'ai-command-palette');
                        setError(message);
                        announceToScreenReader(message);
                        setTimeout(() => setIsOpen(false), 1500);
                    }
                } else {
                    const message = result.message || __('Command failed', 'ai-command-palette');
                    setError(message);
                    announceToScreenReader(`Error: ${message}`);
                }
            }
        } catch (err) {
            console.error('Execution error:', err);
            const message = __('Failed to execute command', 'ai-command-palette');
            setError(message);
            announceToScreenReader(`Error: ${message}`);
        } finally {
            setLoading(false);
        }
    };

    // Reset state when closing
    const handleClose = () => {
        console.log('handleClose');
        setIsOpen(false);
        setQuery('');
        setCommands(window.aicpData?.staticCommands || []);
        setSelectedIndex(0);
        setError(null);
    };

    // Fetch dynamic commands on open
    useEffect(() => {
        if (!isOpen) return;
        setDynamicLoading(true);
        apiFetch({ path: '/ai-command-palette/v1/discovered-endpoints' })
            .then((endpoints: any) => {
                // Convert endpoints to dynamic command format for search
                const commands = (endpoints as any[]).map(ep => {
                    const id = 'dynamic_' + btoa(ep.route + (ep.methods ? ep.methods.join(',') : ''));
                    return {
                        id,
                        title: ep.description || ep.route,
                        description: ep.route,
                        category: ep.category || 'api',
                        icon: 'dashicons-rest-api',
                        action: {
                            type: 'dynamic_api',
                            endpoint: ep.route,
                            methods: ep.methods,
                            parameters: ep.schema?.parameters || {},
                        },
                        keywords: [ep.route, ep.description],
                        priority: 5,
                    };
                });
                setDynamicCommands(commands);
                setDynamicLoading(false);
            })
            .catch(() => setDynamicLoading(false));
    }, [isOpen]);

    // Fetch contextual suggestions on palette open
    useEffect(() => {
        if (!isOpen) return;
        setContextualLoading(true);
        apiFetch({ path: '/ai-command-palette/v1/contextual-suggestions' })
            .then((suggestions: any) => {
                // suggestions is an object: { commandId: { reason, score }, ... }
                // Map to array and sort by score
                const arr = Object.entries(suggestions as any)
                    .map(([id, meta]: any) => ({ id, ...meta }))
                    .sort((a, b) => b.score - a.score)
                    .slice(0, 5); // Top 5
                setContextualSuggestions(arr);
                setContextualLoading(false);
            })
            .catch(() => setContextualLoading(false));
    }, [isOpen]);

    // Merge static and dynamic commands for search
    useEffect(() => {
        if (window.aicpData?.staticCommands) {
            setCommands([
                ...window.aicpData.staticCommands,
                ...dynamicCommands,
            ]);
        }
    }, [dynamicCommands]);

    // Handle command selection
    const handleSelectCommand = (cmd: any) => {
        setSelectedCommand(cmd);
        setParamValues({});
        setExecResult(null);
        setExecError(null);
    };

    // Handle parameter input change
    const handleParamChange = (name: string, value: any) => {
        setParamValues(prev => ({ ...prev, [name]: value }));
    };

    // Handle dynamic command execution
    const handleDynamicExecute = async () => {
        if (!selectedCommand) return;
        setExecLoading(true);
        setExecError(null);
        setExecResult(null);
        try {
            const method = (selectedCommand.action.methods || ['GET'])[0];
            const res = await apiFetch({
                path: '/ai-command-palette/v1/execute-dynamic',
                method: 'POST',
                headers: { 'X-WP-Nonce': window.aicpData?.nonce },
                data: {
                    endpoint: selectedCommand.action.endpoint,
                    method,
                    params: paramValues,
                },
            });
            setExecResult(res as any);
        } catch (err: any) {
            setExecError(err.message || 'Failed to execute command');
        } finally {
            setExecLoading(false);
        }
    };

    // When an AI multi-step command is selected, show workflow review UI
    useEffect(() => {
        if (selectedCommand && selectedCommand.action?.type === 'ai_execute' && selectedCommand.action.plan?.steps?.length > 1) {
            setWorkflowPlan(selectedCommand.action.plan);
            setWorkflowSteps(selectedCommand.action.plan.steps.map((step: any, i: number) => ({
                ...step,
                status: 'pending',
                index: i
            })));
            setWorkflowResult(null);
            setWorkflowError(null);
        } else {
            setWorkflowPlan(null);
            setWorkflowSteps([]);
            setWorkflowResult(null);
            setWorkflowError(null);
        }
    }, [selectedCommand]);

    // Execute workflow plan step-by-step
    const handleRunWorkflow = async () => {
        if (!workflowPlan) return;
        setWorkflowRunning(true);
        setWorkflowError(null);
        setWorkflowResult(null);
        let steps = [...workflowSteps];
        let result = null;
        for (let i = 0; i < steps.length; i++) {
            steps[i].status = 'running';
            setWorkflowSteps([...steps]);
            try {
                // Ensure method is included in the step
                const stepToSend = { ...steps[i] };
                if (steps[i].method) stepToSend.method = steps[i].method;
                const response = await apiFetch({
                    path: '/ai-command-palette/v1/execute',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.aicpData?.nonce },
                    data: {
                        command: 'ai_execute',
                        params: {
                            ai_plan: {
                                steps: [stepToSend],
                                summary: workflowPlan.summary
                            }
                        }
                    }
                });
                if (typeof response === 'object' && response && (response as any).success) {
                    steps[i].status = 'success';
                    steps[i].result = response;
                } else {
                    steps[i].status = 'error';
                    steps[i].error = (response as any)?.message || 'Unknown error';
                    setWorkflowSteps([...steps]);
                    setWorkflowError((response as any)?.message || 'Workflow failed');
                    break;
                }
            } catch (err: any) {
                steps[i].status = 'error';
                steps[i].error = err.message || 'Unknown error';
                setWorkflowSteps([...steps]);
                setWorkflowError(err.message || 'Workflow failed');
                break;
            }
            setWorkflowSteps([...steps]);
        }
        setWorkflowRunning(false);
        setWorkflowResult(result);
    };

    // Workflow stepper UI
    const renderWorkflowStepper = () => (
        <div className="aicp-workflow-stepper mb-4">
            {workflowSteps.map((step, i) => (
                <div key={i} className={classNames('aicp-workflow-step', {
                    'aicp-workflow-step--pending': step.status === 'pending',
                    'aicp-workflow-step--running': step.status === 'running',
                    'aicp-workflow-step--success': step.status === 'success',
                    'aicp-workflow-step--error': step.status === 'error',
                    'aicp-workflow-step--rolled-back': step.rolled_back,
                })}>
                    <div className="aicp-workflow-step-index">{i + 1}</div>
                    <div className="aicp-workflow-step-title font-bold">{step.function}</div>
                    <div className="aicp-workflow-step-desc text-xs text-gray-500">
                        {Object.entries(step.arguments || {}).map(([k, v]) => (
                            <div key={k}>
                                <span className="font-semibold">{k}:</span> {typeof v === 'object' && v !== null && 'raw' in v ? (v as any).raw : (typeof v === 'object' && v !== null ? <pre style={{display:'inline'}}>{JSON.stringify(v, null, 2)}</pre> : String(v))}
                            </div>
                        ))}
                    </div>
                    <div className="aicp-workflow-step-status text-xs">
                        {step.status === 'pending' && 'Pending'}
                        {step.status === 'running' && 'Running...'}
                        {step.status === 'success' && 'Success'}
                        {step.status === 'error' && (
                            <span className="text-red-600">Error: {step.error}</span>
                        )}
                        {step.rolled_back && (
                            <span className="text-yellow-600 ml-2">Rolled Back</span>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );

    // Helper to get command data by ID
    const getCommandById = (id: string) => {
        return (
            (window.aicpData?.staticCommands || []).find((cmd: any) => cmd.id === id) ||
            dynamicCommands.find((cmd: any) => cmd.id === id)
        );
    };

    // Fetch shortcut from user meta if logged in, else fallback to localStorage
    useEffect(() => {
        if (settingsOpen && isLoggedIn) {
            apiFetch({ path: '/ai-command-palette/v1/user-shortcut', headers: { 'X-WP-Nonce': window.aicpData?.nonce } })
                .then((res: any) => {
                    if (res.shortcut) {
                        setShortcut(res.shortcut);
                        setShortcutInput(res.shortcut);
                    }
                });
        }
    }, [settingsOpen, isLoggedIn]);

    // Conflict detection for common browser/OS shortcuts
    const commonShortcuts = [
        'ctrl+c', 'cmd+c', 'ctrl+v', 'cmd+v', 'ctrl+x', 'cmd+x', 'ctrl+tab', 'cmd+tab',
        'ctrl+w', 'cmd+w', 'ctrl+t', 'cmd+t', 'ctrl+n', 'cmd+n', 'ctrl+shift+n', 'cmd+shift+n',
        'ctrl+shift+t', 'cmd+shift+t', 'ctrl+shift+tab', 'cmd+shift+tab', 'alt+tab', 'ctrl+alt+del',
        'cmd+q', 'ctrl+q', 'ctrl+shift+esc', 'cmd+space', 'ctrl+space', 'alt+f4', 'cmd+f4',
    ];
    const isConflict = (val: string) => {
        return commonShortcuts.includes(val.toLowerCase());
    };

    // Record shortcut logic
    useEffect(() => {
        if (!recording) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            console.log('handleKeyDown', e);
            e.preventDefault();
            // Only record when a non-modifier key is pressed
            if (["Control", "Meta", "Alt", "Shift"].includes(e.key)) {
                // Don't record yet, wait for a non-modifier key
                return;
            }
            let keys = [];
            if (e.ctrlKey) keys.push('ctrl');
            if (e.metaKey) keys.push('cmd');
            if (e.altKey) keys.push('alt');
            if (e.shiftKey) keys.push('shift');
            keys.push(e.key.toLowerCase());
            console.log('keys', keys);
            setRecordedKeys(keys);
            setShortcutInput(keys.join('+'));
            setRecording(false);
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [recording]);

    // Update shortcut in localStorage and user meta
    const handleShortcutSave = async () => {
        const normalized = normalizeShortcut(shortcutInput);
        if (!normalized.match(/^[\w+]+(\+[\w+]+)*$/i)) {
            setShortcutError('Invalid shortcut format. Example: ctrl+k or cmd+shift+p');
            return;
        }
        if (isConflict(normalized)) {
            setShortcutError('This shortcut is commonly used by your browser or OS. Please choose another.');
            return;
        }
        setShortcut(normalized);
        setShortcutInput(normalized);
        if (window.aicpData?.settings) {
            window.aicpData.settings.keyboard_shortcut = normalized;
        }
        // Dispatch event for settings page real-time update
        window.dispatchEvent(new CustomEvent('aicp-shortcut-updated', { detail: { shortcut: normalized } }));
        localStorage.setItem('aicp_palette_shortcut', normalized);
        setShortcutError(null);
        if (isLoggedIn) {
            try {
                await apiFetch({
                    path: '/ai-command-palette/v1/user-shortcut',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.aicpData?.nonce },
                    data: { shortcut: normalized },
                });
            } catch (e) {
                // Ignore error, fallback to localStorage
            }
        }
        setSettingsOpen(false);
    };
    const handleShortcutReset = () => {
        setShortcut('cmd+k,ctrl+k');
        setShortcutInput('cmd+k,ctrl+k');
        localStorage.removeItem('aicp_palette_shortcut');
        setShortcutError(null);
    };

    // Announce search results to screen reader
    useEffect(() => {
        if (commands.length > 0 && query) {
            announceToScreenReader(`Found ${commands.length} commands`);
        }
    }, [commands.length, query]);

    // Add after other useState declarations
    const [fullFunctionSchema, setFullFunctionSchema] = useState<any | null>(null);
    const [fullSchemaLoading, setFullSchemaLoading] = useState(false);

    // Type guard for object with raw property
    function hasRaw(obj: any): obj is { raw: string } {
        return obj && typeof obj === 'object' && 'raw' in obj && typeof obj.raw === 'string';
    }

    // Effect to fetch full schema when a single-step AI workflow is selected
    useEffect(() => {
        if (
            selectedCommand &&
            selectedCommand.action?.type === 'ai_execute' &&
            selectedCommand.action.plan?.steps?.length === 1
        ) {
            const step = selectedCommand.action.plan.steps[0];
            if (step && step.function) {
                setFullSchemaLoading(true);
                setFullFunctionSchema(null);
                apiFetch({
                    path: `/ai-command-palette/v1/function-schema?name=${encodeURIComponent(step.function)}`
                })
                    .then((res: any) => {
                        if (res) {
                            setFullFunctionSchema(res); // FIX: set schema directly
                        } else {
                            setFullFunctionSchema(null);
                        }
                    })
                    .catch(() => setFullFunctionSchema(null))
                    .finally(() => setFullSchemaLoading(false));
            }
        } else {
            setFullFunctionSchema(null);
            setFullSchemaLoading(false);
        }
    }, [selectedCommand]);

    // In renderFullSchemaForm, ensure we read properties from fullFunctionSchema.parameters
    const renderFullSchemaForm = () => {
        if (!fullFunctionSchema || !selectedCommand?.action?.plan?.steps?.[0]) return null;
        const step = selectedCommand.action.plan.steps[0];
        const args = step.arguments || {};
        const summary = selectedCommand.action.plan.summary;

        return (
            <form
                onSubmit={e => {
                    e.preventDefault();
                    handleFullSchemaExecute();
                }}
                aria-labelledby="full-schema-form-heading"
            >
                <h4 className="font-bold mb-2" id="full-schema-form-heading">
                    {summary || fullFunctionSchema.description || fullFunctionSchema.name}
                </h4>
                {Object.entries(args).map(([name, value]: [string, any]) => (
                    <div key={name} className="mb-2">
                        <label className="block text-sm font-medium mb-1" htmlFor={`full-param-${name}`}>{name}</label>
                        <input
                            id={`full-param-${name}`}
                            type="text"
                            value={paramValues[name] ?? (typeof value === 'object' ? JSON.stringify(value) : value)}
                            onChange={e => handleParamChange(name, e.target.value)}
                            className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
                            placeholder={name}
                        />
                    </div>
                ))}
                <Button
                    type="submit"
                    isBusy={execLoading}
                    disabled={execLoading}
                    className="aicp-btn mt-2"
                    aria-describedby="full-execute-help"
                >
                    {__('Confirm & Execute', 'ai-command-palette')}
                </Button>
                <div id="full-execute-help" className="sr-only">
                    {__('Press Enter or click to execute this command', 'ai-command-palette')}
                </div>
            </form>
        );
    };

    // Handle execution for full schema form
    const handleFullSchemaExecute = async () => {
        if (!selectedCommand || !selectedCommand.action?.plan?.steps?.[0] || !fullFunctionSchema) return;
        setExecLoading(true);
        setExecError(null);
        setExecResult(null);
        try {
            // Transform paramValues to match schema: only send string for string fields, and JSON for object fields
            const origStep = selectedCommand.action.plan.steps[0];
            const step = { ...origStep };
            const newArgs: Record<string, any> = {};
            for (const [name, value] of Object.entries(paramValues)) {
                const paramType = fullFunctionSchema?.parameters?.[name]?.type;
                if (paramType === 'object') {
                    // Try to parse JSON, fallback to string
                    try {
                        newArgs[name] = typeof value === 'string' ? JSON.parse(value) : value;
                    } catch {
                        newArgs[name] = value;
                    }
                } else {
                    // If value is an object with a raw property, use value.raw
                    if (hasRaw(value)) {
                        newArgs[name] = value.raw;
                    } else {
                        newArgs[name] = typeof value === 'string' ? value : String(value);
                    }
                }
            }
            step.arguments = newArgs;
            // Ensure method is included
            if (origStep.method) step.method = origStep.method;
            const response = await apiFetch({
                path: '/ai-command-palette/v1/execute',
                method: 'POST',
                headers: { 'X-WP-Nonce': window.aicpData?.nonce },
                data: {
                    command: 'ai_execute',
                    params: {
                        ai_plan: {
                            steps: [step],
                            summary: selectedCommand.action.plan.summary
                        }
                    }
                }
            });
            setExecResult(response as any);
        } catch (err: any) {
            setExecError(err.message || 'Failed to execute command');
        } finally {
            setExecLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <>
            {/* Main Palette Modal */}
            <Modal
                title={__('Command Palette', 'ai-command-palette')}
                onRequestClose={handleClose}
                className="aicp-modal"
                overlayClassName="aicp-overlay"
                isDismissible={true}
                shouldCloseOnClickOutside={true}
                shouldCloseOnEsc={true}
                role="dialog"
                aria-labelledby="palette-title"
                aria-describedby="palette-description"
            >
                {/* Settings Modal (nested, overlays main modal) */}
                {settingsOpen && (
                    <Modal
                        title={__('Palette Settings', 'ai-command-palette')}
                        onRequestClose={() => setSettingsOpen(false)}
                        className="aicp-modal"
                        overlayClassName="aicp-overlay"
                        isDismissible={true}
                        shouldCloseOnClickOutside={true}
                        shouldCloseOnEsc={true}
                        role="dialog"
                        aria-labelledby="settings-title"
                        aria-describedby="settings-description"
                    >
                        <div className="aicp-modal-header">
                            <div className="aicp-modal-title-row">
                                <h2 className="aicp-section-title aicp-modal-title" id="settings-title">
                                    {__('Palette Settings', 'ai-command-palette')}
                                </h2>
                            </div>
                            <div className="aicp-subtitle" id="settings-description">
                                {__('Configure AI preferences and keyboard shortcuts for the command palette', 'ai-command-palette')}
                            </div>
                        </div>
                        <div className="aicp-container">
                            <div className="aicp-settings-section mb-4">
                                <h4 className="aicp-section-title mb-2">{__('AI Preferences', 'ai-command-palette')}</h4>
                                {/* AI Status Display */}
                                {(() => {
                                    const aiAbstraction = AIAbstraction.getInstance();
                                    const status = aiAbstraction.getStatus();
                                    return (
                                        <div className="mb-3 p-3 bg-gray-50 rounded">
                                            <div className="text-sm font-medium mb-2">{__('AI Status', 'ai-command-palette')}</div>
                                            <div className="text-xs space-y-1">
                                                <div>
                                                    <span className="font-medium">{__('Browser:', 'ai-command-palette')}</span> {status.browserInfo.name} {status.browserInfo.version}
                                                </div>
                                                <div>
                                                    <span className="font-medium">{__('Client-side AI:', 'ai-command-palette')}</span>
                                                    <span className={status.clientSideAvailable ? 'text-green-600' : 'text-red-600'}>
                                                        {status.clientSideAvailable ? __('Available', 'ai-command-palette') : __('Not available', 'ai-command-palette')}
                                                    </span>
                                                </div>
                                                {status.clientSideAvailable && (
                                                    <div>
                                                        <span className="font-medium">{__('Available features:', 'ai-command-palette')}</span> {status.availableFeatures.join(', ')}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })()}
                                <label className="block text-sm font-medium mb-1">
                                    <input
                                        type="checkbox"
                                        checked={aiPreferences.preferClientSide}
                                        onChange={e => {
                                            const next = { ...aiPreferences, preferClientSide: e.target.checked };
                                            setAiPreferences(next);
                                            localStorage.setItem('aicp_ai_preferences', JSON.stringify(next));
                                            // Update AI abstraction preferences
                                            AIAbstraction.getInstance().updatePreferences(next);
                                        }}
                                        aria-describedby="client-ai-description"
                                        disabled={!AIAbstraction.getInstance().getStatus().clientSideAvailable}
                                    />{' '}
                                    {__('Prefer client-side AI (Chrome only, more private, experimental)', 'ai-command-palette')}
                                </label>
                                <div id="client-ai-description" className="text-xs text-gray-500 mt-1">
                                    {AIAbstraction.getInstance().getStatus().clientSideAvailable
                                        ? __('Use Chrome\'s built-in AI for faster, more private processing when available', 'ai-command-palette')
                                        : __('Client-side AI is not available in your browser. Update to Chrome 138+ to enable this feature.', 'ai-command-palette')
                                    }
                                </div>
                            </div>
                            <div className="aicp-settings-section mb-4">
                                <h4 className="aicp-section-title mb-2">{__('Keyboard Shortcut', 'ai-command-palette')}</h4>
                                <label htmlFor="aicp-shortcut-input" className="block text-sm font-medium mb-1">{__('Shortcut', 'ai-command-palette')}</label>
                                <div className="flex gap-2 items-center">
                                    <input
                                        id="aicp-shortcut-input"
                                        type="text"
                                        value={shortcutInput}
                                        onChange={e => setShortcutInput(e.target.value)}
                                        className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
                                        placeholder="cmd+k,ctrl+k"
                                        disabled={recording}
                                    />
                                    <Button
                                        onClick={() => {
                                            setRecording(true);
                                            setShortcutError(null);
                                        }}
                                        variant="secondary"
                                        disabled={recording}
                                    >
                                        {recording ? __('Recording...', 'ai-command-palette') : __('Record', 'ai-command-palette')}
                                    </Button>
                                </div>
                                <div className="text-xs text-gray-500 mt-1">
                                    {recording
                                        ? __('Press your desired key combination...', 'ai-command-palette')
                                        : __('Example: ctrl+k or cmd+shift+p', 'ai-command-palette')}
                                </div>
                                {shortcutError && <div className="text-red-600 mt-1">{shortcutError}</div>}
                                <div className="flex gap-2 mt-3">
                                    <Button className="aicp-btn" onClick={handleShortcutSave}>{__('Save', 'ai-command-palette')}</Button>
                                    <Button className="aicp-btn-secondary" onClick={handleShortcutReset}>{__('Reset to Default', 'ai-command-palette')}</Button>
                                </div>
                            </div>
                        </div>
                    </Modal>
                )}
                {/* Fix modal header and section title alignment */}
                <div className="aicp-modal-header">
                    <div className="aicp-modal-title-row">
                        <h2 className="aicp-section-title aicp-modal-title" id="palette-title">
                            {__('Command Palette', 'ai-command-palette')}
                        </h2>
                        <button
                            className="aicp-settings-btn"
                            onClick={() => !settingsOpen && setSettingsOpen(true)}
                            aria-label={__('Open settings', 'ai-command-palette')}
                            title={__('Settings', 'ai-command-palette')}
                            disabled={settingsOpen}
                            type="button"
                        >
                            <span className="dashicons dashicons-admin-generic"></span>
                        </button>
                    </div>
                    <div className="aicp-subtitle" id="palette-description">
                        {__('Search and execute WordPress commands using natural language or keywords', 'ai-command-palette')}
                    </div>
                </div>
                <div
                    className="aicp-container"
                    onKeyDown={handleKeyDown}
                    role="application"
                    aria-label={__('Command Palette', 'ai-command-palette')}
                >
                    {/* Recommended/Contextual Suggestions */}
                    {/* Only show 'Recommended for you' if there are actionable suggestions */}
                    {contextualSuggestions.length > 0 && !query && !selectedCommand && contextualSuggestions.some(s => getCommandById(s.id)) && (
                        <div className="aicp-contextual-suggestions mb-4">
                            <div className="aicp-section-title font-semibold mb-1" id="suggestions-heading">
                                {__('Recommended for you', 'ai-command-palette')}
                            </div>
                            {contextualLoading ? (
                                <div aria-live="polite">
                                    {React.createElement(Spinner as any)}
                                    <span className="sr-only">{__('Loading suggestions', 'ai-command-palette')}</span>
                                </div>
                            ) : (
                                <div
                                    className="flex flex-col gap-2"
                                    role="listbox"
                                    aria-labelledby="suggestions-heading"
                                >
                                    {contextualSuggestions.map((suggestion, index) => {
                                        const cmd = getCommandById(suggestion.id);
                                        if (!cmd) return null;
                                        return (
                                            <button
                                                key={cmd.id}
                                                className="aicp-suggestion-btn text-left p-2 rounded hover:bg-blue-50 focus:bg-blue-100 border border-gray-200"
                                                onClick={() => handleSelectCommand(cmd)}
                                                role="option"
                                                aria-selected="false"
                                                tabIndex={0}
                                            >
                                                <div className="font-medium">{cmd.title}</div>
                                                {cmd.description && <div className="text-xs text-gray-600">{cmd.description}</div>}
                                                <div className="text-xs text-gray-400 mt-1">
                                                    {__('Reason:', 'ai-command-palette')} {suggestion.reason}
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}
                    <div className="aicp-search">
                        <div className="aicp-input">
                            <TextControl
                                ref={inputRef}
                                value={query}
                                onChange={handleQueryChange}
                                placeholder={__('Type a command or search...', 'ai-command-palette')}
                                className="aicp-input-field"
                                aria-label={__('Search commands', 'ai-command-palette')}
                                aria-describedby="search-help"
                            />
                            <span className="aicp-search-icon-wrapper" style={{position: 'absolute', right: 16, top: '50%'}}>
                                <span
                                    className={classNames('aicp-search-icon dashicons', {
                                        'dashicons-search': !(loading || aiLoading),
                                        'dashicons-update': (loading || aiLoading),
                                        'aicp-spin': (loading || aiLoading),
                                    })}
                                    aria-hidden="true"
                                ></span>
                            </span>
                        </div>
                        <div id="search-help" className="sr-only">
                            {__('Type to search commands. Use arrow keys to navigate, Enter to execute, Escape to close.', 'ai-command-palette')}
                        </div>
                        {(loading || aiLoading || dynamicLoading) && (
                            <div className="aicp-loading" aria-live="polite" />
                        )}
                    </div>
                    <hr className="aicp-section-divider" />

                    {error && (
                        <div
                            className="aicp-error"
                            role="alert"
                            aria-live="assertive"
                        >
                            {error}
                        </div>
                    )}

                    {/* Workflow UI for multi-step AI plans */}
                    {workflowPlan && workflowSteps.length > 1 ? (
                        <div className="aicp-workflow-ui-card mt-4">
                            <h4 className="font-bold mb-2" id="workflow-heading">
                                {__('Workflow Plan', 'ai-command-palette')}
                            </h4>
                            <div className="mb-2 text-sm text-gray-600" id="workflow-description">
                                {workflowPlan.summary && !/^Handles \d+,? ?\d* requests? for/.test(workflowPlan.summary)
                                    ? workflowPlan.summary
                                    : ''}
                            </div>
                            <hr className="aicp-section-divider" />
                            <div className="mb-2 font-semibold text-base" style={{paddingLeft: '2px'}}>{__('Steps', 'ai-command-palette')}</div>
                            <div
                                role="list"
                                aria-labelledby="workflow-heading"
                                aria-describedby="workflow-description"
                            >
                                {renderWorkflowStepper()}
                            </div>
                            {!workflowRunning && !workflowResult && !workflowError && (
                                <Button
                                    onClick={handleRunWorkflow}
                                    className="mt-2"
                                    aria-describedby="workflow-description"
                                >
                                    {__('Run Workflow', 'ai-command-palette')}
                                </Button>
                            )}
                            {workflowRunning && (
                                <div aria-live="polite">
                                    {React.createElement(Spinner as any)}
                                    <span className="sr-only">{__('Workflow is running', 'ai-command-palette')}</span>
                                </div>
                            )}
                            {workflowError && (
                                <Notice
                                    status="error"
                                    isDismissible={false}
                                    className="mt-2"
                                >
                                    {workflowError}
                                </Notice>
                            )}
                            {workflowResult && (
                                <Notice
                                    status="success"
                                    isDismissible={false}
                                    className="mt-2"
                                >
                                    {__('Workflow complete!', 'ai-command-palette')}
                                </Notice>
                            )}
                            <hr className="aicp-section-divider mt-4 mb-3" />
                            <div style={{display:'flex', justifyContent:'flex-end', background:'#f8fafc', borderRadius:'8px', padding:'12px 0 0 0'}}>
                                <Button
                                    className="aicp-btn"
                                    onClick={() => setSelectedCommand(null)}
                                    aria-label={__('Back to search', 'ai-command-palette')}
                                >
                                    {__('Back to search', 'ai-command-palette')}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* If a dynamic command is selected, show parameter form */}
                            {selectedCommand && selectedCommand.action?.type === 'dynamic_api' ? (
                                <div className="aicp-dynamic-form mt-4">
                                    <h4 className="aicp-section-title mb-2" id="dynamic-form-heading">
                                        {selectedCommand.title}
                                    </h4>
                                    <form
                                        onSubmit={e => {
                                            e.preventDefault();
                                            handleDynamicExecute();
                                        }}
                                        aria-labelledby="dynamic-form-heading"
                                    >
                                        {Object.entries(selectedCommand.action.parameters).filter(([name]) => !paramValues[name]).map(([name, info]: any) => (
                                            <div key={name} className="mb-2">
                                                <label
                                                    className="block text-sm font-medium mb-1"
                                                    htmlFor={`param-${name}`}
                                                >
                                                    {name}
                                                    {info.required && <span className="text-red-500" aria-label="required">*</span>}
                                                </label>
                                                <input
                                                    id={`param-${name}`}
                                                    type={info.type === 'number' ? 'number' : 'text'}
                                                    value={paramValues[name] || ''}
                                                    onChange={e => handleParamChange(name, e.target.value)}
                                                    className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
                                                    placeholder={info.description || name}
                                                    required={!!info.required}
                                                    aria-describedby={`param-${name}-help`}
                                                />
                                                {info.description && (
                                                    <div id={`param-${name}-help`} className="text-xs text-gray-500 mt-1">
                                                        {info.description}
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                        {Object.entries(selectedCommand.action.parameters).every(
                                            ([name]) => paramValues[name] !== undefined && paramValues[name] !== null
                                        ) && (
                                            <Button
                                                type="submit"
                                                isBusy={execLoading}
                                                disabled={execLoading}
                                                className="mt-2"
                                                aria-describedby="execute-help"
                                            >
                                                {__('Confirm & Execute', 'ai-command-palette')}
                                            </Button>
                                        )}
                                        <div id="execute-help" className="sr-only">
                                            {__('Press Enter or click to execute this command', 'ai-command-palette')}
                                        </div>
                                    </form>
                                    {execError && (
                                        <div
                                            className="text-red-600 mt-2"
                                            role="alert"
                                            aria-live="assertive"
                                        >
                                            {execError}
                                        </div>
                                    )}
                                    {execResult && (
                                        <div className="aicp-dynamic-result mt-4">
                                            <pre
                                                className="bg-gray-100 p-2 rounded text-xs overflow-x-auto"
                                                aria-label={__('Command result', 'ai-command-palette')}
                                            >
                                                {JSON.stringify(execResult, null, 2)}
                                            </pre>
                                        </div>
                                    )}
                                    <Button
                                        className="aicp-btn"
                                        onClick={() => setSelectedCommand(null)}
                                        aria-label={__('Back to search', 'ai-command-palette')}
                                    >
                                        {__('Back to search', 'ai-command-palette')}
                                    </Button>
                                </div>
                            ) : (
                                // Only show command list if no workflow or fullschema form is active
                                !workflowPlan && !(selectedCommand && selectedCommand.action?.type === 'ai_execute' && selectedCommand.action.plan?.steps?.length === 1) && (
                                    <>
                                        {!workflowPlan && !(selectedCommand && selectedCommand.action?.type === 'ai_execute') && (
                                            <div className="aicp-section-title mb-2" style={{padding: '0 24px'}}>{__('Commands', 'ai-command-palette')}</div>
                                        )}
                                        <div
                                            className={classNames('aicp-commands-accordion', {
                                                'aicp-commands-collapsed': workflowPlan || (selectedCommand && selectedCommand.action?.type === 'ai_execute'),
                                                'aicp-commands-expanded': !workflowPlan && !(selectedCommand && selectedCommand.action?.type === 'ai_execute'),
                                            })}
                                            style={{
                                                maxHeight: workflowPlan || (selectedCommand && selectedCommand.action?.type === 'ai_execute') ? 0 : 400,
                                                overflow: 'hidden',
                                                transition: 'max-height 0.4s cubic-bezier(0.4,0,0.2,1)',
                                                opacity: workflowPlan || (selectedCommand && selectedCommand.action?.type === 'ai_execute') ? 0 : 1,
                                            }}
                                            role="region"
                                            aria-labelledby="palette-title"
                                            aria-describedby="palette-description"
                                        >
                                            <CommandList
                                                commands={commands}
                                                onCommandSelect={handleSelectCommand}
                                                selectedIndex={selectedIndex}
                                                onHoverIndex={idx => {
                                                    if (idx >= 0) setSelectedIndex(idx);
                                                }}
                                            />
                                            {/* Live region for error/status messages */}
                                            <div aria-live="polite" aria-atomic="true" className="sr-only" id="aicp-palette-status">
                                                {error ? error : loading ? 'Loading...' : ''}
                                            </div>
                                        </div>
                                    </>
                                )
                            )}
                        </>
                    )}

                    {/* aicp-fullschema-form section */}
                    {selectedCommand && selectedCommand.action?.type === 'ai_execute' && selectedCommand.action.plan?.steps?.length === 1 ? (
                        <div className="aicp-fullschema-form-card mt-4">
                            <div className="aicp-section-title mb-2">
                                {fullFunctionSchema?.title || selectedCommand.title}
                            </div>
                            {fullSchemaLoading ? (
                                <div aria-live="polite">
                                    {React.createElement(Spinner as any)}
                                    <span className="aicp-ai-status">{__('Loading function schema...', 'ai-command-palette')}</span>
                                </div>
                            ) : fullFunctionSchema ? (
                                <form
                                    onSubmit={e => {
                                        e.preventDefault();
                                        handleFullSchemaExecute();
                                    }}
                                    aria-labelledby="full-schema-form-heading"
                                    style={{marginBottom: '0'}}
                                >
                                    <h4 className="font-bold mb-3" id="full-schema-form-heading">
                                        {selectedCommand.action.plan.summary || fullFunctionSchema.description || fullFunctionSchema.name}
                                    </h4>
                                    {Object.entries(selectedCommand.action.plan.steps[0].arguments || {}).map(([name, value]: [string, any]) => {
                                        // Determine the type from the schema if available
                                        const paramType = fullFunctionSchema?.parameters?.[name]?.type;
                                        // Type guard for object with raw property
                                        function hasRaw(obj: any): obj is { raw: string } {
                                            return obj && typeof obj === 'object' && 'raw' in obj && typeof obj.raw === 'string';
                                        }
                                        let inputValue = '';
                                        if (paramType === 'object') {
                                            inputValue = paramValues[name] ?? (typeof value === 'object' ? JSON.stringify(value, null, 2) : value);
                                        } else {
                                            if (paramValues[name] !== undefined) {
                                                inputValue = paramValues[name];
                                            } else if (typeof value === 'object') {
                                                inputValue = hasRaw(value) ? value.raw : '';
                                            } else {
                                                inputValue = value;
                                            }
                                        }
                                        console.log('[AICP fullschema input]', { name, value, paramType, inputValue });
                                        return (
                                            <div key={name} className="mb-4">
                                                <label className="block text-sm font-medium mb-1" htmlFor={`full-param-${name}`}>{name}</label>
                                                <input
                                                    id={`full-param-${name}`}
                                                    type="text"
                                                    value={hasRaw(inputValue) ? inputValue.raw : inputValue}
                                                    onChange={e => handleParamChange(name, e.target.value)}
                                                    className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 py-2 px-3"
                                                    placeholder={name}
                                                />
                                            </div>
                                        );
                                    })}
                                    <Button
                                        type="submit"
                                        isBusy={execLoading}
                                        disabled={execLoading}
                                        className="aicp-btn mt-2"
                                        aria-describedby="full-execute-help"
                                    >
                                        {__('Confirm & Execute', 'ai-command-palette')}
                                    </Button>
                                    <div id="full-execute-help" className="sr-only">
                                        {__('Press Enter or click to execute this command', 'ai-command-palette')}
                                    </div>
                                </form>
                            ) : (
                                <div className="text-gray-500 text-sm">{__('No schema available for this function.', 'ai-command-palette')}</div>
                            )}
                            {execError && (
                                <div className="text-red-600 mt-2" role="alert" aria-live="assertive">{execError}</div>
                            )}
                            {execResult && (
                                <div className="aicp-dynamic-result mt-4">
                                    <pre className="bg-gray-100 p-2 rounded text-xs overflow-x-auto" aria-label={__('Command result', 'ai-command-palette')}>
                                        {typeof execResult === 'object' ? JSON.stringify(execResult, null, 2) : String(execResult)}
                                    </pre>
                                </div>
                            )}
                            <hr className="aicp-section-divider mt-4 mb-3" />
                            <div style={{display:'flex', justifyContent:'flex-end', background:'#f8fafc', borderRadius:'8px', padding:'12px 0 0 0'}}>
                                <Button className="aicp-btn" onClick={() => setSelectedCommand(null)} aria-label={__('Back to search', 'ai-command-palette')}>
                                    {__('Back to search', 'ai-command-palette')}
                                </Button>
                            </div>
                        </div>
                    ) : null}

                    <div className="aicp-footer">
                        <span className="aicp-hint">
                            {__('↑↓ Navigate', 'ai-command-palette')}
                        </span>
                        <span className="aicp-hint">
                            {__('↵ Select', 'ai-command-palette')}
                        </span>
                        <span className="aicp-hint">
                            {__('ESC Close', 'ai-command-palette')}
                        </span>
                    </div>

                    {Boolean(window.aicpData && 'woocommerce_active' in window.aicpData && window.aicpData.woocommerce_active) && <WooCommerceReports />}
                </div>
            </Modal>
        </>
    );
};

export default CommandPalette;