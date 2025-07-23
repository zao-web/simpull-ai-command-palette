<?php
/**
 * AI Command Palette Settings Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['aicp_save_settings']) && wp_verify_nonce($_POST['aicp_settings_nonce'], 'aicp_settings')) {
    $settings = [
        'api_provider' => sanitize_text_field($_POST['api_provider'] ?? 'openai'),
        'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
        'ai_model' => sanitize_text_field($_POST['ai_model'] ?? 'gpt-4'),
        'enable_frontend' => !empty($_POST['enable_frontend']),
        'enable_personalization' => !empty($_POST['enable_personalization'])
    ];

    update_option('aicp_settings', $settings);

    // Save per-user shortcut
    $user_id = get_current_user_id();
    if (!empty($_POST['keyboard_shortcut'])) {
        update_user_meta($user_id, 'aicp_palette_shortcut', sanitize_text_field($_POST['keyboard_shortcut']));
    }

    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'ai-command-palette') . '</p></div>';
}

// Test connection if requested
if (isset($_POST['aicp_test_connection']) && wp_verify_nonce($_POST['aicp_settings_nonce'], 'aicp_settings')) {
    $test_provider = sanitize_text_field($_POST['api_provider'] ?? 'openai');
    $test_api_key = sanitize_text_field($_POST['api_key'] ?? '');

    if (empty($test_api_key)) {
        echo '<div class="notice notice-error"><p>' . __('Please enter an API key to test the connection.', 'ai-command-palette') . '</p></div>';
    } else {
        // Create a temporary AI service with the form values
        $temp_settings = [
            'api_provider' => $test_provider,
            'api_key' => $test_api_key,
            'ai_model' => 'gpt-3.5-turbo' // Default for testing
        ];

        // Temporarily override the settings
        $original_settings = get_option('aicp_settings', []);
        update_option('aicp_settings', $temp_settings);

        $ai_service = new \AICP\AI\AI_Service();
        $test_result = $ai_service->test_connection();

        // Restore original settings
        update_option('aicp_settings', $original_settings);

        if ($test_result['success']) {
            echo '<div class="notice notice-success"><p>' . esc_html($test_result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($test_result['message']) . '</p></div>';
        }
    }
}

// Get current settings
$settings = get_option('aicp_settings', [
    'api_provider' => 'openai',
    'api_key' => '',
    'ai_model' => 'gpt-4',
    'enable_frontend' => true,
    'enable_personalization' => true
]);

// Ensure ai_model is set
if (!isset($settings['ai_model'])) {
    $settings['ai_model'] = 'gpt-4';
}

// Get per-user shortcut, fallback to global if not set
$user_id = get_current_user_id();
$user_shortcut = get_user_meta($user_id, 'aicp_palette_shortcut', true);
if (!$user_shortcut) {
    $user_shortcut = $settings['keyboard_shortcut'] ?? 'cmd+k,ctrl+k';
}

// Function to get available models for a provider
function aicp_get_available_models($provider) {
    switch ($provider) {
        case 'openai':
            return [
                'gpt-4' => 'GPT-4 (Recommended)',
                'gpt-4-turbo-preview' => 'GPT-4 Turbo',
                'gpt-4o' => 'GPT-4o (Latest)',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Faster, Less Accurate)'
            ];
        case 'anthropic':
            return [
                'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Recommended)',
                'claude-3-opus-20240229' => 'Claude 3 Opus (Most Capable)',
                'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (Balanced)',
                'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fastest)'
            ];
        default:
            return [];
    }
}

// Get usage statistics
$context_engine = new \AICP\Core\Context_Engine();
$usage_stats = $context_engine->get_usage_stats();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('aicp_settings', 'aicp_settings_nonce'); ?>

        <h2 class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php _e('General', 'ai-command-palette'); ?></a>
            <a href="#ai" class="nav-tab" data-tab="ai"><?php _e('AI Configuration', 'ai-command-palette'); ?></a>
            <a href="#usage" class="nav-tab" data-tab="usage"><?php _e('Usage Statistics', 'ai-command-palette'); ?></a>
            <a href="#help" class="nav-tab" data-tab="help"><?php _e('Help', 'ai-command-palette'); ?></a>
        </h2>

        <!-- General Settings -->
        <div id="general-tab" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_frontend"><?php _e('Enable on Frontend', 'ai-command-palette'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="enable_frontend" name="enable_frontend" value="1" <?php checked($settings['enable_frontend']); ?> />
                        <p class="description"><?php _e('Allow the command palette to be used on the frontend for logged-in users.', 'ai-command-palette'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="enable_personalization"><?php _e('Enable Personalization', 'ai-command-palette'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="enable_personalization" name="enable_personalization" value="1" <?php checked($settings['enable_personalization']); ?> />
                        <p class="description"><?php _e('Track usage patterns to provide personalized command suggestions.', 'ai-command-palette'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="keyboard_shortcut"><?php _e('Keyboard Shortcut', 'ai-command-palette'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="keyboard_shortcut" name="keyboard_shortcut" value="<?php echo esc_attr($user_shortcut); ?>" class="regular-text" />
                        <p class="description"><?php _e('Keyboard shortcut to open the command palette (default: cmd+k on Mac, ctrl+k on Windows/Linux).', 'ai-command-palette'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- AI Configuration -->
        <div id="ai-tab" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_provider"><?php _e('AI Provider', 'ai-command-palette'); ?></label>
                    </th>
                    <td>
                        <select id="api_provider" name="api_provider">
                            <option value="openai" <?php selected($settings['api_provider'], 'openai'); ?>>OpenAI (GPT-4)</option>
                            <option value="anthropic" <?php selected($settings['api_provider'], 'anthropic'); ?>>Anthropic (Claude)</option>
                        </select>
                        <p class="description"><?php _e('Select your preferred AI provider.', 'ai-command-palette'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="api_key"><?php _e('API Key', 'ai-command-palette'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Enter your API key from your AI provider.', 'ai-command-palette'); ?>
                            <span id="openai-help" <?php echo $settings['api_provider'] !== 'openai' ? 'style="display:none;"' : ''; ?>>
                                <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('Get OpenAI API Key', 'ai-command-palette'); ?></a>
                            </span>
                            <span id="anthropic-help" <?php echo $settings['api_provider'] !== 'anthropic' ? 'style="display:none;"' : ''; ?>>
                                <a href="https://console.anthropic.com/account/keys" target="_blank"><?php _e('Get Anthropic API Key', 'ai-command-palette'); ?></a>
                            </span>
                        </p>
                    </td>
                </tr>

                <tr id="ai-model-row" <?php echo $settings['api_provider'] !== 'openai' && $settings['api_provider'] !== 'anthropic' ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="ai_model"><?php _e('AI Model', 'ai-command-palette'); ?></label>
                    </th>
                    <td>
                        <select id="ai_model" name="ai_model">
                            <?php
                            $available_models = aicp_get_available_models($settings['api_provider']);
                            foreach ($available_models as $model_id => $model_name):
                            ?>
                                <option value="<?php echo esc_attr($model_id); ?>" <?php selected($settings['ai_model'], $model_id); ?>><?php echo esc_html($model_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the AI model to use for natural language processing.', 'ai-command-palette'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"></th>
                    <td>
                        <button type="submit" name="aicp_test_connection" class="button button-secondary"><?php _e('Test Connection', 'ai-command-palette'); ?></button>
                        <span class="spinner" style="float: none;"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Usage Statistics -->
        <div id="usage-tab" class="tab-content" style="display: none;">
            <h3><?php _e('Your Usage Statistics', 'ai-command-palette'); ?></h3>

            <div class="aicp-stats-grid">
                <div class="aicp-stat-box">
                    <h4><?php _e('Total Commands', 'ai-command-palette'); ?></h4>
                    <p class="aicp-stat-number"><?php echo esc_html($usage_stats['total_commands']); ?></p>
                </div>

                <div class="aicp-stat-box">
                    <h4><?php _e('Success Rate', 'ai-command-palette'); ?></h4>
                    <p class="aicp-stat-number"><?php echo esc_html($usage_stats['success_rate']); ?>%</p>
                </div>
            </div>

            <?php if (!empty($usage_stats['most_used_commands'])): ?>
            <h4><?php _e('Most Used Commands', 'ai-command-palette'); ?></h4>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Command', 'ai-command-palette'); ?></th>
                        <th><?php _e('Usage Count', 'ai-command-palette'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($usage_stats['most_used_commands'], 0, 10, true) as $command_id => $count): ?>
                    <tr>
                        <td><?php echo esc_html($command_id); ?></td>
                        <td><?php echo esc_html($count); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <p style="margin-top: 20px;">
                <button type="button" class="button" onclick="if(confirm('<?php esc_attr_e('Are you sure you want to clear your usage data?', 'ai-command-palette'); ?>')) { window.location.href='<?php echo esc_url(add_query_arg('aicp_clear_data', '1')); ?>'; }"><?php _e('Clear My Usage Data', 'ai-command-palette'); ?></button>
            </p>
        </div>

        <!-- Help -->
        <div id="help-tab" class="tab-content" style="display: none;">
            <h3><?php _e('Getting Started', 'ai-command-palette'); ?></h3>
            <p><?php _e('The AI Command Palette allows you to control your WordPress site using natural language commands.', 'ai-command-palette'); ?></p>

            <h4><?php _e('How to Use', 'ai-command-palette'); ?></h4>
            <ol>
                <li><?php _e('Press Cmd+K (Mac) or Ctrl+K (Windows/Linux) to open the command palette', 'ai-command-palette'); ?></li>
                <li><?php _e('Type your command in natural language', 'ai-command-palette'); ?></li>
                <li><?php _e('Press Enter to execute or select from suggestions', 'ai-command-palette'); ?></li>
            </ol>

            <h4><?php _e('Example Commands', 'ai-command-palette'); ?></h4>
            <ul>
                <li><code><?php _e('Create a new blog post about WordPress', 'ai-command-palette'); ?></code></li>
                <li><code><?php _e('Deactivate the Akismet plugin', 'ai-command-palette'); ?></code></li>
                <li><code><?php _e('Change site title to My Awesome Site', 'ai-command-palette'); ?></code></li>
                <li><code><?php _e('Show me the latest 5 draft posts', 'ai-command-palette'); ?></code></li>
                <li><code><?php _e('Copy the About Us page and replace Mary with Jane', 'ai-command-palette'); ?></code></li>
            </ul>

            <h4><?php _e('Supported Actions', 'ai-command-palette'); ?></h4>
            <ul>
                <li><?php _e('Create, edit, and delete posts/pages', 'ai-command-palette'); ?></li>
                <li><?php _e('Manage plugins (activate/deactivate)', 'ai-command-palette'); ?></li>
                <li><?php _e('Update site settings', 'ai-command-palette'); ?></li>
                <li><?php _e('Search and navigate content', 'ai-command-palette'); ?></li>
                <li><?php _e('Clear cache', 'ai-command-palette'); ?></li>
                <li><?php _e('And much more!', 'ai-command-palette'); ?></li>
            </ul>
        </div>

        <p class="submit">
            <input type="submit" name="aicp_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-command-palette'); ?>" />
        </p>
    </form>
</div>

<style>
.aicp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.aicp-stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    text-align: center;
}

.aicp-stat-box h4 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.aicp-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin: 0;
}

.nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').hide();
        $('#' + $(this).data('tab') + '-tab').show();
    });

    // Cache for fetched models
    var modelCache = {};

        // Function to fetch models from API
    function fetchModels(provider, apiKey) {
        if (!apiKey) {
            return Promise.reject('API key required');
        }

        // Check cache first
        if (modelCache[provider + '_' + apiKey]) {
            return Promise.resolve(modelCache[provider + '_' + apiKey]);
        }

        return jQuery.ajax({
            url: '<?php echo esc_url(rest_url('ai-command-palette/v1/models')); ?>',
            method: 'GET',
            data: {
                provider: provider,
                api_key: apiKey
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            }
        }).then(function(response) {
            if (response.success) {
                // Cache the results
                modelCache[provider + '_' + apiKey] = response.models;
                return response.models;
            } else {
                throw new Error(response.message || 'Failed to fetch models');
            }
        });
    }

    // Show/hide provider-specific options and update models
    $('#api_provider').on('change', function() {
        var provider = $(this).val();
        var modelSelect = $('#ai_model');
        var apiKey = $('#api_key').val();

        // Show/hide help links
        if (provider === 'openai') {
            $('#openai-help').show();
            $('#anthropic-help').hide();
        } else if (provider === 'anthropic') {
            $('#openai-help').hide();
            $('#anthropic-help').show();
        }

        // Show/hide model row and update options
        if (provider === 'openai' || provider === 'anthropic') {
            $('#ai-model-row').show();

            // Clear existing options and show loading
            modelSelect.empty();
            modelSelect.append($('<option>', {
                value: '',
                text: 'Loading models...'
            }));

            // Fetch models from API
            fetchModels(provider, apiKey).then(function(models) {
                modelSelect.empty();

                // Add fetched models
                Object.keys(models).forEach(function(modelId) {
                    modelSelect.append($('<option>', {
                        value: modelId,
                        text: models[modelId]
                    }));
                });

                // Set default model for the provider
                if (provider === 'openai') {
                    modelSelect.val('gpt-4');
                } else if (provider === 'anthropic') {
                    modelSelect.val('claude-3-5-sonnet-20241022');
                }
            }).catch(function(error) {
                modelSelect.empty();
                modelSelect.append($('<option>', {
                    value: '',
                    text: 'Error loading models: ' + error.message
                }));
                console.error('Failed to fetch models:', error);
            });
        } else {
            $('#ai-model-row').hide();
        }
    });

    // Also fetch models when API key changes
    $('#api_key').on('input', function() {
        var provider = $('#api_provider').val();
        var apiKey = $(this).val();

        if ((provider === 'openai' || provider === 'anthropic') && apiKey.length > 10) {
            // Debounce the API call
            clearTimeout(window.modelFetchTimeout);
            window.modelFetchTimeout = setTimeout(function() {
                $('#api_provider').trigger('change');
            }, 500);
        }
    });

    // On page load, fetch models if provider and API key are present
    var initialProvider = $('#api_provider').val();
    var initialApiKey = $('#api_key').val();
    var initialModel = '<?php echo esc_js($settings['ai_model']); ?>';
    var modelSelect = $('#ai_model');

    if ((initialProvider === 'openai' || initialProvider === 'anthropic') && initialApiKey.length > 10) {
        // Show loading
        modelSelect.empty();
        modelSelect.append($('<option>', {
            value: '',
            text: 'Loading models...'
        }));
        fetchModels(initialProvider, initialApiKey).then(function(models) {
            modelSelect.empty();
            Object.keys(models).forEach(function(modelId) {
                modelSelect.append($('<option>', {
                    value: modelId,
                    text: models[modelId]
                }));
            });
            // Set to saved model if available
            if (models[initialModel]) {
                modelSelect.val(initialModel);
            } else {
                // Otherwise, select the first model
                modelSelect.prop('selectedIndex', 0);
            }
        }).catch(function(error) {
            modelSelect.empty();
            modelSelect.append($('<option>', {
                value: '',
                text: 'Error loading models: ' + error.message
            }));
            console.error('Failed to fetch models:', error);
        });
    }
});
// Listen for real-time shortcut updates from the modal
window.addEventListener('aicp-shortcut-updated', function(e) {
    var shortcut = e.detail.shortcut;
    var input = document.getElementById('keyboard_shortcut');
    if (input) {
        input.value = shortcut;
    }
});
</script>