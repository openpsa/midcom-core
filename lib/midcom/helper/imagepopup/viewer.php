<?php
/**
 * @author tarjei huse
 * @package midcom.helper.imagepopup
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This is the class that defines which URLs should be handled by this module.
 *
 * @package midcom.helper.imagepopup
 */
class midcom_helper_imagepopup_viewer extends midcom_baseclasses_components_plugin
{
    public static function get_navigation(array $data) : array
    {
        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX) . "__ais/imagepopup/";

        $navlinks = [
            'links' => [
                'url' => $prefix . 'links/' . $data['filetype'] . '/',
                'label' => $data['l10n']->get('links'),
                'selected' => false
            ],
            'page' => [
                'url' => $prefix . $data['filetype'] . '/',
                'label' => $data['l10n_midcom']->get('page'),
                'selected' => false
            ],
            'folder' => [
                'url' => $prefix . 'folder/' . $data['filetype'] . '/',
                'label' => $data['l10n_midcom']->get('folder'),
                'selected' => false
            ],
            'unified' => [
                'url' => $prefix . 'unified/' . $data['filetype'] . '/',
                'label' => $data['l10n']->get('unified search'),
                'selected' => false
            ]
        ];
        $navlinks[$data['list_type']]['selected'] = true;

        if (!empty($data['object'])) {
            foreach ($navlinks as &$link) {
                $link['url'] .= $data['object']->guid . '/';
            }
        } else {
            unset($navlinks['page']);
        }
        if ($data['filetype'] !== 'file') {
            unset($navlinks['links']);
        }
        return $navlinks;
    }
}
