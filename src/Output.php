<?php
/* Copyright (c) 2014 Curtis Oakley
 * http://chockly.org/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Handles output to the browser.
 */
class Output
{
    /**
     * Holds the data to be returned as JSON.
     * @var array
     */
    private $js_response = array();

    /**
     * Holds the HTML to be returned.
     * @var string
     */
    private $html_response = '';

    /**
     * Indicates if this should output as HTML or JSON.
     * When true, ouputs as HTML.
     * @var boolean
     */
    private $output_as_html = false;

    /**
     * Holds the configuration settings.
     * @var Settings
     */
    private $settings;

    /**
     * Construct a new output handler.
     * @param Settings $settings The settings to use when outputing.
     * @param boolean $output_as_html When true outputs the data as HTML,
     * otherwise outputs as JSON.
     */
    public function __construct(Settings $settings, $output_as_html = false)
    {
        mb_internal_encoding("UTF-8");

        $this->settings = $settings;
        $this->output_as_html = $output_as_html;
    }

    /**
     * Clears any output currently stored.
     */
    public function clear()
    {
        $this->html_response = '';
        $this->js_response = array();
    }

    /**
     * Send the output message to the browser.
     */
    public function reply()
    {
        if (!headers_sent()) {
            // Set the correct content-type header now
            header('Content-Type: ' . ($this->output_as_html ? 'text/html' : 'application/json'));
        }

        if ($this->output_as_html) {
            echo $this->html_response;
        } else {
            echo json_encode($this->js_response);
        }
    }

    /**
     * Sends the success message.
     */
    public function success()
    {
        if ($this->output_as_html) {
            $this->html_response .= '<div class="success">Success!</div>';
        } else {
            $this->js_response['success'] = true;
        }
        $this->reply();
    }

    /**
     * Append a message to the output.
     * @param mixed $message The message to output.
     * @param string $key Only used when in JSON output mode. The array key to
     * use for storing the message. If not specified the message is pushed onto
     * the end of the reply.
     * @param boolean $subarray Only used when in JSON output mode. When true
     * and the key is set, the value of key is treated as an array and the
     * $message is pushed onto the end of it. Defaults to false.
     */
    public function append($message, $key = null, $subarray = false)
    {
        if ($this->output_as_html) {
            $this->html_response .= $message;
        } else {
            if ($key == null) {
                $this->js_response[] = $message;
            } else if($subarray) {
                $this->js_response[$key][] = $message;
            } else {
                $this->js_response[$key] = $message;
            }
        }
    }

    /**
     * Sets the output mode. When true, the the content will be output
     * as HTML, when false the content will be JSON encoded. Default mode
     * is to output as JSON.
     * @param boolean $output_as_html Whether or not reply should output HTML or
     * a JSON object.
     */
    public function setHTMLMode($output_as_html)
    {
        $this->output_as_html = (boolean) $output_as_html;
    }

    /**
     * Takes a string and runs HTML special chars on it.
     *
     * When truncate is set to <tt>true</tt> and the string is longer than the
     * max length (typically 120 characters), the string wil be truncated
     * to the nearest word that brings it under the length and an ellipsis will
     * be added to the end.
     *
     * @param string $message The string to truncate.
     * @param boolean $truncate Whether or not the string should be truncated.
     * Defaults to <tt>false</tt>.
     * @param int $max_length The longest the returned string can be. Only used
     * when $truncate is <tt>true</tt>. Defaults to 120. If the max length is
     * less than three, it will be set to the three.
     * @return string The HTML prepared string.
     */
    public function prepareHTML($message, $truncate = false, $max_length = 120)
    {
        $prepared = htmlspecialchars($message);

        if ($truncate) {
            // Make sure the max length is valid
            if ($max_length < 3) {
                $max_length = 3;
            }

            $msg_len = mb_strlen($prepared);
            if ($msg_len > $max_length) {
                $word_break = mb_strrpos($prepared, ' ', -($msg_len - $max_length));
                if ($word_break < $max_length/2) {
                    // If the word break is far into the string, just truncate it
                    $word_break = $max_length;
                }
                $prepared = mb_substr($prepared, 0, $word_break) . '&hellip;';
            }
        }

        return $prepared;
    }

    /**
     * Outputs an error message as a JSON object, and then optionally exits the
     * script.
     * @param string $message The error message. If HTML output mode is enabled
     * this message is HTML special chars encoded.
     * @param array $debug_extra Any extra debugging to include.
     * @param boolean $reply_now When true the output will be output immediatly.
     * message.
     */
    public function error($message = 'Unkown error', $debug_extra = array(), $reply_now = true)
    {
        if ($this->output_as_html) {
            $this->html_response .= '<div class="error">' . $this->prepareHTML($message) . "</div>";
            if ($this->settings->debugMode() && !empty($debug_extra)) {
                $this->html_response .= "<div style='display:none'><pre>" . print_r($debug_extra, true) . "</pre></div>";
            }
        } else {
            $this->js_response['error'] = $message;
            if ($this->settings->debugMode() && !empty($debug_extra)) {
                $this->js_response['debug'] = $debug_extra;
            }
        }
        if ($reply_now) {
            $this->reply();
        }
    }

    public function exception(Exception $exception, $extra = array())
    {
        if ($this->settings->debugMode()) {
            $extra['stacktrace'] = $exception->getTrace();
        }
        $this->error($exception->getMessage(), $extra, true);
    }

}
