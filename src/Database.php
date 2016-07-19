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

require_once('DatabaseException.php');

/**
 * Acts as a wrapper around an SQL database connection.
 * @author Curtis Oakley
 */
class Database
{

    /**
     * The MySQL database connection.
     * @var mysqli
     */
    private $conn;

    /**
     * Construct a Database connection handler.
     *
     * Handles communication with the database.
     *
     * @param Settings $settings The settings.
     * @throws DatabaseException If there was an issue with connection
     * to the database.
     */
    public function __construct(Settings $settings)
    {
        mb_internal_encoding("UTF-8");

        // Connect to the database
        $this->conn = new mysqli(
            $settings->getDatabaseHost(),
            $settings->getDatabaseUsername(),
            $settings->getDatabasePassword(),
            $settings->getDatabaseName()
        );

        if ($this->conn->connect_errno) {
            throw new DatabaseException(
                "Unabled to connect to the database.",
                $this->conn->connect_errno
            );
        }

        if (!$this->conn->set_charset("utf8")) {
            throw new DatabaseException("Unable to set utf8 character set.");
        }
    }

    public function __destruct()
    {
        // Assures that the database connection is closed
        @$this->close();
    }

    /**
     * Closes the database connection.
     */
    public function close()
    {
        $this->conn->close();
    }

    /**
     * Indicates if the specified column exits in the table.
     * @param string $table The table to check.
     * @param string $column The column to look for.
     * @return boolean True if the column exists.
     * @throws DatabaseException If the query fails.
     */
    public function columnExists($table, $column)
    {
        $table = $this->sanitize($table);
        $column = $this->sanitize($column);
        $result = $this->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

        $exists = $result->num_rows != 0;
        $result->free();

        return $exists;
    }

    /**
     * Gets the date and time as a string ready to be inserted into the database.
     * @param int $timestamp The UNIX timestamp, defaults to the current time if
     * not specified.
     * @param bool $with_time Indicate if the time should be included. If set to false only the date is returned.
     * @return string The date and time.
     */
    public static function getDate($timestamp = null, $with_time = true)
    {
        if (empty($timestamp)) {
            $timestamp = time();
        }
        $format = 'Y-m-d';
        if ($with_time) {
            $format .= ' H:i:s';
        }
        return date($format, $timestamp);
    }

    /**
     * Performs a query against the database.
     * @param string $sql The query string.
     * @param string $error_message An optional error message to output if
     * the query fails.
     * @return mixed For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries
     * this will return a mysqli_result object. For other successful queries
     * this will return TRUE.
     * @throws DatabaseException If the query fails.
     */
    public function &query($sql, $error_message = 'Nothing found.')
    {
        $result = $this->conn->query($sql);

        if($result === false){
            throw new DatabaseException(
                $error_message,
                $this->conn->errno,
                $this->conn->error,
                $sql
            );
        }

        return $result;
    }

    /**
     * Performs a query against the database and returns all the rows returned
     * by the query as an array.
     * @param string $sql The query string.
     * @param string $error_message An optional error message to output if
     * the query fails.
     * @return array An array containing associative arrays of each row.
     * @throws DatabaseException If the query fails.
     */
    public function &queryRows($sql, $error_message = 'Nothing found.')
    {
        $result = $this->query($sql, $error_message);

        $rows = array();

        while($row = $result->fetch_assoc()){
            $rows[] = $row;
        }
        $result->free();

        return $rows;
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
     * @throws DatabaseException If the query fails.
     */
    public function queryRowsIntoOutput($sql, Output $output, $key = null, $error_message = 'Nothing found.')
    {
        $result = $this->query($sql, $error_message);

        while($row = $result->fetch_assoc()){
            $output->append($row, $key, true);
        }
        $result->free();
    }

    /**
     * Performs a query against the database and returns the first row returned.
     * @param string $sql The query string.
     * @param string $error_message An optional error message to output if
     * the query fails.
     * @return array The associative array of the returned row.
     * @throws DatabaseException If the query fails, or doesn't return any rows.
     */
    public function querySingleRow($sql, $error_message = 'Nothing found.')
    {
        $result = $this->query($sql, $error_message);

        if ($result->num_rows == 0) {
            throw new DatabaseException($error_message, 0, "", $sql);
        }

        $row = $result->fetch_assoc();
        $result->free();

        return $row;
    }

    /**
     * Runs the provided SQL query and returns the ID of the inserted row.
     * @param string $sql The query string.
     * @return The value of the AUTO_INCREMENT field that was updated by the
     * query. Returns zero if the query did not update an AUTO_INCREMENT value.
     * @throws DatabaseException If the query fails.
     */
    public function insert($sql)
    {
        $this->query($sql);

        // Return the id
        return $this->conn->insert_id;
    }

    /**
     * Sanitizes input for use with the the database. All data that is from
     * user input needs to be sanitized before use in a SQL query.
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
       if (!empty($input)) {
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
               return $this->conn->real_escape_string($input);
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
     * @throws DatabaseException If the query fails.
     */
    public function tableExists($table)
    {
        $table = $this->sanitize($table);
        $result = $this->query("SHOW TABLES LIKE '$table'");

        $exists = $result->num_rows != 0;

        $result->free();

        return $exists;
    }
}
