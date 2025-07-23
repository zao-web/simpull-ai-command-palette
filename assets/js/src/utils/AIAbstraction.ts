import { ClientSideAI } from './ClientSideAI';

export interface AIRequest {
  type: 'intent_classification' | 'suggestions' | 'embedding' | 'text_generation';
  query?: string;
  context?: any;
  text?: string;
  options?: any;
}

export interface AIResponse {
  success: boolean;
  data?: any;
  error?: string;
  source: 'client' | 'server' | 'fallback';
}

export class AIAbstraction {
  private static instance: AIAbstraction;
  private clientSideAvailable: boolean = false;
  private userPreferences: { preferClientSide: boolean } = { preferClientSide: true };

  private constructor() {
    this.clientSideAvailable = ClientSideAI.isAvailable();
    this.loadPreferences();
  }

  static getInstance(): AIAbstraction {
    if (!AIAbstraction.instance) {
      AIAbstraction.instance = new AIAbstraction();
    }
    return AIAbstraction.instance;
  }

  private loadPreferences(): void {
    try {
      const stored = localStorage.getItem('aicp_ai_preferences');
      if (stored) {
        this.userPreferences = JSON.parse(stored);
      }
    } catch (error) {
      console.warn('Failed to load AI preferences:', error);
    }
  }

  updatePreferences(preferences: { preferClientSide: boolean }): void {
    this.userPreferences = preferences;
    try {
      localStorage.setItem('aicp_ai_preferences', JSON.stringify(preferences));
    } catch (error) {
      console.warn('Failed to save AI preferences:', error);
    }
  }

  async process(request: AIRequest): Promise<AIResponse> {
    // Try client-side AI first if available and preferred
    if (this.shouldUseClientSide(request)) {
      try {
        const result = await this.processClientSide(request);
        return { success: true, data: result, source: 'client' };
      } catch (error) {
        console.warn('Client-side AI failed, falling back to server:', error);
      }
    }

    // Fallback to server-side AI
    try {
      const result = await this.processServerSide(request);
      return { success: true, data: result, source: 'server' };
    } catch (error) {
      console.warn('Server-side AI failed, using fallback:', error);

      // Final fallback to rule-based matching
      const result = await this.processFallback(request);
      return { success: true, data: result, source: 'fallback' };
    }
  }

  private shouldUseClientSide(request: AIRequest): boolean {
    if (!this.clientSideAvailable || !this.userPreferences.preferClientSide) {
      return false;
    }

    // Only use client-side AI for simple operations
    switch (request.type) {
      case 'intent_classification':
        return request.query && request.query.split(' ').length <= 10;
      case 'suggestions':
        return true;
      case 'embedding':
        return request.text && request.text.length <= 1000;
      case 'text_generation':
        return false; // Complex text generation should use server-side
      default:
        return false;
    }
  }

  private async processClientSide(request: AIRequest): Promise<any> {
    switch (request.type) {
      case 'intent_classification':
        if (!request.query) throw new Error('Query required for intent classification');
        return await ClientSideAI.classifyIntent(request.query);

      case 'suggestions':
        if (!request.context) throw new Error('Context required for suggestions');
        return await ClientSideAI.generateSuggestions(request.context);

      case 'embedding':
        if (!request.text) throw new Error('Text required for embedding');
        return await ClientSideAI.generateEmbedding(request.text);

      case 'text_generation':
        throw new Error('Text generation not supported in client-side mode');

      default:
        throw new Error(`Unknown request type: ${request.type}`);
    }
  }

  private async processServerSide(request: AIRequest): Promise<any> {
    const response = await fetch('/wp-json/ai-command-palette/v1/ai-process', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.aicpData?.nonce || ''
      },
      body: JSON.stringify(request)
    });

    if (!response.ok) {
      throw new Error(`Server error: ${response.status}`);
    }

    const result = await response.json();
    if (!result.success) {
      throw new Error(result.error || 'Server processing failed');
    }

    return result.data;
  }

  private async processFallback(request: AIRequest): Promise<any> {
    switch (request.type) {
      case 'intent_classification':
        return this.fallbackIntentClassification(request.query || '');

      case 'suggestions':
        return this.fallbackSuggestions(request.context || {});

      case 'embedding':
        return this.fallbackEmbedding(request.text || '');

      case 'text_generation':
        return this.fallbackTextGeneration(request.query || '', request.options || {});

      default:
        throw new Error(`Unknown request type: ${request.type}`);
    }
  }

  private fallbackIntentClassification(query: string): string {
    const keywords = query.toLowerCase();

    if (keywords.includes('post') || keywords.includes('article') || keywords.includes('blog')) {
      return 'content';
    }
    if (keywords.includes('plugin') || keywords.includes('theme')) {
      return 'plugin';
    }
    if (keywords.includes('user') || keywords.includes('admin')) {
      return 'user';
    }
    if (keywords.includes('media') || keywords.includes('image') || keywords.includes('file')) {
      return 'media';
    }
    if (keywords.includes('setting') || keywords.includes('config')) {
      return 'settings';
    }
    if (keywords.includes('order') || keywords.includes('product') || keywords.includes('woocommerce')) {
      return 'ecommerce';
    }
    if (keywords.includes('analytics') || keywords.includes('report') || keywords.includes('stats')) {
      return 'analytics';
    }

    return 'system';
  }

  private fallbackSuggestions(context: any): string[] {
    const role = context.role || 'administrator';
    const page = context.page || 'dashboard';

    const suggestions: Record<string, string[]> = {
      administrator: [
        'Create a new post',
        'Manage plugins',
        'View site analytics',
        'Edit site settings'
      ],
      editor: [
        'Create a new post',
        'Edit existing posts',
        'Manage media',
        'View comments'
      ],
      author: [
        'Create a new post',
        'Edit my posts',
        'Upload media',
        'View my profile'
      ]
    };

    return suggestions[role] || suggestions.administrator;
  }

  private fallbackEmbedding(text: string): number[] {
    // Simple hash-based embedding for fallback
    const hash = text.split('').reduce((a, b) => {
      a = ((a << 5) - a) + b.charCodeAt(0);
      return a & a;
    }, 0);

    // Generate a simple 10-dimensional embedding
    const embedding = new Array(10).fill(0);
    for (let i = 0; i < 10; i++) {
      embedding[i] = Math.sin(hash + i) * 0.5;
    }

    return embedding;
  }

  private fallbackTextGeneration(query: string, options: any): string {
    // Simple template-based text generation
    if (query.includes('hello') || query.includes('hi')) {
      return 'Hello! How can I help you with WordPress today?';
    }
    if (query.includes('help')) {
      return 'I can help you with WordPress commands. Try asking me to create a post, manage plugins, or view analytics.';
    }

    return 'I understand you\'re asking about WordPress. Please try a more specific command.';
  }

  getStatus(): {
    clientSideAvailable: boolean;
    userPreferences: { preferClientSide: boolean };
    availableFeatures: string[];
    browserInfo: { name: string; version: string; supportsAI: boolean };
  } {
    return {
      clientSideAvailable: this.clientSideAvailable,
      userPreferences: this.userPreferences,
      availableFeatures: ClientSideAI.getAvailableFeatures(),
      browserInfo: ClientSideAI.getBrowserInfo()
    };
  }
}