<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\datamanager\datamanager;

/**
 * Simple object deleted page
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_object_deleted extends midcom_baseclasses_components_handler
{
    use midgard_admin_asgard_handler;

    /**
     * Handler for deleted objects
     */
    public function _handler_deleted(string $handler_id, string $guid, array &$data)
    {
        $this->add_breadcrumb($this->router->generate('welcome'), $this->_l10n->get($this->_component));

        if (midcom::get()->auth->admin) {
            $data['object'] = $this->prepare_admin_view($guid);
            if (!$data['object']->metadata->deleted) {
                return new midcom_response_relocate($this->router->generate('object_open', ['guid' => $data['object']->guid]));
            }
            midgard_admin_asgard_plugin::bind_to_object($data['object'], $handler_id, $data);
        }
        $data['view_title'] = $this->_l10n->get('object deleted');

        $this->add_breadcrumb("", $data['view_title']);

        return $this->get_response('midgard_admin_asgard_object_deleted');
    }

    private function prepare_admin_view(string $guid) : midcom_core_dbaobject
    {
        $object = $this->load_deleted($guid);
        $this->prepare_dm($object);

        $this->_request_data['asgard_toolbar']->add_item([
            MIDCOM_TOOLBAR_URL => $this->router->generate('trash_type', ['type' => $object->__mgdschema_class_name__]),
            MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('undelete'),
            MIDCOM_TOOLBAR_GLYPHICON => 'recycle',
            MIDCOM_TOOLBAR_POST => true,
            MIDCOM_TOOLBAR_POST_HIDDENARGS => [
                'undelete[]' => $guid
            ]
        ]);
        $this->_request_data['asgard_toolbar']->add_item([
            MIDCOM_TOOLBAR_URL => $this->router->generate('trash_type', ['type' => $object->__mgdschema_class_name__]),
            MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('purge'),
            MIDCOM_TOOLBAR_GLYPHICON => 'trash',
            MIDCOM_TOOLBAR_POST => true,
            MIDCOM_TOOLBAR_POST_HIDDENARGS => [
                'undelete[]' => $guid,
                'purge' => true
            ]
        ]);
        if (   midcom::get()->config->get('midcom_services_rcs_enable')
            && $object->can_do('midgard:update')
            && $object->_use_rcs) {
            $this->_request_data['asgard_toolbar']->add_item([
                MIDCOM_TOOLBAR_URL => $this->router->generate('object_rcs_history', ['guid' => $object->guid]),
                MIDCOM_TOOLBAR_LABEL => midcom::get()->i18n->get_string('show history', 'midcom.admin.rcs'),
                MIDCOM_TOOLBAR_GLYPHICON => 'history',
                MIDCOM_TOOLBAR_ACCESSKEY => 'h',
            ]);
        }

        $this->add_breadcrumb($this->router->generate('trash'), $this->_l10n->get('trash'));
        $this->add_breadcrumb($this->router->generate('trash_type', ['type' => $object->__mgdschema_class_name__]), midgard_admin_asgard_plugin::get_type_label($object->__midcom_class_name__));
        $label = midcom_helper_reflector::get($object)->get_object_label($object);
        $this->add_breadcrumb('', $label);
        return $object;
    }

    /**
     * Loads the schemadb from the helper class
     */
    private function prepare_dm(midcom_core_dbaobject $object)
    {
        $schema_helper = new midgard_admin_asgard_schemadb($object, $this->_config);
        $schemadb = $schema_helper->create([]);
        $datamanager = new datamanager($schemadb);
        $datamanager
            ->set_storage($object)
            ->get_form(); // currently needed to add head elements
        $this->_request_data['datamanager'] = $datamanager;
    }
}
