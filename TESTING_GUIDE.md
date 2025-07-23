# AI Command Palette - Comprehensive Testing Guide

## Overview
This guide provides step-by-step testing procedures for the AI Command Palette plugin. Test all features systematically to ensure everything works as expected.

## Pre-Testing Setup

### 1. Environment Requirements
- WordPress 6.0+ installation
- PHP 8.0+
- Modern browser (Chrome 138+ for full client-side AI features)
- AI API key (OpenAI or Anthropic) for server-side AI features
- Test plugins: WooCommerce, Yoast SEO, Contact Form 7 (optional)

### 2. Installation Steps
1. Upload the plugin to `/wp-content/plugins/ai-command-palette/`
2. Activate the plugin in WordPress admin
3. Go to Settings → AI Command Palette
4. Configure your AI API key and preferred model
5. Test the connection

## Core Functionality Testing

### Test 1: Basic Command Palette Access
**Objective**: Verify the command palette opens and is accessible globally

**Steps**:
1. Log in as an administrator
2. Navigate to different areas of WordPress admin (Dashboard, Posts, Pages, etc.)
3. Press `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux)
4. Verify the command palette opens with a modal overlay
5. Test on frontend while logged in (should also work)
6. Verify the palette closes when pressing `Esc`

**Expected Results**:
- ✅ Command palette opens consistently across all admin pages
- ✅ Works on frontend for logged-in users
- ✅ Modal overlay appears with proper styling
- ✅ Closes properly with Escape key

### Test 2: Keyboard Navigation
**Objective**: Test keyboard navigation within the command palette

**Steps**:
1. Open the command palette
2. Use arrow keys to navigate through suggestions
3. Press `Enter` to execute a command
4. Test `Tab` for focus trapping
5. Verify `Escape` closes the palette

**Expected Results**:
- ✅ Arrow keys navigate through suggestions
- ✅ Enter executes selected command
- ✅ Tab cycles through focusable elements
- ✅ Escape closes the palette
- ✅ Focus is trapped within the modal

### Test 3: Basic Search Functionality
**Objective**: Test basic search and command discovery

**Steps**:
1. Open the command palette
2. Type "post" - should show post-related commands
3. Type "plugin" - should show plugin-related commands
4. Type "user" - should show user-related commands
5. Test fuzzy search with typos (e.g., "plgin" should find "plugin")

**Expected Results**:
- ✅ Search results appear as you type
- ✅ Commands are grouped by category
- ✅ Fuzzy search works with typos
- ✅ Results update in real-time

### Test 4: AI Integration Testing
**Objective**: Test AI-powered natural language processing

**Steps**:
1. Open the command palette
2. Type natural language queries:
   - "Create a new blog post about WordPress"
   - "Show me all pages"
   - "Deactivate the Akismet plugin"
   - "What's the site title?"
3. Verify AI interprets and executes these commands

**Expected Results**:
- ✅ AI interprets natural language queries
- ✅ Commands are executed correctly
- ✅ Results are displayed appropriately
- ✅ Error handling works for invalid requests

### Test 5: Chrome Built-in AI Testing (Chrome 138+)
**Objective**: Test client-side AI features in supported browsers

**Steps**:
1. Use Chrome 138+ browser
2. Open the command palette
3. Go to Settings in the palette
4. Verify "Client-side AI" option is available and enabled
5. Test simple queries to see if client-side AI is used
6. Check browser console for AI usage indicators

**Expected Results**:
- ✅ Client-side AI option appears in settings
- ✅ Shows browser compatibility information
- ✅ Faster response times for simple queries
- ✅ Privacy indicator shows "client-side" processing

### Test 6: Fallback Testing (Non-Chrome Browsers)
**Objective**: Test fallback to server-side AI in unsupported browsers

**Steps**:
1. Use Firefox, Safari, or Edge
2. Open the command palette
3. Go to Settings
4. Verify client-side AI option is disabled/grayed out
5. Test natural language queries
6. Verify server-side AI is used

**Expected Results**:
- ✅ Client-side AI option is disabled
- ✅ Server-side AI processes queries
- ✅ Clear indication of AI source used
- ✅ Graceful degradation works

## Advanced Feature Testing

### Test 7: Contextual Suggestions
**Objective**: Test personalized command suggestions

**Steps**:
1. Open the command palette without typing anything
2. Verify contextual suggestions appear
3. Execute a few commands
4. Reopen the palette and check if recent commands appear
5. Test role-based suggestions (switch between admin/editor roles)

**Expected Results**:
- ✅ Contextual suggestions appear on empty search
- ✅ Recent commands are prioritized
- ✅ Role-appropriate suggestions are shown
- ✅ Suggestions update based on usage patterns

### Test 8: Plugin Integration Testing
**Objective**: Test automatic plugin capability discovery

**Steps**:
1. Install and activate WooCommerce
2. Open the command palette
3. Type "order" or "product" - should show WooCommerce commands
4. Test WooCommerce-specific commands:
   - "Show me recent orders"
   - "Create a new product"
5. Repeat with other plugins (Yoast SEO, Contact Form 7)

**Expected Results**:
- ✅ Plugin commands are automatically discovered
- ✅ WooCommerce commands work correctly
- ✅ Plugin-specific functionality is available
- ✅ Commands respect user permissions

### Test 9: Data Visualization Testing
**Objective**: Test chart and visualization capabilities

**Steps**:
1. Open the command palette
2. Type queries that should generate charts:
   - "Show me page views for the last month"
   - "Compare this quarter's sales to last year"
   - "Display user registration trends"
3. Verify charts are generated and displayed

**Expected Results**:
- ✅ Charts are generated for appropriate queries
- ✅ Different chart types work (line, bar, pie)
- ✅ Data is formatted correctly
- ✅ Charts are interactive and responsive

### Test 10: Voice Command Testing
**Objective**: Test voice recognition capabilities

**Steps**:
1. Open the command palette
2. Look for voice input option (microphone icon)
3. Click to enable voice recognition
4. Speak commands like "create a new page"
5. Verify voice input is transcribed and executed

**Expected Results**:
- ✅ Voice input option is available
- ✅ Speech is accurately transcribed
- ✅ Voice commands are executed
- ✅ Error handling for unclear speech

### Test 11: Chat Mode Testing
**Objective**: Test conversational AI interface

**Steps**:
1. Open the command palette
2. Look for chat mode option
3. Enter conversational queries:
   - "How do I optimize my site for SEO?"
   - "Can you help me set up a contact form?"
4. Verify multi-turn conversations work

**Expected Results**:
- ✅ Chat mode is accessible
- ✅ Multi-turn conversations work
- ✅ AI provides helpful responses
- ✅ Context is maintained throughout conversation

### Test 12: Multisite Testing
**Objective**: Test multisite functionality (if applicable)

**Steps**:
1. Set up WordPress multisite
2. Activate the plugin network-wide
3. Test network admin commands:
   - "Show me all sites in the network"
   - "Add a new site"
   - "Network activate a plugin"
4. Test site-specific commands

**Expected Results**:
- ✅ Network commands work for super admins
- ✅ Site-specific commands work for site admins
- ✅ Proper permission checking
- ✅ Site switching functionality

## Performance Testing

### Test 13: Response Time Testing
**Objective**: Verify performance meets requirements

**Steps**:
1. Open browser developer tools
2. Open the command palette
3. Time how long it takes to:
   - Open the palette
   - Show search results
   - Execute simple commands
   - Execute complex AI commands
4. Test with different network conditions

**Expected Results**:
- ✅ Palette opens in <200ms
- ✅ Search results appear in <300ms
- ✅ Simple commands execute in <500ms
- ✅ Complex AI commands execute in <2s

### Test 14: Memory and Resource Testing
**Objective**: Test resource usage and memory consumption

**Steps**:
1. Open browser developer tools
2. Monitor memory usage
3. Open and close the palette multiple times
4. Execute various commands
5. Check for memory leaks

**Expected Results**:
- ✅ No significant memory leaks
- ✅ Resource usage remains stable
- ✅ No performance degradation over time

## Security Testing

### Test 15: Permission Testing
**Objective**: Verify proper permission enforcement

**Steps**:
1. Test with different user roles:
   - Administrator
   - Editor
   - Author
   - Contributor
   - Subscriber
2. Try to execute commands beyond user permissions
3. Verify proper error messages

**Expected Results**:
- ✅ Commands respect user roles and capabilities
- ✅ Unauthorized actions are blocked
- ✅ Clear error messages for permission issues
- ✅ No privilege escalation possible

### Test 16: Input Sanitization Testing
**Objective**: Test security against malicious input

**Steps**:
1. Try entering potentially dangerous commands:
   - SQL injection attempts
   - XSS attempts
   - Command injection attempts
2. Verify all input is properly sanitized

**Expected Results**:
- ✅ Malicious input is blocked
- ✅ No security vulnerabilities exploited
- ✅ Proper error handling for invalid input

## Error Handling Testing

### Test 17: Network Error Testing
**Objective**: Test behavior when network is unavailable

**Steps**:
1. Disconnect from internet
2. Try to use AI features
3. Verify graceful fallback to rule-based processing
4. Reconnect and test recovery

**Expected Results**:
- ✅ Graceful fallback when AI is unavailable
- ✅ Clear error messages
- ✅ Basic functionality still works
- ✅ Recovery when connection is restored

### Test 18: API Error Testing
**Objective**: Test handling of API errors

**Steps**:
1. Use invalid API key
2. Test with rate-limited API
3. Test with expired API key
4. Verify proper error handling

**Expected Results**:
- ✅ Clear error messages for API issues
- ✅ Graceful degradation when AI fails
- ✅ Helpful troubleshooting information
- ✅ No crashes or undefined behavior

## Accessibility Testing

### Test 19: Screen Reader Testing
**Objective**: Test accessibility for screen readers

**Steps**:
1. Enable screen reader (NVDA, JAWS, or VoiceOver)
2. Navigate the command palette using only keyboard
3. Verify all elements are properly announced
4. Test with high contrast mode

**Expected Results**:
- ✅ All elements are properly labeled
- ✅ Screen reader announcements are clear
- ✅ Keyboard navigation works completely
- ✅ High contrast mode is supported

### Test 20: Keyboard-Only Testing
**Objective**: Test complete keyboard accessibility

**Steps**:
1. Disable mouse/trackpad
2. Navigate entire interface using only keyboard
3. Execute commands using only keyboard
4. Verify no mouse-dependent functionality

**Expected Results**:
- ✅ Complete keyboard accessibility
- ✅ No mouse-dependent features
- ✅ Clear focus indicators
- ✅ Logical tab order

## Integration Testing

### Test 21: WordPress Core Integration
**Objective**: Test integration with WordPress core features

**Steps**:
1. Test with different WordPress versions (6.0+)
2. Verify compatibility with core updates
3. Test with different themes (block themes, classic themes)
4. Check for conflicts with core features

**Expected Results**:
- ✅ Works with all supported WordPress versions
- ✅ No conflicts with core features
- ✅ Compatible with different themes
- ✅ Follows WordPress coding standards

### Test 22: Plugin Compatibility Testing
**Objective**: Test compatibility with popular plugins

**Steps**:
1. Test with popular plugins:
   - WooCommerce
   - Yoast SEO
   - Contact Form 7
   - Elementor
   - Jetpack
2. Verify no conflicts
3. Test automatic integration

**Expected Results**:
- ✅ No conflicts with popular plugins
- ✅ Automatic integration works
- ✅ Plugin commands are discovered
- ✅ Performance is not degraded

## User Experience Testing

### Test 23: First-Time User Experience
**Objective**: Test experience for new users

**Steps**:
1. Install plugin on fresh WordPress site
2. Activate without configuration
3. Try basic commands
4. Configure AI settings
5. Test advanced features

**Expected Results**:
- ✅ Plugin works out of the box
- ✅ Clear setup instructions
- ✅ Intuitive interface
- ✅ Helpful error messages

### Test 24: Power User Experience
**Objective**: Test experience for advanced users

**Steps**:
1. Test complex multi-step commands
2. Use advanced features
3. Customize settings
4. Test keyboard shortcuts
5. Verify efficiency gains

**Expected Results**:
- ✅ Complex commands work correctly
- ✅ Advanced features are accessible
- ✅ Customization options are available
- ✅ Significant efficiency improvements

### Test 25: End-to-End Workflow Testing
**Objective**: Test complete user workflows using the AI Command Palette

**Steps**:
1. Open the command palette.
2. Type a natural language command to create a new post (e.g., "Create a new blog post about WordPress security").
3. Verify the post is created and appears in the Posts list.
4. Use the palette to edit the post (e.g., "Edit the post titled 'WordPress security'").
5. Add media to the post using a command (e.g., "Add an image to the post").
6. Publish the post using a command (e.g., "Publish the post titled 'WordPress security'").
7. Use the palette to view the published post.
8. Attempt an unauthorized action (e.g., "Delete a post" as a non-admin) and verify proper error handling.
9. Check the audit log (if available) for recorded actions.
10. Repeat the workflow as a different user role (e.g., Editor, Contributor) to verify permissions.

**Expected Results**:
- ✅ Each command is interpreted and executed correctly.
- ✅ The post is created, edited, and published as expected.
- ✅ Media is added successfully.
- ✅ Unauthorized actions are blocked with clear error messages.
- ✅ The workflow is smooth and intuitive.
- ✅ Audit logs (if present) accurately reflect actions taken.
- ✅ Permissions are enforced for different user roles.

### Test 26: "Magical" AI Workflow Functionality Testing
**Objective**: Test the complete AI-powered workflow experience from natural language to execution

**Steps**:
1. Open the command palette
2. Type a natural language query: "Create a new blog post about WordPress security"
3. Verify that the AI generates a workflow plan and shows the workflow UI
4. Check that the workflow plan includes:
   - Step 1: create_post function with extracted parameters (title: "WordPress security")
   - Proper parameter extraction from the natural language query
5. Click "Run Workflow" to execute the plan
6. Verify that the workflow executes successfully and shows progress
7. Confirm that a new post is created with the correct title
8. Test with other natural language queries:
   - "Create a new page called Contact Us"
   - "Update the site title to My Awesome Blog"
   - "Search for posts about plugins"
9. Verify that each query generates an appropriate workflow plan
10. Test error handling by trying an invalid query or one that requires permissions you don't have

**Expected Results**:
- ✅ Natural language queries are interpreted by AI
- ✅ Workflow plans are generated with proper parameter extraction
- ✅ Workflow UI is displayed for user review and approval
- ✅ Workflow execution shows step-by-step progress
- ✅ Commands are executed with extracted parameters
- ✅ Results are displayed appropriately
- ✅ Error handling works for invalid requests or permission issues
- ✅ The experience feels "magical" and intuitive

### Test 27: Dynamic Function Discovery Testing
**Objective**: Test that the AI has access to all available commands and can generate accurate workflow plans

**Steps**:
1. Install and activate WooCommerce plugin
2. Open the command palette
3. Type: "Show me recent orders"
4. Verify that WooCommerce commands are available to the AI
5. Check that the workflow plan includes WooCommerce-specific functions
6. Test with other plugin-specific commands
7. Verify that the AI can access dynamic API endpoints

**Expected Results**:
- ✅ AI has access to all registered commands
- ✅ Plugin-specific commands are available
- ✅ Dynamic API endpoints are discoverable
- ✅ Workflow plans use the most appropriate functions
- ✅ No "unknown function" errors occur

### Test 28: Parameter Extraction Testing
**Objective**: Test that the AI correctly extracts parameters from natural language queries

**Steps**:
1. Test various natural language patterns:
   - "Create a post titled 'My First Post' with content 'Hello world'"
   - "Make a page called About Us that's published"
   - "Add a new user John Smith with email john@example.com as an editor"
   - "Change the site title to 'My Blog' and tagline to 'Welcome to my blog'"
2. Verify that parameters are correctly extracted:
   - Post/page titles
   - Content
   - User details (names, emails, roles)
   - Settings values
   - Status values (draft, publish, etc.)
3. Check that the workflow plan includes all extracted parameters

**Expected Results**:
- ✅ All relevant parameters are extracted from natural language
- ✅ Parameters are correctly mapped to function arguments
- ✅ Default values are applied when parameters are not specified
- ✅ Complex queries with multiple parameters are handled correctly

## Final Verification

### Test 25: End-to-End Workflow Testing
**Objective**: Test complete user workflows

**Steps**:
1. Create a new post using natural language
2. Edit the post using commands
3. Add media using commands
4. Publish and manage the post
5. Verify entire workflow works seamlessly

**Expected Results**:
- ✅ Complete workflows work end-to-end
- ✅ No broken steps in processes
- ✅ Consistent user experience
- ✅ All features integrate properly

## Reporting Issues

When issues are found during testing:

1. **Document the Issue**:
   - Describe the problem clearly
   - Include steps to reproduce
   - Note the environment (browser, WordPress version, etc.)
   - Include screenshots if helpful

2. **Categorize the Issue**:
   - Critical: Blocks core functionality
   - High: Major feature doesn't work
   - Medium: Minor feature issue
   - Low: Cosmetic or minor UX issue

3. **Report Format**:
   ```
   Issue: [Brief description]
   Severity: [Critical/High/Medium/Low]
   Steps to Reproduce:
   1. [Step 1]
   2. [Step 2]
   3. [Step 3]
   Expected Result: [What should happen]
   Actual Result: [What actually happened]
   Environment: [Browser, WordPress version, etc.]
   ```

## Success Criteria

The plugin is ready for production when:

- ✅ All critical and high-priority tests pass
- ✅ Performance meets requirements (<200ms for basic operations)
- ✅ Security tests pass completely
- ✅ Accessibility requirements are met
- ✅ No major conflicts with popular plugins
- ✅ User experience is intuitive and efficient
- ✅ AI integration works reliably
- ✅ Fallback mechanisms work properly

## Conclusion

This comprehensive testing guide ensures all aspects of the AI Command Palette are thoroughly tested before production deployment. Follow each test systematically and document any issues found. The goal is to deliver a robust, secure, and user-friendly tool that significantly enhances WordPress administration efficiency.