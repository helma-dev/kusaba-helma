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

<script type="text/javascript" src="{$cwebpath}custom/js/backlinks.js"></script>

<script type="text/javascript"><!--
if (localStorage.getItem("navBarFixed") == "true") {
    document.getElementsByClassName("topbar")[0].style = 'right: 8px;box-shadow: rgba(0, 0, 0, 0.15) 0px 1px 2px;z-index: 999;position:fixed;';
    document.getElementsByClassName("navtop")[0].style = 'text-align: right;line-height: 1em;padding-bottom: 5px;';
}
//--></script>
<script type="text/javascript"><!--
if (localStorage.getItem("navButtons") == "true") {
    document.getElementsByClassName("navbuttons")[0].style = 'visibility: visible!important;';
}
//--></script>
</body>
</html>

{/if}
