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

    angular.module('banManager').controller('NavigationController', NavigationController);

    NavigationController.$inject = [
        '$scope', '$location', 'Player', 'CurrentSearch', '$uibModal', 'authService', 'goToUser'
    ];
    function NavigationController($scope, $location, Player, CurrentSearch, $uibModal, authService, goToUser) {
        $scope.authService = authService;

        $scope.tabs = [
            { link: 'user', label: 'Manage' },
            { link: 'bans', label: 'Bans' },
            { link: 'watchlist', label: 'Watchlist' },
            { link: 'search', label: 'Search' }
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
                templateUrl: 'presentation/src/player/add-player.html',
                controller: 'AddUserController',
                size: 'lg'
            });
        };
        $scope.addIncident = function() {
            $uibModal.open({
                templateUrl: 'presentation/src/incident/add-incident.html',
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
    }
})();
