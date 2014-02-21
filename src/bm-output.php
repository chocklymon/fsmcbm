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
class Output {
    
    private static $js_response = array();
    private static $html_response = '';
    private static $outputAsHtml = false;
    
    /**
     * Clears any output currently stored.
     */
    public static function clear() {
        self::$html_response = '';
        self::$js_response = array();
    }
    
    /**
     * Send the output message to the browser.
     */
    public static function reply() {
        if (!headers_sent()) {
            // Set the correct content-type header now
            header('Content-Type:' . (self::$outputAsHtml ? 'text/html' : 'application/json'));
        }
        
        if (self::$outputAsHtml) {
            echo self::$html_response;
        } else {
            echo json_encode(self::$js_response);
        }
    }
    
    /**
     * Sends the success message.
     */
    public static function success() {
        if (self::$outputAsHtml) {
            self::$html_response .= '<div class="success">Success!</div>';
        } else {
            self::$js_response['success'] = true;
        }
        Output::reply();
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
    public static function append($message, $key = null, $subarray = false) {
        if (self::$outputAsHtml) {
            self::$html_response .= $message;
        } else {
            if ($key == null) {
                self::$js_response[] = $message;
            } else if($subarray) {
                self::$js_response[$key][] = $message;
            } else {
                self::$js_response[$key] = $message;
            }
        }
    }
    
    /**
     * Sets the output mode. When true, the the content will be output
     * as HTML, when false the content will be JSON encoded. Default mode
     * is to output as JSON.
     * @param boolean $htmlOutput Whether or not reply should output HTML or
     * a JSON object.
     */
    public static function setHTMLMode($htmlOutput) {
        self::$outputAsHtml = (boolean) $htmlOutput;
    }
    
    /**
     * Takes a string and truncates it and runs it through HTML special chars.
     * If the string is over 120 characters long and $truncate is set to true,
     * it will remove the truncate the string to and append an ellipsis.
     * @param string $string The string to truncate.
     * @param boolean $truncate Whether or not the string should be truncated.
     * Defaults to false.
     * @return string The truncated string.
     */
    public static function &prepareHTML($message, $truncate = false) {
        $string = htmlspecialchars($message);
        
        if ($truncate && strlen($string) > 120){
            $string = substr($string, 0, 120) . " ...";
        }
        
        return $string;
    }
    
    /**
     * Outputs an error message as a JSON object, and then optionally exits the
     * script.
     * @param string $message The error message. If HTML output mode is enabled
     * this message is HTML special chars encoded.
     * @param array $debug_extra Any extra debugging to include.
     * @param boolean $fatal When true the script will exit after outputing the
     * message.
     */
    public static function error($message = 'Unkown error', $debug_extra = array(), $fatal = true)
    {
        if (self::$outputAsHtml) {
            self::$html_response .= '<div class="error">' . self::prepareHTML($message) . "</div>";
            if (Settings::debugMode() && !is_null($debug_extra)) {
                self::$html_response .= "<div style='display:none'><pre>" . print_r($debug_extra, true) . "</pre><pre>" . print_r(debug_backtrace(), true) . "</pre></div>";
            }
        } else {
            self::$js_response['error'] = $message;
            if (Settings::debugMode() && !is_null($debug_extra)) {
                self::$js_response['debug'] = $debug_extra;
                self::$js_response['stacktrace'] = debug_backtrace();
            }
        }
        if ($fatal) {
            self::reply();
            //exit();
        }
    }
}
