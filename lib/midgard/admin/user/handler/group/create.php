<?php
/**
 * @package midgard.admin.user
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\datamanager\datamanager;
use Symfony\Component\HttpFoundation\Request;

/**
 * group creation class
 *
 * @package midgard.admin.user
 */
class midgard_admin_user_handler_group_create extends midcom_baseclasses_components_handler
{
    use midgard_admin_asgard_handler;

    public function _handler_create(Request $request, array &$data)
    {
        $group = new midcom_db_group;
        $dm = datamanager::from_schemadb($this->_config->get('schemadb_group'));
        $dm->set_storage($group);
        $data['controller'] = $dm->get_controller();
        switch ($data['controller']->handle($request)) {
            case 'save':
                // Show confirmation for the group
                midcom::get()->uimessages->add($this->_l10n->get($this->_component), sprintf($this->_l10n->get('group %s saved'), $group->name));
                return new midcom_response_relocate($this->router->generate('group_edit', ['guid' => $group->guid]));

            case 'cancel':
                return new midcom_response_relocate($this->router->generate('group_list'));
        }

        $data['view_title'] = $this->_l10n->get('create group');

        $this->add_breadcrumb($this->router->generate('user_list'), $this->_l10n->get($this->_component));
        $this->add_breadcrumb($this->router->generate('group_create'), $data['view_title']);
        return $this->get_response('midgard-admin-user-group-create');
    }
}
