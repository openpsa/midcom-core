<?php
/**
 * @package org.openpsa.mail
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Wrapper class for emails
 *
 * @package org.openpsa.mail
 */
class org_openpsa_mail_message
{
    private $_to;

    private $_encoding;

    private $_headers;

    private $_body;
    private $_html_body;

    /**
     * @var Swift_Message
     */
    private $_message;

    public function __construct($to, array $headers, string $encoding)
    {
        $this->_to = $this->_encode_address_field($to);
        $this->_headers = $headers;
        $this->_encoding = $encoding;

        $this->_message = new Swift_Message('');
    }

    public function get_recipients()
    {
        return $this->_to;
    }

    public function get_message() : Swift_Message
    {
        // set headers
        $headers_setter_map = [
            "content-type" => "setContentType",
            "content-description" => "setDescription",
            "from" => "setFrom",
            "to" => "setTo",
            "cc" => "setCc",
            "bcc" => "setBcc",
            "reply-to" => "setReplyTo",
            "subject" => "setSubject",
            "date" => "setDate",
            "return-path" => "setReturnPath"
        ];

        // map headers we got to swift setter methods
        $msg_headers = $this->_message->getHeaders();
        $headers = $this->get_headers();
        foreach ($headers as $name => $value) {
            if (array_key_exists(strtolower($name), $headers_setter_map)) {
                $setter = $headers_setter_map[strtolower($name)];
                $this->_message->$setter($value);
            } elseif ($msg_headers->has($name)) {
                // header already exists => just set a new value
                $msg_headers->get($name)->setValue($value);
            } else {
                $msg_headers->addTextHeader($name, $value);
            }
        }

        // somehow we need to set the body after the headers...
        if (!empty($this->_html_body)) {
            $this->_message->setBody($this->_html_body, 'text/html');
            $this->_message->addPart($this->_body, 'text/plain');
        } else {
            $this->_message->setBody($this->_body, 'text/plain');
        }

        return $this->_message;
    }

    public function set_header_field(string $name, $value)
    {
        $this->_headers[$name] = $value;
    }

    public function get_headers() : array
    {
        if (empty($this->_headers['Content-Type'])) {
            $this->_headers['Content-Type'] = "text/plain; charset={$this->_encoding}";
        }

        reset($this->_headers);
        foreach ($this->_headers as $header => $value) {
            if (is_string($value)) {
                $this->_headers[$header] = trim($value);
            }
            if (in_array(strtolower($header), ['from', 'reply-to', 'to'])) {
                $this->_headers[$header] = $this->_encode_address_field($value);
            }
        }

        return $this->_headers;
    }

    public function get_body()
    {
        return $this->_body;
    }

    public function set_body($body)
    {
        $this->_body = $body;
        $this->_html_body = null;
    }

    public function set_html_body(string $body, string $altBody, array $attachments, bool $do_image_embedding)
    {
        $this->_body = $altBody;
        $this->_html_body = $body;

        // adjust html body
        if ($do_image_embedding) {
            $this->_embed_images();
        }

        // process attachments
        $this->_process_attachments($attachments);
    }

    private function _embed_images()
    {
        // anything with SRC = "" something in it (images etc)
        $regExp_src = "/(src|background)=([\"'�])(((https?|ftp):\/\/)?(.*?))\\2/i";
        preg_match_all($regExp_src, $this->_html_body, $matches_src);
        debug_print_r("matches_src:", $matches_src);

        $matches = [
            "whole" => $matches_src[0],
            "uri" => $matches_src[3],
            "proto" => $matches_src[4],
            "location" => $matches_src[6]
        ];

        foreach ($matches["whole"] as $key => $match) {
            $location = $matches["location"][$key];
            // uri is fully qualified
            if ($matches['proto'][$key]) {
                $uri = $matches["uri"][$key];
            }
            // uri is relative
            elseif (preg_match('/^\//', $location)) {
                $uri = midcom::get()->get_host_name() . $location;
            } else {
                debug_add('No usable uri found, skipping embed', MIDCOM_LOG_WARN);
                continue;
            }

            // replace src by swiftmailer embedded image
            $repl = $this->_message->embed(Swift_Image::fromPath($uri));
            $new_html = str_replace($location, $repl, $match);
            $this->_html_body = str_replace($match, $new_html, $this->_html_body);
        }
    }

    private function _process_attachments(array $attachments)
    {
        foreach ($attachments as $att) {
            if (empty($att['mimetype'])) {
                $att['mimetype'] = "application/octet-stream";
            }

            $swift_att = false;
            // we got a file path
            if (!empty($att['file'])) {
                $swift_att = Swift_Attachment::fromPath($att['file'], $att['mimetype']);
            }
            // we got the contents (bytes)
            elseif (!empty($att['content'])) {
                $swift_att = new Swift_Attachment($att['content'], $att['name'], $att['mimetype']);
            }

            if ($swift_att) {
                $this->_message->attach($swift_att);
            }
        }
    }

    /**
     * Helper function that provides backwards compatibility
     * to addresses specified in a "Name <email@addre.ss>" format
     *
     * @param string $value The value to encode
     * @return mixed the encoded value
     */
    private function _encode_address_field($value)
    {
        if (is_array($value)) {
            array_walk($value, [$this, '_encode_address_field']);
            return $value;
        }
        if ($pos = strpos($value, '<')) {
            $name = substr($value, 0, $pos);
            $name = preg_replace('/^\s*"/', '', $name);
            $name = preg_replace('/"\s*$/', '', $name);
            $address = substr($value, $pos + 1);
            $address = substr($address, 0, strlen($address) - 1);
            $value = [$address => $name];
        }
        return $value;
    }
}
