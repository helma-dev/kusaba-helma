{if not $sideload}

<!doctype html>
<html class="{if $oldshim}old-shim-html{/if}">
<head>
	{include('includes/headMeta.html')}
	<title>{%KU_NAME}</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="/custom/css/common.css">
	<link rel="stylesheet" href="/custom/css/board.css">
	
	{if $oldshim}
		<link rel="stylesheet" href="/custom/css/old_shim.css">
		<base target="_top">
	{/if}
	
	{* include temporarily *}
	<link id="sitestyle" rel="stylesheet" href="/custom/css/board_burichan.css">
        <link id="darksidebar" rel="stylesheet" href="/custom/css/old_shim_dark.css" disabled="">
</head>
<body>

{/if}

	<div id="sidebar-container">
		<h3 id="sidebar-title" class="text-center">{%KU_NAME}</h3>
		<ul class="list">
			<li><a href="/">Front Page</a></li>
			{* <li>
				<a href="javascript:void(0)" id="sidebar-change-style">Site Styles</a>
			</li> *}
			<li>
				<span class="sidebar-style-chooser"><a href="javascript:void(0)" id="sidebar-change-style">Site Styles </a><span class="sidebar-styles"><a href="javascript:void(0)" id="sidebar-change-style-1">B</a> <a href="javascript:void(0)" id="sidebar-change-style-2">N</a></span></span>
				<!-- it's good enough -->
			</li>
			<li><a href="javascript:void(0)" id="sidebar-toggle-directory">Toggle Directory</a></li>
			{*
				we don't need this anymore, since we mobile now
				<li><a href="javascript:void(0)" id="sidebar-remove-frame">Remove Frames</a></li>
			*}
			<li><a href="https://archive.helma.xyz/">Helma.us Archive</a></li>
			<li><a href="mailto:friendlywitch@protonmail.com">Contact</a></li>
		</ul>
		
		{foreach name=sections item=sect from=$boards}
			{if count($sect.boards) > 0}
                                {if $sect.hidden}<div class="sidebar-section-hidden">{/if}
				<h3 class="sidebar-section-title"{if $sect.hidden}style="font-style: italic;"{/if}>{$sect.name}</h3>
				<ul class="list sidebar-section-list{if $sect.hidden} section-list-hidden{/if}">
				
					{foreach name=brds item=brd from=$sect.boards}
					
					<li>
						<a href="/{$brd.name}/">
							<span class="sidebar-board-directory hide">/{$brd.name}/ - </span> {$brd.desc}
						</a>
						{if $brd.locked eq 1}
							<i class="icon icon-lock" title="Board is locked"></i>
						{/if}
					</li>
					
					{/foreach}
					
				</ul>
				{if $sect.hidden}</div>{/if}
			{/if}
		{/foreach}
	</div>

{if $oldshim} 
<!-- Darkmode on the sidebar -->
<script type="text/javascript"><!--
if (localStorage.getItem('helma-site-style') == 'board_nachthexe') {
	document.getElementById('darksidebar').disabled = false;
 }
//--></script>
{/if}

{if not $sideload}
	
	{include('includes/bodyJquery.html')}
	<script src="/custom/js/board_lib.js"></script>
	<script src="/custom/js/board.js"></script> 
</body>
</html>

{/if}
