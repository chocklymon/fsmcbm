/*
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
'use strict';


/* ======================
 * Ban Manager Module
 * ======================
 */
angular.module('banManager', ['ngAnimate', 'ngRoute', 'ui.bootstrap', 'angular-loading-bar', 'auth0.lock', 'angular-jwt'])


    /* ======================
     * Factories & Services
     * ======================
     */

    /**
     * Perform a request to the ban-manager API.
     */
    .factory('request', ['$http', function($http) {
        var baseUrl = 'ban-manager.php?action=';
        function request(endpoint, payload) {
            return $http.post(baseUrl + endpoint, payload);
        }
        request.get = function(endpoint) {
            return $http.get(baseUrl + endpoint);
        };

        return request;
    }])

    /**
     * Provides caching for requests to the server. If a response comes from the server it is cached and then the
     * cached version is returned.
     */
    .factory('CachedRequest', ['$q', 'request', function($q, request) {
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
                    // Not cached, request the user from the server
                    var cachePromise = request(endpoint, payload).then(function(response) {
                        if (response && response.data) {
                            cache[endpoint] = response.data;
                            return cache[endpoint];
                        } else {
                            return $q.reject('Bad response from the server');
                        }
                    }).finally(function() {
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
    }])

    /**
     * Provides a wrapper around the localStorage window object.
     */
    .factory('localStore', [function() {
        var storage = window.localStorage;
        return {
            clear: function() {
                storage.clear();
            },
            getItem: function(key) {
                storage.getItem(key);
            },
            getJson: function(key) {
                return JSON.parse(storage.getItem(key));
            },
            key: function(index) {
                storage.key(index);
            },
            setItem: function(key, item) {
                storage.setItem(key, item);
            },
            setJson: function(key, item) {
                storage.setItem(key, JSON.stringify(item));
            },
            removeItem: function(key) {
                storage.removeItem(key);
            }
        };
    }])

    /**
     * Current selected player factory.
     *
     * Provides a cache for the currently managed user.
     */
    .factory('Player', ['request', 'CachedRequest', function(request, CachedRequest) {
        var cachedPlayer = null,
            getUUID = function() {
                if (cachedPlayer) {
                    return cachedPlayer.user.uuid;
                } else {
                    return null;
                }
            },

            /**
             * Get if the provided UUID matches the cached user.
             * @param uuid The UUID to check against.
             * @returns {boolean} True if the UUID matches.
             */
            matches = function(uuid) {
                return uuid === getUUID();
            };

        return {

            /**
             * Returns a promise the resolves when the player is added.
             * @param player
             * @returns {Promise}
             */
            add: function(player) {
                return request('add_user', player).then(function(response) {
                    // Response is the user_id on success
                    // TODO ?
                    return response;
                });
            },

            /**
             * Return a promise that returns the requested user.
             * @param uuid The player UUID
             * @param force If the player should be force loaded from the server.
             * @returns {Promise}
             */
            get: function(uuid, force) {
                if (force || !matches(uuid)) {
                    // Reset the cache, different user requested or being forced
                    CachedRequest.reset('lookup');
                }

                return CachedRequest.get('lookup', {'uuid': uuid}).then(function(player) {
                    // Convert data fields into data objects
                    angular.forEach(player.incident, function(incident) {
                        incident.created_date = new Date(incident.created_date);
                        incident.modified_date = new Date(incident.modified_date);
                        incident.incident_date = new Date(incident.incident_date);
                    });

                    // Maintain our own cache of the player for easier access
                    cachedPlayer = player;
                    return cachedPlayer;
                });
            },

            /**
             * Get the current user's universally unique identifier.
             * @returns {object} The current user's UUID, or null.
             */
            getUUID: getUUID,

            /**
             * Saves the user to the database.
             * @returns {Promise}
             */
            save: function() {
                return request('update_user', cachedPlayer.user).then(function(data) {
                    // TODO messaging?
                    return data;
                });
            }
        };
    }])

    /**
     * Gets settings from the server.
     */
    .factory('Settings', ['CachedRequest', function(CachedRequest) {
        return {
            getRanks: function () {
                return CachedRequest.get('get_ranks');
            },
            getWorlds: function () {
                return CachedRequest.get('get_worlds');
            }
        }
    }])

    /**
     * Current search terms factory.
     *
     * Caches the current search term.
     */
    .factory('CurrentSearch', [function() {
        var term = '';
        return {
            /**
             * Set the current search term.
             * @param searchTerm The search term to store.
             */
            set: function(searchTerm) {
                term = searchTerm;
            },

            /**
             * Get the current search term.
             * @returns {string} The current search term, or an empty string if there is no current search.
             */
            get: function() {
                return term;
            }
        };
    }])

    /**
     * Converts an un-formatted UUID to a formatted one.
     * E.g., 51f05c21503c4e309a3f9c7a6dbdb2ea becomes 51f05c21-503c-4e30-9a3f-9c7a6dbdb2ea
     */
    .factory('formatUUID', function() {
        var splice = function(target, index, insert) {
            return target.slice(0, index) + insert + target.slice(index);
        };
        return function(input) {
            if (input && input.length === 32) {
                var uuid = input.toLowerCase();
                uuid = splice(uuid, 8, '-');
                uuid = splice(uuid, 13, '-');
                uuid = splice(uuid, 18, '-');
                uuid = splice(uuid, 23, '-');
                return uuid;
            } else {
                return input;
            }
        };
    })

    /**
     * Goes to the user page.
     */
    .factory('goToUser', ['$location', 'formatUUID', function($location, formatUUID) {
        return function(uuid) {
            $location.path('/user/' + formatUUID(uuid));
        };
    }])

    /**
     * Contains messages and notifications.
     */
    .factory('message', [function() {
        var messages = [];
        return {
            SUCCESS: 'success',
            INFO: 'info',
            WARNING: 'warning',
            DANGER: 'danger',

            getMessages: function() {
                return messages;
            },
            addMessage: function(type, msg, timeout) {
                if (!timeout) {
                    // Default the timeout to 1 minute
                    timeout = 60000;
                }
                return messages.push({type: type, msg: msg, timeout: timeout});
            },
            successMsg: function(msg, timeout) {
                this.addMessage(this.SUCCESS, msg, timeout);
            },
            errorMsg: function(msg, timeout) {
                this.addMessage(this.DANGER, msg, timeout);
            },
            removeMessage: function(index) {
                return messages.splice(index, 1);
            }
        };
    }])

    .factory('Confirm', ['$rootScope', '$sce', '$uibModal', function($rootScope, $sce, $uibModal) {
        var $uibModalInstance,
            modalScope = $rootScope.$new();
        var closeModal = function() {
                $uibModalInstance.close();
            },
            dismissModal = function() {
                $uibModalInstance.dismiss();
            };
        var defaultButtons = [
            {
                action: closeModal,
                disabled: false,
                class: 'btn-primary',
                txt: 'Ok'
            },
            {
                action: dismissModal,
                disabled: false,
                class: 'btn-warning',
                txt: 'Cancel'
            }
        ];

        modalScope.callButtonFunction = function($index) {
            modalScope.buttons[$index].action.call(modalScope.buttons[$index].action, $uibModalInstance);
        };

        return function(msg) {
            if (!angular.isObject(msg)) {
                msg = {body: msg};
            }
            modalScope.title = msg.title || 'Confirm';
            if (msg.templateUrl) {
                modalScope.isTemplate = true;
                modalScope.body = msg.templateUrl;
            } else {
                modalScope.isTemplate = false;
                modalScope.body = $sce.trustAsHtml(msg.body);
            }
            if (msg.buttons) {
                modalScope.buttons = [];
                angular.forEach(msg.buttons, function(btn) {
                    var button = {};
                    button.action = btn.action || closeModal;
                    button.class = btn.class || 'btn-default';
                    button.txt = btn.txt || '';
                    if ('disabled' in btn) {
                        button.disabled = btn.disabled;
                    } else {
                        button.disabled = false;
                    }
                    modalScope.buttons.push(button);
                });
            } else {
                modalScope.buttons = defaultButtons;
            }
            $uibModalInstance = $uibModal.open({
                scope: modalScope,
                templateUrl: 'presentation/templates/base-modal.html',
                backdrop: 'static'
            });

            return $uibModalInstance.result;
        }
    }])

    .service('authService', ['$rootScope', 'lock', 'authManager', 'localStore', function authService($rootScope, lock, authManager, localStore) {
        var userProfile = localStore.getJson('profile') || {};

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
        // This method is called from app.run.js
        function initialize() {
            lock.on('authenticated', function(authResult) {
                localStore.setItem('id_token', authResult.idToken);
                authManager.authenticate();
                $rootScope.$broadcast('authComplete');

                lock.getProfile(authResult.idToken, function(error, profile) {
                    if (error) {
                        console.log(error);
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

        return {
            userProfile: userProfile,
            login: login,
            logout: logout,
            initialize: initialize
        }
    }])


    /* ======================
     * Filters
     * ======================
     */

    /**
     * Takes a boolean input and returns ✓ when true and ✘ when false.
     */
    .filter('checkmark', function() {
        return function(input) {
            var value = true;
            if (typeof input === 'boolean') {
                value = input;
            } else if (typeof input === 'number') {
                value = input === 1;
            } else if (typeof input === 'string') {
                value = input === 'true' || input === '1';
            } else if (!value) {
                value = false;
            }
            return value ? '\u2713' : '';
        };
    })


    /* ======================
     * Directives
     * ======================
     */
    .directive('bmPlayer', ['$log', 'Settings', 'message', function($log, Settings, message) {
        return {
            scope: {
                player: '=',
                adding: '=',
                form: '=?'
            },
            restrict: 'E',
            templateUrl: 'presentation/templates/user.html',
            link: function(scope) {
                if (scope.form) {
                    scope.form = scope.userForm;
                }
                Settings.getRanks().then(
                    function(ranks) {
                        scope.ranks = ranks;
                    },
                    function(err) {
                        $log.warn('Failed to load ranks', err);
                        message.errorMsg('Unable to load ranks');
                    }
                );
                var playerImage = '';
                if (scope.player.uuid) {
                    playerImage = "https://crafatar.com/avatars/" + scope.player.uuid + "?helm=1&size=64";
                }
                scope.playerImage = playerImage;
            }
        };
    }])
    .directive('incident', ['$log', 'Settings', 'message', function($log, Settings, message) {
        return {
            scope: {
                incident: '=',
                adding: '=',
                form: '=?'
            },
            restrict: 'E',
            templateUrl: 'presentation/templates/incident.html',
            link: function(scope) {
                if (scope.form) {
                    scope.form = scope.incidentForm;
                }
                Settings.getWorlds().then(
                    function(worlds) {
                        scope.worlds = worlds;
                    },
                    function(err) {
                        $log.warn('Failed to load worlds', err);
                        message.errorMsg('Unable to load worlds');
                    }
                );
                scope.selectUser = function($item) {
                    scope.incident.user_id = $item.value;
                };
            }
        };
    }])
    .directive('lookup', ['request', function(request) {
        return {
            scope: {
                onSelect: '&',
                required: '=?',
                selected: '=?selectValue',
                selectFirst: '=?'
            },
            restrict: 'EA',
            templateUrl: 'presentation/templates/lookup.html',
            link: function(scope) {
                // Calls the on select function with the currently selected item
                scope.selectUser = function($item) {
                    scope.onSelect({'$item': $item});
                };

                // Loads the type-ahead terms via AJAX
                scope.getPossibleUsers = function(val) {
                    return request('auto_complete', {
                        term: val
                    }).then(function(res){
                        return res.data;
                    });
                };
            }
        };
    }])
    .directive('bmAlerts', ['message', function(message) {
        return {
            scope: false,
            restrict: 'AE',
            templateUrl: 'presentation/templates/alerts.html',
            controller: ['$scope', function($scope) {
                $scope.alerts = message.getMessages();
                $scope.closeAlert = function(index) {
                    message.removeMessage(index);
                };
            }]
        };
    }])


    /* ======================
     * Configuration
     * ======================
     */
    .config(['$routeProvider', '$httpProvider', 'uibDatepickerConfig', 'lockProvider', 'jwtOptionsProvider',
        function($routeProvider, $httpProvider, uibDatepickerConfig, lockProvider, jwtOptionsProvider) {
        // Auth0 Configuration
        // Set the client ID and domain
        lockProvider.init({
            clientID: AUTH_INFO.clientId,
            domain: AUTH_INFO.domain,
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
                return localStorage.getItem('id_token');
            }
        });
        // Add the jwtInterceptor so that the jwt token is added to all requests
        $httpProvider.interceptors.push('jwtInterceptor');

        $routeProvider
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
        $httpProvider.interceptors.push(['$q', function($q){
            return {
                'requestError': function(rejection) {
                    // TODO handle the error
                    return $q.reject(rejection);
                },
                'response': function(response) {
                    if (response.data && response.data.error) {
                        // TODO handle errors
                        console.warn(response.data);
                        return $q.reject(response);
                    }
                    return response;
                },
                'responseError': function(rejection) {
                    // TODO handle errors
                    console.warn(rejection);
                    return $q.reject(rejection);
                }
            };
        }]);

        // Set the defaults for the date picker component
        uibDatepickerConfig.showWeeks = false;
    }])


    /* ======================
     * Controllers
     * ======================
     */
    .controller('InitializingController', ['$scope', '$timeout', '$location', '$route', 'authService', 'localStore',
        function($scope, $timeout, $location, $route, authService, localStore) {
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
    }])
    .controller('UserController', ['$scope', '$routeParams', 'Player', 'request', 'formatUUID', 'message', function($scope, $routeParams, Player, request, formatUUID, message) {
        // Create a function to set the data in the scope
        var setPlayer = function(player) {
            // Set the player data into the scope
            $scope.user = {
                player: player.user,
                incidents: player.incident,
                history: player.history
            };
            $scope.user.player.uuid = formatUUID(player.user.uuid);
        };

        // Button functions
        $scope.reset = function() {
            // Reload from the server
            Player.get($routeParams.uuid, true).then(setPlayer);
        };
        $scope.saveIncident = function(incident) {
            request('update_incident', incident).success(function() {
                message.successMsg('Incident updated.', 6000);
            });
        };
        $scope.saveUser = function() {
            Player.save().then(function() {
                message.successMsg('User updated.', 6000);
            });
        };

        // If we have a UUID in the router parameters, load up the user
        if ($routeParams.uuid) {
            Player.get($routeParams.uuid).then(setPlayer);
        }
    }])
    .controller('UserListController', ['$scope', '$location', 'request', 'goToUser', function($scope, $location, request, goToUser) {
        $scope.lookupUser = goToUser;

        var endpoint = $location.path() === '/bans' ? 'get_bans' : 'get_watchlist';
        request.get(endpoint).then(function(response) {
            $scope.users = response.data;
        });
    }])
    .controller('SearchController', ['$scope', '$routeParams', 'request', 'goToUser', function($scope, $routeParams, request, goToUser) {
        $scope.lookupUser = goToUser;

        if ($routeParams.term) {
            request('search', {search: $routeParams.term})
                .success(function(data){
                    if (data.users) {
                        $scope.users = data.users;
                    } else {
                        $scope.users = [];
                    }
                    if (data.incidents) {
                        $scope.incidents = data.incidents;
                    } else {
                        $scope.incidents = [];
                    }
                });
        }
    }])
    .controller('NavigationController',
        ['$scope', '$location', 'Player', 'CurrentSearch', '$uibModal', 'authService', 'goToUser',
        function($scope, $location, Player, CurrentSearch, $uibModal, authService, goToUser)
    {
        $scope.authService = authService;

        $scope.tabs = [
            {link: 'user', label: 'Manage'},
            {link: 'bans', label: 'Bans'},
            {link: 'watchlist', label: 'Watchlist'},
            {link: 'search', label: 'Search'}
        ];

        // Try to find the currently selected tab
        var getSelectedTab = function() {
            for (var i = 0; i < $scope.tabs.length; i++) {
                if ($location.path().indexOf($scope.tabs[i].link) !== -1) {
                    return $scope.tabs[i];
                }
            }
            // No currently selected tab, select the first one by default
            return $scope.tabs[0];
        };
        var search = function() {
            $location.path('search/' + CurrentSearch.get());
        };

        $scope.selectedTab = getSelectedTab();

        // Change what tab is selected
        $scope.selectTab = function(tab) {
            if (tab === $scope.tabs[0] && Player.getUUID()) {
                // User tab, load the current user
                $scope.loadUser(Player.getUUID());
            } else if (tab === $scope.tabs[3]) {
                // Search tab, load the current search term
                if (CurrentSearch.get()) {
                    search();
                } else {
                    // No search, exit now
                    return;
                }
            } else {
                $location.path(tab.link);
            }
            $scope.selectedTab = tab;
        };

        // Set the class for each tab
        $scope.tabClass = function(tab) {
            if ($scope.selectedTab === tab) {
                return 'active';
            } else if (tab === $scope.tabs[3] && !CurrentSearch.get()) {
                return 'disabled';
            } else {
                return '';
            }
        };

        // Loads a selected user
        $scope.loadUser = function(uuid) {
            // If we are passed a user or lookup object, extract the UUID from it
            if (typeof uuid === 'object' && uuid.uuid) {
                uuid = uuid.uuid;
            }
            goToUser(uuid);
        };

        // Add user and add incident buttons
        $scope.addUser = function() {
            $uibModal.open({
                templateUrl: 'presentation/templates/add-player.html',
                controller: 'AddUserController',
                size: 'lg'
            });
        };
        $scope.addIncident = function() {
            $uibModal.open({
                templateUrl: 'presentation/templates/add-incident.html',
                controller: 'AddIncidentController',
                size: 'lg'
            });
        };

        // Performs the search
        $scope.search = function() {
            CurrentSearch.set($scope.search.text);
            search();
        };
        $scope.search.text = '';

        // Watch for changes on the route
        $scope.$on('$routeChangeSuccess', function() {
            $scope.selectedTab = getSelectedTab();
        });
    }])
    .controller('AddUserController', ['$scope', 'Player', '$uibModalInstance', 'message', 'Confirm', function($scope, Player, $uibModalInstance, message, Confirm){
        var addUser = function() {
            $scope.submitting = true;
            Player.add($scope.player.info).then(
                function(response) {
                    message.successMsg('User added.', 6000);
                    $uibModalInstance.close(response);
                },
                function(err) {
                    var errorMsg = 'Problem adding user. ';
                    if (err.data && err.data.error) {
                        errorMsg += err.data.error;
                    }
                    message.errorMsg(errorMsg);
                    $scope.submitting = false;
                }
            );
        };

        $scope.player = {
            info: {}
        };
        $scope.playerForm = {};
        $scope.save = function() {
            $scope.playerForm.attempted = true;
            if ($scope.playerForm.$invalid) {
                message.addMessage(message.WARNING, 'Please fill out all required fields!', 10000);
            } else if (!$scope.player.info.username) {
                Confirm(
                    'Are you sure that you wish to add a user without a username?<br><br>Press Ok to add the user.<br>Press Cancel to close this dialog and add a username.'
                ).then(addUser);
            } else {
                addUser();
            }
        };
        $scope.cancel = function() {
            $uibModalInstance.dismiss('cancel');
        };
    }])
    .controller('AddIncidentController', ['$scope', 'request', '$uibModalInstance', 'message', 'Confirm', function($scope, request, $uibModalInstance, message, Confirm) {
        $scope.incident = {};
        $scope.incidentForm = {};

        $scope.incident.selectUser = function($item) {
            $scope.incident.user_id = $item.user_id;
        };

        var addIncident = function() {
            $scope.submitting = true;
            request('add_incident', $scope.incident).then(
                function(response) {
                    message.successMsg('Incident added.', 6000);
                    $uibModalInstance.close(response);
                },
                function(err) {
                    var errorMsg = 'Problem adding incident. ';
                    if (err.data && err.data.error) {
                        errorMsg += err.data.error;
                    }
                    message.errorMsg(errorMsg);
                    $scope.submitting = false;
                }
            );
        };

        $scope.save = function() {
            // Validate that the incident is correctly set up
            if ($scope.incidentForm.$invalid) {
                if (!$scope.incidentForm.$dirty) {
                    $scope.incidentForm.$setDirty(true);
                }
                message.addMessage(message.WARNING, 'Please fill out all required fields!', 10000);
            } else if (!$scope.incident.user_id) {
                message.addMessage(message.WARNING, 'Please select a user!', 10000);
            } else if (!$scope.incident.notes) {
                Confirm(
                    'Are you sure that you wish to add an incident without any notes?<br><br>Press Ok to add the incident.<br>Press Cancel to close this dialog and add notes.'
                ).then(addIncident);
            } else {
                addIncident();
            }
        };
        $scope.cancel = function() {
            $uibModalInstance.dismiss('cancel');
        };
    }])

    .run(['authService', function(authService) {
        authService.initialize();
    }]);