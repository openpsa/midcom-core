<?php
/**
 * @package midcom.baseclasses
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use Symfony\Component\Console\Output\OutputInterface;

/**
 * This is the base class used for all jobs run by MidCOM CRON.
 *
 * It gives you an easy to use way of building cron jobs. You should rely only on
 * the two event handlers _on_initialize and execute, which are called by the
 * cron service.
 *
 * A simple (and useless) handler class would look like this:
 *
 * <code>
 * <?php
 * class net_nehmer_static_cron_test extends midcom_baseclasses_components_cron_handler
 * {
 *     public function execute()
 *     {
 *         $this->print_error("Executing...");
 *         $this->print_error(date('r'));
 *     }
 * }
 * </code>
 *
 * <b>Cron Job implementation suggestions</b>
 *
 * You should keep output to stdout to an absolute minimum. Normally, no output whatsoever
 * should be made, as the cron service itself is invoked using some kind of Cron Daemon. Only
 * if you output nothing, no status mail will be generated by cron.
 *
 * @see midcom_services_cron
 * @package midcom.baseclasses
 */
abstract class midcom_baseclasses_components_cron_handler
{
    use midcom_baseclasses_components_base;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * Initialize the cron job. Before calling the on_initialize callback, it prepares
     * the instance with various configuration variables
     */
    public function initialize(OutputInterface $output)
    {
        $this->output = $output;

        return $this->_on_initialize();
    }

    /**
     * This callback is executed immediately after object construction. You can initialize your class here.
     * If you return false here, the handler is not executed, the system skips it.
     *
     * All class members are already initialized when this event handler is called.
     *
     * @return boolean Returns true, if initialization was successful, false to abort execution
     */
    public function _on_initialize() : bool
    {
        return true;
    }

    /**
     * This is the actual handler operation, it is called only after successful operation.
     * You should use the print_error() helper of this class in case you need to notify
     * the user of any errors. As long as everything goes fine, you should not print anything
     * to avoid needless cron mailings.
     */
    abstract public function execute();

    /**
     * Echo the error message to the client, automatically appending
     * the classname to the prefix. Passed messages are also written to the error log.
     */
    public function print_error(string $message, $var = null)
    {
        $prefix = 'ERROR';
        if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            $prefix .= ' ' . static::class;
        }
        $this->output->writeln("$prefix: $message");
        debug_add($message, MIDCOM_LOG_ERROR);
        if ($var !== null) {
            debug_print_r('Passed argument: ', $var);
        }
    }
}
