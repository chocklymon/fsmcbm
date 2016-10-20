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

(function() {
    'use strict';

    /**
     * Provides caching for requests to the server. If a response comes from the server it is cached and then the
     * cached version is returned.
     */
    angular.module('banManager').factory('CachedRequest', CachedRequest);

    CachedRequest.$inject = ['$q', 'request'];
    function CachedRequest($q, request) {
        var cache = {};
        var ongoingPromises = {};

        return {
            /**
             * Get data from the server.
             * If the data has been requested before a cached version of the data will be returned.
             * @param {string} endpoint
             * @param {*} payload
             * @returns {*}
             */
            get: function(endpoint, payload) {
                if (endpoint in cache) {
                    return $q.when(cache[endpoint]);
                } else if (ongoingPromises[endpoint]) {
                    return ongoingPromises[endpoint];
                } else {
                    // Not cached, request from the server
                    var cachePromise = request(endpoint, payload)
                        .then(function(response) {
                            if (response && response.data) {
                                cache[endpoint] = response.data;
                                return cache[endpoint];
                            } else {
                                return $q.reject('Bad response from the server');
                            }
                        })
                        .finally(function() {
                            ongoingPromises[endpoint] = null;
                        });

                    ongoingPromises[endpoint] = cachePromise;
                    return cachePromise;
                }
            },

            /**
             * Clears the cache for a given endpoint.
             * @param {string} endpoint
             */
            reset: function(endpoint) {
                if (endpoint) {
                    // Delete the given endpoint from the cache, if it exists
                    if (endpoint in cache) {
                        delete cache[endpoint];
                    }
                } else {
                    // Reset all
                    cache = {};
                }
            }
        }
    }
})();
