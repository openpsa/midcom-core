<?php
/**
 * @package midcom.helper.reflector
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midgard\portable\storage\connection;
use midgard\portable\api\mgdobject;

/**
 * The Grand Unified Reflector
 *
 * @package midcom.helper.reflector
 */
class midcom_helper_reflector extends midcom_baseclasses_components_purecode
{
    public $mgdschema_class = '';

    /**
     * @var midgard_reflection_property
     */
    protected $_mgd_reflector;

    protected $_dummy_object;

    private static $_cache = [
        'l10n' => [],
        'instance' => [],
        'title' => [],
        'name' => [],
        'fieldnames' => [],
        'object_icon_map' => null,
        'create_icon_map' => null
    ];

    /**
     * Constructor, takes classname or object, resolved MgdSchema root class automagically
     *
     * @param string|mgdobject $src classname or object
     */
    public function __construct($src)
    {
        parent::__construct();

        // Resolve root class name
        $this->mgdschema_class = self::resolve_baseclass($src);
        // Could not resolve root class name
        if (empty($this->mgdschema_class)) {
            // Handle object vs string
            $original_class = (is_object($src)) ? get_class($src) : $src;
            throw new midcom_error("Could not determine MgdSchema baseclass for '{$original_class}'");
        }

        // Instantiate midgard reflector
        $this->_mgd_reflector = new midgard_reflection_property($this->mgdschema_class);

        // Instantiate dummy object
        $this->_dummy_object = new $this->mgdschema_class;
    }

    /**
     * Get cached reflector instance
     *
     * @param mixed $src Object or classname
     * @return static
     */
    public static function &get($src)
    {
        $identifier = get_called_class() . (is_object($src) ? get_class($src) : $src);

        if (!isset(self::$_cache['instance'][$identifier])) {
            self::$_cache['instance'][$identifier] = new static($src);
        }
        return self::$_cache['instance'][$identifier];
    }

    /**
     * Get object's (mgdschema) fieldnames.
     *
     * @param object $object Object The object to query
     */
    public static function get_object_fieldnames(object $object) : array
    {
        $classname = get_class($object);
        $metadata = false;

        if (midcom::get()->dbclassloader->is_midcom_db_object($object)) {
            $classname = $object->__mgdschema_class_name__;
        } elseif ($object instanceof midcom_helper_metadata) {
            $metadata = true;
            $classname = $object->object->__mgdschema_class_name__;
        }

        if (is_subclass_of($classname, mgdobject::class)) {
            $cm = connection::get_em()->getClassMetadata($classname);
            return $cm->get_schema_properties($metadata);
        }
        return array_keys(get_object_vars($object));
    }

    public function property_exists(string $property, bool $metadata = false) : bool
    {
        return $this->_mgd_reflector->property_exists($property, $metadata);
    }

    /**
     * Gets a midcom_helper_l10n instance for component governing the type
     */
    public function get_component_l10n() : midcom_services_i18n_l10n
    {
        if (!isset(self::$_cache['l10n'][$this->mgdschema_class])) {
            if ($component = midcom::get()->dbclassloader->get_component_for_class($this->mgdschema_class)) {
                self::$_cache['l10n'][$this->mgdschema_class] = $this->_i18n->get_l10n($component);
            } else {
                debug_add("Could not resolve component for class {$this->mgdschema_class}, using our own l10n", MIDCOM_LOG_INFO);
                self::$_cache['l10n'][$this->mgdschema_class] = $this->_l10n;
            }
        }

        return self::$_cache['l10n'][$this->mgdschema_class];
    }

