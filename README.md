<p align="center">
  <img src="papayalogo.png" alt="Papaya Assist" width="120">
</p>

# Papaya Assist -- AI Chatbot for Websites

An open-source, embeddable AI chatbot powered by RAG (Retrieval-Augmented Generation). Upload your documents and the chatbot answers visitor questions based on your content.

Two integration paths depending on your platform:

- **Any website** -- add a chat widget with a single `<script>` tag
- **WordPress** -- install a plugin, sign up, upload docs, done

---

## For Any Website (HTML Embed)

### Quick start

1. [Sign up](https://weng.ca/get-started) to get your tenant ID and embed code, or open `demo/index.html` to try the widget locally.
2. Paste the embed code before `</body>` in your HTML:

```html
<script
  src="https://your-widget-host/chatbot-widget.min.js"
  data-stream-url="https://your-stream-endpoint/"
  data-tenant-id="your-tenant-id"
  data-title="Chat with us"
  data-primary-color="#0073e6">
</script>
```

A chat bubble appears in the bottom-right corner. Visitors click it, type a question, and get an answer from your documents.

### Features

- Single script tag -- no dependencies, no build step
- Streaming responses -- real-time token-by-token replies via SSE
- Shadow DOM isolation -- widget styles never conflict with your page
- Mobile responsive -- full-screen on small viewports
- Customizable colors and title via `data-*` attributes

### Configuration attributes

| Attribute          | Required | Default        | Description                                                        |
|--------------------|----------|----------------|--------------------------------------------------------------------|
| data-stream-url    | Yes*     | ""             | Streaming SSE endpoint URL. Responses stream token-by-token.       |
| data-api-url       | Yes*     | ""             | REST chat endpoint URL. Fallback when `data-stream-url` is not set.|
| data-tenant-id     | Yes      | ""             | Your tenant identifier (assigned during sign-up).                  |
| data-title         | No       | "Chat with us" | Text shown in the header bar and the floating label.               |
| data-primary-color | No       | "#4f46e5"      | Hex color for the bubble, header, send button, and user messages.  |
| data-user-token    | No       | ""             | Optional token for authenticated users (per-user tracking).        |

*Provide at least one of `data-stream-url` or `data-api-url`. If both are set, streaming is used.

### Examples

**Minimal (streaming):**

```html
<script
  src="/chatbot-widget.min.js"
  data-stream-url="https://your-stream-endpoint/"
  data-tenant-id="my-site">
</script>
```

**Fully customized:**

```html
<script
  src="/chatbot-widget.min.js"
  data-api-url="https://your-rest-api/chat"
  data-stream-url="https://your-stream-endpoint/"
  data-tenant-id="my-site"
  data-title="Hi! Ask me anything"
  data-primary-color="#1a7f37"
  data-user-token="usr_abc123">
</script>
```

**REST-only (no streaming):**

```html
<script
  src="/chatbot-widget.min.js"
  data-api-url="https://your-rest-api/chat"
  data-tenant-id="my-site">
</script>
```

### How the widget works

1. The script creates a `<div>` with a closed Shadow DOM at the end of `<body>`.
2. A floating chat bubble appears in the bottom-right corner.
3. Clicking the bubble opens a 380x520px chat window (full-screen on mobile).
4. Messages are sent to the configured endpoint as JSON:
   ```json
   {
     "message": "What are your business hours?",
     "history": [/* last 10 messages */],
     "tenant_id": "acme-corp"
   }
   ```
5. Streaming responses arrive as SSE: `data: {"token": "We"}`, `data: {"token": " are"}`, ...
6. REST responses arrive as: `{"reply": "We are open 9-5 Monday through Friday."}`

### Managing your chatbot

After [signing up](https://weng.ca/get-started), you can:

- Upload documents (PDF, DOCX, TXT) to teach your chatbot
- Process documents to index them for search
- View your embed code with the correct tenant ID
- Upgrade your plan at [weng.ca/pricing](https://weng.ca/pricing)

---

## For WordPress

The WordPress plugin provides a complete no-code experience. No embed code, no manual configuration.

### Requirements

- WordPress 5.8+
- PHP 7.4+

### Installation

1. Download [`wordpress-plugin/bitesize-chatbot.zip`](wordpress-plugin/bitesize-chatbot.zip).
2. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload the zip.
3. Activate the plugin.
4. Go to **Settings > Bitesize Chatbot**.

### Setup

1. **Sign up** -- click "Sign Up / Log In" on the settings page. A popup opens where you create an account with Google or email/password. Your site is automatically assigned a tenant ID based on your domain (e.g., `example-com`).

2. **Upload documents** -- a Documents section appears on the settings page. Upload PDF, DOCX, or TXT files containing the knowledge your chatbot should use.

3. **Process documents** -- click "Process Documents" to index your files into the vector database.

4. **Customize** -- adjust the widget title, primary color, and enable/disable toggle.

5. **Done** -- the chat widget appears on all public pages of your site.

### Plugin settings

| Setting        | Default        | Description                                   |
|----------------|----------------|-----------------------------------------------|
| Widget Title   | "Chat with us" | Displayed in the chat header and bubble label |
| Primary Color  | "#4f46e5"      | Hex color for the widget theme                |
| Enable Chatbot | Checked        | Show/hide the widget on the frontend          |

### Features

- One-click sign-up directly from WordPress admin
- Document upload and management without leaving the dashboard
- Streaming AI responses
- Automatic tenant ID from your domain
- No manual API keys or endpoint configuration needed
- Presigned S3 uploads -- files go directly from your browser to storage, not through your WordPress server
- Nonce-verified AJAX for all API communication

### How the plugin works

- On activation, a `tenant_id` is generated from your site's domain.
- The settings page opens a popup to the auth page for sign-up/login. Credentials are returned via `postMessage` and stored in `wp_options`.
- The widget JS is inlined in `wp_footer` with config injected via `window.__bitesize`.
- All backend URLs are pre-configured -- the plugin connects to the hosted Papaya Assist API.

---

## Architecture

```
Visitor -> Widget (JS) -> API Gateway (REST) -----> Lambda (Rust) -> OpenAI
                       -> Function URL (Stream) --> Lambda (Rust)       |
                                                        |           Pinecone
                                                    DynamoDB
                                                 (tenant config)
```

| Component        | Location                             | Technology                            |
|------------------|--------------------------------------|---------------------------------------|
| Chat widget      | `frontend/`                          | Vanilla JS, Shadow DOM                |
| Chat REST API    | `backend/chat-rust/chat-rest/`       | Rust, lambda_http                     |
| Chat streaming   | `backend/chat-rust/chat-stream/`     | Rust, axum SSE, Lambda Web Adapter    |
| Admin API        | `backend/chat-rust/admin-rust/`      | Rust, axum, Lambda Web Adapter        |
| Shared library   | `backend/chat-rust/shared/`          | Rust (DynamoDB, S3, OpenAI, Pinecone) |
| WordPress plugin | `wordpress-plugin/bitesize-chatbot/` | PHP                                   |
| Infrastructure   | `backend/template.yaml`              | AWS SAM                               |

---

## Self-Hosting

If you want to run the entire stack yourself instead of using the hosted service.

### Prerequisites

- Rust (stable) + `cargo-lambda`
- Node.js (for widget minification)
- AWS CLI + AWS SAM CLI
- Docker (for Lambda image builds)
- OpenAI API key
- Pinecone API key + index

### Backend

1. Copy `.env.example` to `.env` and fill in your keys:

   ```
   OPENAI_API_KEY=sk-proj-...
   S3_BUCKET_NAME=your-bucket-name
   AWS_REGION=us-east-1
   PINECONE_API_KEY=pcsk_...
   ```

2. Build and deploy:

   ```bash
   cd backend
   sam build
   sam deploy --resolve-image-repos
   ```

3. Note the outputs: `ChatApiUrl`, `StreamFunctionUrl`, `AdminFunctionUrl`.

### Widget hosting

Upload the minified widget to S3, a CDN, or any static file host:

```bash
aws s3 cp frontend/chatbot-widget.min.js s3://your-bucket/chatbot-widget.min.js
```

### Multi-tenant configuration

Each tenant is a row in DynamoDB with:

- `tenant_id` -- unique identifier, also the Pinecone namespace
- `display_name` -- human-readable name
- `system_prompt` -- must contain `{context}` where retrieved document content is injected
- `tier` -- plan level (`free`, `standard`, `pro`)

Tenants are created via the [onboarding page](https://weng.ca/get-started) or the admin API.

---

## Customizing the Widget

Edit `frontend/chatbot-widget.js`:

- **Styles** -- the `styles` template literal contains all CSS
- **Layout** -- the `html` template literal contains the markup
- **Behavior** -- functions at the bottom handle messaging, history, and streaming

After editing, rebuild:

```bash
npm run build
```

## Project Structure

```
papaya-assist/
├── frontend/
│   ├── chatbot-widget.js          # Widget source
│   └── chatbot-widget.min.js      # Minified widget
├── demo/
│   └── index.html                 # Demo page showing how to embed the widget
├── wordpress-plugin/
│   └── bitesize-chatbot/          # WordPress plugin
│       ├── bitesize-chatbot.php
│       ├── includes/
│       │   ├── class-bitesize-settings.php
│       │   ├── class-bitesize-documents.php
│       │   └── class-bitesize-widget.php
│       └── assets/
│           ├── js/chatbot-widget.js
│           ├── js/admin-settings.js
│           ├── js/admin-documents.js
│           └── css/admin.css
├── package.json                   # npm build script
└── LICENSE                        # MIT
```

## License

MIT
