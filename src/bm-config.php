<?php

class Config {
    
    private static $settings;
    
    public static function generateSettings() {
        require_once 'bm-settings.php';
        $defaults = array(
            'cookie_name' => 'fsmcbm',
            'debug' => FALSE,
            'url' => '',
        );
        self::$settings = array_merge($defaults, $settings);
    }
    
    public static function debugMode() {
        return self::$settings['debug'];
    }
    
    public static function cookieName() {
        return self::$settings['cookie_name'];
    }
    
}

Config::generateSettings();
