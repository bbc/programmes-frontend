/**
 * Snippets
 *
 * @module modules/snippet
 * @author Tom Franks <tom.franks@bbc.co.uk>
 * @author Stuart Wakefield <stuart.wakefield@bbc.co.uk>
 * @author Nicholas Angeli <nicholas.angeli@bbc.co.uk>
 */
define([
    './util/logging',
    './util/obj',
    './util/dom',
    './util/events',
    './driver/html5',
    './driver/smp',
    './playables',
    './i18n/locales',
    './animation/progress',
    './component/transform',
    './context',
    './stats',
    './storage/timer'
], function(logging, obj, dom, events, htmlDriver, smpDriver, playables, locales, progress, transform, context, stats, timerStorage) {

    "use strict";

    window.timerStorage = timerStorage;

    function Configuration(config) {
        config = obj.merge(Configuration.defaults, config || {});

        this.isStatsLoggingEnabled = function() {
            return config.istats_enabled;
        };

        this.isContinuousPlayEnabled = function() {
            return config.continuous;
        };

        this.isPauseEnabled = function() {
            return config.pause_enabled;
        };

        this.getBaseUrl = function() {
            return config.base_url;
        };

        this.getLocale = function() {
            return config.locale;
        };

        this.isUk = function() {
            return config.uk;
        };

        this.getContext = function() {
            return config.context;
        };

        this.getWaitingDetectionTimeout = function() {
            return config.waiting_detection_timeout;
        };

        this.getLoadingTimeout = function() {
            return config.loading_timeout;
        };

        this.getCounterName = function() {
            return config.counterName;
        };

        this.getAppVersion = function() {
            return config.appVersion;
        };

        this.getAppName = function() {
            return config.appName;
        };

        this.getBBCSite = function() {
            return config.bbcSite;
        };

        this.getTheme = function() {
            return config.theme;
        };

        this.getFullPlayback = function() {
            return config.fullPlayback;
        };

        this.getPlaylistType = function() {
            return config.playlistType;
        };


    }

    Configuration.defaults = {
        istats_enabled: true,
        continuous: false,
        //remember: false,
        pause_enabled: true,
        base_url: '/modules/snippet',
        locale: 'en',
        uk: null,
        context: null,
        loading_timeout: 10000,
        waiting_detection_timeout: 5000,
        counterName: null,
        appVersion: null,
        appName: null,
        bbcSite: null,
        theme: 'default',
        fullPlayback: false,
        playlistType: null
    };

    /**
     * @param playCounter
     * @param continuousPlayCounter
     * @param playTimer
     * @constructor
     */
    function Statistics(playCounter, continuousPlayCounter, playTimer) {

        /**
         * Returns the number of plays performed on this page
         * @returns {*}
         */
        this.getPlayCount = function() {
            return playCounter.getCount();
        };

        /**
         * Returns the number of continuous plays on this page
         * @returns {*}
         */
        this.getContinuousPlayCount = function() {
            return continuousPlayCounter.getCount();
        };

        /**
         * Returns the duration of the playback in milliseconds
         * @returns {*}
         */
        this.getPlayDuration = function() {
            return playTimer.getDuration();
        };

    }

    var snippetEvents = {
        'metaLoaded': true,
        'playbackLoading': true,
        'playbackStarted': true,
        'playbackPaused': true,
        'playbackResumed': true,
        'playbackEnded': true,
        'playbackStopped': true,
        'playbackError': true,
        'playbackWaiting': true,
        'skippedUnplayable': true,
        'endOfSnippets': true,
        'volumeChanged': true,
        'playbackProgressUpdate': true,
        'playbackNext': true,
        'playbackPrev': true
    };

    var inited = false;

    var requestAnimationFrame = (
        window.webkitRequestAnimationFrame ||
        window.mozRequestAnimationFrame ||
        window.oRequestAnimationFrame ||
        window.msRequestAnimationFrame ||
        function(callback) {
            window.setTimeout(callback, 1000 / 60);
        });

    var getContainingSnippet = function(elem) {

        if (!dom.matches(elem, '.spt-snippet')) {
            elem = dom.querySelectorParents(elem, '.spt-snippet');
        }

        return elem;
    };

    var getAllSnippetElements = function() {
        if (priv.config.getFullPlayback()) {
            return document.querySelectorAll('.full-track-wrapper .spt-snippet');
        } else {
            return document.querySelectorAll('.spt-snippet');
        }

    };

    var isSnippetPaused = function(snippetElement) {
        return dom.matches(snippetElement, '.is-paused');
    };

    var isSnippetPlaying = function(snippetElement) {
        return dom.matches(snippetElement, '.is-playing');
    };

    var isSnippetPlayable = function(snippetElement) {
        return !dom.matches(snippetElement, '.is-disabled');
    };

    var isSnippetLoading = function(snippetElement) {
        return dom.matches(snippetElement, '.is-loading');
    };

    // @TODO too much logic in event listeners

    // Event handler to start snippet playback.
    // Stops currently playing snippet.
    var selectSnippetListener = function(event) {

        var snippetElement = getContainingSnippet(event.target);

        if (snippetElement) {

            if (isSnippetPaused(snippetElement)) {
                if (priv.snippet.waiting) {
                    // Don't do anything SMP will resume when connectivity
                    // re-established just show something for the user's
                    // benefit.
                    showSnippetBuffering();
                }
                priv.resumeSnippet();

            } else if (isSnippetPlaying(snippetElement) && priv.config.isPauseEnabled()) {
                // Should pause instead
                priv.pauseSnippet();
            } else if (isSnippetPlaying(snippetElement)) {
                priv.stopSnippet(true);
            } else if (!isSnippetLoading(snippetElement) && isSnippetPlayable(snippetElement)) {
                // Set the current snippet and play the snippet.
                priv.setActiveSnippet(snippetElement);
                priv.playSnippet(true);
            }
        }

    };

    var showSnippetBuffering = function() {
        priv.snippet.waiting = false;
        priv.snippet.loading = true;
        priv.setSnippetVisualState('loading');
        priv.apiEvent('playbackLoading');
        priv.stopPlaybackAnimation();
    };

    var playerErrorListener = function(ev) {
        priv.logger.error('Error listener');
        if (!ev.severity || ev.severity == 'critical') {
            priv.snippetNetworkError();
            cancelWaitingDetectionTimeout();
            cancelLoadingTimeout();
        }
    };

    var playerWaitingListener = function(ev) {
        if (!priv.snippet.paused) {
            priv.logger.info('Waiting listener');

            priv.apiEvent('playbackWaiting');

            if (priv.snippet.playing) {
                priv.snippet.waiting = true;
                priv.snippet.playing = false;
                priv.setSnippetVisualState('paused');
                priv.pausePlaybackAnimation();
            }
        }
    };

    var playerLoadedListener = function() {
        priv.logger.info('Loaded listener');
        cancelWaitingDetectionTimeout();
    };

    var playerTimeUpdateListener = function() {

        if (priv.snippet.loading && priv.driver.getCurrentTime()) {
            // If playback is being resumed, do not change state until the desired playback time has been reached.
            if (priv.previousPlaybackState && priv.driver.getCurrentTime() < priv.previousPlaybackState.currentTime) {
                return;
            }

            priv.logger.info('Started listener');
            priv.snippet.loading = false;
            priv.snippet.playing = true;
            priv.snippet.duration = priv.driver.getDuration();
            priv.copySnippetMetaDataToApi();
            priv.setSnippetVisualState('playing');
            cancelLoadingTimeout();

            priv.startPlaybackAnimation();
            priv.setSnippetTooltip(priv.config.isPauseEnabled() ? 'Pause playback' : 'Stop playback');

            priv.statsLogger.logPlay();
            if (!priv.userPlayed)
                priv.statsLogger.logContinuousPlay();

            priv.statsLogger.logStartPlayback();

            priv.triggerIstats('playlister_snippet_playback' + (priv.userPlayed ? '' : '_continuous'));
            priv.apiEvent('playbackStarted');

        } else if (priv.snippet.waiting) {
            priv.snippet.waiting = false;
            priv.snippet.playing = true;
            priv.resumePlaybackAnimation();
            priv.apiEvent('playbackResumed');
            priv.setSnippetVisualState('playing');

        } else {
            priv.logger.info('Time update listener');

            triggerWaitingDetectionTimeout(priv.config.getWaitingDetectionTimeout());

            if (priv.snippet.duration === undefined) {
                priv.snippet.duration = priv.driver.getDuration();
            }
            priv.snippet.currentTime = priv.driver.getCurrentTime();
            priv.copySnippetMetaDataToApi();
            priv.apiEvent('playbackProgressUpdate');
            if (!priv.snippet.playing) {
                priv.updatePlaybackAnimation();
            }
        }
    };

    var playerEndedListener = function() {
        priv.logger.info('Ended listener');
        priv.stopSnippet(false);
        cancelWaitingDetectionTimeout();
    };

    var loadingTimeoutListener = function() {
        priv.logger.error('Timeout listener');
        priv.snippetError();
    };

    var loadingTimeout;

    var triggerLoadingTimeout = function(timeout) {
        if (loadingTimeout) {
            cancelLoadingTimeout();
        }
        loadingTimeout = setTimeout(loadingTimeoutListener, timeout);
    };

    var cancelLoadingTimeout = function() {
        clearTimeout(loadingTimeout);
        loadingTimeout = null;
    };

    // Required due to issues with SMP not announcing the waiting event
    // until after network connectivity has been restored. This timeout
    // detects the absence of expected timeupdate event and fakes up the
    // waiting event. This can be removed after SMP have resolved the
    // issue.
    var waitingDetectionTimeout = null;

    var triggerWaitingDetectionTimeout = function(timeout) {
        if (waitingDetectionTimeout) {
            cancelWaitingDetectionTimeout();
        }
        waitingDetectionTimeout = setTimeout(playerWaitingListener, timeout);
    };

    var cancelWaitingDetectionTimeout = function() {
        clearTimeout(waitingDetectionTimeout);
        waitingDetectionTimeout = null;
    };

    var playbackStateNeedsUpdating = function() {
        return !priv.previousPlaybackState || priv.previousPlaybackState.snippetId !== priv.snippet.id || priv.previousPlaybackState.currentTime < priv.snippet.currentTime;
    };

    function Snippet(elem, config) {

        this.element = elem;
        this.duration = config.duration;
        this.resource = config.resource;
        this.format = config.format;
        this.tooltip = config.tooltip;

        this.id = config.id;

        // TODO these properties are to be deprecated
        this.title = config.title;
        this.artist = config.artist;
        this.artistId = config.artistId;
        this.imageSrc = config.imageSrc;

        // TODO these properties are extrinsic, require representation for playlist
        this.position = config.index + 1;
        this.index = config.index;
        this.totalSnippets = config.count;

        // Audio play state, define state machine
        this.playable = !!this.resource;
        this.loading = false;
        this.playing = false;
        this.paused = false;
        this.waiting = false;
        this.currentTime = 0;

    }

    // @TODO objects that represent states for easy switching


    function EmptySnippet() {}

    var priv = {

        player: null, // Audio player.
        snippet: {}, // Current snippet.
        skipUnplayable: false, // Skips unplayable snippets when moving tracks.
        //volume : 1,                         // Volume for snippets playback.
        playbackAnimation: {}, // Audio playback animation settings.
        reporting: false, // Log events to console on sandbox.
        config: null,
        userPlayed: false,
        paused: false,
        istats: null,
        driver: null,
        logger: null,
        previousPlaybackState: null,
        playedSnippets: [],

        statsObject: null,

        init: function(opts, bump) {
            this.config = new Configuration(opts);
            var playCounter = new stats.StatsPlayCounter();
            var continuousPlayCounter = new stats.StatsPlayCounter();
            var playTimer = new stats.StatsPlayTimer();

            this.statsLogger = new stats.StatsLogger(playCounter, continuousPlayCounter, playTimer);
            this.stats = new Statistics(playCounter, continuousPlayCounter, playTimer);

            locales.setLocale(this.config.getLocale());

            this.geo = new context.UserGeo(this.config.isUk());
            this.context = new context.PlaybackContext(this.config.getContext());

            this.audioEnabled = this.isNativePlaybackSupported();
            this.playbackAnimation.enabled = this.isCanvasSupported();
            if (this.config.isContinuousPlayEnabled()) {
                this.skipUnplayable = true;
            }

            if (!opts.statsObject)
                console.warn('snippets/main but no statsObject');

            this.statsObject = opts.statsObject;
            priv.logger = priv.reporting ? new logging.Logger() : new logging.NoOpLogger();

            if (this.audioEnabled) {
                if (!inited) {
                    this.bindApi();
                    // If BUMP has been passed in then we can use the SMP
                    // player. Using dependency injection to resolve issues
                    // with missing page dependencies
                    // Since BUMP will not switch to HTML5 on desktop, we
                    // also check whether the flash plugin is present and
                    // switch to HTML5 ourselves if needed
                    if (bump) {
                        priv.driver = smpDriver;
                        var additionalOptions = Object.assign({
                            theme: this.config.getTheme(),
                        }, opts);
                        window.smpc = this.config;
                        priv.driver.init(bump, additionalOptions);
                        priv.driver.on('initialised', function(e) {
                            priv.bindUIEvents();
                            priv.setAllSnippetsStateEnabled();
                        });
                    } else {
                        console.warn('no bump. using native html player');
                        // Without BUMP we can only use the native HTML5 player
                        // to provide audio playback functionaliy
                        priv.driver = htmlDriver;
                        priv.driver.init();
                        priv.bindUIEvents();
                        priv.setAllSnippetsStateEnabled();
                    }
                }

                inited = true;
            }

            transform.process(opts.renderCallback);

            if (this.config.getFullPlayback()) {
                priv.fullPlayback = true;
                var playlistId = document.getElementsByClassName("plr-playlist-fulltrack")[0].getAttribute("data-playlist-id");
                timerStorage.checkIfShouldReset(playlistId);
                priv.updateCurrentSkipCount();
            } else {
                priv.fullPlayback = false;
            }

        },

        setAllSnippetsStateEnabled: function() {
            dom.addClass(document.body, 'spt-enabled');
        },

        setAllSnippetsStateDisabled: function() {
            dom.removeClass(document.body, 'spt-enabled');
        },

        destroy: function() {
            this.setAllSnippetsStateDisabled();
            if (inited) {
                this.stopSnippet(true);
                this.unbindApi();
                this.unbindUIEvents();

                if (this.audioEnabled)
                    priv.driver.destroy();
            }
            this.snippet = {};
            this.playbackAnimation = {};
            this.audioEnabled = null;
            this.config = null;
            inited = false;
        },

        /**
         * Log an item to the console.
         */
        log: function(item) {
            if (this.reporting) {
                console.log(item);
            }
        },

        /**
         * Snippets API.
         */
        bindApi: function() {
            window.bbcSnippets = {

                // Snippet meta data.
                snippet: {},

                // Snippet playback volume.
                volume: 1,
                functions: {

                    // Stop playback of currenty playing snippet.
                    stop: function() {
                        return priv.stopSnippet(true);
                    },

                    // Start playback of current snippet.
                    play: function(args) {
                        priv.snippet.skipped = false;
                        return priv.playSnippet(true, args);
                    },

                    pause: function() {
                        return priv.pauseSnippet();
                    },

                    resume: function() {
                        return priv.resumeSnippet();
                    },

                    // Move to next snippet.
                    next: function() {
                        if (priv.checkCanSkip()) {

                            if (priv.config.getFullPlayback()) {
                                var snippetThatWasSkipped = document.querySelector('li[data-record-id="'+ priv.snippet.id + '"]');
                                var newDiv = document.createElement("div");
                                newDiv.className = "skipOverlay";

                                var skippedTrackText = document.createElement("div");
                                skippedTrackText.innerHTML = "Skipped Track";
                                skippedTrackText.className = "skipOverlay-text";
                                newDiv.appendChild(skippedTrackText);
                                snippetThatWasSkipped.appendChild(newDiv);

                                priv.updateCurrentSkipCount();
                            }

                            return priv.moveToNextSnippet();

                        }
                    },

                    // Move to previous snippet.
                    prev: function() {
                        return priv.moveToPreviousSnippet();
                    },

                    /**
                     * Returns a boolean flag indicating whether the currently selected
                     * track is the first track. It will return false if there is no currently
                     * selected track.
                     * @returns {Boolean}
                     */
                    isFirst: function() {
                        return this.hasCurrentSnippet() && this.getCurrentSnippet().index === 0;
                    },

                    /**
                     * Returns a boolean flag indicating whether the currently selected
                     * track is the last track. It will return false if there is no currently
                     * selected track.
                     * @returns {Boolean}
                     */
                    isLast: function() {
                        return this.hasCurrentSnippet() && this.getCurrentSnippet().index === this.getSnippetCount() - 1;
                    },

                    /**
                     * Returns a boolean flag indicating whether the currently selected track
                     * is the last playable track, that is a track with a resource and format.
                     * It will return false if there is no currently selected track.
                     * @returns {Boolean}
                     */
                    isFirstPlayable: function() {
                        return this.hasCurrentSnippet() && this.getCurrentSnippet().index === priv.indexOfFirstPlayableSnippet();
                    },

                    /**
                     * Returns a boolean flag indicating whether the currently selected track
                     * is the last playable track, that is a track with a resource and format.
                     * It will return false if there is no currently selected track.
                     * @returns {Boolean}
                     */
                    isLastPlayable: function() {
                        return this.hasCurrentSnippet() && this.getCurrentSnippet().index === priv.indexOfLastPlayableSnippet();
                    },

                    /**
                     * Skips forward to the next track, if there is a track currently selected
                     * and it is not the last playable track. It will do nothing otherwise. The
                     * next track will automatically play if currently playing.
                     */
                    skipForward: function() {
                        if (this.hasCurrentSnippet() && !this.isLastPlayable()) {
                            var snippet = this.getCurrentSnippet();

                            var play = snippet.playing || snippet.loading;

                            this.next();

                            if (play)
                                this.play();

                            priv.triggerIstats('playlister_snippet_skipped_forward');
                        }
                    },

                    skipBackward: function() {
                        var snippet = this.getCurrentSnippet();
                        var play = snippet.playing || snippet.loading;

                        if (this.isFirstPlayable() || snippet.currentTime > 2) {
                            priv.seekTo(0);
                            priv.triggerIstats('playlister_snippet_skipped_to_beginning');

                        } else {
                            this.prev();

                            if (play)
                                this.play();

                            priv.triggerIstats('playlister_snippet_skipped_backward');
                        }
                    },

                    // Move to first snippet.
                    first: function() {
                        return priv.moveToSnippet(0);
                    },

                    // Move to last snippet.
                    last: function() {
                        return priv.moveToSnippet(getAllSnippetElements().length - 1);
                    },

                    // Move to first playable snippet.
                    firstPlayable: function() {
                        return priv.moveToFirstPlayableSnippet();
                    },

                    // Move to last playable snippet.
                    lastPlayable: function() {
                        return priv.moveToLastPlayableSnippet();
                    },

                    // Sets whether to skip unplayable snippets (automated playback)
                    skipUnplayable: function(args) {
                        return priv.setSkipUnplayable(args);
                    },

                    // Sets the player volume.
                    volume: function(args) {
                        return priv.setVolume(args);
                    },

                    // Returns Snippets meta data.
                    /** @deprecated please use getCurrentSnippet */
                    getMetaData: function() {
                        return window.bbcSnippets ? window.bbcSnippets : false;
                    },

                    // Returns all snippets in the DOM.
                    /** @deprecated please use listSnippets */
                    getSnippets: function() {
                        return priv.getAllSnippets();
                    },

                    // Like getSnippets but always returns an array
                    listSnippets: function() {
                        return priv.listSnippets();
                    },

                    getSnippetCount: function() {
                        return this.listSnippets().length;
                    },

                    getCurrentSnippet: function() {
                        return this.getMetaData().snippet;
                    },

                    hasCurrentSnippet: function() {
                        return !obj.isEmpty(this.getCurrentSnippet());
                    }
                }
            };

        },

        unbindApi: function() {

            // Clear callbacks
            for (var eventName in snippetEvents) {
                events.off(snippetEvents[eventName]);
            }

            window.bbcSnippets = null;
        },

        /**
         * Returns all of the Snippets from the DOM.
         * @deprecated
         * @return {Object[]|Boolean}
         */
        getAllSnippets: function() {
            var domSnippets = getAllSnippetElements(),
                snippets = [];
            if (domSnippets.length > 0) {
                for (var i = 0, i_len = domSnippets.length; i < i_len; i++) {
                    snippets[i] = this.getSnippetMeta(domSnippets[i]);
                }
            }
            return snippets.length ? snippets : false;
        },

        /**
         * Returns snippet meta for all snippets on the current page.
         * Like getAllSnippets, however, will always return an array,
         * if no snippets have been found the array will be empty.
         * @returns {Object[]}
         */
        listSnippets: function() {
            var elems = getAllSnippetElements(),
                result = [],
                i = 0,
                l = elems.length;

            for (; i < l; i++)
                result.push(this.getSnippetMeta(elems[i]));

            return result;
        },

        /**
         * Identifies support for native browser MP3 playback.
         */
        isNativePlaybackSupported: function() {
            return document.createElement("audio").canPlayType && document.createElement("audio").canPlayType("audio/mpeg");
        },

        /**
         * Identifies whether HTML5 Canvas is supported.
         */
        isCanvasSupported: function() {
            return !!window.CanvasRenderingContext2D;
        },

        indexOfFirstPlayableSnippet: function() {
            return this.indexOfNextPlayableSnippet(-1);
        },

        indexOfLastPlayableSnippet: function() {
            return this.indexOfPreviousPlayableSnippet(Infinity);
        },

        indexOfNextPlayableSnippet: function(i) {
            var elems = getAllSnippetElements();
            var l = elems.length;
            i = Math.max(0, i + 1);

            for (; i < l; i++) {
                if (this.getSnippetMeta(elems[i]).playable) {
                    return i;
                }
            }

            return -1;
        },

        indexOfPreviousPlayableSnippet: function(i) {
            var elems = getAllSnippetElements();
            var l = elems.length;
            i = Math.max(0, l - 1 - i + 1);

            for (; i < l; i++) {
                var j = l - 1 - i;
                if (this.getSnippetMeta(elems[j]).playable) {
                    return j;
                }
            }

            return -1;
        },

        /**
         * Moves to the first playable Snippet in the DOM.
         */
        moveToFirstPlayableSnippet: function() {
            if (this.config.getFullPlayback()) {
                this.setActiveSnippet(this.getSnippetRandomlyFromAllSnippets());
                return true;
            } else {
                var i = this.indexOfFirstPlayableSnippet();
                if (i !== -1) {
                    var elems = getAllSnippetElements();
                    this.setActiveSnippet(elems[i]);
                    return true;
                }
                return false;
            }
        },

        /**
         * Moves to the final playable Snippet found in the DOM.
         */
        moveToLastPlayableSnippet: function() {
            var i = this.indexOfLastPlayableSnippet();
            if (i !== -1) {
                var elems = getAllSnippetElements();
                this.setActiveSnippet(elems[i]);
                return true;
            }

            return false;
        },

        /**
         * Skip unplayable tracks when playback is automated.
         */
        setSkipUnplayable: function(skip) {
            if (typeof skip === 'boolean') {
                this.skipUnplayable = skip;
                return true;
            } else {
                return false;
            }
        },

        /**
         * Set the playback volume.
         */
        setVolume: function(volume) {
            if (typeof volume === 'number') {
                window.bbcSnippets.volume = volume;
                this.volume = volume;
                // Adjust volume of current playback.

                if (this.audioEnabled)
                    priv.driver.setVolume(this.volume);

                this.apiEvent('volumeChanged');
                return true;
            } else {
                return false;
            }
        },

        seekTo: function(time) {
            if (this.audioEnabled)
                priv.driver.setCurrentTime(time);
        },

        /**
         * Move to a Snippet specified by an index.
         */
        moveToSnippet: function(position) {
            var snippets = getAllSnippetElements();
            var index = position < 0 ? snippets.length + position : position;
            var snippet = snippets[index];
            if (snippet) {
                this.setActiveSnippet(snippet);
                return true;
            } else {
                return false;
            }
        },

        /**
         * Move to a Snippet x away from startTimercurrent Snippet.
         */
        getSnippetByOffsetFromCurrentSnippet: function(offset) {
            var self = this,
                snippets = getAllSnippetElements(),
                index = dom.indexOf(snippets, self.snippet.element);

            if (index + offset < 0) {
                return false;
            }
            var snippet = snippets[index + offset];

            return snippet || false;
        },

        /**
         * Move to a Snippet randomly in the list of snippets in the DOM.
         */
        getSnippetRandomlyFromAllSnippets: function() {

            var self = this,
                snippets = getAllSnippetElements();

            var snippet = snippets[Math.floor(Math.random() * snippets.length)];

            return snippet || false;
        },

        /**
         * Moves to the next Snippet found in the DOM from current Snippet position.
         */
        moveToNextSnippet: function() {
            this.apiEvent('playbackNext');
            var self = this;

            self.stopSnippet(true);

            // need to select random snippet to play instead of taking taking snippet in dom.
            if (this.config.getFullPlayback()) {
                var snippet = self.getSnippetRandomlyFromAllSnippets();
            } else {
                var snippet = self.getSnippetByOffsetFromCurrentSnippet(1);
            }

            if (snippet) {
                self.setActiveSnippet(snippet);

                if (self.skipUnplayable && !self.snippet.playable) {
                    self.apiEvent('skippedUnplayable');
                    return self.moveToNextSnippet();
                } else {
                    return true;
                }
            } else {
                self.apiEvent('endOfSnippets');
                return false;
            }
        },

        /**
         * Checks the localstroage for skip ability of playlist
         */
        checkCanSkip: function() {
            if (this.config.getFullPlayback()) {
                if ( timerStorage.canSkip(document.getElementsByClassName("plr-playlist-fulltrack")[0].getAttribute("data-playlist-id")) ) {
                this.enableSkipButton();
                return true;
                } else {
                    this.disableSkipButton();
                    this.showOnwardJourney();
                    return false;
                }
            } else {
                return true;
            }

        },

        /**
        * Get the current skip count from the local storage
        */

        getCurrentSkipCount: function() {
            var playlistId = document.getElementsByClassName("plr-playlist-fulltrack")[0].getAttribute("data-playlist-id");
            return timerStorage.getCount(playlistId);
        },

        updateCurrentSkipCount: function() {
            document.getElementById('remainingSkipCount').innerHTML = (timerStorage.getSkipLimit() - this.getCurrentSkipCount());
        },

        /**
         * Disables the skip button on the browser
         */
        disableSkipButton: function() {
            document.getElementsByClassName('spt-ply-next')[0].style.cursor = 'not-allowed';
            if ( document.getElementsByClassName('spt-ply-next')[0].className.indexOf('deactivated') == -1 ) {
                document.getElementsByClassName('spt-ply-next')[0].className += ' deactivated'
            }
        },

        showOnwardJourney: function() {
            var overlay = document.getElementById('skipWarning');
            overlay.style.opacity = 1;
            setTimeout(function() {
                overlay.style.opacity = 0;
            }, 4000);
        },

        /**
         * Enables the skip button on the browser
         */
        enableSkipButton: function() {
            document.getElementsByClassName('spt-ply-next')[0].style.opacity = 1;
            document.getElementsByClassName('spt-ply-next')[0].style.cursor = 'default';
            document.getElementsByClassName('spt-ply-next')[0].disabled = false;
            priv.logger.error('Set skip button to enabled');
        },

        /**
         * Updates the image at the top of the playlist
         */
        updateImage: function(imageUrl) {
            if (imageUrl == '') {
                // this should really be a local asset
                imageUrl = "http://static.test.bbci.co.uk/playlister/393/1.8.32.393/img/playlists/default-playlist_2x.png";
            }
        },

        /**
         * Moves to the previous Snippet found in the DOM from current Snippet posiiton.
         */
        moveToPreviousSnippet: function() {
            this.apiEvent('playbackPrev');
            var self = this;

            self.stopSnippet(true);
            var snippet = self.getSnippetByOffsetFromCurrentSnippet(-1);

            if (snippet) {
                self.setActiveSnippet(snippet);
                if (self.skipUnplayable && !self.snippet.playable) {
                    self.apiEvent('skippedUnplayable');
                    return self.moveToPreviousSnippet();
                } else {
                    return true;
                }
            } else {
                self.apiEvent('endOfSnippets');
                return false;
            }
        },

        /**
         * Bind Snippet buttons user interaction
         */
        bindUIEvents: function() {
            dom.addEventListener(document.body, 'click', selectSnippetListener);
        },

        unbindUIEvents: function() {
            dom.removeEventListener(document.body, 'click', selectSnippetListener);
        },

        /**
         * Parses a specified Snippet and sets it to be the current Snippet.
         */
        setActiveSnippet: function(element) {
            this.stopSnippet(true);
            this.snippet = this.getSnippetMeta(element);
            this.copySnippetMetaDataToApi();
            this.apiEvent('metaLoaded');
            priv.logger.info('Snippet loaded:');
            priv.logger.info(this.snippet);
        },

        /**
         * Updates Snippets API to have the current Snippet.
         */
        copySnippetMetaDataToApi: function() {
            window.bbcSnippets.snippet = this.snippet;
        },

        /**
         * Starts Snippet playback progress animation.
         */
        startPlaybackAnimation: function() {
            progress.start(window.bbcSnippets.snippet);
        },

        pausePlaybackAnimation: function() {
            progress.pause();
        },

        resumePlaybackAnimation: function() {
            progress.resume();
        },

        updatePlaybackAnimation: function() {
            progress.update();
        },

        /**
         * Stops Snippet playback.
         */
        stopPlaybackAnimation: function() {
            progress.stop();
        },

        /**
         * Called to fire a custom istats label.
         * @param label - custom istats label
         */
        triggerIstats: function(label) {
            var self = this;
            if (this.config.isStatsLoggingEnabled()) {

                var data = {
                    playlist_type: this.config.getPlaylistType(),
                    record_id: self.snippet.id,
                    uk: this.config.isUk(), // Whether the user is identified as UK / non-UK
                    context: this.config.getContext(), // The playback context used
                    play_count: this.stats.getPlayCount(), // Number of tracks played
                    continuous_play_count: this.stats.getContinuousPlayCount(), // Number of tracks played from continuous (passive)
                    play_duration: Math.floor(this.stats.getPlayDuration() / 1000), // Amount of audio consumed on this page in seconds
                    source_type: this.snippet.format //,                              // MP3 / clip
                        //audio_type: null                                               // Full / snippet
                };

                priv.logger.info('iStats action: ' + label);
                if (priv.istats) {
                    priv.istats.log("click", label, data);
                }
            }
        },

        /**
         * Sets the tooltip title for the Snippet.
         */
        setSnippetTooltip: function(title) {
            if (title && priv.snippet.element) {
                var button = priv.snippet.element.querySelector('.spt-button');
                if (button) {
                    button.title = locales.t(title);
                }
            }
            return this;
        },

        resetSnippetTooltip: function() {
            if (priv.snippet.element && priv.snippet.tooltip) {
                var button = priv.snippet.element.querySelector('.spt-button');
                if (button) {
                    button.title = priv.snippet.tooltip;
                }
            }
            return this;
        },

        /**
         * Starts Snippet playback.
         */
        playSnippet: function(userPlayed, playSnippet) {
            var self = this;

            priv.userPlayed = userPlayed;

            // Load a specific snippet and play it.
            if (playSnippet !== undefined) {
                self.setActiveSnippet(playSnippet.element);
            }

            // Do not try and play the same snippet again if already playing.
            if (!obj.isEmpty(this.snippet) && this.snippet.playing) {
                return false;
            }

            var snippet = this.snippet;

            // Native browser playback.
            if (this.audioEnabled && !obj.isEmpty(snippet) && snippet.playable && !snippet.playing) {
                snippet.loading = true;
                this.setSnippetVisualState('loading');
                this.apiEvent('playbackLoading');

                // Check for clip protocol in resource
                var playable = '';
                if (snippet.format === 'clip') {
                    playable = new playables.ClipAudioSource(snippet.resource);
                } else {
                    playable = new playables.UrlAudioSource(snippet.resource);
                }

                var startTime = undefined;
                // If playback is being restarted after a network error, start from the previous point.
                if (priv.previousPlaybackState && priv.previousPlaybackState.snippetId === snippet.id) {
                    startTime = priv.previousPlaybackState.currentTime;
                }

                var playlistOpts = {
                    title: [
                        snippet.artist,
                        snippet.title
                    ]
                        .filter(Boolean)
                        .join('-')
                        .replace(/[^A-Z0-9_\-]/gi, '_')
                        .substr(0, 60)
                };

                priv.driver.play(playable, startTime, playlistOpts, {
                    // the statsObject needs passing again with every new playlist
                    // undocumented in Echo - attaches to end of query becoming "media name"
                    statsObject: Object.assign({}, self.statsObject, {
                        episodePID: snippet.id,
                    })
                });

                if (this.config.getFullPlayback()) {
                    this.updateImage(snippet.imageSrc);
                    priv.playedSnippets.push(snippet);

                    document.getElementsByClassName("playbackhistory-wrapper")[0].className = "playbackhistory-wrapper";

                    var snippetToMove = document.querySelector('li[data-record-id="'+ snippet.id + '"]');

                    window.addtoPlaybackHistory(snippetToMove);
                    document.title = snippet.artist + ' - ' + snippet.title + ' - BBC Music';
                }

                // Add player event handlers.
                priv.driver.on("error", playerErrorListener);

                // Overwrite specified snippet duration with more accurate duration from player.
                priv.driver.on("loadedmetadata", playerLoadedListener);

                // Use the timeupdate event to help verify when actual device audio playback has commenced.
                priv.driver.on("timeupdate", playerTimeUpdateListener);

                // Snippet playback has finished.
                priv.driver.on("ended", playerEndedListener);

                priv.driver.on("waiting", playerWaitingListener);

                triggerLoadingTimeout(priv.config.getLoadingTimeout());

                return true;
            } else {
                this.snippetError();
                return false;
            }
        },

        pauseSnippet: function() {
            if (priv.snippet.playing) {
                priv.driver.pause();
                priv.snippet.playing = false;
                priv.snippet.paused = true;

                // Reset any previously stored playback position.
                priv.previousPlaybackState = undefined;

                priv.pausePlaybackAnimation();
                priv.setSnippetVisualState('paused');
                priv.setSnippetTooltip('Resume playback');
                priv.apiEvent('playbackPaused');
                priv.triggerIstats('playlister_snippet_paused');
                priv.logger.info('Snippet paused');
                return true;
            } else {
                return false;
            }
        },

        resumeSnippet: function() {
            if (priv.snippet.paused) {
                // If playback is being resumed after a network error, restart from the previous point.
                if (priv.previousPlaybackState) {
                    var startTime = priv.previousPlaybackState.currentTime;
                    var playable = '';
                    if (priv.snippet.format == 'clip') {
                        playable = new playables.ClipAudioSource(priv.snippet.resource);
                    } else {
                        playable = new playables.UrlAudioSource(priv.snippet.resource);
                    }

                    priv.driver.play(playable, startTime);
                    priv.snippet.loading = true;

                    priv.stopPlaybackAnimation();
                    priv.setSnippetVisualState('loading');
                    priv.resetSnippetTooltip();
                } else {
                    priv.driver.resume();
                    priv.snippet.playing = true;
                    priv.snippet.paused = false;

                    priv.resumePlaybackAnimation();
                    priv.setSnippetVisualState('playing');
                    priv.setSnippetTooltip(priv.config.isPauseEnabled() ? 'Pause playback' : 'Stop playback');
                    priv.apiEvent('playbackResumed');
                }

                priv.triggerIstats('playlister_snippet_resumed');
                priv.logger.info('Snippet resumed');
                return true;
            } else {
                return false;
            }
        },

        /**
         * Snippet error event. Update Snippet button and stop any playback.
         */
        snippetError: function() {
            this.setSnippetVisualState('error');
            this.apiEvent('playbackError');

            this.stopSnippet(false, true);
            priv.logger.error('Snippet error!');
        },

        /**
         * Snippet network error event. Update Snippet button and pause playback, recording the current snippet playback state.
         */
        snippetNetworkError: function() {
            this.snippet.playing = true;
            this.snippet.waiting = false;

            this.apiEvent('playbackError');
            this.pauseSnippet();

            // If playback fails again while there is no network connection, we need to recreate the progress bar
            // on the paused button, since it may have been removed when attempting to restart the snippet.
            priv.startPlaybackAnimation();
            priv.pausePlaybackAnimation();

            if (playbackStateNeedsUpdating()) {
                priv.previousPlaybackState = {
                    snippetId: priv.snippet.id,
                    currentTime: priv.snippet.currentTime
                };
            }

            priv.logger.error('Snippet network error!');
        },

        /**
         * Triggers a Snippet API event.
         */
        apiEvent: function(eventName) {
            events.emit(snippetEvents, eventName, {
                type: eventName,
                target: window.bbcSnippets
            });
            priv.logger.info('API event triggered: ' + eventName);
        },

        /**
         * Stops playback of the Snippet.
         * userStopped : true (Playback stopped manually by the user).
         * userStopped : false (Playback stopped by reaching the end of the audio).
         * errorStopped : true (Playback stopped due to a player error event).
         */
        stopSnippet: function(userStopped, errorStopped) {

            if (obj.isEmpty(priv.snippet))
                return false;

            priv.statsLogger.logStopPlayback();

            priv.driver.stop();

            this.stopPlaybackAnimation();
            this.setSnippetVisualState('stopped');

            // Clean up event listeners
            priv.driver.off("error", playerErrorListener);
            priv.driver.off("loadedmetadata", playerLoadedListener);
            priv.driver.off("timeupdate", playerTimeUpdateListener);
            priv.driver.off("ended", playerEndedListener);
            priv.driver.off("waiting", playerWaitingListener);

            this.snippet.loading = false;
            this.snippet.playing = false;
            this.snippet.paused = false;
            this.snippet.currentTime = 0;

            this.copySnippetMetaDataToApi();
            this.resetSnippetTooltip();

            // User manually stopped playback.
            if (userStopped) {
                this.triggerIstats('playlister_snippet_stopped');
                this.apiEvent('playbackStopped');
            } else if (!errorStopped) {
                this.apiEvent('playbackEnded');
                if (this.config.isContinuousPlayEnabled()) {
                    if (this.moveToNextSnippet()) {
                        this.playSnippet(false);
                    }
                }
            }

            // Reset any previously stored playback position if this is not an error.
            if (!errorStopped) {
                priv.previousPlaybackState = undefined;
            }

            return true;
        },

        /**
         * Decrypts Snippet resource URL.
         */
        decode: function(url) {
            var e = {},
                i, b = 0,
                c, x, l = 0,
                a, r = '',
                w = String.fromCharCode,
                L = url.length,
                tmp = '';
            var A = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
            for (i = 0; i < 64; i++) {
                e[A.charAt(i)] = i;
            }
            for (x = 0; x < L; x++) {
                c = e[url.charAt(x)];
                b = (b << 6) + c;
                l += 6;
                while (l >= 8) {
                    tmp = ((a = (b >>> (l -= 8)) & 0xff) || (x < (L - 2))) && (r += w(a));
                }
            }
            return r;
        },

        /**
         * Retuns an object of snippet meta data for a given snippet element.
         * @param {HTMLElement} snippet
         * @returns {meta}
         */
        getSnippetMeta: function(snippet) {
            if (snippet && snippet.getAttribute) {
                var snippets = getAllSnippetElements();

                // Store original tooltip
                var tooltip = '';
                var button = snippet.querySelector('.spt-button');
                if (button) {
                    tooltip = button.title;
                }

                var data = {
                    duration: dom.getData(snippet, 'duration', 30),
                    resource: this.decode(dom.getData(snippet, 'resource', '')),
                    format: dom.getData(snippet, 'format'),
                    id: dom.getData(snippet, 'id'),
                    title: dom.getData(snippet, 'title'),
                    artist: dom.getData(snippet, 'artist'),
                    artistId: dom.getData(snippet, 'artist-id'),
                    imageSrc: dom.getData(snippet, 'image-src'),
                    index: dom.indexOf(snippets, snippet),
                    tooltip: tooltip,
                    count: snippets.length
                };
                return new Snippet(snippet, data);

            }
            return new EmptySnippet();
        },

        /**
         * Updates the visual appearance of the current Snippet dependant upon playback state.
         * @param {String} state
         * @returns {priv}
         */
        setSnippetVisualState: function(state) {
            var snippetElement = this.snippet.element;


            if (snippetElement) {
                var button = snippetElement.querySelector('.spt-button');
                switch (state) {
                    case 'loading':
                        dom.removeClass(snippetElement, 'is-playing has-error is-paused can-pause');
                        button && dom.removeClass(button, 'gelicon gelicon--alert');
                        dom.addClass(snippetElement, 'is-loading');
                        break;
                    case 'playing':
                        dom.removeClass(snippetElement, 'is-loading has-error is-paused');
                        button && dom.removeClass(button, 'gelicon gelicon--alert');
                        dom.addClass(snippetElement, 'is-playing');
                        if (priv.config.isPauseEnabled()) {
                            dom.addClass(snippetElement, 'can-pause');
                        }
                        break;
                    case 'paused':
                        dom.removeClass(snippetElement, 'is-loading has-error is-playing can-pause');
                        button && dom.removeClass(button, 'gelicon gelicon--alert');
                        dom.addClass(snippetElement, 'is-paused');
                        break;
                    case 'stopped':
                        dom.removeClass(snippetElement, 'is-loading is-playing is-paused can-pause');
                        button && dom.removeClass(button, 'gelicon gelicon--alert');
                        break;
                    case 'error':
                        dom.removeClass(snippetElement, 'is-loading is-playing is-paused can-pause');
                        button && dom.addClass(button, 'gelicon gelicon--alert');
                        break;
                    default:
                        break;
                }
            }

            priv.logger.info('Snippet visual state changed: ' + state);

            return this;
        }
    };

    var snippets = {

        /**
         * Initialise snippets functionality
         * @param {Object} opts
         * @returns {snippets}
         */
        init: function(opts, bump, istats) {
            priv.init(opts, bump, istats);
            return this;
        },

        create: function(ids, callback) {
            transform.request(ids, callback);
            return this;
        },

        /**
         * Listen to API events.
         * @param {String} event
         * @param {Function} callback
         * @returns {snippets}
         */
        on: function(eventName, listener) {
            events.on(snippetEvents, eventName, listener);
            return this;
        },

        /**
         * Stop listening to API events
         * @param {String} event
         * @param {Function} callback
         * @returns {snippets}
         */
        off: function(eventName, listener) {
            events.off(snippetEvents, eventName, listener);
            return this;
        },

        /**
         * Control Snippets API.
         * @param {String} command
         * @param {*} args
         * @returns {*}
         */
        cmd: function(command, args) {
            if (window.bbcSnippets) {
                if (typeof window.bbcSnippets.functions[command] === 'function') {
                    return window.bbcSnippets.functions[command](args);
                }
            }
        },

        /**
         * Returns the configuration settings object or null if not initialised
         * @returns Configuration|null
         */
        getConfig: function() {
            return priv.config;
        },

        /**
         * Returns the statistics object or null if not initialised
         * @returns stats.Statistics|null
         */
        getStats: function() {
            return priv.stats;
        },

        /**
         * Cleans up snippets
         * @returns {snippets}
         */
        destroy: function() {
            priv.destroy();
            return this;
        }
    };

    return snippets;
});
