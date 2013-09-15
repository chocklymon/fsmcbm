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
            error("DB Connection Issue");
        }
        
        if (!$this->conn->set_charset("utf8")) {
            error("Unable to set utf8 character set.");
        }
    }
    
    /**
     * Closes the database connection.
     */
    public function close() {
        $this->conn->close();
    }
    
    /**
     * Performs a query against the database. 
     * @param string $sql The query string.
     * @return mixed For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries
     * this will return a mysqli_result object. For other successful queries
     * this will return TRUE. 
     */
    public function &query($sql) {
        $result = $this->conn->query($sql);
        
        if($result === false){
            // TODO better error handling
            error("Nothing Found. " . $this->conn->errno . " " . $this->conn->error . " SQL: $sql");
        }
        
        return $result;
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
    
}