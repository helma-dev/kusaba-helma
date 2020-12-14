<!doctype html>
<html lang="en">
<head>
	{include('includes/headMeta.html')}
	<meta name="robots" content="noindex">
	
	<title>Manage Boards</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="/custom/css/common.css">
	<link rel="stylesheet" href="/custom/css/manage.css">
</head>
<body id="page">
	<form action="?action=login" method="post" id="manage-login" name="managelogin">
		<p>
			<input class="input input-block" type="text" name="username" placeholder="Username" required autofocus>
		</p>
		
		<p>
			<input class="input input-block" type="password" name="password" placeholder="Password" required>
		</p>
		
		<p>
			<button class="btn btn-lg btn-block" type="submit">
				<i class="icon icon-log-in"></i> Log In
			</button>
		</p>
	</form>
	
	<div class="text-center text-small">
		<a target="_blank" href="http://kusabax.cultnet.net/">Kusaba X</a> |
		<a target="_blank" href="http://necolas.github.io/normalize.css/">Normalize</a> |
		<a target="_blank" href="http://jquery.com/">jQuery</a> |
		<a target="_blank" href="http://glyphicons.com/">Glyphicons</a>
	</div>
	
	<img src="/custom/img/helma_salute.png" id="manage-img">
</body>
</html>
