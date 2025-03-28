<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Plugin interface
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_plugin extends midcom_baseclasses_components_plugin
{
    public function _on_initialize()
    {
        self::setup($this->_request_data);
    }

    /**
     * Static method other plugins may use
     */
    public static function prepare_plugin(string $title, array &$data)
    {
        self::setup($data);

        $data['view_title'] = $title;

        midcom::get()->style->prepend_component_styledir(str_replace('asgard_', '', $data['plugin_name']));
    }

    private static function setup(array &$data)
    {
        midcom::get()->auth->require_user_do('midgard.admin.asgard:access', class: 'midgard_admin_asgard_plugin');
        // Disable content caching
        midcom::get()->cache->content->no_cache();

        // Preferred language
        if ($language = self::get_preference('interface_language', false)) {
            midcom::get()->i18n->set_language($language);
        }
        // Ensure we get the correct styles
        midcom::get()->style->prepend_component_styledir('midgard.admin.asgard');

        // Set the default object mode
        $data['default_mode'] = self::get_preference('edit_mode') ? 'edit' : 'view';

        $data['asgard_toolbar'] = new midgard_admin_asgard_toolbar;
    }

    public static function get_type_label(string $type) : string
    {
        return midcom_helper_reflector_tree::get($type)->get_class_label();
    }

    /**
     * Bind view to an object
     */
    public static function bind_to_object(midcom_core_dbaobject $object, string $handler_id, array &$data)
    {
        // Tell our object to MidCOM
        midcom::get()->metadata->set_request_metadata($object->metadata->revised, $object->guid);
        $data['object_reflector'] = midcom_helper_reflector::get($object);
        $data['tree_reflector'] = midcom_helper_reflector_tree::get($object);

        $data['object'] = $object;

        // Populate toolbars
        if (midcom::get()->dbclassloader->is_midcom_db_object($object)) {
            // Bind the object to the metadata service
            midcom::get()->metadata->bind_to($object);

            // These toolbars only work with DBA objects as they do ACL checks
            $view_toolbar = midcom::get()->toolbars->get_view_toolbar();
            $view_toolbar->bind_to($object);
            $data['asgard_toolbar']->bind_to_object($object, $handler_id, $data);
            self::_set_object_breadcrumb($object, $handler_id, $data);
        }

        self::set_pagetitle($object, $handler_id, $data);
    }

    public static function set_pagetitle($object, string $handler_id, array &$data)
    {
        $l10n = midcom::get()->i18n->get_l10n('midgard.admin.asgard');
        // Figure out correct title and language handling
        $title_string = match ($handler_id) {
            'object_edit' => $l10n->get('edit %s %s'),
            'object_metadata' => $l10n->get('metadata of %s %s'),
            'object_attachments',
            'object_attachments_edit' => $l10n->get('attachments of %s %s'),
            'object_parameters' => $l10n->get('parameters of %s %s'),
            'object_permissions' => $l10n->get('permissions for %s %s'),
            'object_create' => sprintf($l10n->get('create %s under %s'), self::get_type_label($data['current_type']), '%s %s'),
            'object_delete' => $l10n->get('delete %s %s'),
            default => $l10n->get('%s %s')
        };

        $label = $data['object_reflector']->get_object_label($object);
        $type_label = self::get_type_label($object::class);
        $data['view_title'] = sprintf($title_string, $type_label, $label);
    }

    /**
     * Construct urls for the breadcrumbs
     */
    private static function _generate_url(string $action, string $guid) : string
    {
        return '__mfa/asgard/object/' . $action . '/' . $guid . '/';
    }

    /**
     * Populate the object breadcrumb
     */
    private static function _set_object_breadcrumb(midcom_core_dbaobject $object, string $handler_id, array $data)
    {
        $breadcrumb = [];
        $label = $data['object_reflector']->get_object_label($object);
        $breadcrumb[] = [
            MIDCOM_NAV_URL => self::_generate_url('view', $object->guid),
            MIDCOM_NAV_NAME => html_entity_decode($label),
        ];

        $parent = $object->get_parent();

        if (   $object instanceof midcom_db_parameter
            && !empty($parent->guid)) {
            // Add "parameters" list to breadcrumb if we're in a param
            $breadcrumb[] = [
                MIDCOM_NAV_URL => self::_generate_url('parameters', $parent->guid),
                MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('parameters', 'midcom'),
            ];
        }

        $i = 0;
        while (   !empty($parent->guid)
               && $i < 10) {
            $i++;
            $parent_reflector = midcom_helper_reflector::get($parent);
            $parent_label = $parent_reflector->get_object_label($parent);
            $breadcrumb[] = [
                MIDCOM_NAV_URL => self::_generate_url('view', $parent->guid),
                MIDCOM_NAV_NAME => $parent_label,
            ];
            $parent = $parent->get_parent();
        }
        $breadcrumb = array_reverse($breadcrumb);

        switch ($handler_id) {
            case 'object_edit':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('edit', $object->guid),
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('edit', 'midcom'),
                ];
                break;
            case 'object_copy':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('copy', $object->guid),
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('copy', 'midcom'),
                ];
                break;
            case 'components_configuration_edit_folder':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => "__mfa/asgard/components/configuration/edit/{$object->component}/{$object->guid}/",
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('component configuration', 'midcom'),
                ];
                break;
            case 'object_metadata':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('metadata', $object->guid),
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('metadata', 'midcom'),
                ];
                break;
            case 'object_attachments':
            case 'object_attachments_edit':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('attachments', $object->guid),
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('attachments', 'midgard.admin.asgard'),
                ];

                if ($handler_id == 'object_attachments_edit') {
                    $breadcrumb[] = [
                        MIDCOM_NAV_URL => "__mfa/asgard/object/attachments/{$object->guid}/edit/",
                        MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('edit', 'midcom'),
                    ];
                }
                break;
            case 'object_parameters':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('parameters', $object->guid),
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('parameters', 'midcom'),
                ];
                break;
            case 'object_permissions':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('permissions', $object->guid),
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('privileges', 'midcom'),
                ];
                break;
            case 'object_create':
                if ($data['current_type'] == 'midgard_parameter') {
                    // Add "parameters" list to breadcrumb if we're creating a param
                    $breadcrumb[] = [
                        MIDCOM_NAV_URL => self::_generate_url('parameters', $object->guid),
                        MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('parameters', 'midcom'),
                    ];
                }
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('create' . $data['current_type'], $object->guid),
                    MIDCOM_NAV_NAME => sprintf(midcom::get()->i18n->get_string('create %s', 'midcom'), self::get_type_label($data['current_type'])),
                ];
                break;
            case 'object_delete':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => self::_generate_url('delete', $object->guid),
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('delete', 'midcom'),
                ];
                break;
            case '____mfa-asgard_midcom.helper.replicator-object':
                $breadcrumb[] = [
                    MIDCOM_NAV_URL => "__mfa/asgard_midcom.helper.replicator/object/{$object->guid}/",
                    MIDCOM_NAV_NAME => midcom::get()->i18n->get_string('replication information', 'midcom.helper.replicator'),
                ];
                break;
        }

        midcom_core_context::get()->set_custom_key('midcom.helper.nav.breadcrumb', $breadcrumb);
    }

    /**
     * Get a preference for the current user
     */
    public static function get_preference(string $preference, bool $fallback_to_config = true)
    {
        static $preferences = [];

        if (   !array_key_exists($preference, $preferences)
            && $person = midcom::get()->auth->user?->get_storage()) {
            $preferences[$preference] = $person->get_parameter('midgard.admin.asgard:preferences', $preference);
        }

        if (!array_key_exists($preference, $preferences)) {
            if ($fallback_to_config) {
                $preferences[$preference] = midcom_baseclasses_components_configuration::get('midgard.admin.asgard', 'config')->get($preference);
            } else {
                $preferences[$preference] = null;
            }
        }

        return $preferences[$preference];
    }

    /**
     * Get the MgdSchema root classes
     *
     * @return array containing class name and translated name
     */
    public static function get_root_classes() : array
    {
        static $root_classes = [];

        if (empty($root_classes)) {
            // Get the classes
            $classes = midcom_helper_reflector_tree::get_root_classes();

            // Get the translated name
            foreach ($classes as $class) {
                $ref = new midcom_helper_reflector($class);
                $root_classes[$class] = $ref->get_class_label();
            }

            asort($root_classes);
        }

        return $root_classes;
    }
}
