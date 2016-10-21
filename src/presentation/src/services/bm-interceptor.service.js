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

    angular.module('banManager').provider('bmInterceptor', bmInterceptorProvider);

    bmInterceptorProvider.$inject = [];
    function bmInterceptorProvider() {
        this.$get = bmInterceptor;
    }

    bmInterceptor.$inject = ['$rootScope', '$q', '$log', 'bmConfig'];
    function bmInterceptor($rootScope, $q, $log, bmConfig) {
        return {
            'requestError': function(rejection) {
                // TODO handle the error
                return $q.reject(rejection);
            },
            'response': function(response) {
                if (response.data && response.data.error) {
                    // TODO handle errors
                    $log.warn(response.data);
                    return $q.reject(response);
                }
                return response;
            },
            'responseError': function(response) {
                // TODO handle errors
                $log.warn(response);

                // Handle 401 errors if auth0 won't
                if (response.status === 401 && !bmConfig.useAuth0()) {
                    $rootScope.$broadcast('unauthenticated', response);
                }
                return $q.reject(response);
            }
        };
    }
})();
