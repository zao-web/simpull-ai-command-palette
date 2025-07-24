import { useState, useEffect, useCallback, useRef } from 'react';

// Type declarations for Web Speech API
interface SpeechRecognition extends EventTarget {
  continuous: boolean;
  interimResults: boolean;
  lang: string;
  start(): void;
  stop(): void;
  onstart: ((this: SpeechRecognition, ev: Event) => any) | null;
  onend: ((this: SpeechRecognition, ev: Event) => any) | null;
  onresult: ((this: SpeechRecognition, ev: SpeechRecognitionEvent) => any) | null;
  onerror: ((this: SpeechRecognition, ev: SpeechRecognitionErrorEvent) => any) | null;
}

interface SpeechRecognitionConstructor {
  new(): SpeechRecognition;
}

declare global {
  interface Window {
    SpeechRecognition: SpeechRecognitionConstructor;
    webkitSpeechRecognition: SpeechRecognitionConstructor;
  }
}

interface SpeechRecognitionEvent extends Event {
  resultIndex: number;
  results: SpeechRecognitionResultList;
}

interface SpeechRecognitionErrorEvent extends Event {
  error: 'no-speech' | 'audio-capture' | 'not-allowed' | 'network' | 'service-not-allowed' | 'bad-grammar' | 'language-not-supported' | string;
}

interface VoiceRecognitionState {
  isListening: boolean;
  transcript: string;
  isSupported: boolean;
  error: string | null;
}

interface VoiceRecognitionOptions {
  continuous?: boolean;
  interimResults?: boolean;
  lang?: string;
  onResult?: (transcript: string) => void;
  onError?: (error: string) => void;
  onStart?: () => void;
  onEnd?: () => void;
}

export const useVoiceRecognition = (options: VoiceRecognitionOptions = {}) => {
  const {
    continuous = false,
    interimResults = true,
    lang = 'en-US',
    onResult,
    onError,
    onStart,
    onEnd
  } = options;

  const [state, setState] = useState<VoiceRecognitionState>({
    isListening: false,
    transcript: '',
    isSupported: false,
    error: null
  });

  const recognitionRef = useRef<SpeechRecognition | null>(null);

  // Store callbacks in refs to avoid re-creating SpeechRecognition
  const onResultRef = useRef(onResult);
  const onErrorRef = useRef(onError);
  const onStartRef = useRef(onStart);
  const onEndRef = useRef(onEnd);

  // Update refs when callbacks change
  useEffect(() => {
    onResultRef.current = onResult;
    onErrorRef.current = onError;
    onStartRef.current = onStart;
    onEndRef.current = onEnd;
  }, [onResult, onError, onStart, onEnd]);

  // Check if speech recognition is supported
  useEffect(() => {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const isSupported = !!SpeechRecognition;

    setState(prev => ({ ...prev, isSupported }));

    if (isSupported) {
      recognitionRef.current = new SpeechRecognition();
      const recognition = recognitionRef.current;

      recognition.continuous = continuous;
      recognition.interimResults = interimResults;
      recognition.lang = lang;

      recognition.onstart = () => {
        setState(prev => ({ ...prev, isListening: true, error: null }));
        onStartRef.current?.();
      };

      recognition.onresult = (event: SpeechRecognitionEvent) => {
        let finalTranscript = '';
        let interimTranscript = '';

        for (let i = event.resultIndex; i < event.results.length; i++) {
          const transcript = event.results[i][0].transcript;
          if (event.results[i].isFinal) {
            finalTranscript += transcript;
          } else {
            interimTranscript += transcript;
          }
        }

        const fullTranscript = finalTranscript || interimTranscript;
        setState(prev => ({
          ...prev,
          transcript: fullTranscript
        }));

        if (finalTranscript) {
          onResultRef.current?.(finalTranscript);
        }
      };

      recognition.onerror = (event: SpeechRecognitionErrorEvent) => {
        let errorMessage = 'Voice recognition error';

        switch (event.error) {
          case 'no-speech':
            errorMessage = 'No speech detected';
            break;
          case 'audio-capture':
            errorMessage = 'Audio capture failed';
            break;
          case 'not-allowed':
            errorMessage = 'Microphone access denied';
            break;
          case 'network':
            errorMessage = 'Network error';
            break;
          case 'service-not-allowed':
            errorMessage = 'Speech recognition service not allowed';
            break;
          case 'bad-grammar':
            errorMessage = 'Bad grammar';
            break;
          case 'language-not-supported':
            errorMessage = 'Language not supported';
            break;
          default:
            errorMessage = `Unknown error: ${event.error}`;
        }

        setState(prev => ({
          ...prev,
          isListening: false,
          error: errorMessage
        }));
        onErrorRef.current?.(errorMessage);
      };

      recognition.onend = () => {
        setState(prev => ({ ...prev, isListening: false }));
        onEndRef.current?.();
      };
    }
  }, [continuous, interimResults, lang]);

  // Start listening
  const startListening = useCallback(() => {
    console.log('useVoiceRecognition: startListening', { state });
    if (!state.isSupported) {
      setState(prev => ({
        ...prev,
        error: 'Speech recognition is not supported in this browser'
      }));
      return false;
    }

    if (!recognitionRef.current) {
      setState(prev => ({
        ...prev,
        error: 'Speech recognition not initialized'
      }));
      return false;
    }

    try {
      recognitionRef.current.start();
      return true;
    } catch (error) {
      setState(prev => ({
        ...prev,
        error: `Failed to start listening: ${error}`
      }));
      return false;
    }
  }, [state.isSupported]);

  // Stop listening
  const stopListening = useCallback(() => {
    console.log('useVoiceRecognition: stopListening', { state });
    if (recognitionRef.current && state.isListening) {
      try {
        recognitionRef.current.stop();
      } catch (error) {
        setState(prev => ({
          ...prev,
          error: `Failed to stop listening: ${error}`
        }));
      }
    }
  }, [state.isListening]);

  // Clear transcript
  const clearTranscript = useCallback(() => {
    console.log('useVoiceRecognition: clearTranscript', { state });
    setState(prev => ({ ...prev, transcript: '', error: null }));
  }, []);

  // Reset state
  const reset = useCallback(() => {
    console.log('useVoiceRecognition: reset', { state });
    setState({
      isListening: false,
      transcript: '',
      isSupported: state.isSupported,
      error: null
    });
  }, [state.isSupported]);

  // Cleanup on unmount
  useEffect(() => {
    console.log('useVoiceRecognition: cleanup effect');
    return () => {
      if (recognitionRef.current) {
        try {
          recognitionRef.current.stop();
        } catch (error) {
          // Ignore cleanup errors
        }
      }
    };
  }, []);

  return {
    ...state,
    startListening,
    stopListening,
    clearTranscript,
    reset
  };
};

