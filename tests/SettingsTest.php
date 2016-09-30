<?php
/* Copyright (c) 2014-2106 Curtis Oakley
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

use PHPUnit_Framework_TestCase;

/**
 * Test the Settings class
 *
 * @author Curtis Oakley
 */
class SettingsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Settings
     */
    private static $settings;

    public static function setUpBeforeClass()
    {
        self::$settings = new Settings();// TODO be able to specify a settings file, this will remove the need for a mock settings
    }

    public function testConstructor()
    {
        $settings = new Settings();
        $this->assertInstanceOf('Settings', $settings);
    }

    public function testGetCookieName()
    {
        $this->assertEquals('bm', self::$settings->getCookieName());
    }

    public function testGetAccessorKey()
    {
        $key = self::$settings->getAccessorKey('missing');
        $this->assertFalse($key);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testGetDatabaseHost()
    {
        self::$settings->getDatabaseHost();
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testGetDatabaseName()
    {
        self::$settings->getDatabaseName();
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testGetDatabasePassword()
    {
        self::$settings->getDatabasePassword();
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testGetDatabaseUsername()
    {
        self::$settings->getDatabaseUsername();
    }

    public function testDebugMode()
    {
        $this->assertFalse(self::$settings->debugMode());
    }

}
