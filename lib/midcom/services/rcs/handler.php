<?php
/**
 * @package midcom.services.rcs
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 */

use Symfony\Component\HttpFoundation\Request;

/**
 * @package midcom.services.rcs
 */
abstract class midcom_services_rcs_handler extends midcom_baseclasses_components_handler
{
    /**
     * RCS backend
     *
     * @var midcom_services_rcs_backend
     */
    private $backend;

    /**
     * Pointer to midgard object
     *
     * @var midcom_core_dbaobject
     */
    protected $object;

    protected $style_prefix = '';

    protected $url_prefix = '';

    abstract protected function get_object_url() : string;

    abstract protected function handler_callback(string $handler_id);

    abstract protected function get_breadcrumbs();

    protected function resolve_object_title()
    {
        return midcom_helper_reflector::get($this->object)->get_object_label($this->object);
    }

    /**
     * Load the object and the rcs backend
     */
    private function load_object(string $guid)
    {
        $this->object = midcom::get()->dbfactory->get_object_by_guid($guid);

        if (   !midcom::get()->config->get('midcom_services_rcs_enable')
            || !$this->object->_use_rcs) {
            throw new midcom_error_notfound("Revision control not supported for " . get_class($this->object) . ".");
        }

        $this->backend = midcom::get()->rcs->load_backend($this->object);
    }

    /**
     * Prepare version control toolbar
     */
    private function rcs_toolbar(string $revision, string $revision2 = null)
    {
        $this->add_stylesheet(MIDCOM_STATIC_URL . "/midcom.services.rcs/rcs.css");
        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX) . $this->url_prefix;
        $history = $this->backend->get_history();
        $this->_request_data['rcs_toolbar'] = new midcom_helper_toolbar();
        $this->populate_rcs_toolbar($history, $prefix, $revision, $revision2);

        // RCS functional toolbar
        $this->_request_data['rcs_toolbar_2'] = new midcom_helper_toolbar();
        $restore = $revision2 ?: $revision;