    /**
     * Get the localized label of the class
     *
     * @todo remove any hardcoded class names/prefixes
     */
    public function get_class_label() : string
    {
        $component_l10n = $this->get_component_l10n();
        $use_classname = $this->mgdschema_class;

        $midcom_dba_classname = midcom::get()->dbclassloader->get_midcom_class_name_for_mgdschema_object($use_classname);

        if (!empty($midcom_dba_classname)) {
            $use_classname = $midcom_dba_classname;
        }

        $use_classname = preg_replace('/_(db|dba)$/', '', $use_classname);

        $label = $component_l10n->get($use_classname);
        if ($label == $use_classname) {
            // Class string not localized, try Bergie's way to pretty-print
            $classname_parts = explode('_', $use_classname);
            if (count($classname_parts) >= 3) {
                // Drop first two parts of class name
                array_shift($classname_parts);
                array_shift($classname_parts);
            }
            // FIXME: Remove hardcoded class prefixes
            $use_label = preg_replace('/(openpsa|notifications)_/', '', implode('_', $classname_parts));

            $use_label = str_replace('_', ' ', $use_label);
            $label = $component_l10n->get($use_label);
            if ($use_label == $label) {
                $label = ucwords($use_label);
            }
        }
        return $label;
    }

    /**
     * Get property name to use as label
     *
     * @return string name of property to use as label (or false on failure)
     */
    public function get_label_property()
    {
        $midcom_class = midcom::get()->dbclassloader->get_midcom_class_name_for_mgdschema_object($this->mgdschema_class);
        $obj = ($midcom_class) ? new $midcom_class : new $this->mgdschema_class;

        if (method_exists($obj, 'get_label_property')) {
            return $obj->get_label_property();
        }
        if (midcom::get()->dbfactory->is_a($obj, midcom_db_person::class)) {
            return ['rname', 'id'];
        }
        return $this->get_title_property_nonstatic($obj) ??
            $this->get_name_property_nonstatic($obj) ??
            'guid';
    }

    /**
     * Get the object label property value
     */
    public function get_object_label(object $object) : ?string
    {
        if ($object instanceof mgdobject) {
            try {
                $obj = midcom::get()->dbfactory->convert_midgard_to_midcom($object);
            } catch (midcom_error $e) {
                return null;
            }
        } else {
            $obj = $object;
        }
        if (method_exists($obj, 'get_label')) {
            return $obj->get_label();
        }

        $properties = array_flip($obj->get_properties());
        if (empty($properties)) {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            return null;
        }
        if (isset($properties['title'])) {
            return $obj->title;
        }
        if (isset($properties['name'])) {
            return $obj->name;
        }
        if ($obj->id > 0) {
            return $this->get_class_label() . ' #' . $obj->id;
        }
        return '';
    }

    /**
     * Get the name of the create icon image
     */
    public static function get_create_icon(string $type) : string
    {
        if (null === self::$_cache['create_icon_map']) {
            self::$_cache['create_icon_map'] = self::_get_icon_map('create_type_magic', 'file-o');
        }

        $icon_callback = [$type, 'get_create_icon'];
        switch (true) {
            // class has static method to tell us the answer ? great !
            case (is_callable($icon_callback)):
                $icon = call_user_func($icon_callback);
                break;
            // configuration icon
            case (isset(self::$_cache['create_icon_map'][$type])):
                $icon = self::$_cache['create_icon_map'][$type];
                break;

            // heuristics magic (instead of adding something here, take a look at config key "create_type_magic")
            case (str_contains($type, 'member')):
            case (str_contains($type, 'organization')):
                $icon = 'users';
                break;
            case (str_contains($type, 'person')):
                $icon = 'user-o';
                break;
            case (str_contains($type, 'event')):
                $icon = 'calendar-o';
                break;

            // Fallback default value
            default:
                $icon = self::$_cache['create_icon_map']['__default__'];
                break;
        }
        return $icon;
    }

