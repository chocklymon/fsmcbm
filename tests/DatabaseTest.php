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

require_once('MockSettings.php');
require_once('src/Output.php');
require_once('src/Database.php');

/**
 * Test the Database class.
 *
 * @author Curtis Oakley
 */
class DatabaseTest extends PHPUnit_Framework_TestCase
{
    const TABLE_NAME = 'db_test';
    const MSG_COLUMN_NAME = 'msg';

    /**
     * @var MockSettings
     */
    private static $settings;

    /**
     * @var array
     */
    private static $default_row = array();

    /**
     * @var Database
     */
    private $db;

    public static function setUpBeforeClass()
    {
        // Get the test settings
        self::$settings = new MockSettings;

        // Set up the test table
        $db = new Database();
        $db->connect(self::$settings);
        $table = self::TABLE_NAME;
        $msg_name = self::MSG_COLUMN_NAME;
        $sql = <<<EOF
CREATE TABLE IF NOT EXISTS `{$table}` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `{$msg_name}` VARCHAR(60) NOT NULL,
    PRIMARY KEY (`id`)
)
EOF;
        $db->query($sql);

        // Add a value to the test table
        $msg = mt_rand(0, 100000) . "-default-msg";
        $sql = "INSERT INTO `$table` (`$msg_name`) VALUES ('{$msg}')";
        $id = $db->insert($sql);
        self::$default_row['id'] = $id;
        self::$default_row[$msg_name] = $msg;

        $db->close();
    }

    public static function tearDownAfterClass()
    {
        // Remove the table
        $db = new Database();
        $db->connect(self::$settings);
        $sql = 'DROP TABLE IF EXISTS `' . self::TABLE_NAME . '`';
        $db->query($sql);
        $db->close();
    }

    public function setUp()
    {
        $this->db =  new Database();
        $this->db->connect(self::$settings);
    }

    public function tearDown()
    {
        $this->db->close();
    }

    /* Not working
     * @expectedException DatabaseException
     * /
    public function testConnect_error()
    {
        $db = new Database();
        $settings = new MockSettings();
        $settings->setSetting('db_database', 'non_existant_db');
        $db->connect($settings);
    }*/

    public function testColumnExists()
    {
        $column_exists = $this->db->columnExists(self::TABLE_NAME, 'b');
        $this->assertFalse($column_exists);
    }

    public function testInsert()
    {
        $value = mt_rand(0, 9001) . '-msg';
        $sql = "INSERT INTO `" . self::TABLE_NAME . "` (`" . self::MSG_COLUMN_NAME . "`) VALUES ('{$value}')";
        $id = $this->db->insert($sql);
        $this->assertTrue($id > 0);

        // Make sure it was inserted correctly
        $row = $this->db->querySingleRow("SELECT * FROM `" . self::TABLE_NAME . "` WHERE id={$id}");
        $this->assertEquals($value, $row[self::MSG_COLUMN_NAME]);
    }

    public function testQueryRows()
    {
        $row = $this->db->queryRows("SELECT * FROM `" . self::TABLE_NAME . "` WHERE id=" . self::$default_row['id']);
        $this->assertEquals(array(self::$default_row), $row);
    }

    public function testQueryRowsIntoOutput()
    {
        // Build the expected JSON output
        $expected = json_encode(array('test'=>array(array(
            'id' => (string) self::$default_row['id'],
            self::MSG_COLUMN_NAME => self::$default_row[self::MSG_COLUMN_NAME]
        ))));

        $output = new Output(self::$settings);
        $this->db->queryRowsIntoOutput(
            "SELECT * FROM `" . self::TABLE_NAME . "` WHERE id=" . self::$default_row['id'],
            $output,
            'test'
        );

        $this->expectOutputString($expected);
        $output->reply();
    }

    public function testQuerySingleRow()
    {
        $row = $this->db->querySingleRow("SELECT * FROM `" . self::TABLE_NAME . "` WHERE id=" . self::$default_row['id']);
        $this->assertEquals(self::$default_row, $row);
    }

    /**
     * @expectedException DatabaseException
     */
    public function testQuerySingleRow_error()
    {
        $row = $this->db->querySingleRow("SELECT * FROM `" . self::TABLE_NAME . "` WHERE id=100000 AND msg='non-existant-msg'");
        $this->assertEquals(self::$default_row, $row);
    }

    /**
     * @expectedException DatabaseException
     */
    public function testQuery_error()
    {
        $sql = "SELECT * FROM `non_existant_table` WHERE `bad_column` = 5";
        $this->db->query($sql);
    }

    public function testSanitize_int()
    {
        $test_int = "48hi";
        $sanitized = $this->db->sanitize($test_int, true);
        $this->assertEquals(48, $sanitized);
    }

    public function testSanitize_badInt()
    {
        $test_int = "hi";
        $sanitized = $this->db->sanitize($test_int, true);
        $this->assertEquals(0, $sanitized);
    }

    public function testSanitize_empty()
    {
        $sanitized = $this->db->sanitize("");
        $this->assertNull($sanitized);
    }

    public function testSanitize_emptyInt()
    {
        $sanitized = $this->db->sanitize(null, true);
        $this->assertEquals(0, $sanitized);
    }

    public function testTableExists()
    {
        $table_exists = $this->db->tableExists(self::TABLE_NAME);
        $this->assertTrue($table_exists);
    }

    public function testIsConnected()
    {
        $this->assertTrue($this->db->isConnected());
    }
}
