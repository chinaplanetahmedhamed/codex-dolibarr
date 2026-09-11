(function () {
	'use strict';

	var meta = document.querySelector('meta[name="vision-system-brand"]');
	var brand = meta && meta.content ? meta.content.trim() : 'Vision-China Planet OS';
	if (!brand) brand = 'Vision-China Planet OS';
	var logoMeta = document.querySelector('meta[name="vision-system-logo"]');
	var markMeta = document.querySelector('meta[name="vision-system-mark"]');
	var logoUrl = logoMeta && logoMeta.content ? logoMeta.content : '';
	var markUrl = markMeta && markMeta.content ? markMeta.content : logoUrl;

	var oldBrandPattern = /\bDolibarr\b/gi;
	var ignoredTags = {SCRIPT:true, STYLE:true, CODE:true, PRE:true, TEXTAREA:true, NOSCRIPT:true};

	function replaceText(value) {
		return typeof value === 'string' ? value.replace(oldBrandPattern, brand) : value;
	}

	function brandElement(element) {
		if (!element || element.nodeType !== 1 || element.closest('[data-vision-branding-ignore]')) return;
		['title', 'aria-label', 'alt', 'placeholder'].forEach(function (attribute) {
			if (!element.hasAttribute(attribute)) return;
			var current = element.getAttribute(attribute);
			var updated = replaceText(current);
			if (updated !== current) element.setAttribute(attribute, updated);
		});
	}

	function brandTree(root) {
		if (!root) return;
		if (root.nodeType === 3) {
			var parent = root.parentElement;
			if (!parent || ignoredTags[parent.tagName] || parent.closest('[data-vision-branding-ignore]')) return;
			var updatedText = replaceText(root.nodeValue);
			if (updatedText !== root.nodeValue) root.nodeValue = updatedText;
			return;
		}
		if (root.nodeType !== 1 && root.nodeType !== 9 && root.nodeType !== 11) return;
		if (root.nodeType === 1) brandElement(root);
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
		var node;
		while ((node = walker.nextNode())) {
			if (node.nodeType === 1) brandElement(node);
			else brandTree(node);
		}
	}

	function applyBranding() {
		document.title = replaceText(document.title);
		if (document.body && document.body.classList.contains('bodylogin')) {
			document.title = document.title.replace(/\s*@\s*\d+(?:\.\d+)+(?:[^|]*)?/i, '').trim();
		}
		brandTree(document.body);
		var loginLogo = document.getElementById('img_logo');
		if (loginLogo && logoUrl) {
			loginLogo.setAttribute('src', logoUrl);
			loginLogo.setAttribute('alt', brand);
			loginLogo.setAttribute('title', brand);
		}
		document.querySelectorAll('.menulogocontainer img.mycompany').forEach(function (image) {
			if (markUrl) image.setAttribute('src', markUrl);
			image.setAttribute('alt', brand);
			image.setAttribute('title', brand);
		});
		if (markUrl) {
			var favicon = document.querySelector('link[rel~="icon"]');
			if (!favicon) {
				favicon = document.createElement('link');
				favicon.setAttribute('rel', 'icon');
				document.head.appendChild(favicon);
			}
			favicon.setAttribute('href', markUrl);
			favicon.setAttribute('type', 'image/svg+xml');
		}
		document.documentElement.setAttribute('data-vision-white-label', 'active');
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyBranding, {once:true});
	else applyBranding();

	var scheduled = false;
	var observer = new MutationObserver(function (mutations) {
		if (scheduled) return;
		if (!mutations.some(function (mutation) { return mutation.addedNodes.length || mutation.type === 'characterData'; })) return;
		scheduled = true;
		window.requestAnimationFrame(function () {
			scheduled = false;
			applyBranding();
		});
	});
	observer.observe(document.documentElement, {childList:true, subtree:true, characterData:true});
})();
