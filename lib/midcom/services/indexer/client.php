<?php
/**
 * @package midcom.services
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Indexer client base class
 *
 * @package midcom.services
 */
abstract class midcom_services_indexer_client
{
    /**
     * The topic we're working on
     *
     * @var midcom_db_topic
     */
    protected $_topic;

    /**
     * The NAP node corresponding to the topic
     */
    protected array $_node;

    /**
     * The L10n DB for the topic's component
     */
    protected midcom_services_i18n_l10n $_l10n;

    private midcom_services_indexer $_indexer;

    /**
     * The queries we will work on. Each entry consists of a querybuilder
     * instance and a datamanager to render the results, and is indexed by name
     */
    private array $_queries = [];

    /**
     * @param midcom_db_topic $topic The current topic
     */
    public function __construct($topic, ?midcom_services_indexer $indexer = null)
    {
        $this->_topic = $topic;
        $this->_l10n = midcom::get()->i18n->get_l10n($topic->component);
        $this->_indexer = $indexer ?? midcom::get()->indexer;

        $nav = new midcom_helper_nav();
        $this->_node = $nav->get_node($topic->id);
    }

    /**
     * Index a single object from DM
     *
     * @param mixed $object The object instance to use
     */
    public function index($object) : bool
    {
        return $this->_indexer->index($this->new_document($object));
    }

    /**
     * @param midcom\datamanager\datamanager $dm datamanager (or schemadb in dm2)
     */
    public function add_query(string $name, midcom_core_querybuilder $qb, $dm)
    {
        $this->_queries[$name] = [$qb, $dm];
    }

    public function reindex()
    {
        foreach ($this->_queries as $name => [$qb, $dm]) {
            if ($results = $qb->execute()) {
                if ($documents = $this->process_results($name, $results, $dm)) {
                    $this->_indexer->index($documents);
                }
            }
        }
    }

    /**
     * @param mixed $object
     */
    public function new_document($object) : midcom_services_indexer_document
    {
        $document = $this->create_document($object);
        $document->topic_guid = $this->_topic->guid;
        $document->component = $this->_topic->component;
        $document->topic_url = $this->_node[MIDCOM_NAV_FULLURL];
        return $document;
    }

    /**
     * @param midcom_core_dbaobject[] $results
     * @param midcom\datamanager\datamanager $dm datamanager (or schemadb in dm2)
     * @return midcom_services_indexer_document[]
     */
    abstract public function process_results(string $name, array $results, $dm) : array;

    /**
     * @param mixed $object
     */
    abstract public function create_document($object) : midcom_services_indexer_document;
}
