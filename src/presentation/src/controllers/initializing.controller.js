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

    angular.module('banManager').controller('InitializingController', InitializingController);

    InitializingController.$inject = ['$scope', '$timeout', '$location', '$route', 'authService', 'localStore'];
    function InitializingController($scope, $timeout, $location, $route, authService, localStore) {
        $scope.authService = authService;
        $scope.showLoading = true;
        $scope.hidden = false;
        $scope.showLoginBtn = false;

        var redirectUrl = localStore.getJson('redirectUrl');
        var currentTime = new Date().getTime();

        var showLoginTimeout;
        var authCheckTimeout = $timeout(function() {
            if ($scope.isAuthenticated) {
                finishInitialize();
            } else {
                authService.login();
            }
        }, 100);

        // Save the current url if there isn't one, or the current one is older then twenty minutes
        if (!redirectUrl || redirectUrl.timestamp <= currentTime - (20 * 60 * 1000)) {
            redirectUrl = {
                timestamp: currentTime,
                url: $location.url()
            };
            localStore.setJson('redirectUrl', redirectUrl);
        }

        function routeMatches(on, route) {
            if (!route.regexp) {
                return false;
            }

            return route.regexp.test(on);
        }

        function finishInitialize() {
            // Hide the overlay
            $scope.hidden = true;

            // Find where to go
            // This code modified from ngRoute
            var goTo;
            angular.forEach($route.routes, function(route) {
                if (!goTo && routeMatches(redirectUrl.url, route)) {
                    goTo = redirectUrl.url;
                }
            });
            // Fallback to /user route if we didn't find a valid route
            if (!goTo) {
                goTo = '/user';
            }

            // Clear the redirect URL and go to the route
            localStore.removeItem('redirectUrl');
            $location.url(goTo);
        }

        function displayLogin(msg, subMsg, timeout) {
            $scope.hidden = false;
            $scope.showLoading = false;
            $scope.showLoginBtn = true;
            $scope.msg = msg;
            $scope.subMsg = subMsg;
            showLoginTimeout = $timeout(authService.login, timeout);
        }

        $scope.showLogin = function() {
            $timeout.cancel(showLoginTimeout);
            authService.login();
        };

        // Listen for authentication events
        $scope.$on('authComplete', function() {
            $timeout.cancel(authCheckTimeout);
            finishInitialize();
        });
        $scope.$on('unauthenticated', function() {
            displayLogin('Your session has expired', 'Please log in again', 30000);
        });
        $scope.$on('loggedout', function() {
            displayLogin('Logout Successful', '', 20000);
        });
    }
})();
