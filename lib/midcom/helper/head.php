<?php
/**
 * @package midcom.helper
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Helper functions for managing HTML head
 *
 * @package midcom.helper
 */
class midcom_helper_head implements EventSubscriberInterface
{
    /**
     * Array with all JavaScript declarations for the page's head.
     */
    private array $_jshead = [];

    /**
     * Array with all JavaScript file inclusions.
     */
    private array $_jsfiles = [];

    /**
     * Array with all prepend JavaScript declarations for the page's head.
     */
    private array $_prepend_jshead = [];

    /**
     * Boolean showing if jQuery is enabled
     */
    private bool $_jquery_enabled = false;

    /**
     * Array with all JQuery state scripts for the page's head.
     */
    private array $_jquery_states = [];

    /**
     * Array with all linked URLs for HEAD.
     */
    private array $_linkhrefs = [];

    /**
     * Array with all methods for the BODY's onload event.
     */
    private array $_jsonload = [];

    /**
     * string with all metatags to go into the page head.
     */
    private string $_meta_head = '';

    /**
     * String with all css styles to go into a page's head.
     */
    private string $_style_head = '';

    /**
     * Array with all link elements to be included in a page's head.
     */
    private array $_link_head = [];

    const HEAD_PLACEHOLDER = '<!-- MIDCOM_HEAD_ELEMENTS -->';

    private static bool $placeholder_added = false;

    private string $cachebusting = '';

    public static function getSubscribedEvents()
    {
        return [KernelEvents::RESPONSE => ['inject_head_elements']];
    }

    /**
     * Sets the page title for the current context.
     *
     * This can be retrieved by accessing the component context key
     * MIDCOM_CONTEXT_PAGETITLE.
     */
    public function set_pagetitle(string $string)
    {
        midcom_core_context::get()->set_key(MIDCOM_CONTEXT_PAGETITLE, $string);
    }

    /**
     * Register JavaScript File for referring in the page.
     *
     * This allows MidCOM components to register JavaScript code
     * during page processing. The site style code can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note, that these suggestions are not enforced, if you want a JScript
     * clean site, just omit the print calls and you should be fine in almost all
     * cases.
     *
     * The sequence of the add_jsfile and add_jscript commands is kept stable.
     *
     * @see add_jscript()
     * @see add_jsonload()
     * @see print_head_elements()
     * @see print_jsonload()
     */
    public function add_jsfile(string $url, bool $prepend = false)
    {
        // Adds a URL for a <script type="text/javascript" src="tinymce.js"></script>
        // like call. $url is inserted into src. Duplicates are omitted.
        if (!in_array($url, $this->_jsfiles)) {
            $this->_jsfiles[] = $url;
            $js_call = ['url' => $url];
            if ($prepend) {
                // Add the javascript include to the beginning, not the end of array
                array_unshift($this->_jshead, $js_call);
            } else {
                $this->_jshead[] = $js_call;
            }
        }
    }

    /**
     * Register JavaScript Code for output directly in the page.
     *
     * This allows components to register JavaScript code
     * during page processing. The site style can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note, that these suggestions are not enforced
     *
     * The sequence of the add_jsfile and add_jscript commands is kept stable.
     *
     * @see add_jsfile()
     * @see add_jsonload()
     * @see print_head_elements()
     * @see print_jsonload()
     */
    public function add_jscript(string $script, $defer = '', bool $prepend = false)
    {
        $js_call = ['content' => trim($script), 'defer' => $defer];
        if ($prepend) {
            $this->_prepend_jshead[] = $js_call;
        } else {
            $this->_jshead[] = $js_call;
        }
    }

    /**
     * Register JavaScript snippets to jQuery states.
     *
     * This allows components to register JavaScript code to the jQuery states.
     * Possible ready states: document.ready
     *
     * @see print_jquery_statuses()
     */
    public function add_jquery_state_script(string $script, string $state = 'document.ready')
    {
        $this->_jquery_states[$state] ??= '';
        $this->_jquery_states[$state] .= "\n" . trim($script) . "\n";
    }

