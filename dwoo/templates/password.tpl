<!doctype html>
<html>
<head>
	{include('includes/headMeta.html')}
	<title>{%KU_NAME}</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="/custom/css/common.css">
	<link rel="stylesheet" href="/custom/css/board.css">
	
</head>
<body class="default">
	<form id="password-form" method="post" action="/router.php?action=password">
		{if $verified}
			<p class="text-center">
				Thank you, your password has been verified. You will be redirected to the page in 3 seconds.
			</p>
			
			<p class="text-center">
				Alternatively, you can use the <a href="{$uri}">direct link</a>.
			</p>
			
			<script>
				setTimeout(function () {
					location.href = '{$uri}';
				}, 3000);
			</script>
		{else}
			<input type="hidden" name="uri" value="{$uri}">
			
			<p class="text-center">
				Helma.us is now password protected across all boards. To proceed, please type in the password.
			</p>
			
			{if $error}
				<div class="error">{$error}</div>
			{/if}
			
			<p>
				<b>Password:</b>
			</p>
			
			<ul class="list list-float">
				<li>
					<input type="password" id="password-form-input" class="input" name="password" maxlength="20" autofocus required>
				</li>
				<li>
					<button id="password-form-btn" class="btn" type="submit">
						Submit
					</button>
				</li>
			</ul>
		{/if}
	</form>
</body>
</html>
