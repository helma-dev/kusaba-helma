/** 
* Function: toggleTextareaSize. 
* Toggles the text field size. Thanks, DesuChan! 
*/
jQuery.noConflict();
function toggleTextareaSize(textareaId, cols1, rows1, cols2, rows2)
   {
      var textarea = $(textareaId);
      if ((textarea.rows == rows1) && (textarea.cols == cols1))
      {
         textarea.setAttribute("style",
            "width:" + parseInt(window.innerWidth * 0.8) + "px");
         Event.observe(window, 'resize',
            function(e)
            {
               textarea.setAttribute("style",
                  "width:" + parseInt(window.innerWidth * 0.8) + "px");
            });
         textarea.cols = cols2;
         textarea.rows = rows2;
      }
      else
      {
         textarea.removeAttribute("style");
         textarea.cols = cols1;
         textarea.rows = rows1;
         Event.stopObserving(window, 'resize');
      }
   }
/** 
* End toggleTextareasize
*/ 
 

/**
* bbCode control by subBlue design [ www.subBlue.com ]
* Includes unixsafe colour palette selector by SHS`
*/
// Startup variables
var imageTag = false;
var theSelection = false;
var form_name = "postform";
var text_name = "message";

// Check for Browser & Platform for PC & IE specific bits
// More details from: http://www.mozilla.org/docs/web-developer/sniffer/browser_type.html
// This file is part of http://kusabax.blogspot.com
var clientPC = navigator.userAgent.toLowerCase(); // Get client info
var clientVer = parseInt(navigator.appVersion); // Get browser version

var is_ie = ((clientPC.indexOf('msie') != -1) && (clientPC.indexOf('opera') == -1));
var is_win = ((clientPC.indexOf('win') != -1) || (clientPC.indexOf('16bit') != -1));

var baseHeight;
var bbtags = new Array('[b]','[/b]','[i]','[/i]','[u]','[/u]','[quote]','[/quote]','[s]','[/s]','[list]','[/list]','[list=]','[/list]','[img]','[/img]','[url]','[/url]','[spoiler]','[/spoiler]');
var bbcode = new Array();

/**
* Fix a bug involving the TextRange object. From
* http://www.frostjedi.com/terra/scripts/demo/caretBug.html
*/ 
function initInsertions() 
{
	var doc;
	
	if (document.forms[form_name])
	{
		doc = document;
	}
	else 
	{
		doc = opener.document;
	}

	var textarea = doc.forms[form_name].elements[text_name];

	if (is_ie && typeof(baseHeight) != 'number')
	{
		textarea.focus();
		baseHeight = doc.selection.createRange().duplicate().boundingHeight;

		if (!document.forms[form_name])
		{
			document.body.focus();
		}
	}
}

/**
* bbstyle
*/
function bbstyle(bbnumber)
{	
	if (bbnumber != -1)
	{
		bbfontstyle(bbtags[bbnumber], bbtags[bbnumber+1]);
	} 
	else 
	{
		insert_text('[*]');
		document.forms[form_name].elements[text_name].focus();
	}
}

/**
* Apply bbcodes
*/
function bbfontstyle(bbopen, bbclose)
{
	theSelection = false;

	var textarea = document.forms[form_name].elements[text_name];

	textarea.focus();

	if ((clientVer >= 4) && is_ie && is_win)
	{
		// Get text selection
		theSelection = document.selection.createRange().text;

		if (theSelection)
		{
			// Add tags around selection
			document.selection.createRange().text = bbopen + theSelection + bbclose;
			document.forms[form_name].elements[text_name].focus();
			theSelection = '';
			return;
		}
	}
	else if (document.forms[form_name].elements[text_name].selectionEnd && (document.forms[form_name].elements[text_name].selectionEnd - document.forms[form_name].elements[text_name].selectionStart > 0))
	{
		mozWrap(document.forms[form_name].elements[text_name], bbopen, bbclose);
		document.forms[form_name].elements[text_name].focus();
		theSelection = '';
		return;
	}
	
	//The new position for the cursor after adding the bbcode
	var caret_pos = getCaretPosition(textarea).start;
	var new_pos = caret_pos + bbopen.length;		

	// Open tag
	insert_text(bbopen + bbclose);

	// Center the cursor when we don't have a selection
	// Gecko and proper browsers
	if (!isNaN(textarea.selectionStart))
	{
		textarea.selectionStart = new_pos;
		textarea.selectionEnd = new_pos;
	}	
	// IE
	else if (document.selection)
	{
		var range = textarea.createTextRange(); 
		range.move("character", new_pos); 
		range.select();
		storeCaret(textarea);
	}

	textarea.focus();
	return;
}