    /**
     * Get the object icon
     */
    public static function get_object_icon(object $obj) : string
    {
        if (null === self::$_cache['object_icon_map']) {
            self::$_cache['object_icon_map'] = self::_get_icon_map('object_icon_magic', 'file');
        }

        $object_class = get_class($obj);
        $object_baseclass = self::resolve_baseclass($obj);

        switch (true) {
            // object knows it's icon, how handy!
            case (method_exists($obj, 'get_icon')):
                $icon = $obj->get_icon();
                break;

            // configuration icon
            case (isset(self::$_cache['object_icon_map'][$object_class])):
                $icon = self::$_cache['object_icon_map'][$object_class];
                break;
            case (isset(self::$_cache['object_icon_map'][$object_baseclass])):
                $icon = self::$_cache['object_icon_map'][$object_baseclass];
                break;

            // heuristics magic (instead of adding something here, take a look at config key "object_icon_magic")
            case (str_contains($object_class, 'person')):
                $icon = 'user';
                break;
            case (str_contains($object_class, 'event')):
                $icon = 'calendar-o';
                break;
            case (str_contains($object_class, 'member')):
            case (str_contains($object_class, 'organization')):
            case (str_contains($object_class, 'group')):
                $icon = 'users';
                break;
            case (str_contains($object_class, 'element')):
                $icon = 'file-code-o';
                break;

            // Fallback default value
            default:
                $icon = self::$_cache['object_icon_map']['__default__'];
                break;
        }

        return '<i class="fa fa-' . $icon . '"></i>';
    }

    private static function _get_icon_map(string $config_key, string $fallback) : array
    {
        $config = midcom_baseclasses_components_configuration::get('midcom.helper.reflector', 'config');
        $icons2classes = $config->get($config_key);
        $icon_map = [];
        //sanity
        if (!is_array($icons2classes)) {
            throw new midcom_error('Config key "' . $config_key . '" is not an array');
        }
        foreach ($icons2classes as $icon => $classes) {
            $icon_map = array_merge($icon_map, array_fill_keys($classes, $icon));
        }
        if (!isset($icon_map['__default__'])) {
            $icon_map['__default__'] = $fallback;
        }
        return $icon_map;
    }

    /**
     * Get class properties to use as search fields in choosers or other direct DB searches
     */
    public function get_search_properties() : array
    {
        // Return cached results if we have them
        static $cache = [];
        if (isset($cache[$this->mgdschema_class])) {
            return $cache[$this->mgdschema_class];
        }
        debug_add("Starting analysis for class {$this->mgdschema_class}");

        $properties = self::get_object_fieldnames($this->_dummy_object);

        $default_properties = [
            'title' => true,
            'tag' => true,
            'firstname' => true,
            'lastname' => true,
            'official' => true,
            'username' => true,
        ];

        $search_properties = array_intersect_key($default_properties, array_flip($properties));

        foreach ($properties as $property) {
            if (str_contains($property, 'name')) {
                $search_properties[$property] = true;
            }
            // TODO: More per property heuristics
        }
        // TODO: parent and up heuristics

        $label_prop = $this->get_label_property();

        if (    is_string($label_prop)
             && $label_prop != 'guid'
             && $this->_mgd_reflector->property_exists($label_prop)) {
            $search_properties[$label_prop] = true;
        }

        // Exceptions - always search these fields
        $always_search_all = $this->_config->get('always_search_fields') ?: [];
        if (!empty($always_search_all[$this->mgdschema_class])) {
            $fields = array_intersect($always_search_all[$this->mgdschema_class], $properties);
            $search_properties += array_flip($fields);
        }

        // Exceptions - never search these fields
        $never_search_all = $this->_config->get('never_search_fields') ?: [];
        if (!empty($never_search_all[$this->mgdschema_class])) {
            $search_properties = array_diff_key($search_properties, array_flip($never_search_all[$this->mgdschema_class]));
        }

        $search_properties = array_keys($search_properties);
        debug_print_r("Search properties for {$this->mgdschema_class}: ", $search_properties);
        $cache[$this->mgdschema_class] = $search_properties;
        return $search_properties;
    }

