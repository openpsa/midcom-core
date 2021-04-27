<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
*/

namespace midcom\datamanager\storage;

use midcom\datamanager\helper\imagefilter;

/**
 * Images storage
 *
 * Controls a list of images
 */
class images extends blobs implements recreateable
{
    public function recreate() : bool
    {
        $this->map = [];
        $map = $this->load();

        foreach ($map as $identifier => &$images) {
            $filter = new imagefilter($this->config['type_config']);
            $images = $filter->process($images['main'], $images);

            foreach ($images as $name => $image) {
                $this->map[$identifier . $name] = $image;
            }
        }
        return $this->save_image_map($map) && $this->save_attachment_list();
    }

    public function load()
    {
        $results = parent::load();
        $grouped = [];

        $identifiers = [];
        if ($raw_list = $this->object->get_parameter('midcom.helper.datamanager2.type.images', "attachment_map_{$this->config['name']}")) {
            $identifiers = explode(',', $raw_list);
        } else {
            // Reconstruct from blobs data
            foreach (array_keys($results) as $identifier) {
                $identifiers[] = $identifier . ':' . substr($identifier, 0, 32) . ':main';
            }
        }

        $map = [];
        foreach ($identifiers as $item) {
            [$identifier, $images_identifier, $images_name] = explode(':', $item);
            $map[$identifier] = [$images_identifier, $images_name];
        }
        // we iterate over results since that takes sorting into account
        foreach ($results as $identifier => $image) {
            [$images_identifier, $images_name] = $map[$identifier];
            if (!array_key_exists($images_identifier, $grouped)) {
                $grouped[$images_identifier] = [];
            }
            $grouped[$images_identifier][$images_name] = $image;
        }

        return $grouped;
    }

    public function save()
    {
        $this->map = [];
        $map = $this->load();
        foreach ($this->value as $identifier => $images) {
            if (!empty($images['file'])) {
                if (is_numeric($identifier)) {
                    $identifier = md5(time() . $images['file']->name . $images['file']->location);
                }
                $images['file']->parentguid = $this->object->guid;
                $existing = array_key_exists($identifier, $map) ? $map[$identifier] : [];
                $filter = new imagefilter($this->config['type_config']);
                $map[$identifier] = $filter->process($images['file'], $existing);
            }
            foreach ($map[$identifier] as $name => $image) {
                $this->map[$identifier . $name] = $image;
            }

            if (!empty($images['main'])) {
                if (array_key_exists('description', $images)) {
                    $images['main']->set_parameter('midcom.helper.datamanager2.type.blobs', 'description', $images['description']);
                }
                if (array_key_exists('title', $images) && $images['main']->title != $images['title']) {
                    $images['main']->title = $images['title'];
                    $images['main']->update();
                }
                if (!empty($this->config['widget_config']['sortable'])) {
                    $images['main']->update();
                }
            }
        }

        $this->save_image_map($map);
        $this->save_attachment_list();
    }

    private function save_image_map(array $map) : bool
    {
        $list = [];

        foreach ($map as $identifier => $derived) {
            foreach (array_keys($derived) as $name) {
                $list[] = $identifier . $name . ':' . $identifier . ':' . $name;
            }
        }

        return $this->object->set_parameter('midcom.helper.datamanager2.type.images', "attachment_map_{$this->config['name']}", implode(',', $list));
    }
}
