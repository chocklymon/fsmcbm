<?php

class Output {
    
    private static $response = array();
    
    public static function JSONreply() {
        echo json_encode(self::$response);
    }
    
    /**
     * Outputs an error message as a JSON object, and then optionally exits the
     * script.
     * @param string $message The error message.
     * @param array $debug_extra Any extra debugging to include. Only output if
     * DEBUG_MODE is true.
     * @param boolean $fatal When true the script will exit after outputing the
     * message.
     */
    public static function error($message = 'Unkown error', $debug_extra = array(), $fatal = true) {
        self::$response['error'] = $message;
        if (DEBUG_MODE) {
            self::$response['debug'] = $debug_extra;
            self::$response['stacktrace'] = debug_backtrace();
        }
        if ($fatal) {
            self::JSONreply();
            exit();
        }
    }
}
