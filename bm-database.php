<?php

/**
 * Acts as a wrapper around an SQL database connection.
 * @author Curtis Oakley
 */
class Database {
    
    private $conn;
    
    public function Database() {
        // Attempt to establish a connection to the database
        $this->conn = mysqli_connect(
            DB_HOST,
            DB_USERNAME,
            DB_PASSWORD,
            DB_DATABASE
        );

        if($this->conn->connect_errno){
            Output::error(
                "Unable to connect to the database.",
                array($this->conn->connect_errno)
            );
        }
        
        if (!$this->conn->set_charset("utf8")) {
            Output::error("Unable to set utf8 character set.");
        }
    }
    
    /**
     * Closes the database connection.
     */
    public function close() {
        $this->conn->close();
    }
    
    /**
     * Indicates if the specified column exits in the table.
     * @param string $table The table to check.
     * @param string $column The column to look for.
     * @return boolean True if the column exists.
     */
    public function columnExists($table, $column) {
        $table = $this->sanitize($table);
        $column = $this->sanitize($column);
        $result = $this->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        
        $exists = $result->num_rows != 0;
        $result->free();
        
        return $exists;
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
    public function &query($sql, $error_message = 'Nothing found.') {
        $result = $this->conn->query($sql);
        
        if($result === false){
            Output::error(
                $error_message,
                array(
                    'errno' => $this->conn->errno,
                    'error' => $this->conn->error,
                    'query' => $sql
                )
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
     */
    public function &queryRows($sql, $error_message = 'Nothing found.') {
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
     * @param string $key The array key to use for storing the results.
     * @param string $error_message An optional error message to output if
     * the query fails.
     */
    public function queryRowsIntoOutput($sql, $key, $error_message = 'Nothing found.') {
        $result = $this->query($sql, $error_message);
        
        while($row = $result->fetch_assoc()){
            Output::append($row, $key, true);
        }
        $result->free();
    }
    
    /**
     * Performs a query against the database and returns the first row returned.
     * @param string $sql The query string.
     * @param string $error_message An optional error message to output if
     * the query fails.
     * @return array The associative array of the returned row.
     */
    public function querySingleRow($sql, $error_message = 'Nothing found.') {
        $result = $this->query($sql, $error_message);
        
        if ($result->num_rows == 0) {
            Output::error($error_message);
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
     */
    public function insert($sql) {
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
    public function sanitize($input, $integer = false) {

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
     */
    public function tableExits($table) {
        $table = $this->sanitize($table);
        $result = $this->query("SHOW TABLES LIKE '$table'");
        
        $exists = $result->num_rows != 0;
        
        $result->free();
        
        return $exists;
    }
}