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

require_once('bm-settings_mock.php');
require_once('src/bm-output.php');

/**
 * Test the Output class.
 * 
 * Unable to test setting the headers. While this should be able to be tested,
 * it consistently fails.
 * 
 * @author Curtis Oakley
 */
class OutputTest extends PHPUnit_Framework_TestCase
{
    
    protected function tearDown()
    {
        // Clear any residual output in the buffer
        Output::clear();
        
        // Reset the debug mode to off
        Settings::setDebugMode(false);
    }
    
    public function testAppend_html()
    {
        $s1 = "<div>Hello World</div>";
        $s2 = "<p>This is a paragraph.</p>";
        $this->expectOutputString($s1 . $s2);
        
        Output::setHTMLMode(true);
        Output::append($s1);
        Output::append($s2);
        
        Output::reply();
    }
    
    public function testAppend_json()
    {
        $expected = '[{"hello":"hi"},{"world":"hi"}]';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append(array('hello'=>'hi'));
        Output::append(array('world'=>'hi'));
        
        Output::reply();
    }
    
    public function testAppend_json_subkey()
    {
        $expected = '{"hello":"world","cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append('world', 'hello');
        Output::append(array('cheddar','colby'), 'cheese');
        
        Output::reply();
    }
    
    public function testAppend_json_subarray()
    {
        $expected = '{"cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append('cheddar', 'cheese', true);
        Output::append('colby', 'cheese', true);
        
        Output::reply();
    }
    
    public function testClear()
    {
        // Setup
        Output::setHTMLMode(false);
        Output::append(array("hello"=>"world"));
        
        // Act
        Output::clear();
        
        // Verify
        // Since this is JSON, this should be an empty array after clear is called
        $this->expectOutputString('[]');
        Output::reply();
    }
    
    public function testError_html()
    {
        $message = 'PHP Unit Testing Error';
        $expected = '<div class="error">' . $message . '</div>';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(true);
        Output::error($message, null, false);
        Output::reply();
    }
    
    public function testError_html_debugOn()
    {
        Settings::setDebugMode(true);
        $message = 'PHP Unit Testing Error';
        $debug_message = 'A super fatal error has occured';
        $expected = "<div class=\"error\">{$message}</div><div style='display:none'><pre>{$debug_message}</pre></div>";
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(true);
        Output::error($message, $debug_message, false);
        Output::reply();
    }
    
    public function testError_json()
    {
        $message = 'PHP Unit Testing Error';
        $expected = '{"error":"' . $message . '"}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::error($message, null, false);
        Output::reply();
    }
    
    public function testError_json_debugOn()
    {
        Settings::setDebugMode(true);
        $message = 'PHP Unit Testing Error';
        $debug_message = 'A super fatal error has occured';
        $expected = '{"error":"' . $message . '","debug":"' . $debug_message . '"}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::error($message, $debug_message, false);
        Output::reply();
    }
    
    public function testException()
    {
        $message = 'PHP Unit Testing Error';
        $expected = '{"error":"' . $message . '"}';
        $this->expectOutputString($expected);
        $ex = new Exception($message);
        
        Output::setHTMLMode(false);
        Output::exception($ex);
    }
    
    public function testException_debugOn()
    {
        Settings::setDebugMode(true);
        $message = 'PHP Unit Testing Error';
        $ex = new Exception($message);
        $stack = json_encode($ex->getTrace());
        $expected = '{"error":"' . $message . '","debug":{"stacktrace":' . $stack . '}}';
        $this->expectOutputString($expected);
        
        
        Output::setHTMLMode(false);
        Output::exception($ex);
    }
    
    public function testSuccess_html()
    {
        $expected = '<div class="success">Success!</div>';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(true);
        Output::success();
    }
    
    public function testSuccess_json()
    {
        $expected = '{"success":true}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::success();
    }
    
    public function testPrepareHTML_notruncate()
    {
        // This string is 135 characters long, truncate goes at 120
        $string   = 'I <i>like</i> cheese & pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        $expected = 'I &lt;i&gt;like&lt;/i&gt; cheese &amp; pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        
        $actual = Output::prepareHTML($string);
        
        $this->assertEquals($expected, $actual);
    }
    
    public function testPrepareHTML_truncate()
    {
        $string   = 'I <i>like</i> cheese & pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        $expected = 'I &lt;i&gt;like&lt;/i&gt; cheese &amp; pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl se ...';
        
        $actual = Output::prepareHTML($string, true);
        
        $this->assertEquals($expected, $actual);
    }
    
}