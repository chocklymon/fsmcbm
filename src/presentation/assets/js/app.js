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
angular.module('banManager', ['ngRoute']).filter('checkmark', function() {
  return function(input) {
    return input ? '\u2713' : '\u2718';
  };
}).config(['$routeProvider', function($routeProvider) {
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
}]).controller('user', ['$scope', '$routeParams', '$http', function($scope, $routeParams, $http) {
    // TODO
    $scope.incidents = [{
        id:5,
        moderator:'Nefyoni',
        dateCreated:'2013-05-29',
        dateModified:'2013-07-29',
        type:'destruction',
        date:'2013-05-28',
        notes:'Something bad happened',
        actionTaken:'Repaired the damage'
    }];
    if ($routeParams.username) {
        $http.post("ban-manager.php?action=lookup", {username: $routeParams.username})
            .success(function(data) {
                console.log(data);
                if (data.incident) {
                    $scope.incidents = data.incident;
                }
                if (data.history) {
                    $scope.history = data.history;
                }
            });
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
}]);
