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
/* global module:false */

function ConfigureGrunt(grunt) {
    var rootDir = 'src/presentation';
    var sourceFiles = rootDir + '/src/**/*.js';
    var outputDir = rootDir + '/dist';

    var longBanner = '/**\n'
        + ' * <%= pkg.name %> v<%= pkg.version %>\n'
        + ' * Copyright <%= grunt.template.today("yyyy") %> Curtis Oakley\n'
        + ' *\n'
        + ' * License: <%= pkg.license %>\n'
        + ' */\n'
        + "'use strict';\n";
    var shortBanner = '/*! <%= pkg.name %> v<%= pkg.version %> | '
        + 'Copyright <%= grunt.template.today("yyyy") %> Curtis Oakley | '
        + '<%= pkg.license %> License */';

    /**
     * Inserts one string into another string.
     * @param {string} src The source string to have a string inserted into.
     * @param {integer} index The index where to insert the string.
     * @param {string} value The string to insert.
     */
    function insert(src, index, value) {
        return src.slice(0, index) + value + src.slice(index);
    }

    // Project Configuration
    grunt.initConfig({
        // Read the package.json file so we can access the variables within it
        pkg: grunt.file.readJSON('package.json'),

        // Configure concat - Combines all files into a single one
        concat: {
            // Global configuration
            options: {
                stripBanners: true,
                process: function(src, filepath) {
                    // Custom process function for combining files. Removes any "use strict" flags and adds a source
                    // file comment.

                    // Regex to capture 'use strict' and replace it with just whitespace (if any).
                    var useStrictRegex = /(^|\n[ \t]*)('use strict'|"use strict");?\s*/g;
                    var srcComment = '\n// Source: ' + filepath + '\n';

                    var currentBannerLoc = src.indexOf('*/');

                    // Inserts the source comment after the first comment block, if there is one.
                    // Assumes that the first comment block will be at the top of the file.
                    if (currentBannerLoc >= 0) {
                        src = insert(src, currentBannerLoc + 2, srcComment);
                    } else {
                        src = srcComment + src;
                    }

                    // Remove all "use strict" flags in the processed files. There should be one in the banner only.
                    return src.replace(useStrictRegex, '$1');
                }
            },
            dist: {
                options: {
                    banner: longBanner
                },
                src: [sourceFiles],
                dest: outputDir + '/<%= pkg.name %>.js'
            }
        },

        // Configure angular templates - Takes the html templates and saves them inline
        ngtemplates: {
            options: {
                htmlmin: {
                    collapseBooleanAttributes: true,
                    collapseWhitespace: true,
                    conservativeCollapse: true,
                    html5: true,
                    removeComments: true,
                    removeRedundantAttributes: true
                }
            },
            dist: {
                cwd: 'src',
                src: ['presentation/src/**/*.html'],
                dest: outputDir + '/templates.js',
                options: {
                    concat: 'dist',
                    module: 'banManager'
                }
            }
        },

        // Configure Uglify - Minifies the source code
        uglify: {
            options: {
                banner: shortBanner,
                sourceMap: true,
                maxLineLen: 4096,
                mangle: {
                    toplevel: true
                },
                wrap: 'banManager',
                screwIE8: true
            },
            dist: {
                files: [{
                    src: outputDir + '/<%= pkg.name %>.js',
                    dest: outputDir + '/<%= pkg.name %>.min.js'
                }]
            }
        },

        // Configure watch
        watch: {
            lint: {
                files: [sourceFiles, 'Gruntfile.js'],
                tasks: ['lint']
            },
            dist: {
                files: [sourceFiles, rootDir + '/src/**/*.html'],
                tasks: ['dist']
            }
        },

        // Configure linting
        eslint: {
            options: {
                fix: true
            },
            target: [sourceFiles, 'Gruntfile.js']
        }
    });

    // Load plugins
    grunt.loadNpmTasks('grunt-eslint');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-angular-templates');

    // Register tasks
    grunt.registerTask('lint', 'Alias for eslint task', ['eslint']);
    grunt.registerTask('dist', 'Build the files for use', ['ngtemplates:dist', 'concat:dist', 'uglify:dist']);
    grunt.registerTask('default', ['eslint', 'dist']);
}

module.exports = ConfigureGrunt;