    /**
     * Gets a list of link properties and the links target info
     *
     * Link info key specification
     *     'class' string link target class name
     *     'target' string link target property (of target class)
     *     'parent' boolean link is link to "parent" in object tree
     *     'up' boolean link is link to "up" in object tree
     *
     * @return array multidimensional array keyed by property, values are arrays with link info (or false in case of failure)
     */
    public function get_link_properties() : array
    {
        // Return cached results if we have them
        static $cache = [];
        if (isset($cache[$this->mgdschema_class])) {
            return $cache[$this->mgdschema_class];
        }
        debug_add("Starting analysis for class {$this->mgdschema_class}");

        // Shorthands
        $ref = $this->_mgd_reflector;
        $obj = $this->_dummy_object;

        // Get property list and start checking (or abort on error)
        $properties = self::get_object_fieldnames($obj);

        $links = [];
        $parent_property = midgard_object_class::get_property_parent($this->mgdschema_class);
        $up_property = midgard_object_class::get_property_up($this->mgdschema_class);
        foreach ($properties as $property) {
            if ($property == 'guid') {
                // GUID, even though of type MGD_TYPE_GUID, is never a link
                continue;
            }

            if (   !$ref->is_link($property)
                && $ref->get_midgard_type($property) != MGD_TYPE_GUID) {
                continue;
            }
            debug_add("Processing property '{$property}'");
            $linkinfo = [
                'class' => null,
                'target' => null,
                'parent' => false,
                'up' => false,
                'type' => $ref->get_midgard_type($property),
            ];
            if ($parent_property === $property) {
                debug_add("Is 'parent' property");
                $linkinfo['parent'] = true;
            }
            if ($up_property === $property) {
                debug_add("Is 'up' property");
                $linkinfo['up'] = true;
            }

            $type = $ref->get_link_name($property);
            debug_add("get_link_name returned '{$type}'");
            if (!empty($type)) {
                $linkinfo['class'] = $type;
            }

            $target = $ref->get_link_target($property);

            debug_add("get_link_target returned '{$target}'");
            if (!empty($target)) {
                $linkinfo['target'] = $target;
            } elseif ($linkinfo['type'] == MGD_TYPE_GUID) {
                $linkinfo['target'] = 'guid';
            }

            $links[$property] = $linkinfo;
        }

        debug_print_r("Links for {$this->mgdschema_class}: ", $links);
        $cache[$this->mgdschema_class] = $links;
        return $links;
    }

    /**
     * Map extended classes
     *
     * For example org.openpsa.* components often expand core objects,
     * in config we specify which classes we wish to substitute with which
     *
     * @param string $schema_type classname to check rewriting for
     * @return string new classname (or original in case no rewriting is to be done)
     */
    public static function class_rewrite(string $schema_type) : string
    {
        static $extends = false;
        if ($extends === false) {
            $extends = midcom_baseclasses_components_configuration::get('midcom.helper.reflector', 'config')->get('class_extends');
            // Safety against misconfiguration
            if (!is_array($extends)) {
                throw new midcom_error("config->get('class_extends') did not return array, invalid configuration ??");
            }
        }
        if (   isset($extends[$schema_type])
            && class_exists($extends[$schema_type])) {
            return $extends[$schema_type];
        }
        return $schema_type;
    }

    /**
     * See if two MgdSchema classes are the same
     *
     * NOTE: also takes into account the various extended class scenarios
     *
     * @param string $class_one first class to compare
     * @param string $class_two second class to compare
     */
    public static function is_same_class($class_one, $class_two) : bool
    {
        $one = self::resolve_baseclass($class_one);
        $two = self::resolve_baseclass($class_two);
        return $one == $two;
    }

