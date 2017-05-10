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

    angular.module('banManager').directive('bmPlayer', Player);

    Player.$inject = ['$log', 'Settings', 'message'];
    function Player($log, Settings, message) {
        return {
            scope: {
                player: '=',
                adding: '=',
                form: '=?'
            },
            restrict: 'E',
            templateUrl: 'presentation/src/player/player.html',
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
                    playerImage = 'https://crafatar.com/avatars/' + scope.player.uuid + '?helm=1&size=64';
                }
                scope.playerImage = playerImage;
            }
        };
    }
})();
