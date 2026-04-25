=== Shell Pea Game ===
Contributors: shellpeagame
Tags: game, shortcode, interactive
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A classic three-shell-and-a-pea betting game embedded via the [shell_game] shortcode. No external dependencies, no tracking, no real money.

== Description ==

Shell Pea Game adds a virtual shells-and-pea betting mini-game to any post, page, or widget area in WordPress. Drop the `[shell_game]` shortcode anywhere and your visitors can place bets, watch the shuffle, and try to follow the pea.

Features:

* One shortcode — `[shell_game]` — drops the game anywhere in your site.
* Configurable starting bank, currency name, and shuffle speed via Settings → Shell Game.
* Inline shortcode attribute overrides: `currency`, `starting_bank`, `shuffle_speed`.
* Pure SVG walnut-toned shells. No image files, no external requests.
* Sound effects synthesized at runtime with the Web Audio API. No audio files shipped.
* Score and high score persisted client-side via `localStorage`.
* Honors `prefers-reduced-motion` for accessibility.
* Truly random shuffle — no house bias.
* No external dependencies. No tracking. WP.org-friendly.

This is a play-money game for entertainment only. No real currency or wagering of any kind is supported.

== Installation ==

1. Upload the `shell-pea-game` folder to the `/wp-content/plugins/` directory, or install via Plugins → Add New → Upload.
2. Activate the plugin through the Plugins menu in WordPress.
3. Visit Settings → Shell Game to set your defaults.
4. Add `[shell_game]` to any post or page where you want the game to appear.

== Frequently Asked Questions ==

= Is this real-money gambling? =

No. The game uses a virtual currency that lives only in the visitor's browser. Nothing is wagered, sent, or collected.

= Does the plugin make external requests? =

No. The plugin loads its own JS and CSS from your WordPress install and synthesizes its sounds in the browser. There are no third-party calls.

= Can I change the currency name? =

Yes — set it in Settings → Shell Game, or override per-instance with `[shell_game currency="Chips"]`.

= Can I put more than one game on a page? =

Yes. Each shortcode instance bootstraps its own independent state.

= Is the shuffle truly random? =

Yes. Each swap pair is chosen via `Math.random()` with no bias. Watch carefully and you can win.

= Does it respect reduced-motion preferences? =

Yes. If the visitor's OS reports `prefers-reduced-motion: reduce`, the lift, land, and flash animations are disabled.

= Where are the high scores stored? =

In the visitor's browser via `localStorage`. They are not sent to your server.

== Screenshots ==

1. The game embedded on a page, ready for the first bet.
2. Mid-shuffle — the shells in motion.
3. The reveal after a winning pick.
4. The Settings → Shell Game admin panel.

== Changelog ==

= 1.0.0 =
* Initial release.
* `[shell_game]` shortcode with inline attribute overrides.
* Settings page with currency, starting bank, and shuffle speed.
* SVG shells, Web Audio sounds, localStorage high score.

== Upgrade Notice ==

= 1.0.0 =
First public release.
