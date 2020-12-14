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
	<link rel="stylesheet" href="/custom/css/board_burichan.css">
</head>
<body>

{/if}

	<div id="sidebar-container">
		<h3 id="sidebar-title" class="text-center">{%KU_NAME}</h3>
		<ul class="list">
			<li><a href="/">Front Page</a></li>
			<li>
				<a href="javascript:void(0)" id="sidebar-change-style">Site Styles</a>
				<!-- todo: site styles -->
			</li>
			<li><a href="javascript:void(0)" id="sidebar-toggle-directory">Toggle Directory</a></li>
			{*
				we don't need this anymore, since we mobile now
				<li><a href="javascript:void(0)" id="sidebar-remove-frame">Remove Frames</a></li>
			*}
		</ul>
		
		{foreach name=sections item=sect from=$boards}
			{if count($sect.boards) > 0}
				<h3 class="sidebar-section-title">{$sect.name}</h3>
				<ul class="list sidebar-section-list">
				
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
			{/if}
		{/foreach}
	</div>

{if not $sideload}
	
	{include('includes/bodyJquery.html')}
	<script src="/custom/js/board_lib.js"></script>
	<script src="/custom/js/board.js"></script>
</body>
</html>

{/if}
