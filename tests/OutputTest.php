<?php
/* Copyright (c) 2014-2016 Curtis Oakley
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

namespace Chocklymon\fsmcbm;

use Exception;
use PHPUnit_Framework_TestCase;

/**
 * Test the Output class.
 *
 * @author Curtis Oakley
 */
class OutputTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var MockSettings
     */
    private static $settings;

    /**
     * @var Output
     */
    private $output;

    public static function setUpBeforeClass()
    {
        self::$settings = new MockSettings();
    }

    public function setUp()
    {
        $this->output = new Output(self::$settings);
    }

    protected function tearDown()
    {
        // Reset the debug mode to off
        self::$settings->setDebugMode(false);
    }

    /**
     * Run is separate proccess since PHP Unit may have already set the headers.
     * See: http://stackoverflow.com/questions/9745080/test-php-headers-with-phpunit
     * @runInSeparateProcess
     */
    public function testReply_headers()
    {
        ob_start();
        $this->output->reply();

        // Check the headers
        /* Commented out, because when running the the command line PHP doesn't actually set headers.
        $headers_list = headers_list();
        $this->assertNotEmpty($headers_list);
        $this->assertContains('Content-Type: application/json');
        // */
        header_remove();
    }

    public function testAppend()
    {
        $expected = '[{"hello":"hi"},{"world":"hi"}]';
        $this->expectOutputString($expected);

        $this->output->append(array('hello'=>'hi'));
        $this->output->append(array('world'=>'hi'));

        $this->output->reply();
    }

    public function testAppend_subkey()
    {
        $expected = '{"hello":"world","cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);

        $this->output->append('world', 'hello');
        $this->output->append(array('cheddar','colby'), 'cheese');

        $this->output->reply();
    }

    public function testAppend_subarray()
    {
        $expected = '{"cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);

        $this->output->append('cheddar', 'cheese', true);
        $this->output->append('colby', 'cheese', true);

        $this->output->reply();
    }

    public function testClear()
    {
        // Setup
        $this->output->append(array("hello"=>"world"));

        // Act
        $this->output->clear();

        // Verify
        // Since this is JSON, this should be an empty array after clear is called
        $this->expectOutputString('[]');
        $this->output->reply();
    }

    public function testError()
    {
        $message = 'PHP Unit Testing Error';
        $expected = '{"error":"' . $message . '"}';
        $this->expectOutputString($expected);

        $this->output->error($message, null, false);
        $this->output->reply();
    }

    public function testError_debugOn()
    {
        self::$settings->setDebugMode(true);
        $output = new Output(self::$settings);
        
        $message = 'PHP Unit Testing Error';
        $debug_message = 'A super fatal error has occured';
        $expected = '{"error":"' . $message . '","debug":"' . $debug_message . '"}';
        $this->expectOutputString($expected);

        $output->error($message, $debug_message, false);
        $output->reply();
    }

    public function testException()
    {
        $message = 'PHP Unit Testing Error';
        $expected = '{"error":"' . $message . '"}';
        $this->expectOutputString($expected);
        $ex = new Exception($message);

        $this->output->exception($ex);
    }

    public function testException_debugOn()
    {
        self::$settings->setDebugMode(true);
        $output = new Output(self::$settings);
        
        $message = 'PHP Unit Testing Error';
        $ex = new Exception($message);
        $stack = json_encode($ex->getTrace());
        $expected = '{"error":"' . $message . '","debug":{"stacktrace":' . $stack . '}}';
        $this->expectOutputString($expected);

        $output->exception($ex);
    }

    public function testSuccess()
    {
        $expected = '{"success":true}';
        $this->expectOutputString($expected);

        $this->output->success();
    }

    public function testPrepareHTML()
    {
        // This string is 135 characters long, truncate goes at 120
        $string   = 'I <i>like</i> cheese & pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        $expected = 'I &lt;i&gt;like&lt;/i&gt; cheese &amp; pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';

        $actual = $this->output->prepareHTML($string);

        $this->assertEquals($expected, $actual);
    }

    public function testPrepareHTML_truncate()
    {
        $string   = 'I <i>like</i> cheese & pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        $expected = 'I &lt;i&gt;like&lt;/i&gt; cheese &amp; pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis…';

        $actual = $this->output->prepareHTML($string, true);

        $this->assertEquals($expected, $actual);
    }

    public function testGetTruncated_invalidMax()
    {
        $string   = 'Micheal is Green';
        $expected = 'Mic…';

        $actual = $this->output->getTruncated($string, 0);

        $this->assertEquals($expected, $actual);
    }

}