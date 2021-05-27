{if $useNewTheme}

<!doctype html>
<html>
<head>
	{include('includes/headMeta.html')}
	<title>{$title}</title>
	
	<link rel="shortcut icon" href="/favicon.ico">
	
	<link rel="stylesheet" href="/custom/css/common.css">
	<link rel="stylesheet" href="/custom/css/board.css">
	
	{* include temporarily *}
	<link rel="stylesheet" href="/custom/css/board_burichan.css">

{else}

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html{$htmloptions} xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>{$title}</title>
<link rel="shortcut icon" href="{$cwebpath}favicon.ico" />
{if $locale != 'en'}
	<link rel="gettext" type="application/x-po" href="{$cwebpath}inc/lang/{$locale}/LC_MESSAGES/kusaba.po" />
{/if}
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="cache-control" content="no-cache" />
<meta http-equiv="expires" content="Sat, 17 Mar 1990 00:00:01 GMT" />
<meta http-equiv="Content-Type" content="text/html;charset={%KU_CHARSET}" />
<script type="text/javascript" src="{$cwebpath}lib/javascript/gettext.js"></script> 
<script type="text/javascript" src="{$cwebpath}lib/javascript/js.cookies.js"></script>

<!-- old shim -->
<script src="/custom/js/old_shim.js"></script>
<link rel="stylesheet" href="/custom/css/old_shim.css">

{/if}
