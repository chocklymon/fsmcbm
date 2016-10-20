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

    angular.module('banManager').provider('authService', AuthServiceProvider);

    AuthServiceProvider.$inject = ['bmConfigProvider'];
    function AuthServiceProvider(bmConfigProvider) {
        var $get = ['$rootScope'];
        if (bmConfigProvider.useAuth0()) {
            $get.push('$log', 'localStore', 'lock', 'authManager', Auth0Service);
        } else {
            $get.push('request', WPAuthService);
        }

        this.$get = $get;
    }

    function Auth0Service($rootScope, $log, localStore, lock, authManager) {
        var userProfile = localStore.getJson('profile') || {};

        return {
            userProfile: userProfile,
            login: login,
            logout: logout,
            initialize: initialize
        };

        function login() {
            lock.show();
        }

        // Logging out just requires removing the user's id_token and profile
        function logout() {
            localStore.removeItem('id_token');
            localStore.removeItem('profile');
            authManager.unauthenticate();
            angular.forEach(userProfile, function(v, key) {
                delete userProfile[key];
            });
            $rootScope.$broadcast('loggedout');
        }

        // Set up the logic for when a user authenticates
        // This method is called from src.run.js
        function initialize() {
            lock.on('authenticated', function(authResult) {
                localStore.setItem('id_token', authResult.idToken);
                authManager.authenticate();
                $rootScope.$broadcast('authComplete');

                lock.getProfile(authResult.idToken, function(error, profile) {
                    if (error) {
                        $log.log(error);
                    }

                    localStore.setJson('profile', profile);
                    $rootScope.$broadcast('userProfileSet', profile);

                    angular.forEach(profile, function(value, key) {
                        userProfile[key] = value;
                    });
                });
            });
            $rootScope.$on('unauthenticated', function() {
                logout();
            });

            // Use the authManager from angular-jwt to check for
            // the user's authentication state when the page is
            // refreshed and maintain authentication
            authManager.checkAuthOnRefresh();
        }
    }

    function WPAuthService($rootScope, request) {
        $rootScope.isAuthenticated = false;

        request.get('authenticated').then(function() {
            // console.log(arguments);
        });

        return {
            userProfile: {},
            login: login,
            logout: logout,
            initialize: initialize
        };

        function login() {}
        function logout() {
            $rootScope.$broadcast('loggedout');
        }

        function initialize() {

        }
    }
})();
