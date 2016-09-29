<?php

class Util
{
    /**
     * Takes a string and formats it as a uuid without dashes.
     * @param string $uuid A string with a UUID any any format.
     * @param bool $check_length If the length should be checked. Defaults to true. If the string is the wrong length
     * for a UUID an InvalidArgumentException will be thrown.
     * @return string The UUID without dashes and in lowercase.
     */
    public static function formatUUID($uuid, $check_length = true)
    {
        $uuid = mb_ereg_replace('[^a-fA-F0-9]', '', $uuid);
        if ($check_length && strlen($uuid) != 32) {
            throw new InvalidArgumentException("Invalid UUID");
        }
        $uuid = strtolower($uuid);
        return $uuid;
    }
}