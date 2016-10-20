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

    /**
     * Current selected player factory.
     *
     * Provides a cache for the currently managed user.
     */
    angular.module('banManager').factory('Player', Player);

    Player.$inject = ['request', 'CachedRequest'];
    function Player(request, CachedRequest) {
        var cachedPlayer = null;

        return {
            add: addPlayer,
            get: getPlayer,
            getUUID: getUUID,
            save: saveCurrentPlayer
        };

        /**
         * Returns a promise the resolves when the player is added.
         * @param player
         * @returns {Promise}
         */
        function addPlayer(player) {
            return request('add_user', player)
                .then(function(response) {
                    // Response is the user_id on success
                    // TODO ?
                    return response;
                });
        }

        /**
         * Return a promise that returns the requested user.
         * @param uuid The player UUID
         * @param force If the player should be force loaded from the server.
         * @returns {Promise}
         */
        function getPlayer(uuid, force) {
            if (force || !matches(uuid)) {
                // Reset the cache, different user requested or being forced
                CachedRequest.reset('lookup');
            }

            return CachedRequest.get('lookup', { 'uuid': uuid })
                .then(function(player) {
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
        }

        /**
         * Get the current user's universally unique identifier.
         * @returns {object} The current user's UUID, or null.
         */
        function getUUID() {
            if (cachedPlayer) {
                return cachedPlayer.user.uuid;
            } else {
                return null;
            }
        }

        /**
         * Get if the provided UUID matches the cached user.
         * @param uuid The UUID to check against.
         * @returns {boolean} True if the UUID matches.
         */
        function matches(uuid) {
            return uuid === getUUID();
        }

        /**
         * Saves the user to the database.
         * @returns {Promise}
         */
        function saveCurrentPlayer() {
            return request('update_user', cachedPlayer.user)
                .then(function(data) {
                    // TODO messaging?
                    return data;
                });
        }
    }
})();
