# Vichan styles for TinyIB

Use the **Style** menu at the top of the board, a thread, the catalog or the management area. All 35 top-level Vichan styles are available. The browser remembers your selection. **Yotsuba B (original)** is Vichan's base appearance; other choices include Yotsuba, Futaba, Dark, Photon, Miku and Terminal2.

Start the app with the existing **Start TinyIB.bat**. If you had the board open during the update, refresh it once. The launcher still works from the folder containing TinyIB.

## Default and customization

The existing `defaultstyle` in `settings.php` remains the default for new visitors. Use a filename without `.css`, for example `style`, `futaba`, `dark` or `photon`. An unavailable default falls back to `style`.

Themes are discovered from `stylesheets/*.css`. The older `stylesheets` configuration array is retained for configuration compatibility but no longer controls the menu. Do not include `style.css` again inside a new theme: the base file is always loaded first. Keep each theme's supporting files under `stylesheets/`.

Layout adjustments belong in `css/vichan-adapter.css`, loaded after the theme. The original TinyIB files under `css/` remain on disk but are no longer loaded by these pages. After changing a PHP template or the theme menu, use **Manage → Rebuild All** to regenerate the static pages. Browser preferences override the default until changed in the Style menu.

## Compatibility

Posts use `div.post.op` and `div.post.reply`, with Vichan's `intro`, `subject`, `name`, `trip`, `quote`, `fileinfo`, `post-image` and `body` classes. Catalog cards use `theme-catalog`, `threads` and `thread`. TinyIB's form fields and JavaScript hooks are retained. Stored posts receive class aliases during rendering without altering their database contents.

The PHP development router and nginx configuration serve the theme images and web fonts. PHP, configuration, database and other private files remain blocked. Themes use local assets; remote Google font imports and references to missing legacy background images were removed from four supplied themes.

The Vichan/Tinyboard licenses are in `LICENSE.Vichan.md` and `LICENSE.Tinyboard.md`. Their attribution is included in the page footer. TinyIB's own license is unchanged.

This update changes presentation. Existing posting options, moderation rules, accounts and data storage remain governed by TinyIB's configuration and backend.
