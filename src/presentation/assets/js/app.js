/* 
 * The MIT License
 *
 * Copyright 2014 Curtis Oakley.
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
var bm = {};
bm.ranks = [{"value":"1","label":"Everyone"},{"value":"2","label":"Regular"},{"value":"3","label":"Donor"},{"value":"4","label":"Builder"},{"value":"5","label":"Engineer"},{"value":"6","label":"Moderator"},{"value":"7","label":"Admin"},{"value":"8","label":"Default"}];
bm.worlds = [{
                        value:"",
                        label:""
                    },
                    {
                        value:"world",
                        label:"Alpha"
                    },
                    {
                        value:"world3",
                        label:"Delta"
                    },
                    {
                        value:"world4",
                        label:"Gamma"
                    },
                    {
                        value:"omega",
                        label:"Omega"
                    },
                    {
                        value:"world_nether",
                        label:"Alpha Nether"
                    },
                    {
                        value:"world3_nether",
                        label:"Delta Nether"
                    },
                    {
                        value:"world4_nether",
                        label:"Gamma Nether"
                    },
                    {
                        value:"omega_nether",
                        label:"Omega Nether"
                    },
                    {
                        value:"world_the_end",
                        label:"The End"
                    },
                    {
                        value:"custom",
                        label:"Custom"
                    },
                    {
                        value:"dev",
                        label:"Dev"
                    },
                    {
                        value:"outworld",
                        label:"Outworld"
                    }];

angular.module('banManager', ['ngRoute', 'ui.bootstrap'])
.factory('userCache', ['$cacheFactory', function($cacheFactory) {
    return $cacheFactory('userCache', {capacity:1});
}])
.factory('request', ['$http', function($http){
    return function(endpoint, payload) {
        return $http.post('ban-manager.php?action='+endpoint, payload);
    };
}])
.filter('checkmark', function() {
    return function(input) {
        return input == 1 ? '\u2713' : '\u2718';
    };
}).config(['$routeProvider', '$httpProvider', function($routeProvider, $httpProvider) {
    // Set up the routes
    $routeProvider
        .when('/', {
            controller:  'user',
            templateUrl: 'presentation/views/user.html'
        })
        .when('/user/:username', {
            controller:  'user',
            templateUrl: 'presentation/views/user.html'
        })
        .when('/bans', {
            controller:  'userList',
            templateUrl: 'presentation/views/userlist.html'
        })
        .when('/watchlist', {
            controller:  'userList',
            templateUrl: 'presentation/views/userlist.html'
        })
        .when('/search/:term?', {
            controller:  'search',
            templateUrl: 'presentation/views/search.html'
        })
        .otherwise({ redirectTo: '/' });

    // Set up the http request handler
    $httpProvider.interceptors.push(function(){
        return {
            'requestError' : function(rejection) {
                // TODO handle the error
                return rejection;
            },
            'response' : function(response) {
                if (response.data && response.data.error) {
                    // TODO handle errors
                    console.warn(response.data);
                }
                return response;
            },
            'responseError' : function(rejection) {
                // TODO handle errors
                console.warn(rejection);
                return rejection;
            }
        };
    });
}])
.controller('user', ['$scope', '$routeParams', 'userCache', 'request', function($scope, $routeParams, userCache, request) {
    // Create a function to set the data in the scope
    var setUser = function(data) {
        // Make sure we have the data
        if (data && data.user) {
            // Store a copy of the original data
            userCache.put(data.user.username, data);

            // Set the data into the scope
            $scope.user = data.user;
            $scope.incidents = data.incident;
            $scope.history = data.history;

            // Update the navigation to point to this user
            var navScope = angular.element(document.getElementById('manage_user')).scope();
            if (!navScope.data) {
                navScope.data = {};
            }
            navScope.data.username = data.user.username;
        }
    };
    
    // TODO
    $scope.worlds = bm.worlds;
    $scope.ranks = bm.ranks;
    
    // Button functions
    $scope.reset = function() {
        // Reload from the server
        request('lookup', {username: $routeParams.username})
                    .success(setUser);
    };
    $scope.saveIncident = function(incident) {
        request('update_incident', incident).success(function(data){
            console.log(data);
        });
    };
    $scope.saveUser = function(user) {
        request('update_user', user).success(function(data){
            console.log(data);
        });
    };
    
    // If we have a username, load it up
    if ($routeParams.username) {
        // See if this user is cached
        var cachedUser = userCache.get($routeParams.username);
        if (cachedUser) {
            setUser(cachedUser);
        } else {
            // Not cached, request the user from the server
            request('lookup', {username: $routeParams.username})
                    .success(setUser);
        }
    }
}]).controller('userList', ['$scope', '$location', '$http', function($scope, $location, $http) {
    var endpoint = $location.path() === '/bans' ? 'get_bans' : 'get_watchlist';
    $scope.lookupUser = function(username) {
        $location.path('/user/'+username);
    };
    $http.get("ban-manager.php?action=" + endpoint)
        .success(function(data) {
            $scope.users = data;
        });
}]).controller('search', ['$scope', '$routeParams', '$http', function($scope, $routeParams, $http) {
    console.log($routeParams);
}])
.controller('TypeaheadCtrl', ['$scope', '$location', 'request', function TypeaheadCtrl($scope, $location, request) {
    // Loads autocomplete terms via AJAX
    $scope.getLocation = function(val) {
        return request('auto_complete', {
              term: val
        }).then(function(res){
            return res.data;
        });
    };
    $scope.welcome = function() {
        $location.path('/user/'+$scope.selected);
    };
}]);