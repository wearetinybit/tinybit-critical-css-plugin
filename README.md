# TinyBit Critical CSS #
**Contributors:** [danielbachhuber](https://profiles.wordpress.org/danielbachhuber/)  
**Tags:** performance  
**Requires at least:** 4.5  
**Tested up to:** 5.8.2  
**Requires PHP:** 5.6  
**Stable tag:** 0.1.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Generates and serves inline critical CSS.

## Description ##

The TinyBit Critical CSS plugin works with [tinybit-critical-css-server](https://github.com/pinchofyum/tinybit-critical-css-server) to generate and serve inline critical CSS.

Here's how the plugin works:

1. Critical CSS generation process is triggered by WP-CLI or the refresh webhook.
2. Plugin renders a given page. The HTML and CSS are sent to tinybit-critical-css-server.
3. When tinybit-critical-css-server returns a successful response, plugin stores critical CSS to the filesystem.
4. If the critical CSS exists for a given request, the critical CSS is included inline and the stylesheet is deferred.

Et voila! You have inline critical CSS.

## Installation ##

First, you'll need to make sure you have a working installation of [tinybit-critical-css-server](https://github.com/pinchofyum/tinybit-critical-css-server). Do that first if you haven't already.

Once the server is running, add a `TINYBIT_CRITICAL_CSS_SERVER` constant to your preferred place for constants:

```
define( 'TINYBIT_CRITICAL_CSS_SERVER', 'http://localhost:8080' );
```

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

* `handle` is the name of the script handle.
* `source` is the path of the source CSS file.
* `critical` is the path where the critical CSS will be written.
* `when` is the context you'd like the critical CSS file to be used (so you can use it on more than one page).
* `home_url( '/' )` is the page to be rendered and passed to the server as `html`.

To generate critical CSS via WP-CLI, run:

```
wp tinybit-critical-css generate --url=https://tinybit.com/
```

If you'd like to regenerate critical CSS after each deploy, run `wp tinybit-critical-css refresh-webhook` to grab a webhook you can ping. Because the webhook queues a one-time cron job, we'd recommend using some alternate WP Cron system to avoid timeout issues.


## Changelog ##

### 0.1.0 ###
* Initial release.

