<?php
/**
 * @package midcom.console
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midcom\console\command\cleanup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use midcom_db_attachment;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Cleanup the blobdir
 * Search for corrupted (0 bytes) files
 *
 * @package midcom.console
 */
#[AsCommand(
    name: 'midcom:cleanup:blobdir',
    description: 'Cleanup the blobdir',
    aliases: ['blobdircleanup']
)]
class blobdir extends Command
{
    private int $_file_counter = 0;

    private string $_dir = "";

    private bool $dry = false;

    private array $findings = [
        'corrupted' => [],
        'orphaned' => []
    ];

    protected function configure()
    {
        $this->addOption('dry', 'd', InputOption::VALUE_NONE, 'If set, files and attachments will not be deleted');
    }

    public function check_dir(string $outerDir)
    {
        $outerDir = rtrim($outerDir, "/");
        $dirs = array_diff(scandir($outerDir), [".", ".."]);
        foreach ($dirs as $d) {
            if (is_dir($outerDir . "/" . $d)) {
                $this->check_dir($outerDir . "/" . $d);
            } else {
                // got something
                $file = $outerDir . "/" . $d;
                if (filesize($file) == 0) {
                    $this->findings['corrupted'][] = $file;
                } elseif (!$this->get_attachment($file)) {
                    $this->findings['orphaned'][] = $file;
                }
                $this->_file_counter++;
            }
        }
    }

    private function _determine_location(string $path) : string
    {
        return ltrim(str_replace($this->_dir, "", $path), "/");
    }

    private function get_attachment(string $file) : ?midcom_db_attachment
    {
        $location = $this->_determine_location($file);
        // get attachments
        $qb = midcom_db_attachment::new_query_builder();
        $qb->add_constraint("location", "=", $location);
        $attachments = $qb->execute();
        if (empty($attachments)) {
            return null;
        }
        if (count($attachments) === 1) {
            return $attachments[0];
        }
        throw new \midcom_error('Multiple attachments share location ' . $location);
    }

    private function cleanup_corrupted(OutputInterface $output, array $files)
    {
        $i = 0;
        foreach ($files as $file) {
            $i++;
            // cleanup file
            $output->writeln($i . ") " . $file);
            $this->cleanup_file($output, $file);

            if ($att = $this->get_attachment($file)) {
                if (!$this->dry) {
                    $stat = $att->purge();
                    if (!$stat || $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $output->writeln(($stat) ? "<info>Purge OK</info>" : "<comment>Purge FAILED, reason: " . \midcom_connection::get_error_string() . "</comment>");
                    }
                }
            }
        }
    }

    private function cleanup_file(OutputInterface $output, string $file)
    {
        if (!$this->dry) {
            $stat = unlink($file);
            if (!$stat || $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(($stat) ? "<info>Cleanup OK</info>" : "<comment>Cleanup FAILED</comment>");
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $dir = \midgard_connection::get_instance()->config->blobdir;
        if (!is_dir($dir)) {
            $output->writeln("<comment>Unable to detect blobdir</comment>");
            return Command::FAILURE;
        }
        $this->_dir = $dir;
        $this->dry = $input->getOption("dry");
        if ($this->dry) {
            $output->writeln("<comment>Running in dry mode!</comment>");
        }

        $output->writeln("Start scanning dir: <comment>" . $dir . "</comment>");

        $this->check_dir($dir);

        $output->writeln("Scanned <info>" . $this->_file_counter . "</info> files");
        $output->writeln("Found <info>" . count($this->findings['corrupted']) . "</info> corrupted files:");
        $output->writeln("Found <info>" . count($this->findings['orphaned']) . "</info> orphaned files:");

        $this->cleanup_corrupted($output, $this->findings['corrupted']);

        foreach ($this->findings['orphaned'] as $file) {
            $this->cleanup_file($output, $file);
        }

        $output->writeln("<comment>Done</comment>");
        return Command::SUCCESS;
    }
}
