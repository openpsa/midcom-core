<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * Class for intercepting PHP errors and unhandled exceptions. Each fault is caught
 * and converted into Exception handled by midcom_exception_handler::show() with
 * code 500 thus can be customized and make user friendly.
 *
 * @package midcom
 */
class midcom_exception_handler implements EventSubscriberInterface
{
    private Throwable $error;

    public function __construct(private array $error_actions, private midcom_helper_style $style)
    {}

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ['handle']
        ];
    }

    /**
     * Render an error response.
     *
     * This will display a simple HTML Page reporting the error described by $httpcode and $message.
     * The $httpcode is also used to send an appropriate HTTP Response.
     *
     * The error pages can be customized by creating style elements named midcom_error_$httpcode.
     *
     * For a list of the allowed HTTP codes see the Response::HTTP_... constants
     */
    public function handle(ExceptionEvent $event)
    {
        $this->error = $event->getThrowable();

        $httpcode = $this->error->getCode();
        $message = $this->error->getMessage();
        debug_print_r('Exception occurred: ' . $httpcode . ', Message: ' . $message . ', exception trace:', $this->error->getTraceAsString());

        if (!array_key_exists($httpcode, Response::$statusTexts)) {
            debug_add("Unknown Errorcode {$httpcode} encountered, assuming 500");
            $httpcode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        // Send error to special log or recipient as per configuration.
        $this->process_actions($event->getRequest()->server, $httpcode, $message);

        if (PHP_SAPI === 'cli') {
            throw $this->error;
        }

        $event->allowCustomResponseCode();
        $event->setResponse($this->process($httpcode, $message));
    }

    private function process(int $httpcode, string $message) : Response
    {
        if ($httpcode == Response::HTTP_FORBIDDEN) {
            return new midcom_response_accessdenied($message);
        }
        if ($httpcode == Response::HTTP_UNAUTHORIZED) {
            if ($this->error instanceof midcom_error_forbidden) {
                return new midcom_response_login($this->error->get_method());
            }

            return new midcom_response_login;
        }

        $this->style->data['error_title'] = Response::$statusTexts[$httpcode];
        $this->style->data['error_message'] = $message;
        $this->style->data['error_code'] = $httpcode;
        $this->style->data['error_exception'] = $this->error;
        $this->style->data['error_handler'] = $this;

        ob_start();
        if (!$this->style->show_midcom('midcom_error_' . $httpcode)) {
            $this->style->show_midcom('midcom_error');
        }
        $content = ob_get_clean();

        return new Response($content, $httpcode);
    }

    /**
     * Send error for processing.
     *
     * If the given error code has an action configured for it, that action will be
     * performed. This means that system administrators can request email notifications
     * of 500 "Internal Errors" and a special log of 404 "Not Founds".
     */
    private function process_actions(ServerBag $server, int $httpcode, string $message)
    {
        if (!isset($this->error_actions[$httpcode]['action'])) {
            // No action specified for this error code, skip
            return;
        }

        // Prepare the message
        $msg = "{$server->getString('REQUEST_METHOD')} request to {$server->getString('REQUEST_URI')}: ";
        $msg .= "{$httpcode} {$message}\n";
        if ($server->has('HTTP_REFERER')) {
            $msg .= "(Referrer: {$server->getString('HTTP_REFERER')})\n";
        }

        // Send as email handler
        if ($this->error_actions[$httpcode]['action'] == 'email') {
            $this->_send_email($msg, $this->error_actions[$httpcode], $server->getString('SERVER_NAME'));
        }
        // Append to log file handler
        elseif ($this->error_actions[$httpcode]['action'] == 'log') {
            $this->_log($msg, $this->error_actions[$httpcode]);
        }
    }

    private function _log(string $msg, array $config)
    {
        if (empty($config['filename'])) {
            // No log file specified, skip
            return;
        }

        if (   !is_writable($config['filename'])
            && !is_writable(dirname($config['filename']))) {
            debug_add("Error logging file {$config['filename']} is not writable", MIDCOM_LOG_WARN);
            return;
        }

        // Add the line to the error-specific log
        $logger = new Logger(__CLASS__);
        $logger->pushHandler(new StreamHandler($config['filename']));
        $logger = new midcom_debug($logger);
        $logger->log($msg, MIDCOM_LOG_INFO);
    }

    private function _send_email(string $msg, array $config, string $servername)
    {
        if (empty($config['email'])) {
            // No recipient specified, skip
            return;
        }

        $mail = new org_openpsa_mail();
        $mail->to = $config['email'];
        $mail->from = "\"MidCOM error notifier\" <webmaster@{$servername}>";
        $mail->subject = "[{$servername}] " . str_replace("\n", ' ', $msg);
        $mail->body = "{$servername}:\n{$msg}";

        $stacktrace = $this->get_function_stack();

        $mail->body .= "\n" . implode("\n", $stacktrace);

        if (!$mail->send()) {
            debug_add("failed to send error notification email to {$mail->to}, reason: " . $mail->get_error_message(), MIDCOM_LOG_WARN);
        }
    }

    public function get_function_stack(?Throwable $error = null)
    {
        $error = $error ?? $this->error;
        $stack = $error->getTrace();

        $stacktrace = [];
        foreach ($stack as $number => $frame) {
            $line = $number + 1;
            if (array_key_exists('file', $frame)) {
                $file = str_replace(MIDCOM_ROOT, '[midcom_root]', $frame['file']);
                $line .= ": {$file}:{$frame['line']}  ";
            } else {
                $line .= ': [internal]  ';
            }
            if (array_key_exists('class', $frame)) {
                $line .= $frame['class'];
                if (array_key_exists('type', $frame)) {
                    $line .= $frame['type'];
                } else {
                    $line .= '::';
                }
            }
            if (array_key_exists('function', $frame)) {
                $line .= $frame['function'];
            } else {
                $line .= 'require, include or eval';
            }
            $stacktrace[] = $line;
        }

        return $stacktrace;
    }
}
