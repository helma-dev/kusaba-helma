{if $useNewTheme}

	<div class="text-center text-small">
		All trademarks and copyrights on this page are owned by their respective parties. Images uploaded are the responsibility of the Poster. Comments are owned by the Poster.
	</div>
	
	{include('includes/bodyJquery.html')}
	<script src="/custom/js/board_lib.js"></script>
	<script src="/custom/js/board.js"></script>
</body>
</html>

{else}

<a name="bottom"></a>
<div class="copyrightdisclaimer" style="text-align: center;font-size: small;">All trademarks and copyrights on this page are owned by their respective parties. Images uploaded are the responsibility of the Poster. Comments are owned by the Poster.</div>


<iframe id="old-shim-iframe" frameborder="0"></iframe>

<!-- Update Navbar according to settings -->
<script type="text/javascript"><!--
if (localStorage.getItem("navBarFixed") == "true") {
    var fixedNavBar = 'right: 8px;box-shadow: rgba(0, 0, 0, 0.15) 0px 1px 2px;z-index: 999;position:fixed;background:var(--reply-bg);border:1px solid var(--reply-borders);margin: 0 0 0 0;';
    document.getElementsByClassName("navtop")[0].style = 'text-align: right;line-height: 1em;padding-bottom: 5px;';
    if (getCookie('kustyle') === 'Neohelma' | getCookie('kustyle') === 'Nachthexe'){
        document.getElementsByClassName("topbar")[0].style = fixedNavBar.concat('border-radius: 0 0 4px 4px;');
    } else {
        document.getElementsByClassName("topbar")[0].style = fixedNavBar;
    }
}
if (getCookie('kustyle') === 'Harrischan' | getCookie('kustyle') === 'Nachthexe'){
    config .BgColor = '#232323';
    config .BorderColor = '#000000';
    config .FontColor = '#DDDDDD';
}
//--></script>
<script type="text/javascript"><!--
if (localStorage.getItem("navButtons") == "true") {
    document.getElementsByClassName("navbuttons")[0].style = 'visibility: visible!important;display:block!important;';
}
//--></script>
<!-- Update Backlinks if enabled -->
<script type="text/javascript"><!--
if (localStorage.getItem('backlinksEnabled') == 'true'){     
    updateBackLinks();
}
//--></script>
</body>
</html>

{/if}
