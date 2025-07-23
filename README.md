# AI Command Palette for WordPress

A revolutionary AI-powered command palette that transforms how you interact with WordPress. Control your entire WordPress site using natural language commands - no more clicking through menus!

## Features

### 🤖 AI-Powered Natural Language Understanding
- Type commands in plain English: "Create a new blog post about WordPress security"
- Complex multi-step operations: "Copy the About Us page and replace all instances of 2023 with 2024"
- Smart interpretation: The AI understands context and intent

### ⚡ Universal Access
- Works everywhere: WordPress admin, post editor, and even the frontend (for logged-in users)
- Global keyboard shortcut: `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux)
- No more navigating through complex menu structures

### 🔍 Intelligent Search & Discovery
- Fuzzy search through all WordPress functionality
- Auto-discovers plugin capabilities (WooCommerce, ACF, etc.)
- Dynamic command suggestions based on context
- Personalized suggestions based on your usage patterns

### 📊 Data Visualization
- Generate charts and reports on demand
- "Show me page views for the last month"
- "Compare this quarter's sales to last year"

### 🔌 Automatic Plugin Integration
- Zero configuration required
- Automatically detects and integrates with installed plugins
- Works with WooCommerce, Advanced Custom Fields, and more

### 🔒 Secure & Permission-Aware
- Respects WordPress user roles and capabilities
- Only shows commands you have permission to execute
- Secure AI processing with your own API key

## Installation

1. Download the plugin
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your AI API key in Settings → AI Command Palette

## Setup

### 1. Get an AI API Key

The plugin supports multiple AI providers:

- **OpenAI (GPT-4)**: [Get API Key](https://platform.openai.com/api-keys)
- **Anthropic (Claude)**: [Get API Key](https://console.anthropic.com/account/keys)

### 2. Configure the Plugin

1. Go to **Settings → AI Command Palette**
2. Enter your API key
3. Select your preferred AI model
4. Test the connection
5. You're ready to go!

## Usage

### Opening the Command Palette

Press `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux) anywhere in WordPress to open the command palette.

### Example Commands

#### Content Management
- "Create a new blog post about healthy recipes"
- "Edit the About Us page"
- "Delete all posts in trash"
- "Show me the latest 5 draft posts"

#### Plugin Management
- "Deactivate the Akismet plugin"
- "Activate WooCommerce"
- "Update all plugins" (coming soon)

#### Site Settings
- "Change site title to My Awesome Blog"
- "Update the tagline"
- "Set timezone to New York"

#### WooCommerce (if installed)
- "Create a new product called Blue T-Shirt priced at $29.99"
- "Show me orders from last week"
- "Update order #1234 to completed"

#### Complex Operations
- "Copy the Services page to a new draft and replace all prices with 10% increase"
- "Find all posts mentioning 'COVID' and change their category to Archive"
- "Create a landing page for our webinar with a contact form"

### Navigation Commands

Start typing to search through all WordPress admin pages:
- "media" → Media Library
- "users" → Users page
- "settings" → Settings menu

## Development

### Building from Source

```bash
# Install dependencies
npm install
composer install

# Build for production
npm run build

# Development mode with watch
npm run dev
```

### Extending the Plugin

#### Register Custom Commands

```php
add_action('aicp_register_commands', function($registry) {
    $registry->register_command('my_custom_command', [
        'title' => 'My Custom Command',
        'description' => 'Does something special',
        'category' => 'custom',
        'callback' => function($params) {
            // Your command logic here
            return [
                'success' => true,
                'message' => 'Command executed!'
            ];
        }
    ]);
});
```

#### Add AI Functions

```php
add_filter('aicp_ai_functions', function($functions) {
    $functions[] = [
        'name' => 'myCustomFunction',
        'description' => 'My custom function for AI',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'First parameter'
                ]
            ]
        ]
    ];
    return $functions;
});
```

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- An API key from OpenAI or Anthropic

## Privacy & Security

- Your AI API key is stored securely in your WordPress database
- Commands are processed through your chosen AI provider's API
- Usage data is stored locally for personalization (can be disabled)
- No data is sent to third parties except your chosen AI provider

## Troubleshooting

### Command Palette Won't Open
- Check if you have the required permissions (at least 'edit_posts')
- Try using the alternate keyboard shortcut
- Check browser console for JavaScript errors

### AI Commands Not Working
- Verify your API key is correct
- Test the connection in settings
- Check if you have API credits remaining
- Ensure your server can make external HTTPS requests

