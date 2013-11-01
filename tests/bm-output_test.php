<?php

require_once '../bm-config.php';
require_once '../bm-output.php';

/**
 * Test the Output class.
 * @author Curtis Oakley
 */
class OutputTest extends PHPUnit_Framework_TestCase {
    
    public function testAppend_html() {
        $s1 = "<div>Hello World</div>";
        $s2 = "<p>This is a paragraph.</p>";
        $this->expectOutputString($s1 . $s2);
        
        Output::setHTMLMode(true);
        Output::append($s1);
        Output::append($s2);
        
        Output::reply();
        
        // Cleanup
        Output::clear();
    }
    
    public function testAppend_json() {
        $expected = '[{"hello":"hi"},{"world":"hi"}]';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append(array('hello'=>'hi'));
        Output::append(array('world'=>'hi'));
        
        Output::reply();
        
        // Cleanup
        Output::clear();
    }
    
    public function testAppend_json_subkey() {
        $expected = '{"hello":"world","cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append('world', 'hello');
        Output::append(array('cheddar','colby'), 'cheese');
        
        Output::reply();
        
        // Cleanup
        Output::clear();
    }
    
    public function testAppend_json_subarray() {
        $expected = '{"cheese":["cheddar","colby"]}';
        $this->expectOutputString($expected);
        
        Output::setHTMLMode(false);
        Output::append('cheddar', 'cheese', true);
        Output::append('colby', 'cheese', true);
        
        Output::reply();
        
        // Cleanup
        Output::clear();
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
        
        // Cleanup
        Output::clear();
    }
    
}