    /**
     *  Register a metatag to be added to the head element.
     *  This allows components to register metatags to be placed in the
     *  head section of the page.
     *
     *  @param  array  $attributes Array of attribute => value pairs to be placed in the tag.
     *  @see print_head_elements()
     */
    public function add_meta_head(array $attributes)
    {
        $this->_meta_head .= '<meta' . $this->_get_attribute_string($attributes) . ' />' . "\n";
    }

    /**
     * Register a styleblock / style link  to be added to the head element.
     * This allows components to register extra CSS sheets they wants to include.
     * in the head section of the page.
     *
     * @param  string $script    The input between the <style></style> tags.
     * @param  array  $attributes Array of attribute=> value pairs to be placed in the tag.
     * @see print_head_elements()
     */
    public function add_style_head(string $script, array $attributes = [])
    {
        $this->_style_head .= '<style type="text/css"' . $this->_get_attribute_string($attributes) . '>' . $script . "</style>\n";
    }

    private function _get_attribute_string(array $attributes) : string
    {
        $string = '';
        foreach ($attributes as $key => $val) {
            if ($this->cachebusting && $key === 'href') {
                $val .= $this->cachebusting;
            }
            $string .= ' ' . $key . '="' . htmlspecialchars($val, ENT_COMPAT) . '"';
        }
        return $string;
    }

    /**
     * Register a link element to be placed in the page head.
     *
     * This allows components to register extra CSS links.
     * Example to use this to include a CSS link:
     * <code>
     * $attributes = array ('rel' => 'stylesheet',
     *                      'type' => 'text/css',
     *                      'href' => '/style.css'
     *                      );
     * midcom::get()->head->add_link_head($attributes);
     * </code>
     *
     * Each URL will only be added once. When trying to add the same URL a second time,
     * it will be moved to the end of the stack, so that CSS overrides behave as the developer
     * intended
     *
     * @param  array $attributes Array of attribute => value pairs to be placed in the tag.
     * @see print_head_elements()
     */
    public function add_link_head(array $attributes, bool $prepend = false)
    {
        if (!array_key_exists('href', $attributes)) {
            return;
        }

        // Register each URL only once
        if (($key = array_search($attributes['href'], $this->_linkhrefs)) !== false) {
            unset($this->_linkhrefs[$key]);
        }
        if ($prepend) {
            array_unshift($this->_linkhrefs, $attributes['href']);
        } else {
            $this->_linkhrefs[] = $attributes['href'];
        }
        $this->_link_head[$attributes['href']] = $attributes;
    }

    /**
     * Convenience shortcut for appending CSS files
     *
     * @param string $media The media type(s) for the stylesheet, if any
     */
    public function add_stylesheet(string $url, string $media = null)
    {
        $this->add_link_head($this->prepare_stylesheet_attributes($url, $media));
    }

    /**
     * Convenience shortcut for prepending CSS files
     *
     * @param string $media The media type(s) for the stylesheet, if any
     */
    public function prepend_stylesheet(string $url, string $media = null)
    {
        $this->add_link_head($this->prepare_stylesheet_attributes($url, $media), true);
    }

    private function prepare_stylesheet_attributes(string $url, ?string $media) : array
    {
        $attributes = [
            'rel'  => 'stylesheet',
            'type' => 'text/css',
            'href' => $url,
        ];
        if ($media) {
            $attributes['media'] = $media;
        }
        return $attributes;
    }

    /**
     * Register a JavaScript method for the body onload event
     *
     * This allows components to register JavaScript code
     * during page processing. The site style can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note that these suggestions are not enforced.
     *
     * @param string $method    The name of the method to be called on page startup, including parameters but excluding the ';'.
     * @see add_jsfile()
     * @see add_jscript()
     * @see print_head_elements()
     * @see print_jsonload()
     */
    public function add_jsonload(string $method)
    {
        // Adds a method name for <body onload=".."> The string must not end with a ;, it is added automagically
        $this->_jsonload[] = $method;
    }

