<?php

require_once '../bm-config.php';
require_once '../bm-output.php';

/**
 * Test the Output class.
 * @author Curtis Oakley
 */
class OutputTest extends PHPUnit_Framework_TestCase {
    
    protected function tearDown() {
        // Clear any residual output in the buffer
        Output::clear();
    }
    
    public function testAppend_html() {
        $s1 = "<div>Hello World</div>";
        $s2 = "<p>This is a paragraph.</p>";
        $this->expectOutputString($s1 . $s2);
        
        Output::setHTMLMode(true);
        Output::append($s1);
        Output::append($s2);
        
        Output::reply();
    }
    
    public function testAppend_json() {
        $expected = '[{"hello":"hi"},{"world":"hi"}]';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append(array('hello'=>'hi'));
        Output::append(array('world'=>'hi'));
        
        Output::reply();
    }
    
    public function testAppend_json_subkey() {
        $expected = '{"hello":"world","cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append('world', 'hello');
        Output::append(array('cheddar','colby'), 'cheese');
        
        Output::reply();
    }
    
    public function testAppend_json_subarray() {
        $expected = '{"cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append('cheddar', 'cheese', true);
        Output::append('colby', 'cheese', true);
        
        Output::reply();
    }
    
    public function testClear() {
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
    
    public function testError_html() {
        $message = 'PHP Unit Testing Error';
        $expected = '<div class="error">' . $message . '</div>';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(true);
        Output::error($message, null, false);
        Output::reply();
    }
    
    public function testError_json() {
        $message = 'PHP Unit Testing Error';
        $expected = '{"error":"' . $message . '"}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::error($message, null, false);
        Output::reply();
    }
    
    public function testPrepareHTML_notruncate() {
        // This string is 135 characters long, truncate goes at 120
        $string   = 'I <i>like</i> cheese & pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        $expected = 'I &lt;i&gt;like&lt;/i&gt; cheese &amp; pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        
        $actual = Output::prepareHTML($string);
        
        $this->assertEquals($expected, $actual);
    }
    
    public function testPrepareHTML_truncate() {
        $string   = 'I <i>like</i> cheese & pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl sem. Donec quis imperdiet nibh.';
        $expected = 'I &lt;i&gt;like&lt;/i&gt; cheese &amp; pickles. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis in nisl se ...';
        
        $actual = Output::prepareHTML($string, true);
        
        $this->assertEquals($expected, $actual);
    }
    
}