/**
 * DiscHub WooCommerce Blocks Payment Method Registration
 */

( function() {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getPaymentMethodData } = window.wc.wcSettings;
	const { createElement, useState, useEffect } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;
	const { __ } = window.wp.i18n;

	const dischubData = getPaymentMethodData( 'dischub', {} );

	const defaultTitle = __( 'DiscHub (EcoCash & InnBucks)', 'dischub-for-woocommerce' );
	const defaultDesc = __( 'Pay securely on this website with EcoCash or InnBucks.', 'dischub-for-woocommerce' );

	const label = decodeEntities( dischubData.title || defaultTitle );

	const Content = ( props ) => {
		const { eventRegistration, emitResponse } = props;
		const [ method, setMethod ] = useState( 'ecocash' );
		const [ phone, setPhone ] = useState( '' );

		useEffect( () => {
			if ( ! eventRegistration || ! eventRegistration.onPaymentProcessing ) {
				return;
			}
			const unsubscribe = eventRegistration.onPaymentProcessing( () => {
				return {
					type: emitResponse ? emitResponse.responseTypes.SUCCESS : 'success',
					meta: {
						paymentMethodData: {
							dischub_selected_method: method,
							dischub_phone_number: phone,
						},
					},
				};
			} );
			return () => unsubscribe();
		}, [ eventRegistration, emitResponse, method, phone ] );

		return createElement(
			'div',
			{ className: 'wc-block-components-dischub-payment-content', style: { padding: '8px 0' } },
			createElement(
				'p',
				{ style: { marginBottom: '12px', color: '#4b5563', fontSize: '0.92rem', lineHeight: '1.4' } },
				decodeEntities( dischubData.description || defaultDesc )
			),
			createElement(
				'div',
				{ style: { marginBottom: '14px', background: '#f8fafc', padding: '12px', borderRadius: '8px', border: '1px solid #e2e8f0' } },
				createElement(
					'label',
					{ style: { display: 'block', fontWeight: '700', marginBottom: '8px', fontSize: '0.88rem', color: '#0f172a' } },
					__( 'Select Payment Method:', 'dischub-for-woocommerce' )
				),
				createElement(
					'div',
					{ style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
					createElement(
						'label',
						{ style: { display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.9rem', cursor: 'pointer' } },
						createElement( 'input', {
							type: 'radio',
							name: 'dischub_block_method',
							value: 'ecocash',
							checked: method === 'ecocash',
							onChange: () => setMethod( 'ecocash' )
						} ),
						createElement( 'span', null, createElement( 'strong', null, 'EcoCash' ), ' (PIN prompt sent directly to your phone)' )
					),
					createElement(
						'label',
						{ style: { display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.9rem', cursor: 'pointer' } },
						createElement( 'input', {
							type: 'radio',
							name: 'dischub_block_method',
							value: 'innbucks',
							checked: method === 'innbucks',
							onChange: () => setMethod( 'innbucks' )
						} ),
						createElement( 'span', null, createElement( 'strong', null, 'InnBucks' ), ' (Instant Payment Code & QR Code)' )
					)
				)
			),
			createElement(
				'div',
				{ className: 'dischub-phone-field-wrapper', style: { marginTop: '8px' } },
				createElement(
					'label',
					{
						htmlFor: 'dischub-block-phone-input',
						style: { display: 'block', fontWeight: '600', marginBottom: '6px', fontSize: '0.88rem', color: '#1f2937' }
					},
					__( 'Mobile Phone Number for Payment:', 'dischub-for-woocommerce' )
				),
				createElement(
					'input',
					{
						type: 'tel',
						id: 'dischub-block-phone-input',
						className: 'wc-block-components-text-input',
						placeholder: 'e.g. 0774822032 or +263774822032',
						value: phone,
						onChange: ( e ) => setPhone( e.target.value ),
						style: {
							width: '100%',
							padding: '10px 12px',
							border: '1px solid #d1d5db',
							borderRadius: '6px',
							fontSize: '0.95rem',
							boxSizing: 'border-box'
						}
					}
				),
				createElement(
					'small',
					{ style: { display: 'block', color: '#6b7280', marginTop: '4px', fontSize: '0.8rem' } },
					__( 'If left empty, your billing address phone number will be used automatically.', 'dischub-for-woocommerce' )
				)
			)
		);
	};

	const Label = ( props ) => {
		const { PaymentMethodLabel } = props.components;
		return createElement(
			'div',
			{ style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' } },
			createElement( PaymentMethodLabel, { text: label } ),
			dischubData.icon ? createElement( 'img', {
				src: dischubData.icon,
				alt: 'DiscHub',
				style: { maxHeight: '24px', marginLeft: '10px' }
			} ) : null
		);
	};

	registerPaymentMethod( {
		name: 'dischub',
		label: createElement( Label ),
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: dischubData.supports || [ 'products' ],
		},
	} );
} )();
