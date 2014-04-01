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
    const USER_ID = 28;

    /**
     * @var MockSettings
     */
    private static $settings;

    /**
     * @var MockDatabase
     */
    private static $empty_db;

    /**
     * @var Authentication
     */
    private $auth;

    public static function setUpBeforeClass()
    {
        self::$settings = new MockSettings();
        self::$empty_db = new MockDatabase();
    }

    protected function setUp()
    {
        $_COOKIE = array();
        self::$settings->setDebugMode(false);
        $this->auth = new Authentication(self::$empty_db, self::$settings);
    }

    /**
     * Run is separate proccess since authenticate will attempt to set a cookie,
     * and PHP Unit can sometimes already have set the headers.
     * @runInSeparateProcess
     */
    public function testAuthenticate_noCookie()
    {
        $this->assertFalse(
            $this->auth->authenticate()
        );
    }

    public function testAuthenticate_debugMode()
    {
        self::$settings->setDebugMode(true);
        $this->assertTrue(
            $this->auth->authenticate()
        );
        $this->assertEquals(1, $this->auth->getUserId());
    }

    public function testAuthenticate_alreadyLoggedIn()
    {
        $this->setLoggedInCookie();
        $bm_cookie = json_encode(array('id'=>self::USER_ID, 'rank'=>'Admin', 'username'=>self::USERNAME));
        $_COOKIE[self::$settings->getCookieName()] = $bm_cookie;
        $this->assertTrue(
            $this->auth->authenticate()
        );
        $this->assertEquals(self::USER_ID, $this->auth->getUserId());
    }

    /**
     * Run is separate proccess since authenticate will attempt to set a cookie,
     * and PHP Unit has already set the headers.
     * @runInSeparateProcess
     */
    public function testAuthenticate_logIn()
    {
        $this->setLoggedInCookie();
        $db = $this->getModeratorMockDB();
        $auth = new Authentication($db, self::$settings);
        $this->assertTrue(
            $auth->authenticate($db)
        );
        $this->assertEquals(self::USER_ID, $auth->getUserId());
    }

    public function testGetModeratorInfo()
    {
        $db = $this->getModeratorMockDB();
        $auth = new Authentication($db, self::$settings);
        $info = $auth->getModeratorInfo(self::USERNAME);

        $expected = array('id'=>28, 'rank'=>'Admin', 'username'=>self::USERNAME);
        $this->assertEquals($expected, $info);
    }

    public function testGetModeratorInfo_false()
    {
        $info = $this->auth->getModeratorInfo("");
        $this->assertFalse($info);
    }

    public function testGetModeratorInfo_nonAdmin()
    {
        $db = new MockDatabase(array(array('user_id'=>self::USER_ID, 'rank'=>'Regular')));
        $auth = new Authentication($db, self::$settings);
        $info = $auth->getModeratorInfo(self::USERNAME);
        $this->assertFalse($info);
    }

    private function setLoggedInCookie() {
        $_COOKIE = array('wordpress_logged_in_28'=> self::USERNAME . '|1393260248|7fe9e5132050a0ef139492791867b659');
    }

    private function getModeratorMockDB() {
        return new MockDatabase(array(array('user_id'=>self::USER_ID, 'rank'=>'Admin')));
    }
}