// Voice command processor
export const useVoiceCommands = (onCommand: (command: string) => void) => {
  const [isProcessing, setIsProcessing] = useState(false);

  const handleVoiceResult = useCallback((transcript: string) => {
    setIsProcessing(true);

    // Process the transcript to extract commands
    const processedCommand = processVoiceTranscript(transcript);

    if (processedCommand) {
      onCommand(processedCommand);
    }

    setIsProcessing(false);
  }, [onCommand]);

  const voiceRecognition = useVoiceRecognition({
    continuous: false,
    interimResults: false,
    onResult: handleVoiceResult,
    onError: (error) => {
      console.error('Voice recognition error:', error);
    }
  });

  return {
    ...voiceRecognition,
    isProcessing
  };
};

// Process voice transcript to extract commands
const processVoiceTranscript = (transcript: string): string | null => {
  const normalized = transcript.toLowerCase().trim();

  // Common voice command patterns
  const patterns = [
    // Navigation commands
    { pattern: /^(go to|open|show|navigate to)\s+(.+)$/, command: '$2' },
    { pattern: /^(create|make|add)\s+(.+)$/, command: 'create $2' },
    { pattern: /^(edit|modify|change)\s+(.+)$/, command: 'edit $2' },
    { pattern: /^(delete|remove)\s+(.+)$/, command: 'delete $2' },
    { pattern: /^(search|find)\s+(.+)$/, command: 'search $2' },
    { pattern: /^(view|show)\s+(.+)$/, command: 'view $2' },

    // Plugin-specific commands
    { pattern: /^(woocommerce|wc)\s+(.+)$/, command: 'woocommerce $2' },
    { pattern: /^(plugin|plugins)\s+(.+)$/, command: 'plugin $2' },
    { pattern: /^(theme|themes)\s+(.+)$/, command: 'theme $2' },

    // Common actions
    { pattern: /^(save|update|publish)\s+(.+)$/, command: 'save $2' },
    { pattern: /^(backup|export)\s+(.+)$/, command: 'backup $2' },
    { pattern: /^(import|restore)\s+(.+)$/, command: 'import $2' },

    // Analytics and reports
    { pattern: /^(analytics|stats|statistics)\s+(.+)$/, command: 'analytics $2' },
    { pattern: /^(report|reports)\s+(.+)$/, command: 'report $2' },

    // Settings and configuration
    { pattern: /^(settings|config|configuration)\s+(.+)$/, command: 'settings $2' },
    { pattern: /^(permissions|users|roles)\s+(.+)$/, command: 'users $2' },

    // Media and content
    { pattern: /^(media|images|files)\s+(.+)$/, command: 'media $2' },
    { pattern: /^(content|posts|pages)\s+(.+)$/, command: 'content $2' },

    // System commands
    { pattern: /^(system|site|website)\s+(.+)$/, command: 'system $2' },
    { pattern: /^(cache|optimize|performance)\s+(.+)$/, command: 'cache $2' },
    { pattern: /^(security|secure)\s+(.+)$/, command: 'security $2' }
  ];

  // Try to match patterns
  for (const { pattern, command } of patterns) {
    const match = normalized.match(pattern);
    if (match) {
      return command.replace(/\$(\d+)/g, (_, index) => match[parseInt(index)] || '');
    }
  }

  // If no pattern matches, return the original transcript
  return transcript;
};

// Voice command suggestions
export const getVoiceCommandSuggestions = (): string[] => [
  'Create a new post',
  'Edit the homepage',
  'View recent orders',
  'Show analytics',
  'Backup the site',
  'Update plugins',
  'Manage users',
  'Clear cache',
  'View media library',
  'Search content',
  'Open settings',
  'Show system health'
];

// Voice command help text
export const getVoiceCommandHelp = (): string => `
Voice Commands Available:

Navigation:
- "Go to [page name]" - Navigate to admin pages
- "Open [section]" - Open admin sections
- "Show [content]" - Display content

Content Management:
- "Create [content type]" - Create new content
- "Edit [content]" - Edit existing content
- "Delete [content]" - Remove content
- "Search [term]" - Search for content

Plugin Management:
- "Plugin [action]" - Manage plugins
- "WooCommerce [action]" - WooCommerce specific commands
- "Theme [action]" - Theme management

System:
- "Backup [target]" - Create backups
- "Cache [action]" - Manage cache
- "Security [action]" - Security operations
- "Analytics [type]" - View analytics

Examples:
- "Create a new post about technology"
- "Show recent WooCommerce orders"
- "Open plugin settings"
- "Clear site cache"
`;