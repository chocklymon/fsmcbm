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

/**
 * Handles loading and filtering post data. This can handle the standard form
 * post data, or a JSON encoded payload.
 *
 * @author Curtis Oakley
 */
class FilteredInput implements \Iterator
{
    /*
     * @var array
     */
    private $variables;
    
    /**
     * Create a new filtered input.
     * @param boolean $load_now If the post data should be loaded now, or later
     * when loadPost is called.
     * @param array $inputs An array of values to store into the input, values
     * in $_POST will override these.
     */
    public function __construct($load_now = true, array $inputs = array())
    {
        $this->variables = $inputs;
        if ($load_now) {
            $this->loadPost();
        }
    }
    
    /**
     * Override the magic get operator so variables can be retrieved using
     * FilteredInput->key
     * @param string $name The key name to get.
     * @return mixed They value of the variable or null if the value isn't set.
     */
    public function __get($name)
    {
        if ($this->exists($name)) {
            return $this->variables[$name];
        } else {
            return null;
        }
    }
    
    /**
     * Override the magic set operator so variables can be set using
     * FilteredInput->key = 'value'
     * @param string $name The name of the key to set the value into
     * @param string $value The value to store.
     */
    public function __set($name, $value)
    {
        $this->variables[$name] = $value;
    }
    
    /**
     * Returns true if the requested key exists in the input variables.
     * @param string $key
     * @return boolean
     */
    public function exists($key)
    {
        return isset($this->variables[$key]);
    }

    /**
     * Returns true if the requested key exists and contains a non-empty value.
     * @param $key String The key to check.
     * @return bool
     */
    public function existsAndNotEmpty($key)
    {
        return !empty($this->variables[$key]);
    }
    
    /**
     * Gets a boolean value for an input variable. Helps with sometimes boolean
     * values being posted as 'true', 'false, 'on', or 'off'.
     * @param string $name
     * @return int 1 for true, 0 for false, or the boolean value of the variable.
     */
    public function getBoolean($name)
    {
        $value = $this->__get($name);
        if ($value == 'true' || $value == 'on') {
            return 1;
        } elseif ($value == 'false' || $value == 'off') {
            return 0;
        } else {
            return $value ? 1 : 0;
        }
    }

    /**
     * Gets the input as a timestamp.
     * @param string $name The variable name.
     * @return int|null The unix timestamp. Null if the value doesn't exist
     * in the input.
     */
    public function getTimestamp($name)
    {
        $value = $this->__get($name);
        if ($value) {
            $value = strtotime($value);
        }
        return $value;
    }

    /**
     * Re-sorts the variables by their key name.
     */
    public function keySort()
    {
        ksort($this->variables);
    }
    
    /**
     * Loads the data found in the _POST into the input.
     * Values in post will override matching values already in the input.
     */
    public function loadPost()
    {
        $starts_with = function ($haystack, $needle) {
            return $needle === "" || strpos($haystack, $needle) === 0;
        };
        
        // See if the post data is actually a JSON encoded payload
        if (empty($_POST)
            && isset($_SERVER['CONTENT_TYPE'])
            && $starts_with($_SERVER['CONTENT_TYPE'], 'application/json')
        ) {
            $json_variables = json_decode(file_get_contents('php://input'), true);
            $this->variables = (array) $json_variables + $this->variables;
        } else {
            foreach (array_keys($_POST) as $key) {
                $this->variables[$key] = filter_input(INPUT_POST, $key);
            }
        }
        $this->keySort();
    }

    
    // ITERATOR INTERFACE FUNCTIONS
    public function current()
    {
        return current($this->variables);
    }

    public function key()
    {
        return key($this->variables);
    }

    public function next()
    {
        return next($this->variables);
    }

    public function rewind()
    {
        return reset($this->variables);
    }

    public function valid()
    {
        return key($this->variables) !== null;
    }
}
