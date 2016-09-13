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
angular.module('banManager', ['ngRoute', 'ui.bootstrap', 'chieffancypants.loadingBar'])


    /* ======================
     * Factories & Services
     * ======================
     */

    /**
     * Perform a request to the ban-manager API.
     */
    .factory('request', ['$http', function($http) {
        return function(endpoint, payload) {
            return $http.post('ban-manager.php?action=' + endpoint, payload);
        };
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

    .factory('Authentication', [function AuthenticationFactory() {
        var loggedInObj = {
            loggedIn: false
        };
        return {
            loggedInObj: loggedInObj,
            getAuthenticated: function() {
                return loggedInObj.loggedIn;
            },
            setAuthenticated: function(loggedIn) {
                loggedInObj.loggedIn = !!loggedIn;
            }
        };
    }])
    .factory('AuthHandler', ['$q', 'request', 'Authentication', function AuthHandlerFactory($q, request, Authentication) {
        var authInfo = null,
            getAuthResponse = function() {
                if (authInfo.authenticated === true) {
                    return true;
                } else if (authInfo['use-wordpress']) {
                    return 'use-wordpress';
                } else {
                    return false;
                }
            };
        return {
            login: function(username, password) {
                return request('login', {
                    username: username,
                    password: password
                }).then(function(response) {
                    var authenticated = !!response.data.success;
                    if (authInfo) {
                        authInfo.authenticated = authenticated;
                        Authentication.setAuthenticated(authenticated);
                    }
                    return authenticated;
                });
            },
            isAuthenticated: function() {
                if (authInfo) {
                    return $q.when(getAuthResponse());
                } else {
                    return request('authenticated').then(
                        function (response) {
                            authInfo = response.data;
                            Authentication.setAuthenticated(authInfo.authenticated);
                            return getAuthResponse();
                        }
                    );
                }
            }
        }
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

    .factory('Confirm', ['$rootScope', '$sce', '$modal', function($rootScope, $sce, $modal) {
        var $modalInstance,
            modalScope = $rootScope.$new();
        var closeModal = function() {
                $modalInstance.close();
            },
            dismissModal = function() {
                $modalInstance.dismiss();
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
            modalScope.buttons[$index].action.call(modalScope.buttons[$index].action, $modalInstance);
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
            $modalInstance = $modal.open({
                scope: modalScope,
                templateUrl: 'presentation/templates/base-modal.html',
                backdrop: 'static'
            });

            return $modalInstance.result;
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
                selectFirst: '@?'
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


    /* ======================
     * Configuration
     * ======================
     */
    .config(['$routeProvider', '$httpProvider', 'datepickerConfig', function($routeProvider, $httpProvider, datepickerConfig) {
        // Set up the routes
        var requireLoggedIn = {
            isLoggedIn: ['$location', '$q', 'AuthHandler', function($location, $q, AuthHandler) {
                return AuthHandler.isAuthenticated().then(function(authenticated) {
                    if (authenticated === true) {
                        return true;
                    } else if (authenticated === 'use-wordpress') {
                        $location.url('/wp-login.php');
                    } else {
                        $location.path('/login');
                    }
                    return $q.reject(false);
                });
            }]
        };

        $routeProvider
            .when('/', {
                controller: 'InitializingController',
                templateUrl: 'presentation/views/loading.html'
            })
            .when('/login', {
                controller: 'LoginController',
                templateUrl: 'presentation/views/login.html'
            })
            .when('/user', {
                controller: 'UserController',
                templateUrl: 'presentation/views/manage-user.html',
                resolve: requireLoggedIn
            })
            .when('/user/:uuid', {
                controller: 'UserController',
                templateUrl: 'presentation/views/manage-user.html',
                resolve: requireLoggedIn
            })
            .when('/bans', {
                controller: 'UserListController',
                templateUrl: 'presentation/views/userlist.html',
                resolve: requireLoggedIn
            })
            .when('/watchlist', {
                controller: 'UserListController',
                templateUrl: 'presentation/views/userlist.html',
                resolve: requireLoggedIn
            })
            .when('/search/:term?', {
                controller: 'SearchController',
                templateUrl: 'presentation/views/search.html',
                resolve: requireLoggedIn
            })
            .otherwise({ redirectTo: '/' });

        // Set up the http request handler
        $httpProvider.interceptors.push(['$q', 'Authentication', function($q, Authentication){
            return {
                'requestError': function(rejection) {
                    // TODO handle the error
                    return rejection;
                },
                'response': function(response) {
                    if (response.data && response.data.error) {
                        // TODO handle errors
                        console.warn(response.data);
                        if (response.data.authenticated === false) {
                            // User is not logged in
                            Authentication.setAuthenticated(false);
                        }
                        return $q.reject(response);
                    }
                    return response;
                },
                'responseError': function(rejection) {
                    // TODO handle errors
                    console.warn(rejection);
                    return rejection;
                }
            };
        }]);

        // Set the defaults for the date picker component
        datepickerConfig.showWeeks = false;
    }])


    /* ======================
     * Controllers
     * ======================
     */
    .controller('InitializingController', ['$location', '$log', 'AuthHandler', 'message', function($location, $log, AuthHandler, message) {
        AuthHandler.isAuthenticated().then(
            function(authenticated) {
                if (authenticated === true) {
                    $location.path('/user');
                } else if (authenticated === 'use-wordpress') {
                    $location.url('/wp-login.php');
                } else {
                    $location.path('/login');
                }
            },
            function(err) {
                $log.warn('Error initializing: ', err);
                message.errorMsg('Problem communication with the server. Please try again later.');
            }
        );
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
            // See if this user is cached
            Player.get($routeParams.uuid).then(setPlayer);
        }
    }])
    .controller('UserListController', ['$scope', '$location', '$http', 'formatUUID', function($scope, $location, $http, formatUUID) {
        var endpoint = $location.path() === '/bans' ? 'get_bans' : 'get_watchlist';
        $scope.lookupUser = function(uuid) {
            $location.path('/user/' + formatUUID(uuid));
        };
        // TODO use request
        $http.get('ban-manager.php?action=' + endpoint)
            .success(function(data) {
                $scope.users = data;
            });
    }])
    .controller('SearchController', ['$scope', '$routeParams', '$location', 'request', 'formatUUID', function($scope, $routeParams, $location, request, formatUUID) {
        // TODO remove this duplication of lookup user from the userlist
        $scope.lookupUser = function(uuid) {
            $location.path('/user/' + formatUUID(uuid));
        };

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
    .controller('LoginController', ['$scope', '$location', 'AuthHandler', 'message', function($scope, $location, AuthHandler, message) {
        var user = {
            username: '',
            password: ''
        };
        $scope.user = user;

        AuthHandler.isAuthenticated().then(
            function(authenticated) {
                if (authenticated === true) {
                    $location.path('/user');
                } else if (authenticated === 'use-wordpress') {
                    $location.url('/wp-login.php');
                }
            },
            function(err) {
                $log.warn('Error initializing: ', err);
                message.errorMsg('Problem communication with the server. Please try again later.');
            }
        );

        $scope.login = function() {
            $scope.loginForm.attempted = true;
            if($scope.loginForm.$valid) {
                AuthHandler.login(user.username, user.password).then(function () {
                        // Logged in
                        $location.path('/user');
                    })
                    .catch(function () {
                        // Login failed
                        message.addMessage(message.DANGER, 'Login Failed: Incorrect username or password!', 6000);
                        user.password = '';
                        $scope.loginForm.attempted = false;
                    });
            }
        }
    }])
    .controller('NavigationController',
        ['$scope', '$location', 'request', 'Player', 'CurrentSearch', '$modal', 'formatUUID', 'Authentication',
        function($scope, $location, request, Player, CurrentSearch, $modal, formatUUID, Authentication)
    {
        $scope.user = Authentication.loggedInObj;

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
            $location.path('/user/' + formatUUID(uuid));
        };

        // Add user and add incident buttons
        $scope.addUser = function() {
            $modal.open({
                templateUrl: 'presentation/templates/add-player.html',
                controller: 'AddUserController',
                size: 'lg'
            });
        };
        $scope.addIncident = function() {
            $modal.open({
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

        // Alerts
        $scope.alerts = [];
        $scope.closeAlert = function(index) {
            $scope.alerts.splice(index, 1);
        };
        $scope.addAlert = function() {
            $scope.alerts.push({type: 'danger', msg: 'Another alert!'});
        };

        // Watch for changes on the route
        $scope.$on('$routeChangeSuccess', function() {
            $scope.selectedTab = getSelectedTab();
        });
    }])
    .controller('AddUserController', ['$scope', 'Player', '$modalInstance', 'message', 'Confirm', function($scope, Player, $modalInstance, message, Confirm){
        // TODO This and the user controller will be very similar, find a way to combine them?
        var addUser = function() {
            $scope.submitting = true;
            Player.add($scope.player.info).then(
                function(response) {
                    message.successMsg('User added.', 6000);
                    $modalInstance.close(response);
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
            $modalInstance.dismiss('cancel');
        };
    }])
    .controller('AddIncidentController', ['$scope', 'request', '$modalInstance', 'message', 'Confirm', function($scope, request, $modalInstance, message, Confirm) {
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
                    $modalInstance.close(response);
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
            $modalInstance.dismiss('cancel');
        };
    }])

    .controller('MessageController', ['$scope', 'message', function($scope, message) {
        $scope.alerts = message.getMessages();
        $scope.closeAlert = function(index) {
            message.removeMessage(index);
        };
    }]);
