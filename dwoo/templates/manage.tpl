<!doctype html>
<html lang="en">
<head>
	{include('includes/headMeta.html')}
	<meta name="robots" content="noindex">
	
	<title>Manage Boards</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	{if $useOldCss}
		<link rel="stylesheet" href="/custom/css/old_manage.css">
	{else}
		<link rel="stylesheet" href="/custom/css/common.css">
		<link rel="stylesheet" href="/custom/css/manage.css">
	{/if}
</head>
<body id="page">
	{$page}
	
	{if not $useOldCss}
		{include('includes/bodyJquery.html')}
		<script src="/custom/js/manage.js"></script>
	{/if}
</body>
</html>
