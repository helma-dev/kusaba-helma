var style_cookie;
var style_cookie_txt;
var style_cookie_site;
var kumod_set = false;
var quick_reply = false;
var ispage;
if (!Array.prototype.indexOf) {
    Array.prototype.indexOf = function(a) {
        var b = this.length;
        var c = Number(arguments[1]) || 0;
        c = (c < 0) ? Math.ceil(c) : Math.floor(c);
        if (c < 0) c += b;
        for (; c < b; c++) {
            if (c in this && this[c] === a) return c
        }
        return -1
    }
}
var Utf8 = {
    encode: function(a) {
        a = a.replace(/\r\n/g, "\n");
        var b = "";
        for (var n = 0; n < a.length; n++) {
            var c = a.charCodeAt(n);
            if (c < 128) {
                b += String.fromCharCode(c)
            } else if ((c > 127) && (c < 2048)) {
                b += String.fromCharCode((c >> 6) | 192);
                b += String.fromCharCode((c & 63) | 128)
            } else {
                b += String.fromCharCode((c >> 12) | 224);
                b += String.fromCharCode(((c >> 6) & 63) | 128);
                b += String.fromCharCode((c & 63) | 128)
            }
        }
        return b
    },
    decode: function(a) {
        var b = "";
        var i = 0;
        var c = c1 = c2 = 0;
        while (i < a.length) {
            c = a.charCodeAt(i);
            if (c < 128) {
                b += String.fromCharCode(c);
                i++
            } else if ((c > 191) && (c < 224)) {
                c2 = a.charCodeAt(i + 1);
                b += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                i += 2
            } else {
                c2 = a.charCodeAt(i + 1);
                c3 = a.charCodeAt(i + 2);
                b += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                i += 3
            }
        }
        return b
    }
};
var gt = new Gettext({
    'domain': 'kusaba'
});

function _(a) {
    return gt.gettext(a)
}

function toggle(a, b) {
    var c = document.getElementById(b);
    if (c.style.display) {
        c.style.display = ""
    } else {
        c.style.display = "none"
    }
    a.innerHTML = (c.style.display) ? '+' : '&minus;';
    set_cookie('nav_show_' + b, c.style.display ? '0' : '1', 30)
}

function removeframes() {
    var a = document.getElementsByTagName("a");
    for (var i = 0; i < a.length; i++)
        if (a[i].className == "boardlink") a[i].target = "_top";
    document.getElementById("removeframes").innerHTML = 'Frames removed.';
    return false
}

function reloadmain() {
    if (parent.main) {
        parent.main.location.reload()
    }
}

function replaceAll(a, b, c) {
    var d = a.indexOf(b);
    while (d > -1) {
        a = a.replace(b, c);
        d = a.indexOf(b)
    }
    return a
}

function insert(a) {
    if (!ispage || quick_reply) {
        var b = document.forms.postform.message;
        if (b) {
            if (b.createTextRange && b.caretPos) {
                var c = b.caretPos;
                c.text = c.text.charAt(c.text.length - 1) == " " ? a + " " : a
            } else if (b.setSelectionRange) {
                var d = b.selectionStart;
                var e = b.selectionEnd;
                b.value = b.value.substr(0, d) + a + b.value.substr(e);
                b.setSelectionRange(d + a.length, d + a.length)
            } else {
                b.value += a + " "
            }
            b.focus();
            return false
        }
    }
    return true
}

function quote(b, a) {
    var v = eval("document." + a + ".message");
    v.value += (">>" + b + "\r");
    v.focus()
}

