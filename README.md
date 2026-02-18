# Intelligize Chat 🤖

An AI-powered floating chatbot plugin for WordPress that answers visitor questions using your website's own content.

## Features

- **Floating chat widget** — clean, modern design with smooth animations
- **Draggable & dockable** — pop the chat window out and drag it anywhere on screen *(v2.3.0)*
- **3 AI modes**: Local keyword matching (no API needed), OpenAI (GPT-4o-mini), or Anthropic (Claude)
- **Auto-indexes** your pages and posts for instant answers
- **Lead capture** — collect name, email, phone before chat starts
- **Contact buttons** — email, phone, SMS, and contact page links in chat
- **Smart linking** — auto-detects emails, phone numbers, and addresses in bot responses
- **Quick reply suggestions** — configurable prompt buttons
- **Conversation history** — context-aware follow-up questions
- **Chat logging** — full transcript storage with search, export CSV
- **Session tracking** — visitor IP, page URL, message counts
- **Mobile responsive** — full-screen chat on phones, toggle visibility
- **Customizable** — colors, position, bot name, avatar, welcome message
- **Rate limiting** — protects against spam (20 req/min per IP)
- **Lightweight** — vanilla JS, no jQuery dependency on the frontend
- **Auto-updates** — via GitHub releases

## Installation

1. Download the latest release zip
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the `.zip` file and click **Install Now**
4. Click **Activate**
5. Go to **Intelligize Chat → Settings** to configure

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

## Companion Plugin: Intelligize Stats

A separate analytics plugin that adds advanced dashboards, lead management, and notifications under the Intelligize Chat menu. See the Intelligize Stats plugin for details.

## File Structure

```
intelligize-chat/
├── wp-smartchat.php                    # Main plugin file
├── includes/
│   ├── class-wpsc-admin.php           # Admin settings + chat logs UI
│   ├── class-wpsc-frontend.php        # Widget HTML + asset loading
│   ├── class-wpsc-ajax.php            # AJAX message handler
│   ├── class-wpsc-chat-engine.php     # AI answer engine (local/OpenAI/Anthropic)
│   ├── class-wpsc-chat-logger.php     # Chat session & message storage
│   ├── class-wpsc-content-indexer.php # Indexes site content for search
│   └── class-wpsc-github-updater.php  # Auto-update from GitHub releases
├── assets/
│   ├── css/
│   │   └── chat-widget.css            # Widget styles (incl. drag/dock)
│   ├── js/
│   │   └── chat-widget.js             # Widget interactivity + drag system
│   └── images/
│       ├── avatar-bot.svg             # Robot avatar
│       ├── avatar-male.svg            # Male avatar
│       └── avatar-female.svg          # Female avatar
└── README.md
```

## Changelog

### v2.3.0 — Draggable & Dockable Chat Window
- **New:** Pop-out button in chat header to undock the window
- **New:** Drag the chat window anywhere on screen by its header
- **New:** Snap-to-dock — drag back near the corner to re-dock, with visual guide
- **New:** Viewport clamping — window can't be dragged off-screen
- **New:** Drag handle indicator (subtle bar) appears when undocked
- **Changed:** Header now contains a button group (dock + close)
- **Changed:** Dragging disabled on mobile (stays full-screen)
- **Changed:** Version bump across all files

### v2.2.2 — Lead Capture & Contact Buttons
- **New:** Pre-chat lead capture form (name, email, phone — configurable required fields)
- **New:** Contact action buttons (email, phone, SMS, contact page link)
- **New:** Smart auto-linking of emails, phone numbers, and addresses in bot responses
- **New:** "Return to Start" button after each bot response
- **New:** Chat logging with admin viewer, search, and CSV export
- **New:** Session tracking (visitor IP, page URL, timestamps)
- **New:** Avatar selection (robot, male, female)
- **New:** Enable/disable toggle switch on settings page
- **New:** Stats bar on settings page (total chats, today, leads, messages)
- **New:** Auto-open chat window option with configurable delay
- **New:** Page visibility controls (all pages, homepage only, exclude specific pages)
- **New:** Mobile show/hide toggle
- **New:** GitHub auto-updater

### v2.0.0 — Modern Widget Redesign
- Complete CSS rewrite with scoped reset to survive theme conflicts
- Smooth open/close animations with spring physics
- Pulse animation on toggle button
- Typing indicator with bouncing dots
- Quick reply suggestion buttons
- Markdown rendering (bold, links)
- Mobile-responsive full-screen chat
- Source links on bot responses

### v1.0.0 — Initial Release
- Basic floating chat widget
- Local keyword matching engine
- OpenAI and Anthropic API support
- Auto-indexing of pages and posts
- Admin settings page
- Rate limiting

## Roadmap

- [ ] WooCommerce product search integration
- [ ] Multi-language support
- [ ] Custom training data (FAQs, knowledge base)
- [ ] Resizable chat window
- [ ] Chat window themes/skins
