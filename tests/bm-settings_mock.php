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
 * Fake setting class so that Unit Tests can modify settings
 * @author Curtis Oakley
 */
class Settings
{
    
    /**
     * An array containing the settings.
     * @var array
     */
    private static $settings;
    
    /**
     * Load the settings from the configuration file.
     */
    public static function generateSettings($settings = array())
    {
        $defaults = array(
            'cookie_name' => 'bm',
            'debug' => false,
            'debug.stacktrace' => false,
        );
        self::$settings = array_merge($defaults, $settings);
    }
    
    /**
     * Get the name of the cookie used to store the login information.
     * @return string
     */
    public static function cookieName()
    {
        return self::$settings['cookie_name'];
    }
    
    /**
     * Get the database host name.
     * @return string
     */
    public static function databaseHost()
    {
        return self::$settings['db_host'];
    }
    
    /**
     * Get the name of the ban manager database.
     * @return string
     */
    public static function databaseName()
    {
        return self::$settings['db_database'];
    }
    
    /**
     * Get the database password
     * @return string
     */
    public static function databasePassword()
    {
        return self::$settings['db_password'];
    }
    
    /**
     * Return the database username
     * @return string
     */
    public static function databaseUsername()
    {
        return self::$settings['db_username'];
    }
    
    /**
     * Indicates if the ban manager is being run in debugging mode.
     * @return boolean <tt>true</tt> when debug mode is on.
     */
    public static function debugMode()
    {
        return self::$settings['debug'];
    }
    
    /**
     * Set the debug mode flag.
     * @param boolean $debug_mode The debug mode flag
     */
    public static function setDebugMode($debug_mode)
    {
        self::$settings['debug'] = $debug_mode;
    }
    
    /**
     * Get a specific setting by name.
     * @param string $setting_name The name of the setting.
     * @return mixed The setting, or <tt>null</tt> if the setting doesn't
     * exist.
     */
    public static function getSetting($setting_name)
    {
        if (isset(self::$settings[$setting_name])) {
            return self::$settings[$setting_name];
        } else {
            return null;
        }
    }
    
    /**
     * Set a specific setting by name.
     * @param string $setting_name The name of the setting.
     * @param mixed $value The value to set the setting to.
     */
    public static function setSetting($setting_name, $value)
    {
        self::$settings[$setting_name] = $value;
    }
    
}
