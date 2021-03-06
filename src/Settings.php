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
 * Used to access the configurations settings.
 * @author Curtis Oakley
 */
class Settings
{
    /**
     * An array containing the settings.
     * @var array
     */
    protected $settings;

    /**
     * Construct a new settings
     * @global array $settings Defined in the bm-config.php
     */
    public function __construct()
    {
        if (file_exists('bm-config.php')) {
            require_once('bm-config.php');
        } else if (file_exists('../bm-config.php')) {
            require_once('../bm-config.php');
        } else {
            $settings = array();
        }

        $defaults = array(
            'cookie_name' => 'bm',
            'debug' => false,
            'use_wp_login' => false,
            'session_duration' => 43200,// 12 hours
            'log_directory' => '.',
            'log_level' => 4,
        );
        $this->settings = array_merge($defaults, $settings);
    }

    /**
     * Get a configuration setting from a configuration key.
     * @param string $key The settings key name.
     * @return string
     */
    public function get($key)
    {
        return $this->settings[$key];
    }

    /**
     * Get the name of the cookie used to store the login information.
     * @return string
     */
    public function getCookieName()
    {
        return $this->settings['cookie_name'];
    }

    /**
     * Get the database host name.
     * @return string
     */
    public function getDatabaseHost()
    {
        return $this->settings['db_host'];
    }

    /**
     * Get the name of the ban manager database.
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->settings['db_database'];
    }

    /**
     * Get the database password
     * @return string
     */
    public function getDatabasePassword()
    {
        return $this->settings['db_password'];
    }

    /**
     * Return the database username
     * @return string
     */
    public function getDatabaseUsername()
    {
        return $this->settings['db_username'];
    }

    public function getWordpressLoadFile()
    {
        return $this->settings['wp_load_file'];
    }

    /**
     * Indicates if the ban manager is being run in debugging mode.
     * @return boolean <tt>true</tt> when debug mode is on.
     */
    public function debugMode()
    {
        return $this->settings['debug'];
    }

    public function useWPLogin()
    {
        return $this->settings['use_wp_login'];
    }

    public function getCookieKey()
    {
        return $this->settings['cookie_secret'];
    }

    public function getAccessorKey($accessor_name)
    {
        if (isset($this->settings['auth_secret_keys'])) {
            $keys = $this->settings['auth_secret_keys'];
            if (!is_array($keys)) {
                return $keys;
            } else if (isset($keys[$accessor_name])) {
                return $keys[$accessor_name];
            }
        }
        return false;
    }

    public function getLogoutTime()
    {
        return $this->settings['session_duration'];
    }

}