    /**
     * Echo the registered javascript code.
     *
     * This allows components to register JavaScript code
     * during page processing. The site style code can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note, that these suggestions are not enforced
     *
     * The sequence of the add_jsfile and add_jscript commands is kept stable.
     *
     * This is usually called during the BODY region of your style:
     *
     * <code>
     * <html>
     *     <body <?php midcom::get()->head->print_jsonload();?>>
     *            <!-- your actual body -->
     *     </body>
     * </html>
     * </code>
     *
     * @see add_jsfile()
     * @see add_jscript()
     * @see add_jsonload()
     * @see print_head_elements()
     */
    public function print_jsonload()
    {
        if (!empty($this->_jsonload)) {
            $calls = implode("; ", $this->_jsonload);
            echo " onload=\"$calls\" ";
        }
    }

    /**
     * Marks where the _head elements added should be rendered.
     *
     * Place the method within the <head> section of your page.
     *
     * This allows components to register HEAD elements
     * during page processing. The site style can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY tag. Note that these suggestions are not enforced
     *
     * @see add_link_head()
     * @see add_style_head()
     * @see add_meta_head()
     * @see add_jsfile()
     * @see add_jscript()
     */
    public function print_head_elements(string $cachebusting = '')
    {
        if ($cachebusting) {
            $this->cachebusting = '?cb=' . $cachebusting;
        }
        echo self::HEAD_PLACEHOLDER;
        self::$placeholder_added = true;
    }

    /**
     * This function renders the elements added by the various add methods
     * and injects them into the response
     */
    public function inject_head_elements(ResponseEvent $event)
    {
        if (!self::$placeholder_added || !$event->isMainRequest()) {
            return;
        }
        $response = $event->getResponse();
        $content = $response->getContent();

        $first = strpos($content, self::HEAD_PLACEHOLDER);
        if ($first === false) {
            return;
        }

        $head = $this->render();
        $new_content = substr_replace($content, $head, $first, strlen(self::HEAD_PLACEHOLDER));
        $response->setContent($new_content);
        if ($length = $response->headers->get('Content-Length')) {
            $delta = strlen($head) - strlen(self::HEAD_PLACEHOLDER);
            $response->headers->set('Content-Length', $length + $delta);
        }
    }

    public function render() : string
    {
        $head = $this->_meta_head;
        foreach ($this->_linkhrefs as $url) {
            $attributes = $this->_link_head[$url];
            $is_conditional = false;

            if (array_key_exists('condition', $attributes)) {
                $head .= "<!--[if {$attributes['condition']}]>\n";
                $is_conditional = true;
                unset($attributes['condition']);
            }

            $head .= "<link" . $this->_get_attribute_string($attributes) . " />\n";

            if ($is_conditional) {
                $head .= "<![endif]-->\n";
            }
        }

        $head .= $this->_style_head;

        if (!empty($this->_prepend_jshead)) {
            $head .= array_reduce($this->_prepend_jshead, [$this, 'render_js'], '');
        }

        $head .= array_reduce($this->_jshead, [$this, 'render_js'], '');
        return $head . $this->render_jquery_statuses();
    }

    private function render_js(string $carry, array $js_call) : string
    {
        if (array_key_exists('url', $js_call)) {
            if ($this->cachebusting) {
                $js_call['url'] .= $this->cachebusting;
            }
            return $carry . '<script type="text/javascript" src="' . $js_call['url'] . "\"></script>\n";
        }
        $carry .= '<script type="text/javascript"' . ($js_call['defer'] ?? '') . ">\n";
        $carry .= $js_call['content'] . "\n";
        return $carry . "</script>\n";
    }

    public function get_jshead_elements() : array
    {
        return array_merge($this->_prepend_jshead, $this->_jshead);
    }

    public function get_link_head() : array
    {
        return $this->_link_head;
    }

