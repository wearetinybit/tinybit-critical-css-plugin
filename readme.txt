=== TinyBit Critical CSS ===
Contributors: danielbachhuber, tinybit
Tags: performance
Requires at least: 4.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates and serves inline critical CSS.

== Description ==

The TinyBit Critical CSS plugin works with [tinybit-critical-css-server](https://github.com/pinchofyum/tinybit-critical-css-server) to generate and serve inline critical CSS.

Here's how the plugin works:

1. Critical CSS generation process is triggered by WP-CLI or the refresh webhook.
2. Plugin renders a given WordPress page. The HTML and CSS of the page are sent to tinybit-critical-css-server.
3. When tinybit-critical-css-server returns a successful response, plugin stores critical CSS to the filesystem.
4. If the critical CSS exists for a given request, the critical CSS is included inline and the stylesheet is deferred.

Et voila! You have inline critical CSS.

== Installation ==

First, you'll need a working installation of [tinybit-critical-css-server](https://github.com/pinchofyum/tinybit-critical-css-server). Get that running if you haven't already.

Once the server is running, add a `TINYBIT_CRITICAL_CSS_SERVER` constant to your preferred location:

```
define( 'TINYBIT_CRITICAL_CSS_SERVER', 'http://localhost:8080' );
```

This constant tells the plugin where to send requests.

Next, filter `tinybit_critical_css_pages` to define the pages you'd like to generate critical CSS for:

```
add_filter(
    'tinybit_critical_css_pages',
    function() {
        return [
            home_url( '/' ) => [
                'handle'   => 'tinybit-style',
                'source'   => TINYBIT_ASSETS_DIR . '/dist/style.css',
                'critical' => TINYBIT_ASSETS_DIR . '/dist/critical/home.css',
                'when'     => function() {
                    return is_home();
                },
            ],
        ];
    }
);
```

In this particular example:

* `handle` is the name of the script handle (i.e. the first argument to `wp_enqueue_script()`).
* `source` is the file path of the source CSS file.
* `critical` is the file path where the critical CSS will be written.
* `when` is a callback for the context you'd like the critical CSS file to be used (so you can use it on more than one page).
* `home_url( '/' )` is the page to be rendered and passed to the server as `html`.

Next, to generate critical CSS via WP-CLI, run:

```
wp tinybit-critical-css generate --url=https://tinybit.com/
```

If you'd like to regenerate critical CSS after each deploy, run `wp tinybit-critical-css refresh-webhook` to grab a webhook you can ping. Because the webhook queues a one-time cron job, we'd recommend using some alternate WP Cron system to avoid timeout issues.


== Changelog ==

= 0.1.6 =

* Revert to previous cron system, add intervals.
* Add Slack notification support.

= 0.1.5 =

* Bump version.

= 0.1.4 =

* Improve queue processing to avoid stampeding the server.

= 0.1.3 =

* Add support for DB storage of critical CSS.

= 0.1.2 =

* Add hook to allow 3rd party code to initiate critical CSS generation.

= 0.1.1 =

* Add filter to generated HTML for modification before critical CSS is determined.

= 0.1.0 =
* Initial release.
