<!doctype html>
<html lang="en">
<head>
	{include('includes/headMeta.html')}
	<meta name="robots" content="noindex">
	
	<title>Manage Boards</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="/custom/css/common.css">
	<link rel="stylesheet" href="/custom/css/manage.css">
	
	<base target="manage_page">
</head>
<body id="menu">
	<h1>Manage Boards</h1>
	
	{if not $user.isValid }
		<ul class="list">
			<li><a href="/" target="_top">Home</a></li>
			<li><a href="manage_page.php">Log In</a></li>
		</ul>
	{else}
		<p class="text-center">
			Welcome, <b>{$user.username}</b><br>
			Staff rights: 
			
			{if $user.isAdmin}
				<b>Administrator</b>
			{elseif $user.isMod}
				<b>Moderator</b>
			{else}
				<b>Janitor</b>
			{/if}
		</p>
		
		<ul class="list">
			<li><a href="/" target="_blank">Home</a></li>
			<li><a href="manage_page.php?action=logout">Log Out</a></li>
			<li>
				<a id="menu-posting-password-trigger" class="toggle" data-target="#menu-posting-password-container" href="javascript:void(0)">
					Posting Password
				</a>
				
				<div id="menu-posting-password-container" hidden>
					<input type="text" class="input input-block" value="{$user.password}" readonly onclick="this.select()">
				</div>
			</li>
		</ul>
		
		<h2 class="toggle toggle-icon" data-target="#section-home">
			<i class="icon icon-home"></i> Home
		</h2>
		<ul id="section-home" class="list">
			<li><a href="manage_page.php?">View Announcements</a></li>
			<li><a href="manage_page.php?action=posting_rates">Posting Rates (Past Hour)</a></li>
			<li><a href="manage_page.php?action=statistics">Statistics</a></li>
			
			{if $user.isAdmin or $user.isMod}
				<li><a href="manage_page.php?action=changepwd">Change Password</a></li>
			{/if}
		</ul>
		
		{if $user.isAdmin}
			<h2 class="toggle toggle-icon" data-target="#section-custom">
				<i class="icon icon-wrench"></i> Custom
			</h2>
			<ul id="section-custom" class="list">
				<li><a href="manage_page.php?action=custom_editConfiguration">Edit Configuration</a></li>
			</ul>
			
			<h2 class="toggle toggle-icon" data-target="#section-siteadministration">
				<i class="icon icon-picture"></i> Site Administration
			</h2>
			
			<ul id="section-siteadministration" class="list">
				<li><a href="manage_page.php?action=addannouncement">Announcements</a></li>
				<li><a href="manage_page.php?action=news">News</a></li>
				<li><a href="manage_page.php?action=faq">Links (Formerly FAQ)</a></li>
				<li><a href="manage_page.php?action=rules">Rules</a></li>
				
				{if %KU_BLOTTER}
					<li><a href="manage_page.php?action=blotter">Blotter</a></li>
				{/if}
				
				<li><a href="manage_page.php?action=templates">Edit Templates</a></li>
				<li><a href="manage_page.php?action=spaceused">Disk Space Used</a></li>
				<li><a href="manage_page.php?action=staff">Staff</a></li>
				<li><a href="manage_page.php?action=modlog">ModLog</a></li>
				<li><a href="manage_page.php?action=proxyban">Ban Proxy List</a></li>
				<li><a href="manage_page.php?action=sql">SQL Query</a></li>
				<li><a href="manage_page.php?action=cleanup">Cleanup</a></li>
				
				{if %KU_APC}
					<li><a href="manage_page.php?action=apc">APC</a></li>
				{/if}
			</ul>
			
			<h2 class="toggle toggle-icon" data-target="#section-boardsadministration">
				<i class="icon icon-cog"></i> Board Administration
			</h2>
			<ul id="section-boardsadministration" class="list">
				<li><a href="manage_page.php?action=adddelboard">Add/Delete Boards</a></li>
				<li><a href="manage_page.php?action=wordfilter">Wordfilter</a></li>
				<li><a href="manage_page.php?action=spam">Spamfilter</a></li>
				<li><a href="manage_page.php?action=ads"><i class="icon icon-warning-sign text-red"></i> Manage Ads</a></li>
				<li><a href="manage_page.php?action=embeds"><i class="icon icon-warning-sign text-red"></i> Manage Embeds</a></li>
				<li><a href="manage_page.php?action=movethread">Move Thread</a></li>
				<li><a href="manage_page.php?action=ipsearch"><i class="icon icon-warning-sign text-red"></i> IP Search</a></li>
				<li><a href="manage_page.php?action=search"><i class="icon icon-warning-sign text-red"></i> Search Posts</a></li>
				<li><a href="manage_page.php?action=viewthread"><i class="icon icon-warning-sign text-red"></i> View Thread (Including Deleted)</a></li>
				<li><a href="manage_page.php?action=editfiletypes&do=addfiletype">Edit Filetypes</a></li>
				<li><a href="manage_page.php?action=editsections&do=addsection">Edit Sections</a></li>
				<li><a href="manage_page.php?action=rebuildall" onclick="return confirm('Are you sure?')">Rebuild All HTML Files</a></li>
			</ul>
		{/if}
		
		<h2 class="toggle toggle-icon" data-target="#section-boards">
			<i class="icon icon-folder-close"></i> Boards
		</h2>
		<ul id="section-boards" class="list">
			<li><a href="manage_page.php?action=boardopts">Board Options</a></li>
			<li><a href="manage_page.php?action=stickypost">Manage Stickies</a></li>
			<li><a href="manage_page.php?action=lockpost">Manage Locked Threads</a></li>
			<li><a href="manage_page.php?action=delposts">Delete Thread/Post</a></li>
		</ul>
		
		{if $user.isAdmin or $user.isMod}
			<h2 class="toggle toggle-icon" data-target="#section-moderation">
				<i class="icon icon-tower"></i> Moderation
			</h2>
			<ul id="section-moderation" class="list">
				<li><a href="manage_page.php?action=reports"><i class="icon icon-warning-sign text-red"></i> View Reports [{$user.reportCount}]</a></li>
				<li><a href="manage_page.php?action=bans"><i class="icon icon-warning-sign text-red"></i> View/Add/Remove Bans</a></li>
				
				{if %KU_APPEAL}
					<li><a href="manage_page.php?action=appeals"><i class="icon icon-warning-sign text-red"></i> View Appeals</a></li>
				{/if}
				
				<li><a href="manage_page.php?action=deletepostsbyip"><i class="icon icon-warning-sign text-red"></i> Delete All Posts By IP</a></li>
				<li><a href="manage_page.php?action=recentimages"><i class="icon icon-warning-sign text-red"></i> Recently Uploaded Images</a></li>
				<li><a href="manage_page.php?action=recentposts"><i class="icon icon-warning-sign text-red"></i> Recent Posts</a></li>
			</ul>
		{/if}
		
		{if $user.isAdmin}
			<h2 class="toggle toggle-icon" data-target="#section-mboards">
				All Boards
			</h2>
			<ul id="section-mboards" class="list">
				{foreach from=$user.boards item=item}
					<li><a class="text-bold" href="/{$item}">/{$item}/</a></li>
				{/foreach}
			</ul>
		{/if}
	{/if}
	
	{include('includes/bodyJquery.html')}
	<script src="/custom/js/manage.js"></script>
</body>
</html>
