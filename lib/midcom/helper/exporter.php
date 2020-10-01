<?php
/**
 * @author tarjei huse
 * @package midcom.helper
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Exporter baseclass
 *
 * @package midcom.helper
 */
abstract class midcom_helper_exporter
{
    abstract public function array2data(array $array) : string;

    abstract public function data2array(string $data) : array;

    /**
     * Take an object and return an array of useful fields (removing private properties)
     *
     * @param object $object
     */
    public function object2array($object) : array
    {
        if (!is_object($object)) {
            debug_add("Missing object needed as parameter.", MIDCOM_LOG_ERROR);
            return false;
        }

        $out = [];
        $fields = midcom_helper_reflector::get_object_fieldnames($object);

        foreach ($fields as $key) {
            if ($key[0] == '_') {
                // Remove private fields
                continue;
            }
            if (is_object($object->{$key})) {
                $out[$key] = $this->object2array($object->{$key});
            } else {
                $out[$key] = $object->{$key};
            }
        }
        return $out;
    }

    /**
     * Take data from array and move it into an object
     *
     * @param array $data
     * @param midcom_core_dbaobject $object The object in question
     * @return object the updated object (not saved)
     */
    public function array2object(array $data, midcom_core_dbaobject $object) : midcom_core_dbaobject
    {
        // set the object's values to the ones from the data
        $fields = midcom_helper_reflector::get_object_fieldnames($object);
        foreach ($fields as $field_name) {
            // skip private fields.
            if ($field_name[0] == '_') {
                continue;
            }

            // skip read_only fields
            if (in_array($field_name, ['guid', 'id'])) {
                continue;
            }

            // TODO: decide what to do with object metadata
            if ($field_name == 'metadata') {
                continue;
            }

            if (isset($data[$field_name])) {
                $object->{$field_name} = $data[$field_name];
                continue;
            }

            // unset any other value that was there before.
            $object->{$field_name} = null;
        }
        return $object;
    }

    public function data2object(array $data, midcom_core_dbaobject $object) : midcom_core_dbaobject
    {
        return $this->array2object($data, $object);
    }

    /**
     * Get the correct classname
     *
     * @param object $object the object
     */
    protected function _get_classname($object) : string
    {
        if (!empty($object->__mgdschema_class_name__)) {
            return $object->__mgdschema_class_name__;
        }
        return get_class($object);
    }
}
