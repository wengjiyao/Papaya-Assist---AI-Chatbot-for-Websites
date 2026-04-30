=== Bitesize Chatbot ===
Contributors: bitesizeai
Tags: chatbot, ai, rag, customer support, widget
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chatbot widget with document-based knowledge. Upload your documents and the chatbot answers questions using RAG (Retrieval-Augmented Generation).

== Description ==
Bitesize Chatbot adds an AI chat widget to your WordPress site that answers visitor questions based on your uploaded documents.

**Features:**
* One-click sign-up from within WordPress admin
* Embeddable chat widget with customizable colors and title
* Document upload and management from WordPress admin
* Streaming responses for real-time chat experience
* Google Sign-In support

**External Service:**
This plugin connects to the Bitesize Chatbot API to provide AI chat functionality.
When you create an account, upload documents, or visitors use the chat widget, data is
sent to servers hosted on AWS (us-east-1). By activating this plugin and creating an
account, you agree to the [Bitesize AI Terms of Service](https://bitesize.ai/terms)
and [Privacy Policy](https://bitesize.ai/privacy).

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to Settings → Bitesize Chatbot
4. Sign up for an account (or log in with an existing one)
5. Upload documents via Tools → Chatbot Documents
6. Click "Process Documents" to enable chat

== Frequently Asked Questions ==

= What file types can I upload? =
PDF, DOCX, and TXT files are supported.

= How do I get an account? =
Sign up directly from the plugin settings page — no external registration needed.

== Changelog ==
= 1.1.0 =
* Sign up / log in with Google or email directly from the plugin
* Hardcoded backend URLs — no manual API configuration needed
* Tenant ID auto-generated from site domain

= 1.0.0 =
* Initial release
