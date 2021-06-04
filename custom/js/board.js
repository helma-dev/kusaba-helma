
(function (d, w, cache) {
	// flush expired cache
	cache.flushExpired();
	
	$('#sidebar-toggle').on('click', function () {
		$(d.body).toggleClass('open');
	});

	
	$('#sidebar-change-style-1').on('click', function () {
		localStorage.setItem('helma-site-style','board_burichan');
		window.top.location.reload();
	});

	$('#sidebar-change-style-2').on('click', function () {
		localStorage.setItem('helma-site-style','board_nachthexe');
		window.top.location.reload()
	});


	// unfinished
	// $('#sidebar-change-style').on('click', function () {}
	// variables
	/*var changeStyle = function (targetStyle) {
		var name = '.css-board',
			fn = function (index, item) {
				item.disabled = true;
				item.rel = 'alternate stylesheet';
				
				if (item.id == targetStyle) {
					item.disabled = false;
					item.rel = 'stylesheet';
				}
			};
		
		$(name).each(fn);
		
		if (w.parent.board_page) {
			w.parent.board_page.jQuery(name).each(fn);
		}
		
		if (w.parent.board_menu) {
			w.parent.board_menu.jQuery(name).each(fn);
		}
		
		cache.set('main-style', targetStyle);
	};
	
	
	// get default style
	if (cache.get('main-style')) {
		changeStyle(cache.get('main-style'));
	}*/
	
	// toggler
	/*$('.toggle').each(function () {
		$(this).on('click', function (e) {
			var target = $(this).data('target');
			
			e.preventDefault();
			
			if ($(this).hasClass('toggle-animate')) {
				$(target).finish().addClass('no-transition').slideToggle(200, function () {
					$(this).removeClass('no-transition');
				});
			}
			else {
				$(target).toggle();
			}
		});
	});*/
	
	// change style
	//$('#sidebar-change-style').one('click', function () {
		/*$(this).hide();
		
		$('#menu-site-style-list').show().find('a').on('click', function () {
			//changeStyle($(this).data('target-id'));
			
			var targetId = $(this).data('target-id');
			
			$('.css-board').each(changeStyle);
			
			if (w.parent.board_page) {
				w.parent.board_page.jQuery('.css-board').each(changeStyle);
			}
			
			cache.set('main-style', targetId);
		});*/
	//});
	
	/*function changeStyle (targetStyle) {
		
		item.disabled = true;
		item.rel = 'alternate stylesheet';
		
		if (item.id == targetId) {
			item.disabled = false;
			item.rel = 'stylesheet';
		}
	};*/
	
	// toggle dir
	$('#sidebar-toggle-directory').on('click', function () {
		$('.sidebar-board-directory').toggleClass('hide');
	});
	
	// img unveil
	//setTimeout(function () {
		$('.img-unveil').unveil(200, function () {
			$(this).addClass('img-unveil-complete');
		});
	//}, 5000);
	
	
})(document, window, lscache);

