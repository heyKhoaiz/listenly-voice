=== Listenly Voice – Text to Speech & Audio Player ===
Contributors: listenly
Tags: text to speech, tts, audio, accessibility, podcast
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let visitors listen to your posts with natural voices and an accessible audio player.

== Description ==

Listenly Voice adds a "Listen to this article" player to your posts.

* Works with no setup using the visitor's browser voice.
* Natural Google Cloud voices with your own API key.
* An accessible player: keyboard support, 44px targets, screen-reader labels.
* Choose post types and where the player appears.

== External services ==

When you choose Google Cloud as the voice, the text of a post is sent to Google Cloud Text-to-Speech (https://texttospeech.googleapis.com) to make the audio, each time a visitor presses play. Nothing is sent while the browser voice is selected. When an admin connects a key or presses "Sample" in the setup wizard, one short fixed sentence is sent to the same service to check the key and play a sample.
Google Cloud terms: https://cloud.google.com/terms — privacy: https://policies.google.com/privacy

== Changelog ==

= 0.1.0 =
* First version.
* Setup wizard on first activation: pick a voice (with samples and a key check), choose post types and where the player shows.
* Player placement: top of the post, end of the post, or a floating button.
* Player screen: post types, placement, read-along highlighting on or off, and the [listenly_player] shortcode.
* The browser voice reads one sentence at a time and highlights it, so long articles play to the end in Chrome.
* Turn the player off for a single post from the post sidebar in the block editor. The shortcode respects it too.
