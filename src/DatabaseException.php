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
 * Database exception.
 * 
 * @author Curtis Oakley
 */
class DatabaseException extends RuntimeException
{
    /**
     * @var string
     */
    protected $error_msg;
    
    /**
     * @var string
     */
    protected $query;
    
    /**
     * Construct a database exception.
     * 
     * Handles an error that occured with the database.
     * 
     * @param string $message The Exception message to throw.
     * @param int $errorno The SQL exception number.
     * @param string $error_msg The SQL error message.
     * @param string $query The SQL query that caused the exception.
     * @param Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct(
            $message = "",
            $errorno = 0,
            $error_msg = "",
            $query = "",
            Exception $previous = null
    ) {
        parent::__construct($message, $errorno, $previous);
        $this->error_msg = $error_msg;
        $this->query = $query;
    }
    
    /**
     * Returns the SQL query that caused the exception, or
     * an empty string if there was no query.
     * 
     * @return string The SQL query that caused the exception.
     */
    public function getQuery()
    {
        return $this->getQuery();
    }
    
    /**
     * Returns the SQL error message.
     * 
     * @return string The SQL error message.
     */
    public function getErrorMessage()
    {
        return $this->error_msg;
    }
    
}
