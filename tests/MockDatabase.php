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
 * Acts as a mock for the database class.
 * @author Curtis Oakley
 */
class MockDatabase extends Database
{
    private $queries;
    private $responses;
    private $query_count = 0;

    /**
     * Constructs a new database mock.
     * @param array $responses An array of responses that should be returned
     * for the requests. If a response is false, then a database exception will
     * be throw instead.
     */
    public function __construct($responses = array())
    {
        $this->queries = array();
        $this->responses = $responses;
    }

    /**
     * Returns the last query that was sent to the mock database.
     * @return string The last query.
     */
    public function getLastQuery()
    {
        $query_num = $this->query_count - 1;
        if ($query_num < 0) {
            return "";
        } else {
            return $this->queries[$this->query_count - 1];
        }
    }

    /**
     * Gets the number of queries that where run against this mock database.
     * @return int The number of queries.
     */
    public function getQueryCount()
    {
        return $this->query_count;
    }

    /**
     * Returns all the queries that have been run against this mock database.
     * @return array An array containing the query strings.
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * Closes the database connection.
     */
    public function close()
    {
        unset($this->responses);
    }

    /**
     * Indicates if the specified column exits in the table.
     * @param string $table The table to check.
     * @param string $column The column to look for.
     * @return boolean True if the column exists.
     */
    public function columnExists($table, $column)
    {
        return $this->query($table);
    }

    /**
     * Performs a query against the database.
     * @param string $sql The query string.
     * @param string $error_message An optional error message to output if
     * the query fails.
     * @return mixed For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries
     * this will return a mysqli_result object. For other successful queries
     * this will return TRUE.
     */
    public function &query($sql, $error_message = 'Nothing found.')
    {
        // Get the response
        if ($this->query_count >= count($this->responses)) {
            // Reached the end of the array, just return an empty result
            $response = new FakeQueryResult();
        } else {
            $response = $this->responses[$this->query_count];
        }

        // Store the sql query
        $this->queries[] = $sql;
        $this->query_count++;

        // Return the response
        if ($response === false) {
            throw new DatabaseException();
        }
        return $response;
    }

    /**
     * Performs a query against the database and returns all the rows returned
     * by the query as an array.
     * @param string $sql The query string.
     * @param string $error_message An optional error message to output if
     * the query fails.
     * @return array An array containing associative arrays of each row.
     */
    public function &queryRows($sql, $error_message = 'Nothing found.')
    {
        return $this->query($sql);
    }

    /**
     * Performs a query against the database and stores all the rows returned
     * into the output as a subarray of the provided key.
     * Only use this when JSON output mode is on.
     * @param string $sql The query string.
     * @param Output $output The Output object to place the rows into.
     * @param string $key The array key to use for storing the results.
     * @param string $error_message An optional error message to output if
     * the query fails.
     */
    public function queryRowsIntoOutput($sql, Output $output, $key, $error_message = 'Nothing found.')
    {
        return $this->query($sql);
    }

    /**
     * Performs a query against the database and returns the first row returned.
     * @param string $sql The query string.
     * @param string $error_message An optional error message to output if
     * the query fails.
     * @return array The associative array of the returned row.
     */
    public function querySingleRow($sql, $error_message = 'Nothing found.')
    {
        return $this->query($sql);
    }

    /**
     * Runs the provided SQL query and returns the ID of the inserted row.
     * @param string $sql The query string.
     * @return The value of the AUTO_INCREMENT field that was updated by the
     * query. Returns zero if the query did not update an AUTO_INCREMENT value.
     */
    public function insert($sql)
    {
        return $this->query($sql);
    }

    /**
     * Sanitizes input for use with the the database. All data that is from
     * user input needs to be sanitized before use in a SQL query.
     * <b>Warning:</b>
     * This function should only be used for testing since it doesn't escape
     * characters correctly and is only meant for mocking the actual database
     * object's method. Do NOT use for production.
     * @param string $input The string input to sanitize.
     * @param boolean $integer Whether or not the input should be treated as an
     * integer. When true, the input string will be returned as an integer.
     * Optional, defaults to false.
     * @return mixed The sanitized string, or the int if integer is set
     * to true. If the $input is not set or null, returns null, unless $integer
     * is true, then it return 0.
     */
    public function sanitize($input, $integer = false)
    {
       if (isset($input) && $input !== null) {
           if ($integer) {
               // Sanitize as a number
               $num = preg_replace('/[^0-9\-]/', '', $input);
               if (strlen($num) == 0) {
                   // The input string contained no numbers, return 0
                   return 0;
               } else {
                   return (int) $num;
               }
           } else {
               // Remove magic quote escaping if needed
               if (get_magic_quotes_gpc()) {
                   $input = stripslashes($input);
               }

               // Sanitize as a string
               // Use add slashes since it escapes many of the characters that
               // mysqli_real_escape_string does.
               return addslashes($input);
           }
       } else if ($integer) {
           return 0;
       } else {
           return null;
       }
    }

    /**
     * Indicates if the given table exits in the database.
     * @param string $table The name of the table.
     * @return boolean True if the table is in the database.
     */
    public function tableExists($table)
    {
        return $this->query($table);
    }

}

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