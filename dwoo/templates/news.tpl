{if not $sideload}

<!doctype html>
<html>
<head>
	{include('includes/headMeta.html')}
	<title>{%KU_NAME}</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="/custom/css/common.css">
	<link rel="stylesheet" href="/custom/css/board.css">
	
	{* include temporarily *}
	<link rel="stylesheet" href="/custom/css/board_burichan.css">
</head>
<body>

{/if}

	<div id="news-container">
		<div id="news-title" class="text-center">
			<h1 class="text-title">{%KU_NAME}</h1>
			
			{if %KU_SLOGAN neq ''}
				<h3 class="text-title">{%KU_SLOGAN}</h3>
			{/if}
		</div>
		
		<!--[if lt IE 9]>
		<div class="error">
			<b>Your browser is out of date.</b> It may not display all features of this and other websites.
		</div>
		<![endif]-->
		
		<ul id="news-tab" class="border border-light list list-float text-center">
			<li class="{if $page eq 'news'}active{/if}">
				<a class="border border-light" href="/">News</a>
			</li>
			<li class="{if $page eq 'links'}active{/if}">
				<a class="border border-light" href="/?p=links">Links</a>
			</li>
			<li class="{if $page eq 'rules'}active{/if}">
				<a class="border border-light" href="/?p=rules">Rules</a>
			</li>
		</ul>
		
		<ul id="news-entry" class="list">
			{foreach item=entry from=$entries}
			
			<li id="{$page}-{$entry.id}">
				<div class="news-entry-title clear">
					<span class="float-left">
						<b>{$entry.subject|stripslashes}</b>
					</span>
					<a href="#{$page}-{$entry.id}" class="float-right">#</a>
				</div>
				<div class="news-entry-content">
					{$entry.message|stripslashes}
				</div>
			</li>
			
			{/foreach}
		</ul>
	</div>

{if not $sideload}

</body>
</html>

{/if}
