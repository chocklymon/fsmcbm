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
 * Handles storing ouput into an array and echoing it out as JSON to the browser.
 */
class Output
{
    /**
     * Holds the data to be returned
     * @var array
     */
    private $response = array();

    /**
     * Indicates if debugging mode is on.
     * @var boolean
     */
    private $debugging_on;

    /**
     * Construct a new output handler.
     * @param Settings $settings The settings to use when outputing.
     */
    public function __construct(Settings $settings)
    {
        mb_internal_encoding("UTF-8");

        $this->debugging_on = $settings->debugMode();
    }

    /**
     * Clears any output currently stored.
     */
    public function clear()
    {
        $this->response = array();
    }

    /**
     * Send the output message.
     */
    public function reply()
    {
        if (!headers_sent()) {
            // Set header type to JSON
            header('Content-Type: application/json');
        }

        echo json_encode($this->response);
    }

    /**
     * Sends the success message.
     */
    public function success()
    {
        $this->response['success'] = true;
        $this->reply();
    }

    /**
     * Append a message to the output.
     * @param mixed $message The message to output.
     * @param string $key The array key to use for storing the message. If not
     * specified the message is pushed onto the end of the reply.
     * @param boolean $subarray When true and the key is set, the value of key
     * is treated as an array and the $message is pushed onto the end of it.
     * Defaults to false.
     */
    public function append($message, $key = null, $subarray = false)
    {
        if ($key == null) {
            $this->response[] = $message;
        } else if($subarray) {
            $this->response[$key][] = $message;
        } else {
            $this->response[$key] = $message;
        }
    }
    
    /**
     * Gets a truncated version of a string.
     * The string wil be truncated to the nearest word that brings it under the
     * length and an ellipsis will be added to the end.
     * @param string $message The string to shorten.
     * @param int $max_length The longest the returned string can be. Defaults
     * to 120. If the max length is less than three, it will be set to the three.
     * @return string The truncated message.
     */
    public function getTruncated($message, $max_length = 120)
    {
        // Make sure the max length is valid
        if ($max_length < 3) {
            $max_length = 3;
        }

        $msg_len = mb_strlen($message);
        if ($msg_len > $max_length) {
            // Need to truncate, attempt to truncate at a space
            $word_break = mb_strrpos($message, ' ', -($msg_len - $max_length));
            if ($word_break < $max_length/2) {
                // If the word break is far into the string, just truncate it
                $word_break = $max_length;
            }
            $message = mb_substr($message, 0, $word_break) . '…';
        }
        return $message;
    }

    /**
     * Takes a string and runs HTML special chars on it.
     * When truncate is set to true, runs getTruncated on the string first.
     *
     * @param string $message The string to truncate.
     * @param boolean $truncate Whether or not the string should be truncated.
     * Defaults to <tt>false</tt>.
     * @param int $max_length The longest the returned string can be when
     * truncate is true.
     * @return string The HTML prepared string.
     */
    public function prepareHTML($message, $truncate = false, $max_length = 120)
    {
        if ($truncate) {
            $message = $this->getTruncated($message, $max_length);
        }

        return htmlspecialchars($message);
    }

    /**
     * Outputs an error message as a JSON object, and then optionally exits the
     * script.
     * @param string $message The error message.
     * @param array $debug_extra Any extra debugging to include.
     * @param boolean $reply_now When true the message will be output immediately.
     */
    public function error($message = 'Unkown error', $debug_extra = array(), $reply_now = true)
    {
        $this->response['error'] = $message;
        if ($this->debugging_on && !empty($debug_extra)) {
            $this->response['debug'] = $debug_extra;
        }
        if ($reply_now) {
            $this->reply();
        }
    }

    public function exception(Exception $exception, $extra = array())
    {
        if ($this->debugging_on) {
            $extra['stacktrace'] = $exception->getTrace();
        }
        $this->error($exception->getMessage(), $extra, true);
    }

}
