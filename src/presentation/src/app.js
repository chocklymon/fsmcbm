/**
 * The MIT License
 *
 * Copyright 2014-2016 Curtis Oakley.
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
/* globals window:false */

(function($$window, angular) {
    'use strict';

    // Global configuration
    var banManagerConfiguration = new function() {
        var config = $$window.bmConfig || {};

        this.get = getConfig;
        this.set = function(key, value) {
            config[key] = value;
        };
        this.useAuth0 = useAuth0;

        this.$get = [function() {
            return {
                get: getConfig,
                useAuth0: useAuth0
            }
        }];

        function getConfig(key) {
            return config[key];
        }

        function useAuth0() {
            return getConfig('authMode') == 'auth0';
        }
    }();

    /* ======================
     * Ban Manager Module
     * ======================
     */
    var dependencies = ['ngAnimate', 'ngRoute', 'ui.bootstrap', 'angular-loading-bar'];
    if (banManagerConfiguration.useAuth0()) {
        // Include the auth0 modules
        dependencies.push('auth0.lock', 'angular-jwt');
    }
    angular.module('banManager', dependencies);


    /* ======================
     * Factories & Services
     * ======================
     */
    angular.module('banManager').provider('bmConfig', banManagerConfiguration);


    /* ======================
     * Configuration
     * ======================
     */
    angular.module('banManager').config(ModuleConfig);

    ModuleConfig.$inject = ['$routeProvider', '$httpProvider', 'uibDatepickerConfig', 'bmConfigProvider'];
    if (banManagerConfiguration.useAuth0()) {
        ModuleConfig.$inject.push('lockProvider', 'jwtOptionsProvider');
    }
    function ModuleConfig(
        $routeProvider, $httpProvider, uibDatepickerConfig, bmConfigProvider, lockProvider, jwtOptionsProvider
    ) {
        if (bmConfigProvider.useAuth0()) {
            // Auth0 Configuration
            // Set the client ID and domain
            lockProvider.init({
                clientID: bmConfigProvider.get('clientId'),
                domain: bmConfigProvider.get('domain'),
                options: {
                    additionalSignUpFields: [{
                        name: 'minecraft_username',
                        placeholder: 'enter your minecraft username',
                        icon: '/presentation/assets/img/mc-block-icon_14x14.png',
                        validator: function(username) {
                            // See: https://help.mojang.com/customer/en/portal/articles/928638-minecraft-usernames
                            var regex = /[^a-zA-Z0-9_]/g;
                            return {
                                valid: username.length >= 2 && username.length <= 16 && !regex.test(username),
                                hint: 'Invalid minecraft username'
                            }
                        }
                    }],
                    closable: false,
                    languageDictionary: {
                        title: 'Ban Manager Log In'
                    },
                    socialButtonStyle: 'small',
                    theme: {
                        logo: '/presentation/assets/img/mc-block-icon.png',
                        primaryColor: '#649325'
                    }
                }
            });
            // Configure the token storage and retrieval
            jwtOptionsProvider.config({
                tokenGetter: function() {
                    return $$window.localStorage.getItem('id_token');
                }
            });
            // Add the jwtInterceptor so that the jwt token is added to all requests
            $httpProvider.interceptors.push('jwtInterceptor');
        }

        $routeProvider
            .when('/configuration', {
                controller: 'ConfigurationController',
                templateUrl: 'presentation/views/configuration.html'
            })
            .when('/user', {
                controller: 'UserController',
                templateUrl: 'presentation/views/manage-user.html'
            })
            .when('/user/:uuid', {
                controller: 'UserController',
                templateUrl: 'presentation/views/manage-user.html'
            })
            .when('/bans', {
                controller: 'UserListController',
                templateUrl: 'presentation/views/userlist.html'
            })
            .when('/watchlist', {
                controller: 'UserListController',
                templateUrl: 'presentation/views/userlist.html'
            })
            .when('/search/:term?', {
                controller: 'SearchController',
                templateUrl: 'presentation/views/search.html'
            });

        // Set up the http request handler
        $httpProvider.interceptors.push('bmInterceptor');

        // Set the defaults for the date picker component
        uibDatepickerConfig.showWeeks = false;
    }

    angular.module('banManager').run(['authService', function(authService) {
        authService.initialize();
    }]);
})(window, angular);
