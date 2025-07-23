export interface Command {
    id: string;
    title: string;
    icon?: string;
    category?: string;
    action: {
        type: 'navigate' | 'execute' | 'ai_execute' | 'report' | 'rest_api';
        url?: string;
        plan?: any;
        endpoint?: string;
        method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';
    };
    plugin?: string;
    description?: string;
    priority?: number;
    score?: number;
}

export interface CommandResult {
    success: boolean;
    message?: string;
    redirect?: string;
    data?: any;
    execution_time?: number;
}