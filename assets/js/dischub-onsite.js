/**
 * DiscHub On-Site Polling & Verification Script
 * Features visual countdown and automatic real-time status verification for EcoCash & InnBucks.
 */

( function() {
	document.addEventListener( 'DOMContentLoaded', function() {
		const widget = document.getElementById( 'dischub-onsite-widget' );
		if ( ! widget ) {
			return;
		}

		const orderId = widget.dataset.orderId;
		const orderKey = widget.dataset.orderKey;
		const nonce = widget.dataset.nonce;
		const ajaxUrl = widget.dataset.ajaxUrl;
		const statusLabel = document.getElementById( 'dischub-status-label' );
		const countdownSec = document.getElementById( 'dischub-countdown-sec' );
		const progressFill = document.getElementById( 'dischub-progress-fill' );
		const checkBtn = document.getElementById( 'dischub-manual-check-btn' );

		let isChecking = false;
		let totalSeconds = 15;
		let remainingSeconds = 15;
		let timerInterval = null;
		let attempts = 0;
		const maxChecks = 60;

		function updateTimerUI() {
			if ( countdownSec ) {
				countdownSec.style.display = 'inline';
				countdownSec.textContent = remainingSeconds + 's';
			}
			if ( progressFill && totalSeconds > 0 ) {
				const pct = ( ( totalSeconds - remainingSeconds ) / totalSeconds ) * 100;
				progressFill.style.width = Math.min( Math.max( pct, 0 ), 100 ) + '%';
			}
		}

		function startCountdown( seconds ) {
			if ( timerInterval ) {
				clearInterval( timerInterval );
			}
			totalSeconds = seconds;
			remainingSeconds = seconds;
			updateTimerUI();

			timerInterval = setInterval( function() {
				remainingSeconds--;
				updateTimerUI();

				if ( remainingSeconds <= 0 ) {
					clearInterval( timerInterval );
					checkStatus();
				}
			}, 1000 );
		}

		function checkStatus() {
			if ( isChecking ) {
				return;
			}
			isChecking = true;
			attempts++;

			if ( timerInterval ) {
				clearInterval( timerInterval );
			}

			if ( statusLabel ) {
				statusLabel.textContent = 'Verifying payment with DiscHub...';
			}
			if ( countdownSec ) {
				countdownSec.textContent = '';
				countdownSec.style.display = 'none';
			}
			if ( checkBtn ) {
				checkBtn.textContent = 'Verifying with DiscHub...';
				checkBtn.disabled = true;
			}

			const formData = new FormData();
			formData.append( 'action', 'dischub_poll_order_status' );
			formData.append( 'order_id', orderId );
			formData.append( 'order_key', orderKey );
			if ( nonce ) {
				formData.append( 'nonce', nonce );
			}

			fetch( ajaxUrl, {
				method: 'POST',
				body: formData,
			} )
				.then( ( response ) => response.json() )
				.then( ( res ) => {
					isChecking = false;
					if ( checkBtn ) {
						checkBtn.disabled = false;
						checkBtn.textContent = "I've Approved on My Phone - Check Status Now";
					}

					if ( res && res.success && res.data ) {
						const isSuccess = ( 'success' === res.data.status || 'paid' === res.data.status || 'completed' === res.data.status || 'processing' === res.data.order_status || 'completed' === res.data.order_status );
						const isFailed = ( 'failed' === res.data.status || 'cancelled' === res.data.status || 'failed' === res.data.order_status );

						if ( isSuccess ) {
							if ( timerInterval ) {
								clearInterval( timerInterval );
							}
							if ( statusLabel ) {
								statusLabel.innerHTML = '<strong style="color:#15803d; font-size:1.15rem;">Payment Confirmed! Finalizing your order...</strong>';
							}
							if ( progressFill ) {
								progressFill.style.width = '100%';
								progressFill.style.background = '#22c55e';
							}
							setTimeout( function() {
								window.location.reload();
							}, 1200 );
							return;
						} else if ( isFailed ) {
							if ( timerInterval ) {
								clearInterval( timerInterval );
							}
							if ( statusLabel ) {
								statusLabel.innerHTML = '<strong style="color:#b91c1c;">Payment Failed or Timed Out. Please try again.</strong>';
							}
							return;
						}
					}

					// If error returned from server
					if ( res && ! res.success && res.data && res.data.error ) {
						if ( statusLabel ) {
							statusLabel.textContent = res.data.error + ' Retrying in:';
						}
					} else {
						if ( statusLabel ) {
							statusLabel.textContent = 'Waiting for mobile approval. Next check in:';
						}
					}

					// Still pending: continue countdown
					if ( attempts < maxChecks ) {
						startCountdown( 8 );
					} else {
						if ( statusLabel ) {
							statusLabel.textContent = 'Still waiting for approval. Please click the button below to verify.';
						}
					}
				} )
				.catch( () => {
					isChecking = false;
					if ( checkBtn ) {
						checkBtn.disabled = false;
						checkBtn.textContent = "I've Approved on My Phone - Check Status Now";
					}
					if ( attempts < maxChecks ) {
						if ( statusLabel ) {
							statusLabel.textContent = 'Checking for payment confirmation in:';
						}
						startCountdown( 8 );
					}
				} );
		}

		// Initial countdown (15 seconds)
		if ( statusLabel ) {
			statusLabel.textContent = 'Checking for payment confirmation in:';
		}
		startCountdown( 15 );

		// Manual check button
		if ( checkBtn ) {
			checkBtn.addEventListener( 'click', function( e ) {
				e.preventDefault();
				if ( timerInterval ) {
					clearInterval( timerInterval );
				}
				isChecking = false;
				checkStatus();
			} );
		}
	} );
} )();
