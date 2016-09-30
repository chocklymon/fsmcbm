<?php
/* Copyright (c) 2016 Curtis Oakley
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

use InvalidArgumentException;

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