        $buttons = [
            [
                MIDCOM_TOOLBAR_URL => "{$prefix}{$this->object->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('show history'),
                MIDCOM_TOOLBAR_GLYPHICON => 'history',
            ], [
                MIDCOM_TOOLBAR_URL => "{$prefix}restore/{$this->object->guid}/{$restore}/",
                MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n->get('restore version %s'), $restore),
                MIDCOM_TOOLBAR_GLYPHICON => 'recycle',
                MIDCOM_TOOLBAR_ENABLED => ($restore !== $history->get_last()['revision']),
            ]
        ];
        $this->_request_data['rcs_toolbar_2']->add_items($buttons);
    }

    private function populate_rcs_toolbar(midcom_services_rcs_history $history, string $prefix, string $revision, ?string $revision2)
    {
        $first = $history->get_first()['revision'];
        $last = $history->get_last()['revision'];

        $revision2 = $revision2 ?? $revision;
        $diff_view = $revision2 != $revision;

        $previous = $history->get_prev_version($revision) ?? $first;
        $enabled = $revision !== $first;
        $this->add_button($prefix . 'preview', $first, 'fast-backward', $enabled || $diff_view, $first);
        if ($diff_view) {
            $this->add_button($prefix . 'preview', $revision, 'backward', true, $revision);
        } else {
            $this->add_button($prefix . 'preview', $previous, 'backward', $enabled, $previous);
        }
        $this->add_button($prefix . 'diff', $this->_l10n->get('show differences'), 'step-backward', $enabled, $previous, $revision);

        $this->add_button($prefix . 'preview', $revision2, 'file-o', $diff_view, $revision2);

        $next = $history->get_next_version($revision2) ?? $last;
        $enabled = $revision2 !== $last;
        $this->add_button($prefix . 'diff', $this->_l10n->get('show differences'), 'step-forward', $enabled, $revision2, $next);
        $this->add_button($prefix . 'preview', $next, 'forward', $enabled || $diff_view, $next);
        $this->add_button($prefix . 'preview', $last, 'fast-forward', $enabled || $diff_view, $last);
    }

    private function add_button(string $prefix, string $label, string $icon, bool $enabled, ...$args)
    {
        $this->_request_data['rcs_toolbar']->add_item([
            MIDCOM_TOOLBAR_URL => "{$prefix}/{$this->object->guid}/" . implode('/', $args ?? []),
            MIDCOM_TOOLBAR_LABEL => $label,
            MIDCOM_TOOLBAR_GLYPHICON => $icon,
            MIDCOM_TOOLBAR_ENABLED => $enabled,
        ]);
    }

    private function prepare_request_data(string $view_title)
    {
        $breadcrumbs = $this->get_breadcrumbs();
        if (!empty($breadcrumbs)) {
            foreach ($breadcrumbs as $item) {
                $this->add_breadcrumb($item[MIDCOM_NAV_URL], $item[MIDCOM_NAV_NAME]);
            }
        }
        $this->add_breadcrumb($this->url_prefix . "{$this->object->guid}/", $this->_l10n->get('show history'));

        if (!empty($this->_request_data['latest_revision'])) {
            $this->add_breadcrumb(
                $this->url_prefix . "preview/{$this->object->guid}/{$this->_request_data['latest_revision']}/",
                sprintf($this->_l10n->get('version %s'), $this->_request_data['latest_revision'])
            );
        }
        if (!empty($this->_request_data['compare_revision'])) {
            $this->add_breadcrumb(
                $this->url_prefix . "diff/{$this->object->guid}/{$this->_request_data['compare_revision']}/{$this->_request_data['latest_revision']}/",
                sprintf($this->_l10n->get('differences between %s and %s'), $this->_request_data['compare_revision'], $this->_request_data['latest_revision'])
            );
        }
        $this->_request_data['handler'] = $this;
        $this->_request_data['view_title'] = $view_title;
        midcom::get()->head->set_pagetitle($view_title);
    }

    public function translate(string $string) : string
    {
        $translated = $string;
        $component = midcom::get()->dbclassloader->get_component_for_class($this->object->__midcom_class_name__);
        if (midcom::get()->componentloader->is_installed($component)) {
            $translated = midcom::get()->i18n->get_l10n($component)->get($string);
        }
        if ($translated === $string) {
            $translated = $this->_l10n->get($string);
            if ($translated === $string) {
                $translated = $this->_l10n_midcom->get($string);
            }
        }
        return $translated;
    }

    /**
     * Show the changes done to the object
     */
    public function _handler_history(Request $request, string $handler_id, array $args)
    {
        // Check if the comparison request is valid
        $first = $request->query->get('first');
        $last = $request->query->get('last');
        if ($first && $last && $first != $last) {
            return new midcom_response_relocate($this->url_prefix . "diff/{$args[0]}/{$first}/{$last}/");
        }

        $this->load_object($args[0]);
        $view_title = sprintf($this->_l10n->get('revision history of %s'), $this->resolve_object_title());

        $this->prepare_request_data($view_title);
        return $this->handler_callback($handler_id);
    }

    /**
     * @param array $data The local request data.
     */
    public function _show_history(string $handler_id, array &$data)
    {
        $data['history'] = $this->backend->get_history();
        $data['guid'] = $this->object->guid;
        midcom_show_style($this->style_prefix . 'history');
    }

    /**
     * Show a diff between two versions
     */
    public function _handler_diff(string $handler_id, array $args, array &$data)
    {
        $this->load_object($args[0]);
        $history = $this->backend->get_history();

        if (   !$history->version_exists($args[1])
            || !$history->version_exists($args[2])) {
            throw new midcom_error_notfound("One of the revisions {$args[1]} or {$args[2]} does not exist.");
        }

        $data['diff'] = array_filter($this->backend->get_diff($args[1], $args[2]), function($value, $key) {
            return array_key_exists('diff', $value)
                && !is_array($value['diff'])
                && midcom_services_rcs::is_field_showable($key);
        }, ARRAY_FILTER_USE_BOTH);
        $data['comment'] = $history->get($args[2]);

        // Set the version numbers
        $data['compare_revision'] = $args[1];
        $data['latest_revision'] = $args[2];
        $data['guid'] = $args[0];

        $view_title = sprintf($this->_l10n->get('changes between revisions %s and %s'), $data['compare_revision'], $data['latest_revision']);

        // Load the toolbars
        $this->rcs_toolbar($args[1], $args[2]);
        $this->prepare_request_data($view_title);
        return $this->handler_callback($handler_id);
    }

    /**
     * Show the differences between the versions
     */
    public function _show_diff()
    {
        midcom_show_style($this->style_prefix . 'diff');
    }

    /**
     * View previews
     */
    public function _handler_preview(string $handler_id, array $args, array &$data)
    {
        $revision = $args[1];
        $data['latest_revision'] = $revision;
        $data['guid'] = $args[0];

        $this->load_object($args[0]);
        $data['preview'] = array_filter($this->backend->get_revision($revision), function ($value, $key) {
            return !is_array($value)
                && !in_array($value, ['', '0000-00-00'])
                && midcom_services_rcs::is_field_showable($key);
        }, ARRAY_FILTER_USE_BOTH);

        $this->_view_toolbar->hide_item($this->url_prefix . "preview/{$this->object->guid}/{$revision}/");

        $view_title = sprintf($this->_l10n->get('viewing version %s of %s'), $revision, $this->resolve_object_title());
        // Load the toolbars
        $this->rcs_toolbar($revision);
        $this->prepare_request_data($view_title);
        return $this->handler_callback($handler_id);
    }

    public function _show_preview()
    {
        midcom_show_style($this->style_prefix . 'preview');
    }

    /**
     * Restore to diff
     */
    public function _handler_restore(array $args)
    {
        $this->load_object($args[0]);

        $this->object->require_do('midgard:update');
        // TODO: set another privilege for restoring?

        if (   $this->backend->get_history()->version_exists($args[1])
            && $this->backend->restore_to_revision($args[1])) {
            midcom::get()->uimessages->add($this->_l10n->get('midcom.admin.rcs'), sprintf($this->_l10n->get('restore to version %s successful'), $args[1]));
            return new midcom_response_relocate($this->get_object_url());
        }
        throw new midcom_error(sprintf($this->_l10n->get('restore to version %s failed, reason %s'), $args[1], midcom_connection::get_error_string()));
    }
}
