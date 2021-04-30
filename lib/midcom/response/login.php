<?php
/**
 * @package midcom
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper for login page responses
 *
 * @package midcom
 */
class midcom_response_login extends Response
{
    private $method;

    public function __construct(string $method = 'form')
    {
        parent::__construct();
        $this->method = $method;
        if ($method === 'basic') {
            $this->headers->set('WWW-Authenticate', 'Basic realm="Midgard"');
            $this->setStatusCode(Response::HTTP_UNAUTHORIZED);
        } else {
            $this->setStatusCode((int) midcom::get()->config->get('auth_login_form_httpcode'));
        }
        midcom::get()->cache->content->no_cache();
        $this->content = $this->render();
    }

    /**
     * Render the main login form.
     * This only includes the form, no heading or whatever.
     *
     * If the current style has an element called <i>midcom_services_auth_login_page</i>
     * it will be shown. The local scope will contain the two variables
     * $title and $login_warning. $title is the localized string 'login' from the main
     * MidCOM L10n DB, login_warning is empty unless there was a failed authentication
     * attempt, in which case it will have a localized warning message enclosed in a
     * paragraph with the ID 'login_warning'.
     */
    protected function render() : string
    {
        if ($this->method === 'basic') {
            // TODO: more fancy 401 output ?
            return "<h1>Authorization required</h1>\n";
        }

        $title = midcom::get()->i18n->get_string('login', 'midcom');

        // Determine login warning so that wrong user/pass is shown.
        $login_warning = '';
        if (midcom::get()->auth->has_login_data()) {
            $login_warning = midcom::get()->i18n->get_string('login message - user or password wrong', 'midcom');
        }

        // Pass our local but very useful variables on to the style element
        midcom::get()->style->data['midcom_services_auth_show_login_page_title'] = $title;
        midcom::get()->style->data['midcom_services_auth_show_login_page_login_warning'] = $login_warning;

        ob_start();
        midcom::get()->style->show_midcom('midcom_services_auth_login_page');
        return ob_get_clean();
    }
}
