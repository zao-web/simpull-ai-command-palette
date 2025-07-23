import { Command } from '../types';

declare const aicpData: {
    apiUrl: string;
    nonce: string;
};

async function apiFetch(path: string, options: RequestInit = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': aicpData.nonce,
        ...options.headers,
    };

    const response = await fetch(`${aicpData.apiUrl}${path}`, {
        ...options,
        headers,
    });

    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'API request failed');
    }

    return response.json();
}

export async function fetchStreamedAIResponse(prompt: string, context: any, onDelta: (delta: string) => void) {
    // This function will need to be adapted if we want to support true streaming.
    // For now, it will just use the process endpoint.
    const response = await apiFetch('/ai-process', {
        method: 'POST',
        body: JSON.stringify({
            type: 'text_generation',
            query: prompt,
            context: context,
        }),
    });
    if (response.success && response.data) {
        onDelta(response.data);
    } else {
        throw new Error(response.error || 'Failed to get AI response');
    }
}

export async function executeCommand(command: Command, params: any) {
    if (command.action?.type === 'rest_api' && command.action.endpoint) {
        return apiFetch(command.action.endpoint.replace('/wp-json/ai-command-palette/v1', ''), {
            method: command.action.method || 'POST',
            body: JSON.stringify(params),
        });
    }

    // Fallback for older command types
    return apiFetch('/execute-dynamic', {
        method: 'POST',
        body: JSON.stringify({
             endpoint: command.action?.endpoint,
             method: command.action?.method,
             params,
        }),
    });
}

export async function searchCommands(query: string, context: any): Promise<Command[]> {
    if (!query) {
        return fetchContextualSuggestions(context);
    }
    const response = await apiFetch('/ai-process', {
        method: 'POST',
        body: JSON.stringify({
            type: 'intent_classification',
            query: query,
            context: context,
        }),
    });

    if (response.success) {
        // This is a simplified version. We would need to handle different intents
        // and potentially follow up with a workflow plan request.
        const planResponse = await apiFetch('/ai-process', {
            method: 'POST',
            body: JSON.stringify({
                type: 'workflow_plan',
                query: query,
                context: context,
            }),
        });

        if (planResponse.success && planResponse.data.steps) {
            return [{
                id: 'ai-workflow',
                title: 'AI Workflow: ' + planResponse.data.summary,
                action: {
                    type: 'ai_execute',
                    plan: planResponse.data,
                },
                category: 'AI',
                icon: 'dashicons-lightbulb',
            }];
        }
    }
    // Fallback to simple command search if AI fails
    return apiFetch(`/search?query=${encodeURIComponent(query)}`);
}


export async function fetchContextualSuggestions(context: any): Promise<Command[]> {
     const response = await apiFetch('/contextual-suggestions', {
        method: 'POST',
        body: JSON.stringify(context),
    });
    return response.map((suggestion: any) => ({
        id: suggestion.id || suggestion.title,
        title: suggestion.title,
        category: suggestion.category || 'Suggested',
        icon: suggestion.icon || 'dashicons-star-filled',
        action: suggestion.action,
    }));
}