/**
 * Page editor: show variant-draft REST URL (Geo AI) + copy action.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! rwgaBlockEditor || ! rwgaBlockEditor.variantDraftUrl ) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var PanelRow = wp.components.PanelRow;
	var Button = wp.components.Button;
	var __ = wp.i18n.__;

	function fallbackCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		try {
			document.execCommand( 'copy' );
			return Promise.resolve();
		} catch ( err ) {
			return Promise.reject( err );
		} finally {
			document.body.removeChild( ta );
		}
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return fallbackCopy( text );
			} );
		}
		return fallbackCopy( text );
	}

	function RwgaVariantDraftPanel() {
		var state = useState( false );
		var copied = state[0];
		var setCopied = state[1];
		return el(
			PluginDocumentSettingPanel,
			{
				name: 'rwga-variant-draft',
				title: __( 'Geo AI', 'reactwoo-geo-ai' ),
				className: 'rwga-variant-draft-panel',
			},
			el(
				PanelRow,
				null,
				el(
					'p',
					{ className: 'description', style: { marginTop: 0 } },
					__(
						'POST this REST URL with JSON body: page_id, optional instructions, optional country_iso2. Requires a user who can edit pages.',
						'reactwoo-geo-ai'
					)
				),
				el( 'code', {
					className: 'rwga-variant-draft-url',
					style: {
						display: 'block',
						fontSize: '11px',
						wordBreak: 'break-all',
						marginTop: '8px',
					},
				}, rwgaBlockEditor.variantDraftUrl ),
				el(
					'div',
					{
						className: 'rwga-variant-draft-actions',
						style: {
							display: 'flex',
							flexWrap: 'wrap',
							gap: '8px',
							marginTop: '8px',
							alignItems: 'center',
						},
					},
					el( Button, {
						variant: 'secondary',
						isSmall: true,
						onClick: function () {
							copyText( rwgaBlockEditor.variantDraftUrl ).then( function () {
								setCopied( true );
								setTimeout( function () {
									setCopied( false );
								}, 2000 );
							} );
						},
					}, copied ? __( 'Copied!', 'reactwoo-geo-ai' ) : __( 'Copy URL', 'reactwoo-geo-ai' ) ),
					el( Button, {
						variant: 'tertiary',
						isSmall: true,
						onClick: function () {
							window.open( rwgaBlockEditor.variantDraftUrl, '_blank', 'noopener,noreferrer' );
						},
					}, __( 'Open in new tab', 'reactwoo-geo-ai' ) )
				)
			)
		);
	}

	registerPlugin( 'rwga-geo-ai-variant-draft', {
		render: RwgaVariantDraftPanel,
	} );
} )( window.wp );