    /**
     * Init jQuery
     *
     * This method adds jQuery support to the page
     */
    public function enable_jquery()
    {
        if ($this->_jquery_enabled) {
            return;
        }

        $script  = "const MIDCOM_STATIC_URL = '" . MIDCOM_STATIC_URL . "',\n";
        $script .= "      MIDCOM_PAGE_PREFIX = '" . midcom_connection::get_url('self') . "';";
        array_unshift($this->_prepend_jshead, ['content' => $script]);

        $version = midcom::get()->config->get('jquery_version');
        if (midcom::get()->config->get('jquery_load_from_google')) {
            // Use Google's hosted jQuery version
            array_unshift($this->_prepend_jshead, ['content' => 'google.load("jquery", "' . $version . '");']);
            array_unshift($this->_prepend_jshead, ['url' => 'https://www.google.com/jsapi']);
        } else {
            $url = MIDCOM_STATIC_URL . "/jQuery/jquery-{$version}.js";
            array_unshift($this->_prepend_jshead, ['url' => $url]);
        }

        if (!defined('MIDCOM_JQUERY_UI_URL')) {
            define('MIDCOM_JQUERY_UI_URL', MIDCOM_STATIC_URL . "/jQuery/jquery-ui-" . midcom::get()->config->get('jquery_ui_version'));
        }

        $this->_jquery_enabled = true;
    }

    /**
     * Renders the scripts added by the add_jquery_state_script method.
     *
     * This method is called from print_head_elements method.
     *
     * @see add_jquery_state_script()
     * @see print_head_elements()
     */
    private function render_jquery_statuses() : string
    {
        if (empty($this->_jquery_states)) {
            return '';
        }

        $content = '';
        foreach ($this->_jquery_states as $status => $scripts) {
            [$target, $method] = explode('.', $status);
            $content .= "jQuery({$target}).{$method}(function() {\n";
            $content .= $scripts . "\n";
            $content .= "});\n";
        }

        return $this->render_js('', ['content' => $content]);
    }

    /**
     * Add jquery ui components
     *
     * core and widget are loaded automatically. Also loads jquery.ui theme,
     * either the configured theme one or a hardcoded default (base theme)
     */
    public function enable_jquery_ui(array $components = [])
    {
        $this->enable_jquery();
        $this->add_jsfile(MIDCOM_JQUERY_UI_URL . '/core.min.js');

        foreach ($components as $component) {
            $path = $component;
            if (str_starts_with($component, 'effect')) {
                if ($component !== 'effect') {
                    $path = 'effects/' . $component;
                }
            } else {
                $path = 'widgets/' . $component;
            }

            $this->add_jsfile(MIDCOM_JQUERY_UI_URL . '/' . $path . '.min.js');

            if ($component == 'datepicker') {
                $lang = midcom::get()->i18n->get_current_language();
                /*
                 * The calendar doesn't have all lang files and some are named differently
                 * Since a missing lang file causes the calendar to break, let's make extra sure
                 * that this won't happen
                 */
                if (!file_exists(MIDCOM_STATIC_ROOT . "/jQuery/jquery-ui-" . midcom::get()->config->get('jquery_ui_version') . "/i18n/datepicker-{$lang}.min.js")) {
                    $lang = midcom::get()->i18n->get_fallback_language();
                    if (!file_exists(MIDCOM_STATIC_ROOT . "/jQuery/jquery-ui-" . midcom::get()->config->get('jquery_ui_version') . "/i18n/datepicker-{$lang}.min.js")) {
                        $lang = null;
                    }
                }

                if ($lang) {
                    $this->add_jsfile(MIDCOM_JQUERY_UI_URL . "/i18n/datepicker-{$lang}.min.js");
                }
            }
        }

        $this->add_link_head([
            'rel'  => 'stylesheet',
            'type' => 'text/css',
            'href' => MIDCOM_STATIC_URL . '/jQuery/jquery-ui-1.12.icon-font.min.css',
        ], true);
        $this->add_link_head([
            'rel'  => 'stylesheet',
            'type' => 'text/css',
            'href' => midcom::get()->config->get('jquery_ui_theme', MIDCOM_JQUERY_UI_URL . '/themes/base/jquery-ui.min.css'),
        ], true);
    }
}
