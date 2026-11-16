=== Listenly Voice – Text to Speech & Audio Player ===
Contributors: shaikhkhoaiz
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

= Why Listenly Voice =

* **A natural voice for free.** Connect your own Google Cloud key and every reader hears the same natural voice. No account with us and no article limit.
* **Nothing switches off.** No credits that run out, no player that disappears from new posts, no ads in your audio.
* **Your content stays yours.** Text goes only to the voice provider you choose, with your own key. We take no rights to your content, and there is no outside account to reconnect when you move domains.
* **A player everyone can use.** Keyboard support, screen-reader labels, readable contrast in light and dark, and 44px touch targets, built to WCAG 2.1 AA. No plugin can make a whole site compliant, but this one won't add problems.
* **Want files and a podcast?** The optional Listenly Voice Pro add-on saves each post as an MP3 in your uploads folder and publishes a podcast feed. The free plugin is complete without it.

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
* Place it anywhere by hand with the "Listenly player" block, the Elementor widget, or the `[listenly_player]` shortcode (which works in any page builder).
* See how many times each post was played, on the plugin's Home screen.
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

= Is there a limit on posts or listens? =

No. The plugin has no credits or monthly cap and never hides the player. With Google voices, the only limits are your Google account's own quota and the rate limit that protects your bill (see below).

= Can I turn the player off for one post? =

Yes. In the block editor, open the post sidebar, find the Listenly Voice panel and switch off "Audio player on this post". In the classic editor, untick the same option in the Listenly Voice box. The shortcode is hidden on that post too.

= How do I put the player somewhere else? =

In the block editor, add the "Listenly player" block. In Elementor, drag in the "Listenly player" widget. Anywhere else, including other page builders, add the `[listenly_player]` shortcode.

= Does the post title get read? =

Yes. Both voices read the title first, then the post content. The title isn't highlighted, because most themes show it outside the post content.

= Does it store anything about my visitors? =

No cookies and no tracking, and nothing is sent to us. With Google voices, a post's text goes to Google Cloud to make the audio (see External services). When Google voices are on, the plugin keeps a hashed visitor address for ten minutes to count requests for the rate limit, then deletes it. To count plays, it keeps a hashed visitor address for 30 minutes so one visitor pressing play again isn't counted twice; only the totals are stored.

= What happens to my data when I delete the plugin? =

Deleting the plugin from the Plugins screen removes its settings, your Google key, the per-post switches, play counts and any cached audio.

== Screenshots ==

1. The player on a post, highlighting the sentence being read.
2. Setup wizard, step 1: choose the browser voice or Google's natural voices, and play a sample of each.
3. Setup wizard, step 2: choose post types and where the player goes.
4. Player settings: post types, placement, read-along highlighting and colours.
5. Turn the player off for a single post from the block editor sidebar.

== External services ==

When you choose Google Cloud as the voice, the text of a post is sent to Google Cloud Text-to-Speech (https://texttospeech.googleapis.com) to make the audio when a visitor presses play. The request goes to the service's v1beta1 address, which also returns when each sentence starts, for read-along highlighting; if that fails, the plugin asks the v1 address instead. Each part is kept on your server for an hour, so repeat plays in that hour don't send the text again. Nothing is sent while the browser voice is selected. When an admin connects a key, presses "Sample" in the setup wizard, or presses "Save and test" or "Test voice" on the Voices screen, one short fixed sentence is sent to the same service to check the key and play a sample.
Google Cloud terms: https://cloud.google.com/terms — privacy: https://policies.google.com/privacy

== Changelog ==

= 0.1.0 =
* First release.
* Browser voice, or Google Cloud voices with your own API key.
* Setup wizard with voice samples and a key check.
* Player at the top of the post, at the end, or as a floating button, on the post types you choose.
* Read-along sentence highlighting, for the browser voice and Google voices.
* The title is read before the post, with both voices.
* Per-post on/off switch in the block editor and the classic editor, and the `[listenly_player]` shortcode.
* One-hour cache and rate limits for Google audio.
* "Listenly player" block and Elementor widget.
* Play counts per post and for the site, on the Home screen.
* Pronunciation list: tell the voices how to say up to 10 names, brands or abbreviations.
* English sites: money and number shorthand is read the way people say it ("$12.7bn" as "12.7 billion dollars", "3–5" as "3 to 5").
* If your theme or page builder leaves no place for the player, Home says which post and how to fix it; a floating player is added anyway.
* Google key setup guide, and key tests that say exactly what to fix in Google Cloud.