/**
* Insert text at position
*/
function insert_text(text, spaces, popup)
{
	var textarea;
	
	if (!popup) 
	{
		textarea = document.forms[form_name].elements[text_name];
	} 
	else 
	{
		textarea = opener.document.forms[form_name].elements[text_name];
	}
	if (spaces) 
	{
		text = ' ' + text + ' ';
	}
	
	if (!isNaN(textarea.selectionStart))
	{
		var sel_start = textarea.selectionStart;
		var sel_end = textarea.selectionEnd;

		mozWrap(textarea, text, '')
		textarea.selectionStart = sel_start + text.length;
		textarea.selectionEnd = sel_end + text.length;
	}
	else if (textarea.createTextRange && textarea.caretPos)
	{
		if (baseHeight != textarea.caretPos.boundingHeight) 
		{
			textarea.focus();
			storeCaret(textarea);
		}

		var caret_pos = textarea.caretPos;
		caret_pos.text = caret_pos.text.charAt(caret_pos.text.length - 1) == ' ' ? caret_pos.text + text + ' ' : caret_pos.text + text;
	}
	else
	{
		textarea.value = textarea.value + text;
	}
	if (!popup) 
	{
		textarea.focus();
	}
}


/**
* From http://www.massless.org/mozedit/
*/
function mozWrap(txtarea, open, close)
{
	var selLength = txtarea.textLength;
	var selStart = txtarea.selectionStart;
	var selEnd = txtarea.selectionEnd;
	var scrollTop = txtarea.scrollTop;

	if (selEnd == 1 || selEnd == 2) 
	{
		selEnd = selLength;
	}

	var s1 = (txtarea.value).substring(0,selStart);
	var s2 = (txtarea.value).substring(selStart, selEnd)
	var s3 = (txtarea.value).substring(selEnd, selLength);

	txtarea.value = s1 + open + s2 + close + s3;
	txtarea.selectionStart = selEnd + open.length + close.length;
	txtarea.selectionEnd = txtarea.selectionStart;
	txtarea.focus();
	txtarea.scrollTop = scrollTop;

	return;
}

/**
* Insert at Caret position. Code from
* http://www.faqts.com/knowledge_base/view.phtml/aid/1052/fid/130
*/
function storeCaret(textEl)
{
	if (textEl.createTextRange)
	{
		textEl.caretPos = document.selection.createRange().duplicate();
	}
}

/**
* Caret Position object
*/
function caretPosition()
{
	var start = null;
	var end = null;
}


/**
* Get the caret position in an textarea
*/
function getCaretPosition(txtarea)
{
	var caretPos = new caretPosition();
	
	// simple Gecko/Opera way
	if(txtarea.selectionStart || txtarea.selectionStart == 0)
	{
		caretPos.start = txtarea.selectionStart;
		caretPos.end = txtarea.selectionEnd;
	}
	// dirty and slow IE way
	else if(document.selection)
	{
	
		// get current selection
		var range = document.selection.createRange();

		// a new selection of the whole textarea
		var range_all = document.body.createTextRange();
		range_all.moveToElementText(txtarea);
		
		// calculate selection start point by moving beginning of range_all to beginning of range
		var sel_start;
		for (sel_start = 0; range_all.compareEndPoints('StartToStart', range) < 0; sel_start++)
		{		
			range_all.moveStart('character', 1);
		}
	
		txtarea.sel_start = sel_start;
	
		// we ignore the end value for IE, this is already dirty enough and we don't need it
		caretPos.start = txtarea.sel_start;
		caretPos.end = txtarea.sel_start;			
	}

	return caretPos;
}
/**
* end bbCode control 
*/



/**
* Backlinks.js
*/
// Enable by default
if (localStorage.getItem('backlinksEnabled') === null){
    localStorage.setItem('backlinksEnabled', 'true');
}

// updateBacklinks function
function updateBackLinks() {
    var i;
    var links = document.getElementsByTagName('a');
    var linkslen = links.length;
            for (i=0;i<linkslen;i++){
                    var linksclass = links[i].getAttribute('class');
                    var testref = links[i].parentNode.getAttribute('class');
                    if (linksclass != null && linksclass.indexOf('ref|') != -1 && (testref == undefined || testref != 'replybacklinks')) {
                            var onde = links[i].href.substr(links[i].href.indexOf('#') + 1);
                            var quem = links[i].parentNode.parentNode.parentNode.getElementsByTagName('a')[0].name;
                            var br = links[i].href.substring(0, links[i].href.indexOf('/res'));
br = br.substring(br.lastIndexOf('/')+1);
 
var tr = links[i].href.substring(links[i].href.lastIndexOf('/')+1, links[i].href.lastIndexOf('.'));
                            addBackLinks(quem, onde, tr, br);
				var replylinks = 'repback' + onde + br;
                    }
            }
       
        function addBackLinks (quem, onde, tr, br) {
            var ondeid = document.getElementById('reply' + onde);
            if (ondeid != undefined) {
                    var onderefl = ondeid.querySelectorAll('span.replybacklinks')[0];
                    if (onderefl.innerHTML.indexOf(quem) == -1){
                            var e = document.createElement('a');
                            e.innerHTML='&nbsp;<u>>>' + quem + '</u>';
                            e.setAttribute('href','/' + br + '/res/' + tr + '.html#' + quem);
                            e.setAttribute('class','ref|' + br + '|' + tr + '|' + quem);
                            e.setAttribute('onclick','return highlight(\'' + quem + '\', true);');
				onderefl.appendChild(e);
                            return linkslen++;
                    }
            }
        }
    return 0;
}
/**
* End backlinks.js
*/