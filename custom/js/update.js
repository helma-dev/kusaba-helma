/* Credit goes to "Rex" from questden.org for his tgchan autoupdate script */

if (localStorage.getItem("updaterEnabled") == "true"){
jQuery.noConflict();

jQuery(document).ready(function($) {
    var deadline = null;
    var notification = null;
    setInterval(function(){
        if(!deadline || jQuery('.spanUpdating').html() == 'Updating...')
            return;
        var t = deadline - new Date();
        jQuery('.spanUpdating').html((t - t % 1000) / 1000);
    },1000);
    window.onscroll = function() {
        var d = document.documentElement;
        var offset = d.scrollTop + window.innerHeight;
        var height = d.offsetHeight;

        if (offset === height) {
            document.title = document.title.split(') ').pop();
            jQuery('.updated').removeClass('highlight').removeClass('updated').addClass('reply');
        }
    };
    function updateReplies(timeout) {
        jQuery('.spanUpdating').html('Updating...');
        if(!timeout)
            timeout = 0;
        jQuery.ajax({
            url: window.location.href,
            success: function (data) {
                var n = data.indexOf('<a name="s"></a>');
                var d = data.substring(n);
                d = d.substring(0, d.indexOf('</div>'));
                var pagedata = jQuery('<div>').append(d);
                var newReplies = pagedata.find('table:has(.reply,.highlight)').toArray().filter(function(x) { return jQuery('#' + jQuery(x).find('.reply,.highlight').get(0).id).length === 0; });
                if(newReplies.length > 0) {
                    jQuery(newReplies).find('.reply,.highlight').addClass('highlight updated');
                    jQuery('table:has(.reply,.highlight)').last().after(newReplies);
                    timeout = 0;
                    document.title = '(' + jQuery('.updated').length + ') ' + document.title.split(') ').pop();
					if (localStorage.getItem("updaterNotify") == "true") {
						Notification.requestPermission().then(function(result) {
							if(result === 'granted') {
								if(notification)
									notification.close();
								var authorname = jQuery('.postwidth .postername').first().html().trim();
								var authorreplied = jQuery('.updated .postername:contains(' + authorname + ')').length > 0;
								notification = new Notification(jQuery('.postwidth .filetitle').first().html().trim() + ' updated!', { body: jQuery('.updated').length +  ' new replies' + (authorreplied ? '\r\n\r\n' + authorname + ' replied!' : ''), icon: jQuery('link[rel~=icon]').attr('href'), requireInteraction: true });
								jQuery(notification).on('click', function(evt) { parent.focus(); window.focus(); try { jQuery('.updated').first()[0].scrollIntoView(); } catch(err) { console.log(err); window.scrollTo(0,document.body.scrollHeight); } document.title = document.title.split(') ').pop(); evt.target.close(); });
							}
						});
					}
                    jQuery(window).resize();
                }
                jQuery('.spanUpdating').html('');
                if(jQuery('.chkAuto').is(':checked')) {
                    var newTimeout = timeout >= 30000 ? timeout : timeout + 5000;
                    deadline = new Date(new Date().getTime() + newTimeout);
                    setTimeout(function() {
                        updateReplies(newTimeout);
                    }, newTimeout);
                }
            },
            error: function() {
                jQuery('.spanUpdating').html('Failed.');
                if(jQuery('.chkAuto').is(':checked')) {
                    deadline = new Date(new Date().getTime() + timeout);
                    setTimeout(function() {
                        updateReplies(timeout);
                    }, timeout);
                }
            }
        });
    }
    var navbar = jQuery('.navbottom');
    navbar.append(' [');
    navbar.append(jQuery('<a class="aUpdate" href="#">Update</a>'));
    jQuery('.aUpdate').click(function(event) { event.preventDefault(); updateReplies(); });
    navbar.append('] [');
    navbar.append(jQuery('<label><input class="chkAuto" type="checkbox" title="Fetch new replies automatically">Auto</label>'));
    jQuery('.chkAuto').mouseup(function() {
        var chk = jQuery(this).is(':checked');
        jQuery('.chkAuto').prop('checked', !chk);
        updateReplies();
    });
    navbar.append('] ');
    navbar.append(jQuery('<span class="spanUpdating"></span>'));
});
}