<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * User interface messaging service
 *
 * This service is used for passing messages from applications to the MidCOM
 * user.
 *
 * <b>Displaying UI messages on site:</b>
 *
 * If you want the UI messages to be shown in your site, you must place
 * the following call inside the HTML BODY tags of your style:
 *
 * <code>
 * midcom::get()->uimessages->show();
 * </code>
 *
 * <b>Adding UI messages to show:</b>
 *
 * Any MidCOM component can add its own UI messages to be displayed. The
 * messages also carry across a relocate() call so you can tell a document
 * has been saved before relocating user into its view.
 *
 * UI messages can be specified into the following types: <i>info</i>,
 * <i>ok</i>, <i>warning</i> and <i>error</i>.
 *
 * To add a UI message, call the following:
 *
 * <code>
 * midcom::get()->uimessages->add($title, $message, $type);
 * </code>
 *
 * For example:
 *
 * <code>
 * midcom::get()->uimessages->add($this->_l10n->get('net.nemein.wiki'), sprintf($this->_l10n->get('page "%s" added'), $this->_wikiword), 'ok');
 * </code>
 *
 * <b>Configuration:</b>
 *
 * @see midcom_config for configuration options.
 * @package midcom.services
 */
class midcom_services_uimessages
{
    private ?FlashBagInterface $_message_stack = null;

    private array $_allowed_types = ['info', 'ok', 'warning', 'error', 'debug'];

    /**
     * DOM path of the UI message holder object
     */
    public string $uimessage_holder = 'body';

    private function get_message_stack() : FlashBagInterface
    {
        return $this->_message_stack ??= midcom::get()->session->getFlashBag();
    }

    /**
     * Initialize the message stack on service start-up. Reads older unshown
     * messages from user session.
     */
    public function initialize(Request $request)
    {
        if ($request->hasPreviousSession()) {
            $this->get_message_stack();
        }
    }

    public function add_head_elements()
    {
        midcom::get()->head->enable_jquery();
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midcom.services.uimessages/jquery.midcom_services_uimessages.js');
        midcom::get()->head->add_stylesheet(MIDCOM_STATIC_URL . '/stock-icons/font-awesome-4.7.0/css/font-awesome.min.css');
        midcom::get()->head->prepend_stylesheet(MIDCOM_STATIC_URL . '/midcom.services.uimessages/growl.css', 'screen');
    }

    public function get_class_magic_default_privileges() : array
    {
        return [
            'EVERYONE' => [],
            'ANONYMOUS' => [],
            'USERS' => []
        ];
    }

    /**
     * Add a message to be shown to the user.
     *
     * @param string $message Message contents, may contain HTML
     */
    public function add(string $title, string $message, string $type = 'info') : bool
    {
        // Make sure the given class is allowed
        if (!in_array($type, $this->_allowed_types)) {
            // Message class not in allowed list
            debug_add("Message type {$type} is not allowed");
            return false;
        }

        $msg = [
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
        ];
        // Append to message stack
        $this->get_message_stack()->add($type, json_encode($msg));
        return true;
    }

    public function get_messages() : array
    {
        if ($this->_message_stack) {
            if ($messages = $this->_message_stack->all()) {
                return array_merge(...array_values($messages));
            }
        }
        return [];
    }

    /**
     * Show the message stack via javascript calls or simple html
     */
    public function show(bool $show_simple = false)
    {
        if (   $show_simple
            || !midcom::get()->auth->can_user_do('midcom:ajax', class: static::class)) {
            $this->show_simple();
            return;
        }
        if ($this->has_messages()) {
            $this->add_head_elements();
        }

        echo "<script type=\"text/javascript\">\n";
        echo "    // <!--\n";
        echo "        jQuery(document).ready(function()\n";
        echo "        {\n";
        echo "            if (jQuery('#midcom_services_uimessages_wrapper').length == 0)\n";
        echo "            {\n";
        echo "                jQuery('<div id=\"midcom_services_uimessages_wrapper\" class=\"uimessages-fancy\"></div>')\n";
        echo "                    .appendTo('{$this->uimessage_holder}');\n";
        echo "            }\n";

        foreach ($this->get_messages() as $message) {
            echo "            jQuery('#midcom_services_uimessages_wrapper').midcom_services_uimessage(" . $message . ")\n";
        }

        echo "        })\n";
        echo "    // -->\n";

        echo "</script>\n";
    }

    /**
     * Show the message stack via simple html only
     */
    public function show_simple()
    {
        if ($this->has_messages()) {
            midcom::get()->head->prepend_stylesheet(MIDCOM_STATIC_URL . '/midcom.services.uimessages/simple.css', 'screen');

            echo "<div id=\"midcom_services_uimessages_wrapper\">\n";
            foreach ($this->get_messages() as $message) {
                $this->_render_message($message);
            }
            echo "</div>\n";
        }
    }

    private function has_messages() : bool
    {
        return $this->_message_stack && !empty($this->_message_stack->peekAll());
    }

    /**
     * Render the message
     */
    private function _render_message(string $message)
    {
        $message = json_decode($message, true);
        echo "<div class=\"midcom_services_uimessages_message msu_{$message['type']}\">";

        echo "    <div class=\"midcom_services_uimessages_message_type\">{$message['type']}</div>";
        echo "    <div class=\"midcom_services_uimessages_message_title\">{$message['title']}</div>";
        echo "    <div class=\"midcom_services_uimessages_message_msg\">{$message['message']}</div>";

        echo "</div>\n";
    }
}
