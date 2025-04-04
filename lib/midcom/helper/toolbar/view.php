<?php
/**
 * @package midcom.helper
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class is a view toolbar class.
 *
 * @package midcom.helper
 */
class midcom_helper_toolbar_view extends midcom_helper_toolbar
{
    /**
     * @param string $class_style The class style tag for the UL.
     * @param string $id_style The id style tag for the UL.
     */
    public function __construct(?string $class_style = null, ?string $id_style = null)
    {
        $config = midcom::get()->config;
        $class_style = $class_style ?: $config->get('toolbars_view_style_class');
        $id_style = $id_style ?: $config->get('toolbars_view_style_id');
        parent::__construct($class_style, $id_style);
        $this->label = midcom::get()->i18n->get_string('page', 'midcom');
    }

    /**
     * Binds the a toolbar to a DBA object. This will append a number of globally available
     * toolbar options. For example, expect Metadata- and Version Control-related options
     * to be added.
     *
     * Repeated bind calls are intercepted, you can only bind a toolbar to a single object.
     */
    public function bind_to(midcom_core_dbaobject $object)
    {
        if (!midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX)) {
            debug_add("Toolbar for object {$object->guid} was called before topic prefix was available, skipping global items.", MIDCOM_LOG_WARN);
            return;
        }
        if (array_key_exists('midcom_services_toolbars_bound_to_object', $this->customdata)) {
            // We already processed this toolbar, skipping further adds.
            return;
        }
        $this->customdata['midcom_services_toolbars_bound_to_object'] = true;

        $reflector = new midcom_helper_reflector($object);
        $this->set_label($reflector->get_class_label());

        $buttons = $this->get_approval_controls($object);

        if ($object->can_do('midgard:update')) {
            $workflow = new midcom\workflow\datamanager;
            $buttons[] = $workflow->get_button("__ais/folder/metadata/{$object->guid}/", [
                MIDCOM_TOOLBAR_LABEL => midcom::get()->i18n->get_string('edit metadata', 'midcom.admin.folder'),
                MIDCOM_TOOLBAR_GLYPHICON => 'database',
                MIDCOM_TOOLBAR_ACCESSKEY => 'm',
            ]);
            $viewer = new midcom\workflow\viewer;
            $buttons = array_merge($buttons, [
                $viewer->get_button("__ais/folder/move/{$object->guid}/", [
                    MIDCOM_TOOLBAR_LABEL => midcom::get()->i18n->get_string('move', 'midcom.admin.folder'),
                    MIDCOM_TOOLBAR_GLYPHICON => 'arrows',
                    MIDCOM_TOOLBAR_ENABLED => $object instanceof midcom_db_article
                ]),
                [
                    MIDCOM_TOOLBAR_URL => midcom_connection::get_url('self') . "__mfa/asgard/object/open/{$object->guid}/",
                    MIDCOM_TOOLBAR_LABEL => midcom::get()->i18n->get_string('manage object', 'midgard.admin.asgard'),
                    MIDCOM_TOOLBAR_GLYPHICON => 'cog',
                    MIDCOM_TOOLBAR_ENABLED =>    midcom::get()->auth->can_user_do('midgard.admin.asgard:access', class: 'midgard_admin_asgard_plugin')
                                              && midcom::get()->auth->can_user_do('midgard.admin.asgard:manage_objects', class: 'midgard_admin_asgard_plugin'),
                ]
            ]);
        }

        if (   midcom::get()->config->get('midcom_services_rcs_enable')
            && $object->can_do('midgard:update')
            && $object->_use_rcs) {
            $buttons[] = [
                MIDCOM_TOOLBAR_URL => "__ais/rcs/{$object->guid}/",
                MIDCOM_TOOLBAR_LABEL => midcom::get()->i18n->get_string('show history', 'midcom.admin.rcs'),
                MIDCOM_TOOLBAR_GLYPHICON => 'history',
                MIDCOM_TOOLBAR_ACCESSKEY => 'v',
            ];
        }
        $this->add_items($buttons);
    }

    public function get_approval_controls(midcom_core_dbaobject $object, bool $add_accesskey = false) : array
    {
        $buttons = [];
        if (midcom::get()->config->get('metadata_approval')) {
            $published = midcom::get()->config->get('show_hidden_objects') || $object->metadata->is_visible();
            if ($object->metadata->is_approved()) {
                $action = 'unapprove';
                $helptext = 'approved';
                $accesskey = 'u';
                $icon = $published ? 'check-square-o' : 'calendar-check-o';
            } else {
                $action = 'approve';
                $helptext = 'unapproved';
                $accesskey = 'a';
                $icon = $published ? 'times-rectangle-o' : 'calendar-times-o';
            }

            $buttons[] = [
                MIDCOM_TOOLBAR_URL => "__ais/folder/" . $action . "/",
                MIDCOM_TOOLBAR_LABEL => midcom::get()->i18n->get_string($action, 'midcom'),
                MIDCOM_TOOLBAR_HELPTEXT => midcom::get()->i18n->get_string($helptext, 'midcom'),
                MIDCOM_TOOLBAR_GLYPHICON => $icon,
                MIDCOM_TOOLBAR_POST => true,
                MIDCOM_TOOLBAR_POST_HIDDENARGS => [
                    'guid' => $object->guid,
                    'return_to' => $_SERVER['REQUEST_URI'],
                ],
                MIDCOM_TOOLBAR_ACCESSKEY => ($add_accesskey) ? $accesskey : null,
                MIDCOM_TOOLBAR_ENABLED => $object->can_do('midcom:approve'),
            ];
        }
        return $buttons;
    }
}
