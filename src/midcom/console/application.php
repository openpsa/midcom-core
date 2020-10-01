<?php
/**
 * @package midcom.console
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midcom\console;

use Symfony\Component\Console\Application as base_application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use midcom\console\command\exec;
use midcom\console\command\purgedeleted;
use midcom\console\command\blobdircleanup;
use midcom\console\command\rcsdircleanup;
use midcom\console\command\repligard;
use midcom\console\command\reindex;
use midcom\console\command\cron;
use midcom;
use midcom\console\command\cacheinvalidate;

/**
 * OpenPSA CLI command runner
 *
 * @package midcom.console
 */
class application extends base_application
{
    /**
     * @inheritDoc
     */
    public function __construct($name = 'midcom\console', $version = midcom::VERSION)
    {
        parent::__construct($name, $version);

        $this->getDefinition()
            ->addOption(new InputOption('--servername', '-s', InputOption::VALUE_REQUIRED, 'HTTP server name', 'localhost'));
        $this->getDefinition()
            ->addOption(new InputOption('--port', '-p', InputOption::VALUE_REQUIRED, 'HTTP server port', '80'));

        $this->add(new exec);
        $this->add(new purgedeleted);
        $this->add(new repligard);
        $this->add(new blobdircleanup);
        $this->add(new rcsdircleanup);
        $this->add(new reindex);
        $this->add(new cron);
        $this->add(new cacheinvalidate);
    }

    /**
     * @inheritDoc
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $basedir = midcom::get()->getProjectDir() . '/';
        // we need the to register the mgdschema classes before starting midcom,
        if (!\midcom_connection::setup($basedir)) {
            throw new \RuntimeException('Could not open midgard connection: ' . \midcom_connection::get_error_string());
        }

        if (file_exists($basedir . 'config.inc.php')) {
            include $basedir . 'config.inc.php';
        }

        $GLOBALS['midcom_config_local']['cache_module_content_uncached'] = true;

        $port = $input->getParameterOption(['--port', '-p'], '80');
        $servername = $input->getParameterOption(['--servername', '-s'], md5(__FILE__));

        $server_defaults = [
            'HTTP_HOST' => $servername,
            'SERVER_NAME' => $servername,
            'SERVER_SOFTWARE' => __CLASS__,
            'HTTP_USER_AGENT' => $this->getName(),
            'SERVER_PORT' => $port,
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_URI' => '/',
            'REQUEST_TIME' => time(),
            'REMOTE_PORT' => $port
        ];
        $_SERVER = array_merge($server_defaults, $_SERVER);

        if ($_SERVER['SERVER_PORT'] == 443) {
            $_SERVER['HTTPS'] = 'on';
        }

        // This makes sure that existing auth and cache instances get overridden
        midcom::init();

        parent::doRun($input, $output);
    }
}