### Plugin Conflicts
- Disable other plugins one by one to identify conflicts
- Check if another plugin uses the same keyboard shortcut

## Support

- Documentation: [GitHub Wiki](https://github.com/yourusername/ai-command-palette/wiki)
- Issues: [GitHub Issues](https://github.com/yourusername/ai-command-palette/issues)
- Email: support@example.com

## License

GPL v2 or later

## Credits

Created with ❤️ by [Your Name]

Special thanks to the WordPress community and all contributors.

## Chrome Built-in AI Progressive Enhancement

This plugin now supports Chrome's experimental built-in AI APIs (see [Chrome AI APIs](https://developer.chrome.com/docs/ai/built-in-apis)) through a unified AI abstraction layer that provides seamless progressive enhancement.

### Features

- **Client-side AI**: If your browser supports `window.AI` (Chrome 138+), you can opt-in to use client-side AI for intent classification and suggestions.
- **Privacy-first**: Client-side AI processing keeps your data in the browser - no data leaves your device.
- **Performance**: Faster response times for simple operations like intent classification.
- **Graceful degradation**: Automatic fallback to server-side AI for complex operations or when client-side AI is unavailable.
- **User control**: Toggle client-side AI preferences in the palette settings.

### How the Progressive Enhancement Works

#### 1. Feature Detection
The plugin automatically detects available AI capabilities:
- Checks for `window.AI.textGeneration` for text processing
- Checks for `window.AI.embedding` for semantic analysis
- Checks for `window.AI.imageGeneration` for image-related tasks
- Identifies browser type and version

#### 2. Request Routing
The unified AI abstraction layer routes requests based on:
- **Client-side AI**: Used for simple operations when available and preferred
  - Intent classification (queries ≤ 10 words)
  - Contextual suggestions
  - Simple embeddings (text ≤ 1000 characters)
- **Server-side AI**: Used for complex operations or when client-side AI fails
  - Complex text generation
  - Multi-step workflows
  - Advanced natural language processing
- **Rule-based fallback**: Used when all AI services are unavailable
  - Keyword-based intent classification
  - Template-based suggestions
  - Simple hash-based embeddings

#### 3. Fallback Strategy
The system implements a three-tier fallback strategy:
1. **Primary**: Client-side AI (if available and preferred)
2. **Secondary**: Server-side AI (OpenAI/Claude)
3. **Tertiary**: Rule-based matching

#### 4. User Experience
- **Settings modal**: Shows AI status, available features, and browser compatibility
- **Real-time feedback**: Users see which AI source is being used
- **Transparent operation**: No interruption when switching between AI sources
- **Performance indicators**: Loading states indicate AI processing

### Technical Implementation

#### AI Abstraction Layer
The `AIAbstraction` class provides a unified interface:
```typescript
const aiAbstraction = AIAbstraction.getInstance();
const response = await aiAbstraction.process({
  type: 'intent_classification',
  query: 'create a new blog post'
});
```

#### Request Types
- `intent_classification`: Categorizes user queries
- `suggestions`: Generates contextual command suggestions
- `embedding`: Creates semantic embeddings for similarity matching
- `text_generation`: Generates complex text responses

#### Response Format
```typescript
{
  success: boolean,
  data: any,
  error?: string,
  source: 'client' | 'server' | 'fallback'
}
```

### Browser Compatibility

| Browser | Version | Client-side AI | Features |
|---------|---------|----------------|----------|
| Chrome | 138+ | ✅ Full support | textGeneration, embedding, imageGeneration |
| Chrome | <138 | ❌ Not available | Server-side AI only |
| Firefox | Any | ❌ Not available | Server-side AI only |
| Safari | Any | ❌ Not available | Server-side AI only |
| Edge | Any | ❌ Not available | Server-side AI only |

### Privacy and Security

- **Client-side processing**: Data never leaves your browser when using client-side AI
- **No tracking**: Chrome's built-in AI doesn't track or store your queries
- **User control**: You can disable client-side AI at any time
- **Fallback security**: Server-side AI uses your configured API keys and follows your privacy settings

### Performance Benefits

- **Reduced latency**: Client-side AI eliminates network round-trips
- **Lower costs**: Reduces API calls to external AI services
- **Better availability**: Works even when external AI services are down
- **Scalability**: Reduces server load for simple operations

See the [Chrome AI API documentation](https://developer.chrome.com/docs/ai/built-in-apis) for more details about the underlying APIs.