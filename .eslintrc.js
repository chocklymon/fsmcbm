/**
 * The MIT License
 *
 * Copyright 2016 Curtis Oakley.
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

// Define the default configuration for ESLint
module.exports = {
    // Extend the recommended configuration
    "extends": "eslint:recommended",

    // Specify the global objects, set to false to mark the global as read only
    "globals": {
        "angular": false
    },

    // Enable additional rules and modify recommended rules as needed
    // See: http://eslint.org/docs/rules/ Rules marked with a ✓ are enabled by eslint:recommended
    "rules": {
        "no-global-assign": "error",
        "no-unsafe-negation": "error",
        "curly": "warn",
        "no-eval": "error",
        "no-implied-eval": "error",

        // Styles
        "array-bracket-spacing": ["error", "never", { "arraysInArrays": false }],
        "block-spacing": ["error", "always"],
        "brace-style": ["error", "1tbs"],
        "comma-spacing": ["error", { "before": false, "after": true }],
        "comma-style": ["error", "last"],
        "eol-last": ["error", "always"],
        "func-call-spacing": ["error", "never"],
        "indent": ["error", 4],
        "key-spacing": ["error", { "beforeColon": false, "afterColon": true }],
        "keyword-spacing": ["error", { "after": true, "before": true }],
        "max-len": ["warn", { "code": 120 }],
        "new-parens": "error",
        "no-trailing-spaces": "warn",
        "no-whitespace-before-property": "warn",
        "object-curly-spacing": ["error", "always"],
        "quotes": ["warn", "single", { "avoidEscape": true }],
        "semi-spacing": ["error", {"before": false, "after": true}],
        "space-before-blocks": ["error", "always"],
        "space-before-function-paren": ["error", "never"],
        "space-in-parens": ["warn", "never"],
        "spaced-comment": ["warn", "always"]
    }
};
