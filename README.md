# WP SmartChat 🤖

A floating AI-powered chatbot plugin for WordPress that answers visitor questions using your website's own content.

## Features

- **Floating chat widget** — clean, modern design (ApexChat-style)
- **3 modes**: Local keyword matching (no API needed), OpenAI, or Anthropic
- **Auto-indexes** your pages and posts for instant answers
- **Conversation history** — context-aware follow-up questions
- **Mobile responsive** — works perfectly on phones and tablets
- **Customizable** — colors, position, bot name, welcome message
- **Rate limiting** — protects against spam (20 req/min per IP)
- **Lightweight** — vanilla JS, no jQuery dependency on the frontend

## Installation

1. **Zip the plugin folder:**
   ```
   zip -r wp-smartchat.zip wp-smartchat/
   ```

2. **Upload to WordPress:**
   - Go to **Plugins → Add New → Upload Plugin**
   - Choose the `.zip` file and click **Install Now**
   - Click **Activate**

3. **Configure:**
   - Go to **Settings → WP SmartChat**
   - Set your bot name, welcome message, and colors
   - Choose your AI provider (or stick with Local mode)
   - Click **Save Settings**

## AI Provider Setup

### Local Mode (Default)
No API key needed. Uses keyword matching against your indexed content. Good for simple Q&A about your site.

### OpenAI Mode
1. Get an API key from [platform.openai.com](https://platform.openai.com)
2. Select "OpenAI" as the provider in settings
3. Paste your API key

### Anthropic (Claude) Mode
1. Get an API key from [console.anthropic.com](https://console.anthropic.com)
2. Select "Anthropic" as the provider in settings
3. Paste your API key

## File Structure

```
wp-smartchat/
├── wp-smartchat.php              # Main plugin file
├── includes/
│   ├── class-wpsc-content-indexer.php  # Indexes site content
│   ├── class-wpsc-chat-engine.php      # Answers questions
│   ├── class-wpsc-admin.php            # Settings page
│   ├── class-wpsc-frontend.php         # Widget HTML + asset loading
│   └── class-wpsc-ajax.php             # AJAX message handler
├── assets/
│   ├── css/
│   │   └── chat-widget.css       # Widget styles
│   └── js/
│       └── chat-widget.js        # Widget interactivity
└── README.md
```

## Roadmap

### Phase 2 (Coming Next)
- [ ] Conversation memory with session storage
- [ ] Quick reply suggestion buttons
- [ ] Admin chat log viewer
- [ ] Custom training data (FAQs, knowledge base)

### Phase 3
- [ ] Lead capture (email collection)
- [ ] Analytics dashboard (popular questions, satisfaction)
- [ ] Multi-language support
- [ ] Webhook integrations (Slack, email notifications)
- [ ] WooCommerce product search integration
