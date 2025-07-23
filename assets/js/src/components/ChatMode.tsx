import React, { useState, useRef, useEffect } from 'react';
import { Button, TextControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/api-fetch';

interface ChatMessage {
  id: string;
  type: 'user' | 'ai' | 'system';
  content: string;
  timestamp: Date;
  metadata?: any;
}

interface ChatModeProps {
  onClose: () => void;
  initialQuery?: string;
}

const ChatMode: React.FC<ChatModeProps> = ({ onClose, initialQuery = '' }) => {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [inputValue, setInputValue] = useState(initialQuery);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Auto-scroll to bottom when new messages arrive
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Focus input on mount
  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  // Initialize with welcome message
  useEffect(() => {
    if (messages.length === 0) {
      setMessages([
        {
          id: 'welcome',
          type: 'ai',
          content: __(
            'Hello! I\'m your AI assistant. I can help you with WordPress tasks, answer questions, and execute commands. What would you like to do?',
            'ai-command-palette'
          ),
          timestamp: new Date()
        }
      ]);
    }
  }, []);

  // Handle initial query if provided
  useEffect(() => {
    if (initialQuery && messages.length === 1) {
      handleSendMessage(initialQuery);
    }
  }, [initialQuery, messages.length]);

  const handleSendMessage = async (content: string) => {
    if (!content.trim() || isLoading) return;

    const userMessage: ChatMessage = {
      id: Date.now().toString(),
      type: 'user',
      content: content.trim(),
      timestamp: new Date()
    };

    setMessages(prev => [...prev, userMessage]);
    setInputValue('');
    setIsLoading(true);
    setError(null);

    try {
      const response = await apiFetch({
        path: '/ai-command-palette/v1/chat',
        method: 'POST',
        data: {
          message: content.trim(),
          conversation_id: conversationId,
          context: {
            previous_messages: messages.slice(-5).map(msg => ({
              role: msg.type === 'user' ? 'user' : 'assistant',
              content: msg.content
            }))
          }
        }
      });

      const aiMessage: ChatMessage = {
        id: (Date.now() + 1).toString(),
        type: 'ai',
        content: response.message || response.content || __('I understand. How can I help you further?', 'ai-command-palette'),
        timestamp: new Date(),
        metadata: {
          conversation_id: response.conversation_id,
          suggested_actions: response.suggested_actions,
          executed_commands: response.executed_commands
        }
      };

      setMessages(prev => [...prev, aiMessage]);

      if (response.conversation_id) {
        setConversationId(response.conversation_id);
      }

      // Handle any executed commands
      if (response.executed_commands && response.executed_commands.length > 0) {
        response.executed_commands.forEach((cmd: any) => {
          const systemMessage: ChatMessage = {
            id: `cmd-${Date.now()}-${Math.random()}`,
            type: 'system',
            content: `✅ ${cmd.description || 'Command executed successfully'}`,
            timestamp: new Date(),
            metadata: { command: cmd }
          };
          setMessages(prev => [...prev, systemMessage]);
        });
      }

    } catch (err: any) {
      console.error('Chat error:', err);
      const errorMessage: ChatMessage = {
        id: (Date.now() + 1).toString(),
        type: 'ai',
        content: err.message || __('Sorry, I encountered an error. Please try again.', 'ai-command-palette'),
        timestamp: new Date()
      };
      setMessages(prev => [...prev, errorMessage]);
      setError(err.message || 'Unknown error occurred');
    } finally {
      setIsLoading(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    handleSendMessage(inputValue);
  };

  const handleQuickAction = (action: string) => {
    handleSendMessage(action);
  };

  const clearConversation = () => {
    setMessages([
      {
        id: 'welcome',
        type: 'ai',
        content: __(
          'Hello! I\'m your AI assistant. I can help you with WordPress tasks, answer questions, and execute commands. What would you like to do?',
          'ai-command-palette'
        ),
        timestamp: new Date()
      }
    ]);
    setConversationId(null);
    setError(null);
  };

  const getQuickActions = () => [
    __('Create a new post', 'ai-command-palette'),
    __('Show recent orders', 'ai-command-palette'),
    __('Check site health', 'ai-command-palette'),
    __('Update plugins', 'ai-command-palette'),
    __('View analytics', 'ai-command-palette'),
    __('Backup the site', 'ai-command-palette')
  ];

  const renderMessage = (message: ChatMessage) => {
    const isUser = message.type === 'user';
    const isSystem = message.type === 'system';

    return (
      <div
        key={message.id}
        className={`aicp-chat-message ${isUser ? 'aicp-chat-user' : 'aicp-chat-ai'} ${isSystem ? 'aicp-chat-system' : ''}`}
      >
        <div className="aicp-chat-avatar">
          {isUser ? (
            <span className="aicp-avatar-user">👤</span>
          ) : isSystem ? (
            <span className="aicp-avatar-system">⚡</span>
          ) : (
            <span className="aicp-avatar-ai">🤖</span>
          )}
        </div>
        <div className="aicp-chat-content">
          <div className="aicp-chat-text">
            {message.content}
          </div>
          {message.metadata?.suggested_actions && (
            <div className="aicp-chat-suggestions">
              <div className="aicp-suggestions-label">
                {__('Suggested actions:', 'ai-command-palette')}
              </div>
              <div className="aicp-suggestions-list">
                {message.metadata.suggested_actions.map((action: string, index: number) => (
                  <button
                    key={index}
                    className="aicp-suggestion-chip"
                    onClick={() => handleQuickAction(action)}
                  >
                    {action}
                  </button>
                ))}
              </div>
            </div>
          )}
          <div className="aicp-chat-timestamp">
            {message.timestamp.toLocaleTimeString()}
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="aicp-chat-mode">
      <div className="aicp-chat-header">
        <h3>{__('AI Assistant Chat', 'ai-command-palette')}</h3>
        <div className="aicp-chat-actions">
          <Button
            size="small"
            onClick={clearConversation}
            aria-label={__('Clear conversation', 'ai-command-palette')}
          >
            {__('Clear', 'ai-command-palette')}
          </Button>
          <Button
            size="small"
            onClick={onClose}
            aria-label={__('Close chat', 'ai-command-palette')}
          >
            {__('Close', 'ai-command-palette')}
          </Button>
        </div>
      </div>

      <div className="aicp-chat-messages">
        {messages.map(renderMessage)}
        {isLoading && (
          <div className="aicp-chat-message aicp-chat-ai">
            <div className="aicp-chat-avatar">
              <span className="aicp-avatar-ai">🤖</span>
            </div>
            <div className="aicp-chat-content">
              <div className="aicp-chat-typing">
                <Spinner />
                <span>{__('AI is thinking...', 'ai-command-palette')}</span>
              </div>
            </div>
          </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      {error && (
        <Notice status="error" isDismissible={false} className="aicp-chat-error">
          {error}
        </Notice>
      )}

      <div className="aicp-chat-input-section">
        <form onSubmit={handleSubmit} className="aicp-chat-form">
          <TextControl
            ref={inputRef}
            value={inputValue}
            onChange={setInputValue}
            placeholder={__('Type your message or command...', 'ai-command-palette')}
            className="aicp-chat-input"
            disabled={isLoading}
            aria-label={__('Chat message input', 'ai-command-palette')}
          />
          <Button
            type="submit"
            isPrimary
            disabled={!inputValue.trim() || isLoading}
            className="aicp-chat-send"
            aria-label={__('Send message', 'ai-command-palette')}
          >
            {isLoading ? <Spinner /> : __('Send', 'ai-command-palette')}
          </Button>
        </form>

        <div className="aicp-quick-actions">
          <div className="aicp-quick-actions-label">
            {__('Quick actions:', 'ai-command-palette')}
          </div>
          <div className="aicp-quick-actions-list">
            {getQuickActions().map((action, index) => (
              <button
                key={index}
                className="aicp-quick-action-chip"
                onClick={() => handleQuickAction(action)}
                disabled={isLoading}
              >
                {action}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="aicp-chat-help">
        <details>
          <summary>{__('Chat Tips', 'ai-command-palette')}</summary>
          <div className="aicp-chat-help-content">
            <p>{__('You can ask me to:', 'ai-command-palette')}</p>
            <ul>
              <li>{__('Execute WordPress commands', 'ai-command-palette')}</li>
              <li>{__('Answer questions about your site', 'ai-command-palette')}</li>
              <li>{__('Help with troubleshooting', 'ai-command-palette')}</li>
              <li>{__('Provide step-by-step guidance', 'ai-command-palette')}</li>
              <li>{__('Analyze site performance', 'ai-command-palette')}</li>
            </ul>
            <p>
              <strong>{__('Examples:', 'ai-command-palette')}</strong>
            </p>
            <ul>
              <li>"{__('How do I create a new page?', 'ai-command-palette')}"</li>
              <li>"{__('Show me recent WooCommerce orders', 'ai-command-palette')}"</li>
              <li>"{__('What plugins are installed?', 'ai-command-palette')}"</li>
              <li>"{__('Help me optimize my site', 'ai-command-palette')}"</li>
            </ul>
          </div>
        </details>
      </div>
    </div>
  );
};

export default ChatMode;