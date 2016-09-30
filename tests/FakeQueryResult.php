<?php
/* MIT License
 *
 * Copyright (c) 2014-2016 Curtis Oakley
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Chocklymon\fsmcbm;

/**
 * Fakes a mysqli_result from the database.
 */
class FakeQueryResult /* extends mysqli_result */
{
    private $results;
    public $current_field;
    public $num_rows;

    /**
     * Construct a fake query result.
     * @param array $results An array of results.
     */
    public function __construct($results = array())
    {
        $this->results = $results;
        $this->current_field = 0;
        $this->num_rows = count($this->results);
    }

    public function data_seek($offset)
    {
        $this->current_field = $offset;
        return true;
    }

    public function fetch_all($resulttype = MYSQLI_NUM)
    {
        return $this->results;
    }

    public function fetch_array($resulttype = MYSQLI_BOTH)
    {
        if ($this->current_field < count($this->results)) {
            $value = $this->results[$this->current_field];
            $this->current_field++;
        } else {
            $value = false;
        }
        return $value;
    }

    public function fetch_assoc()
    {
        return $this->fetch_array();
    }

    public function fetch_row()
    {
        return $this->fetch_array();
    }

    public function free()
    {
        unset($this->results, $this->current_field, $this->num_rows);
    }
}