    /**
     * Get the MgdSchema classname for given class
     *
     * @param mixed $classname either string (class name) or object
     * @return string the base class name
     */
    public static function resolve_baseclass($classname) : ?string
    {
        static $cached = [];

        if (is_object($classname)) {
            $class_instance = $classname;
            $classname = get_class($classname);
        }

        if (empty($classname)) {
            return null;
        }

        if (isset($cached[$classname])) {
            return $cached[$classname];
        }

        if (!isset($class_instance)) {
            $class_instance = new $classname();
        }

        // Check for decorators first
        if (!empty($class_instance->__mgdschema_class_name__)) {
            $parent_class = $class_instance->__mgdschema_class_name__;
            if (   !empty($class_instance->__object)
                && !$class_instance->__object instanceof $class_instance->__mgdschema_class_name__) {
                $parent_class = get_class($class_instance->__object);
                debug_add('mgdschema object class ' . $parent_class . ' is not an instance of ' . $class_instance->__mgdschema_class_name__, MIDCOM_LOG_INFO);
            }
        } else {
            $parent_class = $classname;
        }

        $cached[$classname] = self::class_rewrite($parent_class);

        return $cached[$classname];
    }

    /**
     * Resolve the "name" property of given object
     *
     * @see midcom_helper_reflector::get_name_property()
     * @param object $object the object to get the name property for
     * @todo when midgard_reflection_property supports flagging name fields use that instead of heuristics
     */
    public function get_name_property_nonstatic(object $object) : ?string
    {
        return $this->get_property('name', $object);
    }

    private function get_property(string $type, object $object) : ?string
    {
        // Cache results per class within request
        $key = get_class($object);
        if (array_key_exists($key, self::$_cache[$type])) {
            return self::$_cache[$type][$key];
        }
        self::$_cache[$type][$key] = null;

        // Configured properties
        $exceptions = $this->_config->get($type . '_exceptions');
        foreach ($exceptions as $class => $property) {
            if (midcom::get()->dbfactory->is_a($object, $class)) {
                if (   $property !== false
                    && !$this->_mgd_reflector->property_exists($property)) {
                    debug_add("Matched class '{$key}' to '{$class}' via is_a but property '{$property}' does not exist", MIDCOM_LOG_ERROR);
                } else {
                    self::$_cache[$type][$key] = $property;
                }
                return self::$_cache[$type][$key];
            }
        }
        // The simple heuristic
        if ($this->_mgd_reflector->property_exists($type)) {
            self::$_cache[$type][$key] = $type;
        }
        return self::$_cache[$type][$key];
    }

    /**
     * Resolve the "name" property of given object
     *
     * @see midcom_helper_reflector::get_name_property_nonstatic()
     * @param object $object the object to get the name property for
     */
    public static function get_name_property(object $object) : ?string
    {
        return self::get($object)->get_name_property_nonstatic($object);
    }

    /**
     * Resolve the "title" of given object
     *
     * NOTE: This is distinctly different from get_object_label, which will always return something
     * even if it's just the class name and GUID, also it will for some classes include extra info (like datetimes)
     * which we do not want here.
     *
     * @param object $object the object to get the name property for
     * @param string $title_property property to use as "name", if left to default (null), will be reflected
     */
    public static function get_object_title(object $object, ?string $title_property = null) : ?string
    {
        if ($title_property === null) {
            $title_property = self::get_title_property($object);
        }
        if (empty($title_property)) {
            // Could not resolve valid property
            return null;
        }

        return (string) $object->{$title_property};
    }

    /**
     * Resolve the "title" property of given object
     *
     * NOTE: This is distinctly different from get_label_property, which will always return something
     * even if it's just the guid
     *
     * @param object $object The object to get the title property for
     */
    public static function get_title_property(object $object) : ?string
    {
        return self::get($object)->get_title_property_nonstatic($object);
    }

    /**
     * Resolve the "title" property of given object
     *
     * NOTE: This is distinctly different from get_label_property, which will always return something
     * even if it's just the guid
     *
     * @see midcom_helper_reflector::get_object_title()
     * @param object $object the object to get the title property for
     */
    public function get_title_property_nonstatic(object $object) : ?string
    {
        return $this->get_property('title', $object);
    }
}
