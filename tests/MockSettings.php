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

require_once('src/Settings.php');

/**
 * Fake setting class so that Unit Tests can modify settings
 * @author Curtis Oakley
 */
class MockSettings extends Settings
{
    /**
     * Construct a new settings
     * @global array $settings Defined in the bm-config.php
     */
    public function __construct()
    {
        $defaults = array(
            // Default to use the Travis CI db settings
            'db_host'     => '127.0.0.1',
            'db_username' => 'travis',
            'db_password' => '',
            'db_database' => 'myapp_test',

            'cookie_name' => 'bm',
            'debug' => false,
        );

        // Allow the settings to be overriden by a test.ini file
        if (is_readable('test.ini')) {
            $settings = parse_ini_file('test.ini');
        } else {
            $settings = array();
        }

        $this->settings = array_merge($defaults, $settings);
    }

    /**
     * Set the debug mode flag.
     * @param boolean $debug_mode The debug mode flag
     */
    public function setDebugMode($debug_mode)
    {
        $this->settings['debug'] = $debug_mode;
    }

    /**
     * Set a specific setting by name.
     * @param string $setting_name The name of the setting.
     * @param mixed $value The value to set the setting to.
     */
    public function setSetting($setting_name, $value)
    {
        $this->settings[$setting_name] = $value;
    }

}
