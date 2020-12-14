(function (d, w) {
	// kick off when dom ready
	d.addEventListener('DOMContentLoaded', function () {
		// xhr and elements
		var xhr,
			
			body = d.querySelector('body'),
			iframe = d.getElementById('old-shim-iframe'),
			
			// simple local poly
			hasLocalStorage = false,
			local = {
				set: function (key, val) {
					if (hasLocalStorage) {
						localStorage.setItem(key, val);
					}
				},
				get: function (key) {
					if (hasLocalStorage) {
						return localStorage.getItem(key);
					}
					else {
						return null;
					}
				},
				unset: function (key) {
					localStorage.removeItem(key);
				}
			},
			
			// local keys, time and check for media query
			keyTest = 'helma-test'
			keyExpire = 'helma-sidebar-expire',
			valExpire = 3600000, // one hour
			
			keyShow = 'helma-sidebar-show',
			valShow = 0,
			
			keyHtml = 'helma-sidebar-content',
			
			now = new Date().getTime(),
			
			hasMediaQuery = (typeof w.matchMedia != 'undefined' || typeof w.msMatchMedia != 'undefined');
		
		// feature detect, localstorage
		try {
			localStorage.setItem(keyTest, 'helma');
			localStorage.removeItem(keyTest);
			
			hasLocalStorage = true;
		}
		catch (e) {}
		
		// only if we have media query
		if (hasMediaQuery) {
			// add the toggle
			d.querySelector('.adminbar').innerHTML += ' <span id="old-shim-toggle-container">' + 
				'[<a id="old-shim-toggle" href="javascript:void(0)" title="Toggle the sidebar">Sidebar</a>]' +
				'</span>';
			
			d.getElementById('old-shim-toggle').onclick = function () {
				// well i'm trying to support IE9
				if (/(^| )old-shim-sidebar( |$)/.test(body.className)) {
					body.className = body.className.replace('old-shim-sidebar', ' ');
					local.set(keyShow, 0);
				}
				else {
					body.className += ' old-shim-sidebar';
					local.set(keyShow, 1);
				}
			};
			
			// check if we want to show this
			valShow = local.get(keyShow);
			if (valShow == null) {
				valShow = 0;
			}
			
			if (valShow == 1) {
				body.className += ' old-shim-sidebar';
			}
			
			var sidebarHtml = null, // local.get(keyHtml),
				sidebarExpire = local.get(keyExpire);
			
			function processSidebar (html, isCache) {
				var iframeDoc = iframe.contentWindow.document;
				
				iframeDoc.open();
				
				// iframe fouc
				iframe.onload = function () {
					iframe.style.display = 'block';
				};
				
				iframeDoc.write(html);
				iframeDoc.close();
				
				if (isCache) {
					iframe.setAttribute('data-cache', 'true');
				}
			}
			
			if (sidebarHtml != null && sidebarExpire != null && now < sidebarExpire) {
				processSidebar(sidebarHtml, true);
			}
			else {
				xhr = new XMLHttpRequest();
				xhr.open('GET', '/menu.php?mode=oldshim', true);
				
				xhr.onload = function () {
					if (this.status >= 200 && this.status < 400) {
						local.set(keyHtml, this.response);
						local.set(keyExpire, valExpire + now);
						// local.set(keyExpire, 0);
						
						processSidebar(this.response, false);
					}
				};
				xhr.send();
			}
		}
	}, false);
})(document, window);
