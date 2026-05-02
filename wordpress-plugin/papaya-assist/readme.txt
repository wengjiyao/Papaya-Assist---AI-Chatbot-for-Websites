=== Papaya Assist ===
Contributors: wengjiyao
Tags: chatbot, ai, customer support, widget, rag
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chatbot using RAG. Upload documents and let the AI answer visitor questions based on your specific knowledge base.

== Description ==

Papaya Assist adds an AI-powered chat widget to your WordPress site that answers visitor questions based on your own documents. Instead of generic AI responses, the chatbot uses Retrieval-Augmented Generation (RAG) to ground every answer in the content you provide — product guides, FAQs, policy documents, or any other material your visitors need.

Upload your files, click a button to process them, and the widget is live. No coding required, no theme edits, and no shortcodes to place. The chat bubble appears in the corner of every page and works on both desktop and mobile.

**Features:**

* One-click sign-up from within WordPress admin — no separate registration site
* Embeddable chat widget with customizable color and title
* Document upload and management (PDF, DOCX, TXT) directly from your dashboard
* Real-time streaming responses so visitors see answers as they are generated
* Shadow DOM isolation — the widget will not conflict with your theme styles or CSS
* Mobile-responsive design that switches to fullscreen on small screens
* Lightweight: no external CSS or JavaScript libraries loaded on your frontend

**How It Works:**

1. Sign up or log in from the plugin settings page.
2. Upload your documents (PDF, DOCX, or TXT) and click "Process Documents."
3. The chatbot answers visitor questions using RAG based on your documents — no training or fine-tuning needed.

== Third-Party Services ==

This plugin relies on external services hosted by Papaya Assist to provide AI chatbot functionality. **No data is sent to any third-party service until you create an account and activate the chatbot.**

= Papaya Assist Admin API =

* **URL:** `https://34i2s32sx774dqpo6wssfi2jzy0ycwmw.lambda-url.us-east-1.on.aws`
* **When used:** Account authentication, document upload (presigned URL generation), document listing, document deletion, document ingestion, usage retrieval, upgrade token generation, and password changes.
* **Data sent:** API key, tenant ID, email address, filenames, and passwords (over HTTPS).

= Papaya Assist Chat API =

* **URL:** `https://iirqt9b6h3.execute-api.us-east-1.amazonaws.com/Prod/chat`
* **When used:** When a site visitor sends a message via the chat widget (non-streaming mode).
* **Data sent:** Visitor message text, conversation history (last 10 messages), and tenant ID.

= Papaya Assist Streaming API =

* **URL:** `https://dd63bb6z7j4r7pyiwvi5sqmhcq0gsgyz.lambda-url.us-east-1.on.aws/`
* **When used:** When a site visitor sends a message via the chat widget (streaming mode, used by default).
* **Data sent:** Visitor message text, conversation history (last 10 messages), and tenant ID.

= Papaya Assist Authentication Page =

* **URL:** `https://weng.ca/auth/chatbot/`
* **When used:** When the WordPress admin clicks "Sign Up / Log In" to connect their account.
* **Data sent:** Tenant ID (as a URL parameter). Credentials are returned to WordPress via postMessage.

= Amazon S3 (Document Storage) =

* **When used:** When uploading documents. The plugin obtains a presigned upload URL from the Admin API, then uploads the file directly to Amazon S3 (us-east-1).
* **Data sent:** Document file contents.

All services are hosted on AWS (us-east-1 region). By activating this plugin and creating an account, you agree to the [Papaya Assist Terms of Service](https://weng.ca/terms) and [Privacy Policy](https://weng.ca/privacy).

== Installation ==

1. Upload the `papaya-assist` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to **Settings > Papaya Assist**.
4. Click **Sign Up / Log In** to connect your account.
5. Customize the widget title, color, and enable/disable toggle.
6. Upload documents via the Documents section (or **Tools > Chatbot Documents**).
7. Click **Process Documents** to ingest documents into the vector database.
8. The chat widget will appear on your site for visitors.

== Frequently Asked Questions ==

= What file types can I upload? =

PDF, DOCX, and TXT files are supported.

= How do I get an account? =

Sign up directly from the plugin settings page — no external registration needed.

= Can I customize the widget appearance? =

Yes. You can change the widget title and primary color from the settings page.

= Does this work with caching plugins? =

Yes. The widget is injected via `wp_footer` and works with most caching plugins. If you use full-page caching, ensure the footer is not cached or excluded.

= Where is my data stored? =

All data is stored on AWS servers in the us-east-1 region. Documents are stored in Amazon S3. Chat messages are processed in real-time and not permanently stored.

= How do I uninstall completely? =

Deactivate and delete the plugin. All plugin settings (API key, tenant ID, etc.) are automatically removed from your WordPress database on uninstall.

== Screenshots ==

1. Settings page — connect your account and customize the widget.
2. Document management — upload, view, and process documents.
3. Chat widget — how it appears to visitors on your site.

== Changelog ==

= 1.5.2 =
* Email verification warning in Account section after sign-up.
* Chatbot state (open/closed and conversation) persists across page navigations.
* Widget config fallback for WordPress inline script injection.

= 1.4.0 =
* Rebranded to Papaya Assist.
* Security: server-side and client-side validation for widget title and color.
* Full internationalization (i18n) support.
* Detailed third-party service disclosures for wordpress.org compliance.

= 1.1.0 =
* Sign up / log in with Google or email directly from the plugin.
* Hardcoded backend URLs — no manual API configuration needed.
* Tenant ID auto-generated from site domain.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.4.0 =
Rebranded to Papaya Assist with security improvements and internationalization. Settings are preserved automatically.
