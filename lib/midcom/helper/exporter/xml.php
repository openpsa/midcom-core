<?php
/**
 * @author tarjei huse
 * @package midcom.helper
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Move midgard objects to and from XML
 *
 * @package midcom.helper
 */
class midcom_helper_exporter_xml extends midcom_helper_exporter
{
    /**
     * Make an array out of some xml.
     *
     * Note, the function expects xml like this:
     * <objecttype><attribute>attribute_value</attribute></objecttype>
     * But it will not return the objecttype.
     *
     * @param string $data xml
     * @return array with attribute => key values
     */
    public function data2array(string $data) : array
    {
        return $this->_xml_to_array(new SimpleXMLIterator($data));
    }

    private function _xml_to_array(SimpleXMLIterator $sxi) : array
    {
        $data = [];
        foreach ($sxi as $key => $val) {
            if ($sxi->hasChildren()) {
                $data[$key] = $this->_xml_to_array($val);
            } else {
                $val = trim($val);
                //TODO: This is mainly here for backward-compatibility. Its main effect
                // is that 0 is replaced by an empty string. The question is: Do we want/need this?
                if (!$val) {
                    $val = '';
                }
                $data[$key] = trim($val);
            }
        }
        return $data;
    }

    /**
     * Make XML out of an array.
     *
     * @param array $array
     */
    public function array2data(array $array, string $root_node = 'array', string $prefix = '') : string
    {
        $data = "{$prefix}<{$root_node}>\n";

        foreach ($array as $key => $field) {
            if (is_numeric($key)) {
                $key = 'value';
            }

            if (is_object($field)) {
                $data .= $this->object2data($field, "{$prefix}    ");
            } elseif (is_array($field)) {
                $data .= $this->array2data($field, $key, "{$prefix}    ") . "\n";
            } elseif (is_numeric($field) || $field === null || is_bool($field)) {
                $data .= "{$prefix}    <{$key}>{$field}</{$key}>\n";
            } else {
                // String
                $data .= "{$prefix}    <{$key}><![CDATA[{$field}]]></{$key}>\n";
            }
        }

        $data .= "{$prefix}</{$root_node}>\n";

        return $data;
    }

    /**
     * Make XML out of an object.
     *
     * @param midcom_core_dbaobject $object
     */
    public function object2data($object, string $prefix = '') : string
    {
        $arr = $this->object2array($object);
        if (!$arr) {
            return '';
        }

        $classname = $this->_get_classname($object);

        $data = "{$prefix}<{$classname}";
        if (!empty($object->guid)) {
            $data .= " id=\"{$object->id}\" guid=\"{$object->guid}\"";
        }
        $data .= ">\n";

        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $root_node = isset($object->{$key}) ? $this->_get_classname($object->{$key}) : "array";
                $data .= $this->array2data($val, $root_node, "    ");
            } elseif (is_numeric($val) || $val === null || is_bool($val)) {
                $data .= "{$prefix}    <{$key}>{$val}</{$key}>\n";
            } else {
                $data .= "{$prefix}    <{$key}><![CDATA[{$val}]]></{$key}>\n";
            }
        }

        $data .= "{$prefix}</{$classname}>";

        return $data;
    }
}
