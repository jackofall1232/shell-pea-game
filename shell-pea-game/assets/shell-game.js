/**
 * Shell Pea Game front-end logic.
 *
 * Each .spg-game element on the page is bootstrapped into an independent
 * game instance. State is kept on a closure so multiple shortcodes on the
 * same page do not collide.
 */
(function () {
	'use strict';

	var GLOBAL_DEFAULTS = (typeof window.SPGData === 'object' && window.SPGData) ? window.SPGData : {
		currency_name: 'Coins',
		starting_bank: 100,
		shuffle_speed: 'medium'
	};

	var SPEED_TABLE = {
		slow:   600,
		medium: 350,
		fast:   180
	};

	var HIGH_SCORE_KEY = 'spg_high_score_v1';

	function clampBank(n) {
		n = parseInt(n, 10) || 0;
		if (n < 50) { n = 50; }
		if (n > 10000) { n = 10000; }
		return n;
	}

	function sanitizeSpeed(s) {
		s = String(s || '').toLowerCase();
		return SPEED_TABLE.hasOwnProperty(s) ? s : 'medium';
	}

	function readHighScore() {
		try {
			var v = window.localStorage.getItem(HIGH_SCORE_KEY);
			var n = parseInt(v, 10);
			return isNaN(n) || n < 0 ? 0 : n;
		} catch (e) {
			return 0;
		}
	}

	function writeHighScore(n) {
		try {
			window.localStorage.setItem(HIGH_SCORE_KEY, String(n));
		} catch (e) {
			/* localStorage might be disabled; ignore. */
		}
	}

	function randInt(min, max) {
		return Math.floor(Math.random() * (max - min + 1)) + min;
	}

	function pickTwoDistinct(max) {
		var a = randInt(0, max - 1);
		var b = randInt(0, max - 1);
		while (b === a) {
			b = randInt(0, max - 1);
		}
		return [a, b];
	}

	/**
	 * Lightweight Web Audio API sound bank — every sound is synthesized at
	 * play time so the plugin ships with no audio files.
	 */
	function createSoundBank() {
		var ctx = null;
		var muted = false;

		function ensure() {
			if (ctx) { return ctx; }
			var Ctor = window.AudioContext || window.webkitAudioContext;
			if (!Ctor) { return null; }
			try {
				ctx = new Ctor();
			} catch (e) {
				ctx = null;
			}
			return ctx;
		}

		function envelope(node, attack, sustain, release, peak) {
			var c = ensure();
			if (!c) { return; }
			var t = c.currentTime;
			node.gain.cancelScheduledValues(t);
			node.gain.setValueAtTime(0.0001, t);
			node.gain.exponentialRampToValueAtTime(peak, t + attack);
			node.gain.setValueAtTime(peak, t + attack + sustain);
			node.gain.exponentialRampToValueAtTime(0.0001, t + attack + sustain + release);
		}

		function tone(opts) {
			if (muted) { return; }
			var c = ensure();
			if (!c) { return; }
			var osc = c.createOscillator();
			var gain = c.createGain();
			osc.type = opts.type || 'sine';
			osc.frequency.setValueAtTime(opts.start, c.currentTime);
			if (typeof opts.end === 'number') {
				osc.frequency.exponentialRampToValueAtTime(opts.end, c.currentTime + (opts.glide || 0.2));
			}
			osc.connect(gain).connect(c.destination);
			envelope(gain, opts.attack || 0.005, opts.sustain || 0.05, opts.release || 0.15, opts.peak || 0.2);
			osc.start();
			osc.stop(c.currentTime + (opts.attack || 0.005) + (opts.sustain || 0.05) + (opts.release || 0.15) + 0.05);
		}

		function noiseBurst(duration, peak) {
			if (muted) { return; }
			var c = ensure();
			if (!c) { return; }
			var bufferSize = Math.max(1, Math.floor(c.sampleRate * duration));
			var buffer = c.createBuffer(1, bufferSize, c.sampleRate);
			var data = buffer.getChannelData(0);
			for (var i = 0; i < bufferSize; i++) {
				data[i] = (Math.random() * 2 - 1) * (1 - i / bufferSize);
			}
			var src = c.createBufferSource();
			src.buffer = buffer;
			var gain = c.createGain();
			gain.gain.value = peak || 0.15;
			var bp = c.createBiquadFilter();
			bp.type = 'bandpass';
			bp.frequency.value = 1200;
			bp.Q.value = 0.6;
			src.connect(bp).connect(gain).connect(c.destination);
			src.start();
			src.stop(c.currentTime + duration + 0.02);
		}

		return {
			lift: function () {
				tone({ type: 'sine', start: 520, end: 700, glide: 0.18, attack: 0.01, sustain: 0.04, release: 0.18, peak: 0.18 });
			},
			thud: function () {
				tone({ type: 'sine', start: 140, end: 60, glide: 0.12, attack: 0.005, sustain: 0.02, release: 0.12, peak: 0.32 });
			},
			whoosh: function () {
				noiseBurst(0.12, 0.18);
			},
			win: function () {
				tone({ type: 'triangle', start: 523.25, attack: 0.01, sustain: 0.05, release: 0.12, peak: 0.22 });
				setTimeout(function () { tone({ type: 'triangle', start: 659.25, attack: 0.01, sustain: 0.05, release: 0.12, peak: 0.22 }); }, 110);
				setTimeout(function () { tone({ type: 'triangle', start: 783.99, attack: 0.01, sustain: 0.08, release: 0.18, peak: 0.24 }); }, 220);
			},
			lose: function () {
				tone({ type: 'sawtooth', start: 220, attack: 0.01, sustain: 0.06, release: 0.12, peak: 0.18 });
				setTimeout(function () { tone({ type: 'sawtooth', start: 165, attack: 0.01, sustain: 0.1, release: 0.18, peak: 0.18 }); }, 130);
			},
			gameOver: function () {
				tone({ type: 'sine', start: 330, end: 80, glide: 1.1, attack: 0.02, sustain: 0.2, release: 0.8, peak: 0.28 });
			},
			setMuted: function (m) {
				muted = !!m;
			},
			isMuted: function () {
				return muted;
			},
			resume: function () {
				var c = ensure();
				if (c && c.state === 'suspended' && typeof c.resume === 'function') {
					c.resume();
				}
			}
		};
	}

	function buildGame(root) {
		var configAttr = root.getAttribute('data-spg-config');
		var instanceConfig = {};
		if (configAttr) {
			try {
				instanceConfig = JSON.parse(configAttr) || {};
			} catch (e) {
				instanceConfig = {};
			}
		}

		var config = {
			currency_name: String(instanceConfig.currency_name || GLOBAL_DEFAULTS.currency_name || 'Coins'),
			starting_bank: clampBank(instanceConfig.starting_bank || GLOBAL_DEFAULTS.starting_bank || 100),
			shuffle_speed: sanitizeSpeed(instanceConfig.shuffle_speed || GLOBAL_DEFAULTS.shuffle_speed || 'medium')
		};

		var swapMs = SPEED_TABLE[config.shuffle_speed];

		var els = {
			bank:      root.querySelector('.spg-bank'),
			round:     root.querySelector('.spg-round'),
			highscore: root.querySelector('.spg-highscore'),
			status:    root.querySelector('.spg-status'),
			betButtons: root.querySelectorAll('.spg-bet-btn'),
			shuffleBtn: root.querySelector('.spg-shuffle-btn'),
			resetBtn:  root.querySelector('.spg-reset-btn'),
			muteBtn:   root.querySelector('.spg-mute-btn'),
			slots:     root.querySelectorAll('.spg-shell-slot'),
			shells:    root.querySelectorAll('.spg-shell')
		};

		// Update the currency stat label so it always matches the runtime value.
		var bankLabel = root.querySelector('.spg-stats .spg-stat:first-child .spg-stat-label');
		if (bankLabel) {
			bankLabel.textContent = config.currency_name;
		}

		var sounds = createSoundBank();

		var state = {
			bank: config.starting_bank,
			currentBet: 0,
			round: 1,
			highScore: readHighScore(),
			peaIndex: 0,
			positions: [0, 1, 2],
			gamePhase: 'betting'
		};

		function setStatus(msg) {
			if (els.status) {
				els.status.textContent = msg;
			}
		}

		function updateHud() {
			if (els.bank) { els.bank.textContent = String(state.bank); }
			if (els.round) { els.round.textContent = String(state.round); }
			if (els.highscore) { els.highscore.textContent = String(state.highScore); }
			if (state.bank > state.highScore) {
				state.highScore = state.bank;
				writeHighScore(state.highScore);
				if (els.highscore) { els.highscore.textContent = String(state.highScore); }
			}
		}

		function setBetButtonsActive(activeBet) {
			for (var i = 0; i < els.betButtons.length; i++) {
				var b = els.betButtons[i];
				var raw = b.getAttribute('data-bet');
				var betValue = raw === 'all' ? state.bank : parseInt(raw, 10);
				if (betValue > state.bank || state.bank <= 0) {
					b.setAttribute('disabled', 'disabled');
				} else {
					b.removeAttribute('disabled');
				}
				if (activeBet !== null && betValue === activeBet && state.bank > 0) {
					b.classList.add('is-active');
				} else {
					b.classList.remove('is-active');
				}
			}
		}

		function setPhase(phase) {
			state.gamePhase = phase;
			root.setAttribute('data-phase', phase);

			var canBet = (phase === 'betting');
			setBetButtonsActive(canBet ? state.currentBet : null);

			if (els.shuffleBtn) {
				if (canBet && state.currentBet > 0) {
					els.shuffleBtn.removeAttribute('disabled');
				} else {
					els.shuffleBtn.setAttribute('disabled', 'disabled');
				}
			}

			var clickable = (phase === 'picking');
			for (var i = 0; i < els.shells.length; i++) {
				if (clickable) {
					els.shells[i].removeAttribute('disabled');
				} else {
					els.shells[i].setAttribute('disabled', 'disabled');
				}
			}
		}

		/**
		 * Apply CSS transforms so each slot's shell is shown at its current logical column.
		 *
		 * positions[i] tells us which physical column slot i is occupying.
		 */
		function renderPositions(animate) {
			var slotWidth = els.slots[0] ? els.slots[0].getBoundingClientRect().width : 140;
			for (var i = 0; i < els.slots.length; i++) {
				var slot = els.slots[i];
				var target = state.positions[i];
				var deltaCols = target - i;
				slot.style.transition = animate ? ('transform ' + swapMs + 'ms cubic-bezier(.45,.05,.35,1)') : 'none';
				slot.style.transform = 'translateX(' + (deltaCols * slotWidth) + 'px)';
			}
		}

		function setPeaVisible(slotIndex, visible) {
			for (var i = 0; i < els.slots.length; i++) {
				var pea = els.slots[i].querySelector('.spg-pea');
				if (!pea) { continue; }
				if (i === slotIndex && visible) {
					pea.classList.add('is-visible');
				} else {
					pea.classList.remove('is-visible');
				}
			}
		}

		function setSlotLifted(slotIndex, lifted) {
			for (var i = 0; i < els.slots.length; i++) {
				var slot = els.slots[i];
				if (i === slotIndex && lifted) {
					slot.classList.add('is-lifted');
				} else {
					slot.classList.remove('is-lifted');
				}
			}
		}

		function delay(ms) {
			return new Promise(function (resolve) {
				window.setTimeout(resolve, ms);
			});
		}

		function swapPositions(a, b) {
			var slotA = -1;
			var slotB = -1;
			for (var i = 0; i < state.positions.length; i++) {
				if (state.positions[i] === a) { slotA = i; }
				if (state.positions[i] === b) { slotB = i; }
			}
			if (slotA < 0 || slotB < 0) { return; }
			var tmp = state.positions[slotA];
			state.positions[slotA] = state.positions[slotB];
			state.positions[slotB] = tmp;
		}

		function startBet(amount) {
			if (state.gamePhase !== 'betting') { return; }
			if (amount <= 0 || amount > state.bank) { return; }
			state.currentBet = amount;
			setBetButtonsActive(amount);
			if (els.shuffleBtn) { els.shuffleBtn.removeAttribute('disabled'); }
			setStatus('Bet ' + amount + '. Press Shuffle when ready.');
		}

		function beginShuffle() {
			if (state.gamePhase !== 'betting' || state.currentBet <= 0) { return; }
			sounds.resume();

			setPhase('watching');
			setStatus('Watch the pea...');

			// Start with pea always under the middle physical column for a fair reveal.
			state.positions = [0, 1, 2];
			state.peaIndex = 1; // physical column 1 holds the pea at the start
			renderPositions(false);

			// Map: which slot currently holds physical column 1?
			var startingSlot = indexOfPosition(1);

			// Show the pea by lifting that slot.
			setPeaVisible(startingSlot, true);
			setSlotLifted(startingSlot, true);
			sounds.lift();

			delay(700).then(function () {
				setSlotLifted(startingSlot, false);
				setPeaVisible(startingSlot, false);
				sounds.thud();
				return delay(250);
			}).then(function () {
				return runShuffle();
			}).then(function () {
				setPhase('picking');
				setStatus('Pick a shell.');
			});
		}

		function indexOfPosition(p) {
			for (var i = 0; i < state.positions.length; i++) {
				if (state.positions[i] === p) { return i; }
			}
			return -1;
		}

		function runShuffle() {
			var swaps = randInt(4, 8);
			var chain = Promise.resolve();
			for (var i = 0; i < swaps; i++) {
				chain = chain.then(function () {
					var pair = pickTwoDistinct(3);
					swapPositions(state.positions[pair[0]], state.positions[pair[1]]);
					sounds.whoosh();
					renderPositions(true);
					return delay(swapMs + 30);
				});
			}
			return chain;
		}

		function handleShellClick(slotIndex) {
			if (state.gamePhase !== 'picking') { return; }
			setPhase('reveal');

			var chosenPosition = state.positions[slotIndex];
			var win = (chosenPosition === state.peaIndex);

			setSlotLifted(slotIndex, true);
			if (win) {
				setPeaVisible(slotIndex, true);
				sounds.lift();
				setTimeout(function () { sounds.win(); }, 150);
				root.classList.add('spg-flash-win');
			} else {
				sounds.lift();
				setTimeout(function () { sounds.lose(); }, 150);
				root.classList.add('spg-flash-lose');
				// Also lift the winning shell so the player can see where the pea was.
				var winningSlot = indexOfPosition(state.peaIndex);
				setTimeout(function () {
					setSlotLifted(winningSlot, true);
					setPeaVisible(winningSlot, true);
				}, 280);
			}

			window.setTimeout(function () {
				root.classList.remove('spg-flash-win');
				root.classList.remove('spg-flash-lose');

				if (win) {
					state.bank += state.currentBet;
					setStatus('You won ' + state.currentBet + ' ' + config.currency_name + '!');
				} else {
					state.bank -= state.currentBet;
					if (state.bank < 0) { state.bank = 0; }
					setStatus('No pea there. You lost ' + state.currentBet + ' ' + config.currency_name + '.');
				}
				state.round += 1;
				state.currentBet = 0;
				updateHud();

				// Reset visuals
				setSlotLifted(-1, false);
				setPeaVisible(-1, false);

				if (state.bank <= 0) {
					sounds.gameOver();
					setStatus('Game over. Bank empty — press Reset to start a new game.');
					setPhase('gameover');
					if (els.shuffleBtn) { els.shuffleBtn.setAttribute('disabled', 'disabled'); }
					for (var i = 0; i < els.betButtons.length; i++) {
						els.betButtons[i].setAttribute('disabled', 'disabled');
					}
					return;
				}

				setPhase('betting');
			}, 1100);
		}

		function resetGame() {
			state.bank = config.starting_bank;
			state.currentBet = 0;
			state.round = 1;
			state.positions = [0, 1, 2];
			state.peaIndex = 1;
			renderPositions(false);
			setSlotLifted(-1, false);
			setPeaVisible(-1, false);
			updateHud();
			setStatus('Place your bet to begin.');
			setPhase('betting');
		}

		function toggleMute() {
			var nextMuted = !sounds.isMuted();
			sounds.setMuted(nextMuted);
			if (els.muteBtn) {
				els.muteBtn.setAttribute('aria-pressed', nextMuted ? 'true' : 'false');
				els.muteBtn.textContent = nextMuted ? 'Sound: Off' : 'Sound: On';
			}
		}

		// Wire events
		for (var i = 0; i < els.betButtons.length; i++) {
			(function (btn) {
				btn.addEventListener('click', function () {
					sounds.resume();
					var raw = btn.getAttribute('data-bet');
					var amount = raw === 'all' ? state.bank : parseInt(raw, 10);
					if (isNaN(amount)) { return; }
					startBet(amount);
				});
			})(els.betButtons[i]);
		}

		if (els.shuffleBtn) {
			els.shuffleBtn.addEventListener('click', beginShuffle);
		}

		for (var j = 0; j < els.shells.length; j++) {
			(function (idx, shellBtn) {
				shellBtn.addEventListener('click', function () {
					handleShellClick(idx);
				});
			})(j, els.shells[j]);
		}

		if (els.resetBtn) {
			els.resetBtn.addEventListener('click', resetGame);
		}
		if (els.muteBtn) {
			els.muteBtn.addEventListener('click', toggleMute);
		}

		window.addEventListener('resize', function () {
			renderPositions(false);
		});

		// Initial render
		updateHud();
		renderPositions(false);
		setPhase('betting');
		setStatus('Place your bet to begin.');
	}

	function boot() {
		var roots = document.querySelectorAll('.spg-game');
		for (var i = 0; i < roots.length; i++) {
			if (roots[i].getAttribute('data-spg-ready') === '1') { continue; }
			roots[i].setAttribute('data-spg-ready', '1');
			buildGame(roots[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
