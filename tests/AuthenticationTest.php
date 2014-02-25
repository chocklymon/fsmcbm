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

require_once('MockDatabase.php');
require_once('MockSettings.php');
require_once('src/Output.php');
require_once('src/Authentication.php');

/**
 * Test the Ban Manager Authentication
 * @author Curtis Oakley
 */
class AuthenticationTest extends PHPUnit_Framework_TestCase
{
    const USERNAME = 'JaneDoe';

    /**
     * @var MockSettings
     */
    private static $settings;

    /**
     * @var Output
     */
    private static $output;

    /**
     * @var Authentication
     */
    private $auth;

    public static function setUpBeforeClass()
    {
        self::$settings = new MockSettings();
        self::$output = new Output(self::$settings);
    }

    protected function setUp()
    {
        $this->auth = new Authentication(self::$settings);
    }

    public function testGetLoggedInName()
    {
        $_COOKIE = array('wordpress_logged_in_28'=> self::USERNAME . '|1393260248|7fe9e5132050a0ef139492791867b659');

        $name = $this->auth->getLoggedInName();

        $this->assertEquals(self::USERNAME, $name);
    }

    public function testGetLoggedInName_false()
    {
        // Empty the cookie so the name returns false
        $_COOKIE = array();

        $name = $this->auth->getLoggedInName();

        $this->assertFalse($name);
    }

    public function testGetModeratorInfo()
    {
        $db = new MockDatabase(array(array('id'=>28, 'rank'=>'Admin')));

        $info = $this->auth->getModeratorInfo($db, self::USERNAME);

        $this->assertEquals(array(28, 'Admin', self::USERNAME), $info);
    }

    public function testGetModeratorInfo_false()
    {
        $db = new MockDatabase();

        $info = $this->auth->getModeratorInfo($db, "");

        $this->assertFalse($info);
    }

    public function testGetModeratorInfo_nonAdmin()
    {
        $db = new MockDatabase(array(array('id'=>28, 'rank'=>'Regular')));

        $info = $this->auth->getModeratorInfo($db, self::USERNAME);

        $this->assertFalse($info);
    }
}