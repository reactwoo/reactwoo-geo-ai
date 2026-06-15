(function () {
	'use strict';

	if (typeof window.rwgaCommerceWeather === 'undefined') {
		return;
	}

	var cfg = window.rwgaCommerceWeather;

	function setStatus(text) {
		var node = document.getElementById('rwga-suggest-weather-facets-status');
		if (node) {
			node.textContent = text || '';
		}
	}

	function applyFacets(facets) {
		facets.forEach(function (slug) {
			var input = document.getElementById('rwgcm_weather_facet_' + slug);
			if (input) {
				input.checked = true;
				if (window.jQuery) {
					window.jQuery(input).trigger('change');
				}
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var btn = document.getElementById('rwga-suggest-weather-facets');
		if (!btn) {
			return;
		}
		btn.addEventListener('click', function () {
			var productId = btn.getAttribute('data-product-id');
			if (!productId) {
				return;
			}
			setStatus(cfg.i18n.working);
			window.wp.apiFetch({
				path: '/geo-ai/v1/products/' + productId + '/suggest-weather-facets',
				method: 'POST',
				headers: { 'X-WP-Nonce': cfg.nonce }
			}).then(function (response) {
				var facets = response && response.facets ? response.facets : [];
				if (!facets.length) {
					setStatus(cfg.i18n.none);
					return;
				}
				applyFacets(facets);
				var msg = cfg.i18n.applied;
				if (response.source === 'remote' && response.rationale) {
					msg += ' ' + response.rationale;
				} else if (response.source === 'remote') {
					msg += ' (AI)';
				}
				setStatus(msg);
			}).catch(function () {
				setStatus(cfg.i18n.error);
			});
		});
	});
})();