function checkhighlight() {
    var a;
    if (a = /#i([0-9]+)/.exec(document.location.toString()))
        if (!document.forms.postform.message.value) insert(">>" + a[1] + "\n");
    if (a = /#([0-9]+)/.exec(document.location.toString())) highlight(a[1])
}

function highlight(a, b) {
    if ((b && ispage) || ispage) {}
    var c = document.getElementsByTagName("td");
    for (var i = 0; i < c.length; i++)
        if (c[i].className == "highlight") c[i].className = "reply";
    var d = document.getElementById("reply" + a);
    var e = d.parentNode;
    while (e.nodeName != 'TABLE') {
        e = e.parentNode
    }
    if ((d || document.postform.replythread.value == a) && e.parentNode.className != "reflinkpreview") {
        if (d) {
            d.className = "highlight"
        }
        var f = /^([^#]*)/.exec(document.location.toString());
        document.location = f[1] + "#" + a;
        return false
    }
    return true
}

function get_password(a) {
    var b = getCookie(a);
    if (b) return b;
    var c = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    var b = '';
    for (var i = 0; i < 8; i++) {
        var d = Math.floor(Math.random() * c.length);
        b += c.substring(d, d + 1)
    }
    set_cookie(a, b, 365);
    return (b)
}

function togglePassword() {
    var a = (navigator.userAgent.indexOf('Safari') != -1);
    var b = (navigator.userAgent.indexOf('Opera') != -1);
    var c = (navigator.appName == 'Netscape');
    var d = document.getElementById("passwordbox");
    if (d) {
        var e;
        if ((a) || (b) || (c)) e = d.innerHTML;
        else e = d.text;
        e = e.toLowerCase();
        var f = '<td></td><td></td>';
        if (e == f) {
            var f = '<td class="postblock">Mod</td><td><input type="text" name="modpassword" size="28" maxlength="75">&nbsp;<acronym onmouseover="Tip(\'Display staff status (Mod/Admin)\')" onmouseout="UnTip()">D</acronym>:&nbsp;<input type="checkbox" name="displaystaffstatus" checked>&nbsp;<acronym onmouseover="Tip(\'Lock\')" onmouseout="UnTip()">L</acronym>:&nbsp;<input type="checkbox" name="lockonpost">&nbsp;&nbsp;<acronym onmouseover="Tip(\'Sticky\')" onmouseout="UnTip()">S</acronym>:&nbsp;<input type="checkbox" name="stickyonpost">&nbsp;&nbsp;<acronym onmouseover="Tip(\'Raw HTML\')" onmouseout="UnTip()">RH</acronym>:&nbsp;<input type="checkbox" name="rawhtml">&nbsp;&nbsp;<acronym onmouseover="Tip(\'Name\')" onmouseout="UnTip()">N</acronym>:&nbsp;<input type="checkbox" name="usestaffname"></td>'
        }
        if ((a) || (b) || (c)) d.innerHTML = f;
        else d.text = f
    }
    return false
}

function toggleOptions(a, b, c) {
    if (document.getElementById('opt' + a)) {
        if (document.getElementById('opt' + a).style.display == '') {
            document.getElementById('opt' + a).style.display = 'none';
            document.getElementById('opt' + a).innerHTML = ''
        } else {
            var d = '<td class="label"><label for="formatting">Formatting:</label></td><td colspan="3"><select name="formatting"><option value="" onclick="javascript:document.getElementById(\'formattinginfo' + a + '\').innerHTML = \'All formatting is performed by the user.\';">Normal</option><option value="aa" onclick="javascript:document.getElementById(\'formattinginfo' + a + '\').innerHTML = \'[aa] and [/aa] will surround your message.\';"';
            if (getCookie('kuformatting') == 'aa') {
                d += ' selected'
            }
            d += '>Text Art</option></select> <input type="checkbox" name="rememberformatting"><label for="rememberformatting">Remember</label> <span id="formattinginfo' + a + '">';
            if (getCookie('kuformatting') == 'aa') {
                d += '[aa] and [/aa] will surround your message.'
            } else {
                d += 'All formatting is performed by the user.'
            }
            d += '</span></td><td><input type="button" value="Preview" class="submit" onclick="javascript:postpreview(\'preview' + a + '\', \'' + c + '\', \'' + a + '\', document.' + b + '.message.value);"></td>';
            document.getElementById('opt' + a).innerHTML = d;
            document.getElementById('opt' + a).style.display = ''
        }
    }
}

function getCookie(a)
{
	b = Cookies.get(a);
	if(b) return b;
	else return '';
}
function set_cookie(a, b, c)
{
  if (c)
  {
    var d = new Date();
    d.setTime(d.getTime() + (c * 24 * 60 * 60 * 1000));
    var e = "; expires=" + d.toGMTString()
  }
  else e = "";
  document.cookie = a + "=" + b + e + "; path=/"
}
function del_cookie(a)
{
  document.cookie = a + '=; expires=Thu, 01-Jan-70 00:00:01 GMT; path=/'
}

function set_stylesheet(a, b, c) {
    if (b) {
        if (a == get_default_stylesheet()) del_cookie("kustyle_txt");
        else set_cookie("kustyle_txt", a, 365)
    } else if (c) {
        if (a == get_default_stylesheet()) del_cookie("kustyle_site");
        else set_cookie("kustyle_site", a, 365)
    } else {
        if (a == get_default_stylesheet()) del_cookie("kustyle");
        else set_cookie("kustyle", a, 365)
    }
    var d = document.getElementsByTagName("link");
    var e = false;
    for (var i = 0; i < d.length; i++) {
        var f = d[i].getAttribute("rel");
        var g = d[i].getAttribute("title");
        if (f.indexOf("style") != -1 && g) {
            d[i].disabled = true;
            if (a == g) {
                d[i].disabled = false;
                e = true
            }
        }
    }
    if (!e) set_preferred_stylesheet()
}

function set_preferred_stylesheet() {
    var a = document.getElementsByTagName("link");
    for (var i = 0; i < a.length; i++) {
        var b = a[i].getAttribute("rel");
        var c = a[i].getAttribute("title");
        if (b.indexOf("style") != -1 && c) a[i].disabled = (b.indexOf("alt") != -1)
    }
}

function get_active_stylesheet() {
    var a = document.getElementsByTagName("link");
    for (var i = 0; i < a.length; i++) {
        var b = a[i].getAttribute("rel");
        var c = a[i].getAttribute("title");
        if (b.indexOf("style") != -1 && c && !a[i].disabled) return c
    }
    return null
}

function get_preferred_stylesheet() {
    var a = document.getElementsByTagName("link");
    for (var i = 0; i < a.length; i++) {
        var b = a[i].getAttribute("rel");
        var c = a[i].getAttribute("title");
        if (b.indexOf("style") != -1 && b.indexOf("alt") == -1 && c) return c
    }
    return null
}

function get_default_stylesheet() {
    var a = document.getElementsByTagName("link");
    for (var i = 0; i < a.length; i++) {
        var b = a[i].getAttribute("rel");
        var c = a[i].getAttribute("title");
        if (b.indexOf("style") != -1 && c && b != 'alternate stylesheet') return c
    }
    return null
}

function delandbanlinks() {
    if (!kumod_set) return;
    togglePassword();
    var b = document.getElementById("fileonly");
    if (b) {
        b = b.parentNode;
        b.innerHTML = '[<input type="checkbox" name="fileonly" id="fileonly" value="on" /><label for="fileonly">File Only</label>] <input name="moddelete" onclick="return confirm(_(\'Are you sure you want to delete these posts?\'))" value="' + _('Delete') + '" type="submit" /> <input name="modban" value="' + _('Ban') + '" onclick="this.form.action=\'' + ku_cgipath + '/manage_page.php?action=bans\';" type="submit" />'
    }
    var c = document.getElementsByTagName('span');
    var d;
    var e;
    for (var i = 0; i < c.length; i++) {
        d = c[i];
        if (d.getAttribute('id')) {
            if (d.getAttribute('id').substr(0, 3) == 'dnb') {
                e = d.getAttribute('id').split('-');
                d.innerHTML = "";
                var f = "";
                new Ajax.Request(ku_boardspath + "/manage_page.php?action=getip&boarddir=" + e[1] + "&id=" + e[2], {
                    method: "get",
                    onSuccess: function(a) {
                        ipaddr = a.responseText.split("=") || "what are you doing get out you don't even fit";
                        span = document.getElementById(ipaddr[0]);
                        span.innerHTML = "[IP: " + ipaddr[1] + " <a href=\"" + ku_boardspath + "/manage_page.php?action=deletepostsbyip&ip=" + ipaddr[1] + "\" onmouseover=\"Tip('" + _('Delete all posts by this IP') + "')\" onmouseout=\"UnTip()\">D</a> / <a href=\"" + ku_boardspath + "/manage_page.php?action=ipsearch&ip=" + ipaddr[1] + "\" onmouseover=\"Tip('" + _('Search for posts with this IP') + "')\" onmouseout=\"UnTip()\">S</a>] " + span.innerHTML
                    },
                    onFailure: function() {
                        f = "[what are you doing get out you don't even fit]";
                        d.innerHTML = f;
                    }
                });
                f += '&#91;<a href="' + ku_cgipath + '/manage_page.php?action=delposts&boarddir=' + e[1] + '&del';
                if (e[3] == 'y') {
                    f += 'thread'
                } else {
                    f += 'post'
                }
                f += 'id=' + e[2] + '" onmouseover="Tip(\'' + _('Delete') + '\')" onmouseout="UnTip()" onclick="return confirm(_(\'Are you sure you want to delete this post/thread?\'));">D<\/a>&nbsp;<a href="' + ku_cgipath + '/manage_page.php?action=delposts&boarddir=' + e[1] + '&del';
                if (e[3] == 'y') {
                    f += 'thread'
                } else {
                    f += 'post'
                }
                f += 'id=' + e[2] + '&postid=' + e[2] + '" onmouseover="Tip(\'' + _('Delete &amp; Ban') + '\')" onmouseout="UnTip()" onclick="return confirm(_(\'Are you sure you want to delete and ban the poster of this post/thread?\'));">&amp;<\/a>&nbsp;<a href="' + ku_cgipath + '/manage_page.php?action=bans&banboard=' + e[1] + '&banpost=' + e[2] + '" onmouseover="Tip(\'' + _('Ban') + '\')" onmouseout="UnTip()">B<\/a>&#93;&nbsp;&#91;<a href="' + ku_cgipath + '/manage_page.php?action=bans&banboard=' + e[1] + '&banpost=' + e[2] + '&instant=y" onmouseover="Tip(\'' + _('Instant Permanent Ban') + '\')" onmouseout="UnTip()" onclick="instantban(\'' + e[1] + '\',' + e[2] + '); return false;">P<\/a>&#93;&nbsp;&#91;<a href="' + ku_cgipath + '/manage_page.php?action=delposts&boarddir=' + e[1] + '&del';
                if (e[3] == 'y') {
                    f += 'thread'
                } else {
                    f += 'post'
                }
                f += 'id=' + e[2] + '&postid=' + e[2] + '&cp=y" onmouseover="Tip(\'' + _('Child Pornography') + '\')" onmouseout="UnTip()" onclick="return confirm(_(\'Are you sure that this is child pornography?\'));">CP<\/a>&#93;';
                c[i].innerHTML = f
            }
        }
    }
}

function instantban(c, d) {
    var e = prompt(_('Are you sure you want to permenently ban the poster of this post/thread?\nIf so enter a ban message or click OK to use the default ban reason. To cancel this operation, click "Cancel".'));
    if (e !== null) {
        var f = ku_cgipath + '/manage_page.php?action=bans&banboard=' + c + '&banpost=' + d + '&instant=y';
        if (e != '') {
            f += '&reason=' + e
        }
        new Ajax.Request(f, {
            method: "get",
            onSuccess: function(a) {
                var b = a.responseText || "Ban failed!";
                if (b == "success") alert(_("Ban was sucessful."));
                else alert(_("Ban failed!"))
            },
            onFailure: function() {
                alert(_("Ban failed!"))
            }
        })
    } else {
        alert(_("OK, no action taken."))
    }
}

function togglethread(a) {
    if (hiddenthreads.toString().indexOf(a) !== -1) {
        document.getElementById('unhidethread' + a).style.display = 'none';
        document.getElementById('thread' + a).style.display = 'block';
        hiddenthreads.splice(hiddenthreads.indexOf(a), 1);
        set_cookie('hiddenthreads', hiddenthreads.join('!'), 30)
    } else {
        document.getElementById('unhidethread' + a).style.display = 'block';
        document.getElementById('thread' + a).style.display = 'none';
        hiddenthreads.push(a);
        set_cookie('hiddenthreads', hiddenthreads.join('!'), 30)
    }
    return false
}

function toggleblotter(a) {
    var b = document.getElementsByTagName('li');
    var c = new Array();
    var d;
    for (i = 0, iarr = 0; i < b.length; i++) {
        att = b[i].getAttribute('class');
        if (att == 'blotterentry') {
            d = b[i];
            if (d.style.display == 'none') {
                d.style.display = '';
                if (a) {
                    set_cookie('ku_showblotter', '1', 365)
                }
            } else {
                d.style.display = 'none';
                if (a) {
                    set_cookie('ku_showblotter', '0', 365)
                }
            }
        }
    }
}

function expandthread(c, d) {
    if (document.getElementById('replies' + c + d)) {
        var e = document.getElementById('replies' + c + d);
        e.innerHTML = _('Expanding thread') + '...<br><br>' + e.innerHTML;
        new Ajax.Request(ku_boardspath + '/expand.php?board=' + d + '&threadid=' + c, {
            method: 'get',
            onSuccess: function(a) {
                var b = a.responseText || _("something went wrong (blank response)");
                e.innerHTML = b;
                delandbanlinks();
				if (localStorage.getItem('backlinksEnabled') == 'true'){     
					updateBackLinks();
				}
                addpreviewevents()
            },
            onFailure: function() {
                alert(_('Something went wrong...'))
            }
        })
    }
    return false
}

function quickreply(a) {
    if (a == 0) {
        quick_reply = false;
        document.getElementById('posttypeindicator').innerHTML = 'new thread'
    } else {
        quick_reply = true;
        document.getElementById('posttypeindicator').innerHTML = 'reply to ' + a + ' [<a href="#postbox" onclick="javascript:quickreply(\'0\');" title="Cancel">x</a>]'
    }
    document.postform.replythread.value = a
}

function getwatchedthreads(c, d) {
    if (document.getElementById('watchedthreadlist')) {
        var e = document.getElementById('watchedthreadlist');
        e.innerHTML = _('Loading watched threads...');
        new Ajax.Request(ku_boardspath + '/threadwatch.php?board=' + d + '&threadid=' + c, {
            method: 'get',
            onSuccess: function(a) {
                var b = a.responseText || _("something went wrong (blank response)");
                e.innerHTML = b
            },
            onFailure: function() {
                alert(_('Something went wrong...'))
            }
        })
    }
}

function addtowatchedthreads(c, d) {
    if (document.getElementById('watchedthreadlist')) {
        new Ajax.Request(ku_boardspath + '/threadwatch.php?do=addthread&board=' + d + '&threadid=' + c, {
            method: 'get',
            onSuccess: function(a) {
                var b = a.responseText || _("something went wrong (blank response)");
                alert('Thread successfully added to your watch list.');
                getwatchedthreads('0', d)
            },
            onFailure: function() {
                alert(_('Something went wrong...'))
            }
        })
    }
}

function removefromwatchedthreads(c, d) {
    if (document.getElementById('watchedthreadlist')) {
        new Ajax.Request(ku_boardspath + '/threadwatch.php?do=removethread&board=' + d + '&threadid=' + c, {
            method: 'get',
            onSuccess: function(a) {
                var b = a.responseText || _("something went wrong (blank response)");
                getwatchedthreads('0', d)
            },
            onFailure: function() {
                alert(_('Something went wrong...'))
            }
        })
    }
}

function hidewatchedthreads() {
    set_cookie('showwatchedthreads', '0', 30);
    if (document.getElementById('watchedthreads')) {
        document.getElementById('watchedthreads').innerHTML = _('The Watched Threads box will be hidden the next time a page is loaded.') + ' [<a href="#" onclick="javascript:showwatchedthreads();return false">' + _('undo') + '</a>]'
    }
}

function showwatchedthreads() {
    set_cookie('showwatchedthreads', '1', 30);
    window.location.reload(true)
}

function checkcaptcha(a) {
    if (document.getElementById(a)) {
        if (document.getElementById(a).captcha) {
            if (document.getElementById(a).captcha.value == '') {
                alert('Please enter the captcha image text.');
                document.getElementById(a).captcha.focus();
                return false
            }
        }
    }
    return true
}

function expandimg(a, H, F, C, G, E, A) {
    element = document.getElementById("thumb" + a);
    var D = '<img src="' + F + '" alt="' + a + '" class="thumb" width="' + E + '" height="' + A + '">';
    var J = '<img src="' + F + '" alt="' + a + '" class="thumb" height="' + A + '" width="' + E + '">';
    var K = '<img src="' + F + '" alt="' + a + '" class="thumb" height="' + A + '" width="' + E + '"/>';
    var B = "<img class=thumb height=" + A + " alt=" + a + ' src="' + F + '" width=' + E + ">";
    if (element.innerHTML.toLowerCase() != D.toLowerCase() && element.innerHTML.toLowerCase() != B.toLowerCase() && element.innerHTML.toLowerCase() != J.toLowerCase() && element.innerHTML.toLowerCase() != K.toLowerCase()) {
        element.innerHTML = D
    } else {
        element.innerHTML = '<img src="' + H + '" alt="' + a + '" class="thumb" height="' + G + '" width="' + C + '">'
    }
}

function postpreview(c, d, e, f) {
    if (document.getElementById(c)) {
        new Ajax.Request(ku_boardspath + '/expand.php?preview&board=' + d + '&parentid=' + e + '&message=' + escape(f), {
            method: 'get',
            onSuccess: function(a) {
                var b = a.responseText || _("something went wrong (blank response)");
                document.getElementById(c).innerHTML = b
            },
            onFailure: function() {
                alert(_('Something went wrong...'))
            }
        })
    }
}

function set_inputs(a) {
    if (document.getElementById(a)) {
        with(document.getElementById(a)) {
            if (!name.value) name.value = getCookie("name");
            if (!em.value) em.value = getCookie("email");
            if (!postpassword.value) postpassword.value = get_password("postpassword")
        }
    }
}

function set_delpass(a) {
    if (document.getElementById(a).postpassword) {
        with(document.getElementById(a)) {
            if (!postpassword.value) postpassword.value = get_password("postpassword")
        }
    }
}

function addreflinkpreview(e) {
    var c;
    var d = "srcElement";
    var f = "href";
    this[f] ? c = this : c = e[d];
    ainfo = c.className.split('|');
    var g = document.createElement('div');
    g.setAttribute("id", "preview" + c.className);
    g.setAttribute('class', 'reflinkpreview');
    g.setAttribute('className', 'reflinkpreview');
    if (e.pageX) {
        g.style.left = '' + (e.pageX + 50) + 'px'
    } else {
        g.style.left = (e.clientX + 50)
    }
    var h = document.createTextNode('');
    g.appendChild(h);
    var i = c.parentNode;
    var j = i.insertBefore(g, c);
    new Ajax.Request(ku_boardspath + '/read.php?b=' + ainfo[1] + '&t=' + ainfo[2] + '&p=' + ainfo[3] + '&single', {
        method: 'get',
        onSuccess: function(a) {
            var b = a.responseText || _("something went wrong (blank response)");
            j.innerHTML = b
        },
        onFailure: function() {
            alert('wut')
        }
    })
}

function delreflinkpreview(e) {
    var a;
    var b = "srcElement";
    var c = "href";
    this[c] ? a = this : a = e[b];
    var d = document.getElementById("preview" + a.className);
    if (d) {
        d.parentNode.removeChild(d)
    }
}

function addpreviewevents() {
    var a = document.getElementsByTagName('a');
    var b;
    var c;
    for (var i = 0; i < a.length; i++) {
        b = a[i];
        if (b.className) {
            if (b.className.substr(0, 4) == "ref|") {
                if (b.addEventListener) {
                    b.addEventListener("mouseover", addreflinkpreview, false);
                    b.addEventListener("mouseout", delreflinkpreview, false)
                } else if (b.attachEvent) {
                    b.attachEvent("onmouseover", addreflinkpreview);
                    b.attachEvent("onmouseout", delreflinkpreview)
                }
            }
        }
    }
}

function keypress(e) {
    if (!e) e = window.event;
    if (e.altKey) {
        var a = document.location.toString();
        if ((a.indexOf('catalog.html') == -1 && a.indexOf('/res/') == -1) || (a.indexOf('catalog.html') == -1 && e.keyCode == 80)) {
            if (e.keyCode != 18 && e.keyCode != 16) {
                if (a.indexOf('.html') == -1 || a.indexOf('board.html') != -1) {
                    var b = 0;
                    var c = a.substr(0, a.lastIndexOf('/') + 1)
                } else {
                    var b = a.substr((a.lastIndexOf('/') + 1));
                    b = (+b.substr(0, b.indexOf('.html')));
                    var c = a.substr(0, a.lastIndexOf('/') + 1)
                }
                if (b == 0) {
                    var d = c
                } else {
                    var d = c + b + '.html'
                }
                if (e.keyCode == 222 || e.keyCode == 221) {
                    if (match = /#s([0-9])/.exec(a)) {
                        var f = (+match[1])
                    } else {
                        var f = -1
                    }
                    if (e.keyCode == 222) {
                        if (f == -1 || f == 9) {
                            var g = 0
                        } else {
                            var g = f + 1
                        }
                    } else if (e.keyCode == 221) {
                        if (f == -1 || f == 0) {
                            var g = 9
                        } else {
                            var g = f - 1
                        }
                    }
                    document.location.href = d + '#s' + g
                } else if (e.keyCode == 59 || e.keyCode == 219) {
                    if (e.keyCode == 59) {
                        b = b + 1
                    } else if (e.keyCode == 219) {
                        if (b >= 1) {
                            b = b - 1
                        }
                    }
                    if (b == 0) {
                        document.location.href = c
                    } else {
                        document.location.href = c + b + '.html'
                    }
                } else if (e.keyCode == 80) {
                    document.location.href = d + '#postbox'
                }
            }
        }
    }
}
window.onload = function(e) {
    if (getCookie("kumod") == "allboards") {
        kumod_set = true
    } else if (getCookie("kumod") != "") {
        var c = getCookie("kumod").split('|');
        var d = document.getElementById("postform").board.value;
        for (var f in c) {
            if (c[f] == d) {
                kumod_set = true;
                break
            }
        }
    }
    delandbanlinks();
    addpreviewevents();
    checkhighlight();
    if (document.getElementById('watchedthreads')) {
        var g = new Draggable('watchedthreads', {
            handle: 'watchedthreadsdraghandle',
            onEnd: function() {
                watchedthreadsdragend()
            }
        });
        var h = new Resizeable('watchedthreads', {
            resize: function() {
                watchedthreadsresizeend()
            }
        });

        function watchedthreadsdragend() {
            set_cookie('watchedthreadstop', document.getElementById('watchedthreads').style.top, 30);
            set_cookie('watchedthreadsleft', document.getElementById('watchedthreads').style.left, 30)
        }

        function watchedthreadsresizeend() {
            var a = document.getElementById('watchedthreads').offsetWidth;
            var b = document.getElementById('watchedthreads').offsetHeight;
            set_cookie('watchedthreadswidth', a, 30);
            set_cookie('watchedthreadsheight', b, 30)
        }
    }
    document.onkeydown = keypress
};
if (style_cookie) {
    var cookie = getCookie(style_cookie);
    var title = cookie ? cookie : get_preferred_stylesheet();
    if (title != get_active_stylesheet()) set_stylesheet(title)
}
if (style_cookie_txt) {
    var cookie = getCookie(style_cookie_txt);
    var title = cookie ? cookie : get_preferred_stylesheet();
    set_stylesheet(title, true)
}
if (style_cookie_site) {
    var cookie = getCookie(style_cookie_site);
    var title = cookie ? cookie : get_preferred_stylesheet();
    set_stylesheet(title, false, true)
}
