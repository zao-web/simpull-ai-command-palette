// Client-side AI utility for Chrome built-in AI APIs
export class ClientSideAI {
  static isAvailable(): boolean {
    return !!(window.AI?.textGeneration || window.AI?.embedding);
  }

  static getAvailableFeatures(): string[] {
    const features: string[] = [];
    if (window.AI?.textGeneration) features.push('textGeneration');
    if (window.AI?.embedding) features.push('embedding');
    if (window.AI?.imageGeneration) features.push('imageGeneration');
    return features;
  }

  static async classifyIntent(query: string): Promise<string> {
    if (!window.AI?.textGeneration) {
      throw new Error('Text generation not available');
    }

    try {
      const response = await window.AI.textGeneration.generateText({
        prompt: `Classify this WordPress command: "${query}"
Categories: content, settings, plugin, user, media, analytics, system, ecommerce
Response:`,
        options: { maxTokens: 20, temperature: 0.1 }
      });
      return response.text?.toLowerCase().trim() || 'unknown';
    } catch (error) {
      console.warn('Client-side AI intent classification failed:', error);
      throw error;
    }
  }

  static async generateSuggestions(context: any): Promise<string[]> {
    if (!window.AI?.textGeneration) {
      throw new Error('Text generation not available');
    }

    try {
      const prompt = `Suggest 3 WordPress commands for a ${context.role} user on ${context.page}.
Format each suggestion as a simple command title.
Context: ${JSON.stringify(context)}`;

      const response = await window.AI.textGeneration.generateText({
        prompt,
        options: { maxTokens: 100, temperature: 0.7 }
      });

      const suggestions = response.text?.split('\n')
        .filter(s => s.trim())
        .map(s => s.replace(/^\d+\.\s*/, '').trim())
        .filter(s => s.length > 0) || [];

      return suggestions.slice(0, 3);
    } catch (error) {
      console.warn('Client-side AI suggestion generation failed:', error);
      throw error;
    }
  }

  static async generateEmbedding(text: string): Promise<number[]> {
    if (!window.AI?.embedding) {
      throw new Error('Embedding not available');
    }

    try {
      const response = await window.AI.embedding.generateEmbedding({
        input: text
      });
      return response.embedding || [];
    } catch (error) {
      console.warn('Client-side AI embedding generation failed:', error);
      throw error;
    }
  }

  static getBrowserInfo(): { name: string; version: string; supportsAI: boolean } {
    const userAgent = navigator.userAgent;
    const isChrome = /Chrome/.test(userAgent) && !/Edge/.test(userAgent);
    const versionMatch = userAgent.match(/Chrome\/(\d+)/);
    const version = versionMatch ? versionMatch[1] : 'unknown';

    return {
      name: isChrome ? 'Chrome' : 'Other',
      version,
      supportsAI: this.isAvailable()
    };
  }
}