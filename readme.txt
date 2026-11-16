=== Listenly Voice – Text to Speech & Audio Player ===
Contributors: listenly
Tags: text to speech, tts, audio, accessibility, read aloud
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let visitors listen to your posts with natural voices, an accessible audio player and read-along highlighting.

== Description ==

Listenly Voice adds a "Listen to this article" player to your posts. Readers press play and hear the post, with the sentence being read highlighted on the page.

It works the moment you activate it. A short setup wizard asks how your posts should sound and where the player goes, and you can skip it.

= Two kinds of voice =

* **Browser voice.** No setup and no account. Each visitor hears the voice built into their own device, so it sounds a little different on phones and computers.
* **Google Cloud voices.** Natural voices that sound the same for everyone. Paste your own Google Cloud API key; Google bills your account directly and this plugin adds nothing on top.

= Built for readers =

* Play, pause and five speeds, from 0.75× to 2×.
* The sentence being read is highlighted, without changing your page's HTML. Works with the browser voice and Google voices.
* Long articles play to the end. Chrome cuts off long speech after about 15 seconds, so the player reads one sentence at a time.
* Keyboard support, screen-reader labels and 44px touch targets, built to WCAG 2.1 AA.
* A small script with no jQuery or React on your pages.

= Built for site owners =

* Choose which post types get the player.
* Put it at the top of the post, at the end, or as a floating button.
* Turn it off for a single post from the post sidebar, in the block editor or the classic editor.
* Place it anywhere by hand with the `[listenly_player]` shortcode.
* Your Google key stays on your server and is never sent to the browser.
* Google audio is cached on your server for an hour, so a popular post isn't sent to Google again for every listener.
* New Google audio is rate-limited per visitor and per site, so a bot can't run up your Google bill. If audio is busy before anything plays, the player switches to the browser voice.

== Installation ==

1. In your dashboard go to Plugins → Add New, search for "Listenly Voice" and click Install, then Activate.
2. The setup wizard opens. Pick a voice, choose where the player shows, and click "Turn on the player". Or click "Skip setup" to keep the defaults: the browser voice, at the top of every post.
3. Open any post to hear it.

To use Google voices, create an API key with the Cloud Text-to-Speech API enabled (Google's guide: https://cloud.google.com/text-to-speech/docs/before-you-begin), then paste it in the wizard or under Listenly Voice → Voices.

== Frequently Asked Questions ==

= Do I need an API key? =

No. The browser voice works with no key and no account. You only need a Google Cloud key for Google's natural voices.

= What do Google voices cost? =

Google bills your own Google Cloud account at its own rates, and offers a monthly free allowance. See https://cloud.google.com/text-to-speech/pricing. Listenly Voice adds no fee.

= Why does the browser voice sound different on my phone? =

It's the voice built into each visitor's device, so Chrome, Safari and Android each sound a little different. Use a Google voice for the same voice everywhere.

= Can I turn the player off for one post? =

Yes. In the block editor, open the post sidebar, find the Listenly Voice panel and switch off "Audio player on this post". In the classic editor, untick the same option in the Listenly Voice box. The shortcode is hidden on that post too.

= How do I put the player somewhere else? =

Add the `[listenly_player]` shortcode to the post, page or widget where you want it.

= Does the post title get read? =

Not yet. The player reads the post content.

= Does it store anything about my visitors? =

No cookies and no tracking. When Google voices are on, the plugin keeps a hashed visitor address for ten minutes to count requests for the rate limit, then deletes it.

= What happens to my data when I delete the plugin? =

Deleting the plugin from the Plugins screen removes its settings, your Google key, the per-post switches and any cached audio.

== Screenshots ==

1. The player on a post, highlighting the sentence being read.
2. Setup wizard, step 1: choose the browser voice or Google's natural voices, and play a sample of each.
3. Setup wizard, step 2: choose post types and where the player goes.
4. Player settings: post types, placement, read-along highlighting and the shortcode.
5. Turn the player off for a single post from the block editor sidebar.

== External services ==

When you choose Google Cloud as the voice, the text of a post is sent to Google Cloud Text-to-Speech (https://texttospeech.googleapis.com) to make the audio when a visitor presses play. The request goes to the service's v1beta1 address, which also returns when each sentence starts, for read-along highlighting; if that fails, the plugin asks the v1 address instead. Each part is kept on your server for an hour, so repeat plays in that hour don't send the text again. Nothing is sent while the browser voice is selected. When an admin connects a key or presses "Sample" in the setup wizard, one short fixed sentence is sent to the same service to check the key and play a sample.
Google Cloud terms: https://cloud.google.com/terms — privacy: https://policies.google.com/privacy

== Changelog ==

= 0.1.0 =
* First release.
* Browser voice, or Google Cloud voices with your own API key.
* Setup wizard with voice samples and a key check.
* Player at the top of the post, at the end, or as a floating button, on the post types you choose.
* Read-along sentence highlighting, for the browser voice and Google voices.
* Per-post on/off switch in the block editor and the classic editor, and the `[listenly_player]` shortcode.
* One-hour cache and rate limits for Google audio.
