<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\datamanager\datamanager;
use midcom\datamanager\controller;
use midcom\datamanager\schemadb;
use Symfony\Component\HttpFoundation\Request;

/**
 * Object management interface
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_object_manage extends midcom_baseclasses_components_handler
{
    use midgard_admin_asgard_handler;

    private ?midcom_core_dbaobject $_object = null;

    /**
     * @var datamanager
     */
    private $datamanager;

    /**
     * @var controller
     */
    private $controller;

    private schemadb $schemadb;

    public function _on_initialize()
    {
        midcom::get()->auth->require_user_do('midgard.admin.asgard:manage_objects', class: 'midgard_admin_asgard_plugin');
    }

    /**
     * Retrieve the object from the db
     */
    private function _load_object(string $guid)
    {
        try {
            $this->_object = midcom::get()->dbfactory->get_object_by_guid($guid);
        } catch (midcom_error $e) {
            if (midcom_connection::get_error() == MGD_ERR_OBJECT_DELETED) {
                $relocate = $this->router->generate('object_deleted', ['guid' => $guid]);
                midcom::get()->relocate($relocate);
            }

            throw $e;
        }
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['object'] = $this->_object;
        $this->_request_data['controller'] = $this->controller;
        $this->_request_data['datamanager'] = $this->datamanager;
        $this->_request_data['style_helper'] = new midgard_admin_asgard_stylehelper($this->_request_data);
    }

    /**
     * Loads the schemadb from the helper class
     */
    private function _load_schemadb(?midcom_core_dbaobject $object = null, ?array $include_fields = null, bool $add_copy_fields = false)
    {
        $schema_helper = new midgard_admin_asgard_schemadb($object ?? $this->_object, $this->_config);
        $schema_helper->add_copy_fields = $add_copy_fields;
        $this->schemadb = $schema_helper->create($include_fields);
    }

    /**
     * Looks up the user's default mode and redirects there. This is mainly useful for links from outside Asgard
     */
    public function _handler_open(string $guid, array &$data)
    {
        $relocate = $this->router->generate('object_' . $data['default_mode'], ['guid' => $guid]);
        return new midcom_response_relocate($relocate);
    }

    /**
     * Object display
     */
    public function _handler_view(string $handler_id, string $guid, array &$data)
    {
        $this->_load_object($guid);
        $this->_load_schemadb();

        // Hide the revision message
        $this->schemadb->get_first()->get_field('_rcs_message')['hidden'] = true;

        $this->datamanager = (new datamanager($this->schemadb))
            ->set_storage($this->_object);

        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        $this->_prepare_request_data();
        return $this->get_response('midgard_admin_asgard_object_view');
    }

    /**
     * Object editing view
     */
    public function _handler_edit(Request $request, string $handler_id, string $guid, array &$data)
    {
        $this->_load_object($guid);
        $this->_object->require_do('midgard:update');
        $this->_load_schemadb();

        $this->controller = (new datamanager($this->schemadb))
            ->set_storage($this->_object, 'default')
            ->get_controller();
        switch ($this->controller->handle($request)) {
            case 'save':
                // Reindex the object
                //$indexer = midcom::get()->indexer;
                //net_nemein_wiki_viewer::index($this->_request_data['controller']->datamanager, $indexer, $this->_topic);
                //Fall-through

            case 'cancel':
                return $this->_prepare_relocate($this->_object);
        }

        $this->_prepare_request_data();
        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        return $this->get_response('midgard_admin_asgard_object_edit');
    }

    /**
     * Object creating view
     */
    public function _handler_create(Request $request, string $handler_id, array $args, array &$data)
    {
        $data['current_type'] = $args[0];
        $create_type = midcom::get()->dbclassloader->get_midcom_class_name_for_mgdschema_object($data['current_type']);
        if (!$create_type) {
            throw new midcom_error_notfound('Failed to find type for the new object');
        }
        $new_object = new $create_type();

        if (in_array($handler_id, ['object_create_toplevel', 'object_create_chooser'])) {
            midcom::get()->auth->require_user_do('midgard:create', class: $create_type);

            $data['view_title'] = sprintf($this->_l10n_midcom->get('create %s'), midgard_admin_asgard_plugin::get_type_label($data['current_type']));
        } else {
            $this->_object = midcom::get()->dbfactory->get_object_by_guid($args[1]);
            $this->_object->require_do('midgard:create');
            midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        }

        $this->_load_schemadb($new_object);

        $this->controller = (new datamanager($this->schemadb))
            ->set_defaults($this->get_defaults($request, $create_type))
            ->set_storage($new_object, 'default')
            ->get_controller();

        if ($handler_id === 'object_create_chooser') {
            midcom::get()->head->set_pagetitle($data['view_title']);
            $workflow = $this->get_workflow('chooser', [
                'controller' => $this->controller
            ]);
            return $workflow->run($request);
        }

        switch ($this->controller->handle($request)) {
            case 'save':
                // Reindex the object
                //$indexer = midcom::get()->indexer;
                //net_nemein_wiki_viewer::index($this->_request_data['controller']->datamanager, $indexer, $this->_topic);
                return $this->_prepare_relocate($new_object);

            case 'cancel':
                if ($this->_object) {
                    return $this->_prepare_relocate($this->_object);
                }
                return new midcom_response_relocate($this->router->generate('type', ['type' => $args[0]]));
        }

        $this->_prepare_request_data();
        return $this->get_response('midgard_admin_asgard_object_create');
    }

    private function get_defaults(Request $request, string $new_type) : array
    {
        $defaults = [];
        if ($this->_object) {
            // Figure out the linking property
            $parent_property = midgard_object_class::get_property_parent($this->_request_data['current_type']);
            $new_type_reflector = midcom_helper_reflector::get($new_type);
            $link_properties = $new_type_reflector->get_link_properties();
            foreach ($link_properties as $property => $link) {
                if (   ($link['class'] && midcom_helper_reflector::is_same_class($link['class'], $this->_object->__mgdschema_class_name__))
                    || $link['type'] == MGD_TYPE_GUID) {
                    $defaults[$property] = $this->_object->{$link['target']};
                } elseif (   $property == $parent_property
                          && midcom_helper_reflector::is_same_class($new_type, $this->_object->__mgdschema_class_name__)) {
                    $defaults[$property] = $this->_object->$parent_property;
                }
            }
            if (empty($defaults)) {
                throw new midcom_error("Could not establish link between {$new_type} and " . $this->_object::class);
            }
        }

        // Allow setting defaults from query string, useful for things like "create event for today" and chooser
        if ($request->query->has('defaults')) {
            $get_defaults = array_intersect_key($request->query->all('defaults'), $this->schemadb->get_first()->get('fields'));
            $defaults = array_merge($defaults, array_map(trim(...), $get_defaults));
        }
        return $defaults;
    }

    private function _prepare_relocate(midcom_core_dbaobject $object, string $mode = 'default') : midcom_response_relocate
    {
        // Redirect parameters to overview
        if ($object instanceof midcom_db_parameter) {
            return new midcom_response_relocate($this->router->generate('object_parameters', ['guid' => $object->parentguid]));
        }

        if ($mode == 'delete') {
            // Redirect person deletion to user management
            if ($object instanceof midcom_db_person) {
                return new midcom_response_relocate("__mfa/asgard_midgard.admin.user/");
            }
            if ($parent = $object->get_parent()) {
                return $this->_prepare_relocate($parent);
            }

            $url = $this->router->generate('type', ['type' => $object->__mgdschema_class_name__]);
        } else {
            // Redirect persons to user management
            if ($object instanceof midcom_db_person) {
                return new midcom_response_relocate("__mfa/asgard_midgard.admin.user/edit/{$object->guid}/");
            }
            // Redirect to default object mode page.
            $url = $this->router->generate('object_' . $this->_request_data['default_mode'], ['guid' => $object->guid]);
        }
        return new midcom_response_relocate($url);
    }

    /**
     * Object deletion
     */
    public function _handler_delete(Request $request, string $handler_id, string $guid, array &$data)
    {
        $this->_load_object($guid);
        $this->_object->require_do('midgard:delete');
        $this->_load_schemadb();

        $this->datamanager = (new datamanager($this->schemadb))
            ->set_storage($this->_object, 'default');

        if ($request->request->has('midgard_admin_asgard_deleteok')) {
            // Deletion confirmed.
            $this->_object->_use_rcs = !$request->request->getBoolean('midgard_admin_asgard_disablercs');

            if (!$this->_object->delete_tree()) {
                throw new midcom_error("Failed to delete object {$guid}, last Midgard error was: " . midcom_connection::get_error_string());
            }

            return $this->_prepare_relocate($this->_object, 'delete');
        }

        if ($request->request->has('midgard_admin_asgard_deletecancel')) {
            return $this->_prepare_relocate($this->_object);
        }

        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        $this->_prepare_request_data();
        $this->_add_jscripts();

        // Initialize the tree
        $data['tree'] = new midgard_admin_asgard_copytree($this->_object, $data);
        $data['tree']->copy_tree = false;
        $data['tree']->inputs = false;

        return $this->get_response('midgard_admin_asgard_object_delete');
    }

    /**
     * Copy handler
     */
    public function _handler_copy(Request $request, string $handler_id, string $guid, array &$data)
    {
        $this->_load_object($guid);

        $parent = midcom_helper_reflector_copy::get_parent_property($this->_object);
        $this->_load_schemadb($this->_object, [$parent], true);
        if ($parent) {
            // Change the name for the parent field
            $this->schemadb->get_first()->get_field($parent)['title'] = $this->_l10n->get('choose the target');
        }

        $this->controller = (new datamanager($this->schemadb))->get_controller();

        $this->_prepare_request_data();
        $reflector = new midcom_helper_reflector($this->_object);

        // Process the form
        switch ($this->controller->handle($request)) {
            case 'save':
                $new_object = $this->_process_copy($request, $parent, $reflector);
                // Relocate to the newly created object
                return $this->_prepare_relocate($new_object);

            case 'cancel':
                return $this->_prepare_relocate($this->_object);
        }

        $this->_add_jscripts();

        // Common hooks for Asgard
        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);

        // Set the page title
        $label = $reflector->get_object_label($this->_object);
        $data['page_title'] = sprintf($this->_l10n->get('copy %s'), $label);

        return $this->get_response();
    }

    /**
     * Add the necessary static files for copy/delete operations
     */
    private function _add_jscripts()
    {
        // Add Colorbox
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/colorbox/jquery.colorbox-min.js');
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/jQuery/colorbox/colorbox.css', 'screen');
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midgard.admin.asgard/object_browser.js');

        // Add jQuery file for the checkbox operations
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midgard.admin.asgard/jquery-copytree.js');
    }

    private function _process_copy(Request $request, ?string $parent, midcom_helper_reflector $reflector) : midcom_core_dbaobject
    {
        $formdata = $this->controller->get_datamanager()->get_content_raw();
        $copy = new midcom_helper_reflector_copy();

        // Copying of parameters, metadata and such
        $copy->parameters = $formdata['parameters'];
        $copy->metadata = $formdata['metadata'];
        $copy->attachments = $formdata['attachments'];
        $copy->privileges = $formdata['privileges'];

        // Set the target - if available
        if (!empty($formdata[$parent])) {
            $link_properties = $reflector->get_link_properties();

            if (empty($link_properties[$parent])) {
                throw new midcom_error('Failed to construct the target class object');
            }
            $class_name = midcom::get()->dbclassloader->get_midcom_class_name_for_mgdschema_object($link_properties[$parent]['class']);
            $copy->target = new $class_name($formdata[$parent]);
        }
        $copy->exclude = array_diff($request->request->all('all_objects'), $request->request->all('selected'));
        $new_object = $copy->execute($this->_object);

        if ($new_object === null) {
            debug_print_r('Copying failed with the following errors', $copy->errors, MIDCOM_LOG_ERROR);
            throw new midcom_error('Failed to successfully copy the object. Details in error level log');
        }

        midcom::get()->uimessages->add($this->_l10n->get($this->_component), $this->_l10n->get('copy successful, you have been relocated to the new object'));
        return $new_object;
    }

    /**
     * Show copy style
     */
    public function _show_copy(string $handler_id, array &$data)
    {
        // Show the tree hierarchy
        $data['tree'] = new midgard_admin_asgard_copytree($this->_object, $data);
        $data['tree']->inputs = true;
        $data['tree']->copy_tree = true;
        midcom_show_style('midgard_admin_asgard_object_copytree');

        midcom_show_style('midgard_admin_asgard_object_copy');
    }
}
