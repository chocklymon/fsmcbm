<?php
/*
 * The MIT License
 *
 * Copyright 2014 Curtis Oakley.
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

require_once('src/FilteredInput.php');

/**
 * Tests the filtered input class.
 * @author Curtis Oakley
 */
class FilteredInputTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var FilteredInput 
     */
    private $input;

    public function setUp()
    {
        $this->input = new FilteredInput(false);
    }
    
    public function testConstructorLoadNow()
    {
        $input = new FilteredInput(true, array('hello'=>'world'));
        $this->assertEquals('world', $input->hello);
    }
    
    public function testGetterAndSetter()
    {
        $test_data = 'Greetings world';
        $this->input->test = $test_data;
        $this->assertEquals($test_data, $this->input->test);
    }
    
    public function testGetterNull()
    {
        $this->assertNull($this->input->no_key_lives_here);
    }
    
    public function testExistsFalse()
    {
        $this->assertFalse($this->input->exists('no_key_lives_here'));
    }
    
    public function testExistsTrue()
    {
        $this->input->test = '';
        $this->assertTrue($this->input->exists('test'));
    }
    
    public function testGetBooleanOn()
    {
        $this->input->test = 'on';
        $this->assertEquals(1, $this->input->getBoolean('test'));
    }
    
    public function testGetBooleanOff()
    {
        $this->input->test = 'off';
        $this->assertEquals(0, $this->input->getBoolean('test'));
    }
    
    public function testGetBooleanValue()
    {
        $this->input->test = "I'm truthy";
        $this->assertTrue($this->input->getBoolean('test'));
    }
    
    public function testKeySort()
    {
        $this->input->b = '2';
        $this->input->a = '1';
        $this->input->keySort();
        
        $this->input->rewind();
        $this->assertEquals('1', $this->input->current());
        $this->assertEquals('2', $this->input->next());
    }
    
    public function testLoadPost()
    {
        $_POST = array('hello'=>'world');
        $this->input->loadPost();
    }
    
    public function testLoadPostJSON()
    {
        $_POST = array();
        $_SERVER['CONTENT_TYPE'] = 'application/json;charset=UTF-8';
        $this->input->loadPost();
    }
    
    public function testImplementsIterator()
    {
        $this->input->a = 'a';
        $this->input->b = 'b';
        
        // Set the first expected value
        $expected_value = 'a';
        foreach ($this->input as $key => $value) {
            $this->assertEquals($expected_value, $value);
            $this->assertEquals($key, $value);
            // Set the next expected value
            $expected_value = 'b';
        }
    }
}
