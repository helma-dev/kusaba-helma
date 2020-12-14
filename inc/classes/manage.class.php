<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 * +------------------------------------------------------------------------------+
 * Manage Class
 * +------------------------------------------------------------------------------+
 * Manage functions, along with the pages available
 * +------------------------------------------------------------------------------+
 */
class Manage {
	// use old css
	private $useOldCss = true;
	
	/* Show the header of the manage page */
	function Header() {
		global $dwoo_data, $tpl_page;

		if (is_file(KU_ROOTDIR . 'inc/pages/modheader.html')) {
			$tpl_includeheader = file_get_contents(KU_ROOTDIR . 'inc/pages/modheader.html');
		} else {
			$tpl_includeheader = '';
		}

		$dwoo_data->assign('includeheader', $tpl_includeheader);
	}

	/* Show the footer of the manage page */
	function Footer() {
		global $dwoo_data, $dwoo, $tpl_page;

		$dwoo_data->assign('page', $tpl_page);
		$dwoo_data->assign('useOldCss', $this->useOldCss);

		$board_class = new Board('');
		
		$dwoo->output(KU_TEMPLATEDIR . '/manage.tpl', $dwoo_data);
	}

	// Creates a salt to be used for passwords
	function CreateSalt() {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$salt = '';

		for ($i = 0; $i < 3; ++$i) {
			$salt .= $chars[mt_rand(0, strlen($chars) - 1)];
		}
		return $salt;
	}

	/* Validate the current session */
	function ValidateSession($is_menu = false) {
		global $tc_db, $tpl_page;

		if (isset($_SESSION['manageusername']) && isset($_SESSION['managepassword'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . " AND `password` = " . $tc_db->qstr($_SESSION['managepassword']) . " LIMIT 1");
			if (count($results) == 0) {
				session_destroy();
				exitWithErrorPage(_gettext('Invalid session.'), '<a href="manage_page.php">'. _gettext('Log in again.') . '</a>');
			}

			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `lastactive` = " . time() . " WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']));

			return true;
		} else {
			if (!$is_menu) {
				$this->LoginForm();
				die($tpl_page);
			} else {
				return false;
			}
		}
	}

	/* Show the login form and halt execution */
	function LoginForm() {
		global $dwoo, $dwoo_data;

		// replacing with tpl
		/*if (file_exists(KU_ROOTDIR . 'inc/pages/manage_login.html')) {
			$tpl_page = file_get_contents(KU_ROOTDIR . 'inc/pages/manage_login.html');
			$tpl_page = str_replace('{%KU_WEBPATH}', KU_WEBPATH, $tpl_page);
		}*/
		
		$dwoo_data->clear();
		$dwoo->output(KU_TEMPLATEDIR.'/manage_login.tpl', $dwoo_data);
	}

	/* Check login names and create session if user/pass is correct */
	function CheckLogin() {
		global $tc_db, $action;

	// Cloudflare support
	if(isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
		$_SERVER['REMOTE_ADDR']= $_SERVER["HTTP_CF_CONNECTING_IP"];
	}


		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `timestamp` < '" . (time() - 1200) . "'");
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `ip` FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` = '" . $_SERVER['REMOTE_ADDR'] . "' LIMIT 6");
		if (count($results) > 5) {
			exitWithErrorPage(_gettext('System lockout'), _gettext('Sorry, because of your numerous failed logins, you have been locked out from logging in for 20 minutes. Please wait and then try again.'));
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username`, `password`, `salt` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_POST['username']) . " AND `type` != 3 LIMIT 1");
			if (count($results) > 0) {
				if (empty($results[0]['salt'])) {
					if (md5($_POST['password']) == $results[0]['password']) {
						$salt = $this->CreateSalt();
						$tc_db->Execute("UPDATE `" .KU_DBPREFIX. "staff` SET salt = '" .$salt. "' WHERE username = " .$tc_db->qstr($_POST['username']));
						$newpass = md5($_POST['password'] . $salt);
						$tc_db->Execute("UPDATE `" .KU_DBPREFIX. "staff` SET password = '" .$newpass. "' WHERE username = " .$tc_db->qstr($_POST['username']));
						$_SESSION['manageusername'] = $_POST['username'];
						$_SESSION['managepassword'] = $newpass;
						$_SESSION['token'] = md5($_SESSION['manageusername'] . $_SESSION['managepassword'] . rand(0,100));
						$this->SetModerationCookies();
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` < '" . $_SERVER['REMOTE_ADDR'] . "'");
						$action = 'posting_rates';
						management_addlogentry(_gettext('Logged in'), 1);
						//die('<script type="text/javascript">top.location.href = \''. KU_CGIPATH .'/manage.php\';</script>');
						die('
							<!doctype html><title>Redirecting...</title>
							<script>!function(a){a.manage_menu.location.reload(),a.manage_page.location.href="/manage_page.php"}(window.top);</script>
						');
					} else {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( " . $tc_db->qstr($_POST['username']) . " , '" . $_SERVER['REMOTE_ADDR'] . "' , '" . time() . "' )");
						exitWithErrorPage(_gettext('Incorrect username/password.'));
					}
				} else {
					if (md5($_POST['password'] . $results[0]['salt']) == $results[0]['password']) {
						$_SESSION['manageusername'] = $_POST['username'];
						$_SESSION['managepassword'] = md5($_POST['password'] . $results[0]['salt']);
            $_SESSION['token'] = md5($_SESSION['manageusername'] . $_SESSION['managepassword'] . rand(0,100));
						$this->SetModerationCookies();
						$action = 'posting_rates';
						management_addlogentry(_gettext('Logged in'), 1);
						//die('<script type="text/javascript">top.location.href = \''. KU_CGIPATH .'/manage.php\';</script>');
						die('
							<!doctype html><title>Redirecting...</title>
							<script>!function(a){a.manage_menu.location.reload(),a.manage_page.location.href="/manage_page.php"}(window.top);</script>
						');
					} else {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( " . $tc_db->qstr($_POST['username']) . " , '" . $_SERVER['REMOTE_ADDR'] . "' , '" . time() . "' )");
						exitWithErrorPage(_gettext('Incorrect username/password.'));
					}
				}
			} else {
				$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( " . $tc_db->qstr($_POST['username']) . " , '" . $_SERVER['REMOTE_ADDR'] . "' , '" . time() . "' )");
				exitWithErrorPage(_gettext('Incorrect username/password.'));
			}
		}
	}

	/* Set mod cookies for boards */
	function SetModerationCookies() {
		global $tc_db, $tpl_page;

		if (isset($_SESSION['manageusername'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . " LIMIT 1");
			if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
				setcookie("kumod", "allboards", time() + 3600, KU_BOARDSFOLDER, KU_DOMAIN);
			} else {
				if ($results[0][0] != '') {
					setcookie("kumod", $results[0][0], time() + 3600, KU_BOARDSFOLDER, KU_DOMAIN);
				}
			}
		}
	}
  
  function CheckToken($posttoken) {
    if ($posttoken != $_SESSION['token']) {
      // Something is strange
      session_destroy();
      exitWithErrorPage(_gettext('Invalid Token'));
    }
  }

	/* Log current user out */
	function Logout() {
		global $tc_db, $tpl_page;

		setcookie('kumod', '', time() - 3600, KU_BOARDSFOLDER, KU_DOMAIN);

		session_destroy();
		unset($_SESSION['manageusername']);
		unset($_SESSION['managepassword']);
		unset($_SESSION['token']);
		
		//die('<script type="text/javascript">top.location.href = \''. KU_CGIPATH .'/manage.php\';</script>');
		die('
			<!doctype html><title>Redirecting...</title>
			<script>!function(a){a.manage_menu.location.reload(),a.manage_page.location.href="/manage_page.php"}(window.top);</script>
		');
		/*
		<script>
			(function (windowTop) {
				windowTop.manage_menu.location.reload();
				windowTop.manage_page.location.href="/manage_page.php";
			})(window.top);
		</script>
		*/
	}

		/* If the user logged in isn't an admin, kill the script */
	function AdministratorsOnly() {
		global $tc_db, $tpl_page;

		if (!$this->CurrentUserIsAdministrator()) {
			exitWithErrorPage('That page is for admins only.');
		}
	}

	/* If the user logged in isn't an moderator or higher, kill the script */
	function ModeratorsOnly() {
		global $tc_db, $tpl_page;

		if ($this->CurrentUserIsAdministrator()) {
			return true;
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
			foreach ($results as $line) {
				if ($line['type'] != 2) {
					exitWithErrorPage(_gettext('That page is for moderators and administrators only.'));
				}
			}
		}
	}

	/* See if the user logged in is an admin */
	function CurrentUserIsAdministrator() {
		global $tc_db, $tpl_page;

		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '' || $_SESSION['token'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
			$_SESSION['token'] = '';
			return false;
		}

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 1) {
				return true;
			} else {
				return false;
			}
		}

		/* If the function reaches this point, something is fishy. Kill their session */
		session_destroy();
		exitWithErrorPage(_gettext('Invalid session, please log in again.'));
	}

	/* See if the user logged in is a moderator */
	function CurrentUserIsModerator() {
		global $tc_db, $tpl_page;

		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '' || $_SESSION['token'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
			$_SESSION['token'] = '';
			return false;
		}

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 2) {
				return true;
			} else {
				return false;
			}
		}

		/* If the function reaches this point, something is fishy. Kill their session */
		session_destroy();
		exitWithErrorPage(_gettext('Invalid session, please log in again.'));
	}

	/* See if the user logged in is a moderator of a specified board */
	function CurrentUserIsModeratorOfBoard($board, $username) {
		global $tc_db, $tpl_page;

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type`, `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if (count($results) > 0) {
			foreach ($results as $line) {
				if ($line['boards'] == 'allboards') {
					return true;
				} else {
					if ($line['type'] == '1') {
						return true;
					} else {
						$array_boards = explode('|', $line['boards']);
						if (in_array($board, $array_boards)) {
							return true;
						} else {
							return false;
						}
					}
				}
			}
		} else {
			return false;
		}
	}

	/*
	* +------------------------------------------------------------------------------+
	* Manage pages
	* +------------------------------------------------------------------------------+
	*/


	/*
	* +------------------------------------------------------------------------------+
	* Home Pages
	* +------------------------------------------------------------------------------+
	*/

	/* View Announcements */
	function announcements() {
		global $tc_db, $tpl_page;
		$this->ModeratorsOnly();
		
		$this->useOldCss = false;

		$tpl_page .= '<h1>Announcements</h1>';

		/* Get all of the announcements, ordered with the newest one placed on top */
		$results = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."announcements` ORDER BY `postedat` DESC");
		foreach($results AS $line) {
			$tpl_page .= '
				<table class="table table-border">
					<tr>
						<th>
							'.stripslashes($line['subject']).' by '.stripslashes($line['postedby']).' - '.date('d M Y, h:i A', $line['postedat']).'
						</th>
					</tr>
					<tr>
						<td>'.stripslashes($line['message']).'</td>
					</tr>
				</table>
			';
		} 
	}

	function posting_rates() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;

		$tpl_page .= '<h1>Posting Rates (Past Hour)</h1>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		if (count($results) > 0) {
			$tpl_page .= '
				<table class="table table-border text-center">
					<tr>
						<th>Board</th>
						<th>Threads</th>
						<th>Replies</th>
						<th>Posts</th>
					</tr>
			';
			
			foreach ($results as $line) {
				$rows_threads = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line['id'] . " AND `parentid` = 0 AND `timestamp` >= " . (time() - 3600));
				$rows_replies = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line['id'] . " AND `parentid` != 0 AND `timestamp` >= " . (time() - 3600));
				$rows_posts = $rows_threads + $rows_replies;
				
				$tpl_page .= '
					<tr>
						<td><a class="text-bold" href="'.KU_WEBFOLDER.$line['name'].'">/'.$line['name'].'/</a></td>
						<td>'.$rows_threads.'</td>
						<td>'.$rows_replies.'</td>
						<td>'.$rows_posts.'</td>
					</tr>
				';
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">No boards</td></tr>';
		}
		
		$tpl_page .= '</table>';
	}

	function statistics() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		
		$data = array(
			'day' => 'Posts per board in past 24 hours',
			'week' => 'Posts per board in past week',
			'postnum' => 'Total posts per board',
			'unique' => 'Unique user posts per board',
			'posttime' => '
				Average number of minutes between posts (past week)<br>
				<small>Boards without posts in past week not shown</small>
			'
		);
		
		$tpl_page .= '<h1>Statistics</h1>';
		
		foreach ($data as $key => $value) {
			$tpl_page .= '
				<table class="table table-border table-fixed">
					<tr><th>'.$value.'</th></tr>
					<tr>
						<td>
							<img class="img-graph" src="manage_page.php?graph&type='.$key.'">
						</td>
					</tr>
				</table>
			';
		}
	}

	function changepwd() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;

		$tpl_page .= '<h1>Change Account Password</h1>';
		
		if (isset($_POST['oldpwd']) && isset($_POST['newpwd']) && isset($_POST['newpwd2'])) {
			$this->CheckToken($_POST['token']);
			if ($_POST['oldpwd'] != '' && $_POST['newpwd'] != '' && $_POST['newpwd2'] != '') {
				if ($_POST['newpwd'] == $_POST['newpwd2']) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . "");
					foreach ($results as $line) {
						$staff_passwordenc = $line['password'];
						$staff_salt = $line['salt'];
					}
					if (md5($_POST['oldpwd'].$staff_salt) == $staff_passwordenc) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `password` = '" . md5($_POST['newpwd'].$staff_salt) . "' WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . "");
						$_SESSION['managepassword'] = md5($_POST['newpwd'].$staff_salt);
						
						$tpl_page .= '<div class="alert alert-green">Password changed successfully</div>';
					} else {
						$tpl_page .= '<div class="alert alert-red">Incorrect current password</div>';
					}
				} else {
					$tpl_page .= '<div class="alert alert-red">New passwords do not match</div>';
				}
			} else {
				$tpl_page .= '<div class="alert alert-red">All required fields are not filled</div>';
			}
		}
		
		$tpl_page .= '
			<form action="manage_page.php?action=changepwd" method="post">
				<input type="hidden" name="token" value="'.$_SESSION['token'].'">
				
				<table class="table table-half table-sm">
					<tr>
						<td class="text-right"><label class="label-required" for="oldpwd">Current password:</label></td>
						<td><input class="input input-block" type="password" id="oldpwd" name="oldpwd" required autofocus></td>
					</tr>
					<tr>
						<td class="text-right"><label class="label-required" for="newpwd">New password:</label></td>
						<td><input class="input input-block" type="password" id="newpwd" name="newpwd" required></td>
					</tr>
					<tr>
						<td class="text-right"><label class="label-required" for="newpwd2">Retype new password:</label></td>
						<td><input class="input input-block" type="password" id="newpwd2" name="newpwd2" required></td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<button type="submit" class="btn btn-lg">
								<i class="icon icon-transfer"></i> Change Password
							</button>
						</td>
					</tr>
				</table>
			</form>
		';
	}

	/*
	* +------------------------------------------------------------------------------+
	* Site Administration Pages
	* +------------------------------------------------------------------------------+
	*/

	function addannouncement() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		
		$this->AdministratorsOnly();
		
		$disptable = true;
		$formval = 'add';
		$title = 'Announcement Management';
		$notice = '';
		$btnAddEdit = '<i class="icon icon-plus"></i> Add';
		$btnBack = false;
		
		if(isset($_GET['act'])) {
			if ($_GET['act'] == 'edit') {
				if (isset($_POST['announcement'])) {
					$this->CheckToken($_POST['token']);
					
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "announcements` SET `subject` = " . $tc_db->qstr($_POST['subject']) . ", `message` = " . $tc_db->qstr($_POST['announcement']) . " WHERE `id` = " . $tc_db->qstr($_GET['id']));
					
					$notice = '<div class="alert alert-green">Announcement edited</div>';
					management_addlogentry(_gettext('Edited an announcement'));
				}
				
				$formval = 'edit&id='. $_GET['id'];
				$title .= ' - Edit';
				$btnAddEdit = '<i class="icon icon-floppy-save"></i> Save';
				$btnBack = true;
				
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				$values = $results[0];
				$disptable = false;
			} elseif ($_GET['act'] == 'del') {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				
				$notice = '<div class="alert alert-green">Announcement deleted</div>';
				management_addlogentry(_gettext('Deleted an announcement'), 9);
			} elseif ($_GET['act'] == 'add' && isset($_POST['announcement']) && isset($_POST['subject'])) {
				if (!empty($_POST['announcement']) && !empty($_POST['subject'])) {
					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "announcements` ( `subject` , `message` , `postedat` , `postedby` ) VALUES ( " . $tc_db->qstr($_POST['subject']) . " , " . $tc_db->qstr($_POST['announcement']) . " , '" . time() . "' , " . $tc_db->qstr($_SESSION['manageusername']) . " )");
					
					$notice = '<div class="alert alert-green">Announcement added</div>';
					management_addlogentry(_gettext('Added an announcement'), 9);
				} else {
					$notice = '<div class="alert alert-red">You must enter a subject as well as a post</div>';
				}
			}
		}
		$tpl_page .= '
			<h1>'.$title.'</h1>'.$notice.'
			
			<form method="post" action="?action=addannouncement&act='.$formval.'">
				<input type="hidden" name="token" value="'.$_SESSION['token'].'">
				
				<table class="table table-post">
					<tr>
						<td class="text-right"><label class="label-required" for="subject">Subject:</label></td>
						<td>
							<input type="text" id="subject" class="input input-block" name="subject" value="'.(
								isset($values['subject']) ? $values['subject'] : ''
							).'" required autofocus>
						</td>
					</tr>
					<tr>
						<td class="text-right"><label class="label-required" for="announcement">Post:</label></td>
						<td>
							<textarea id="announcement" name="announcement" class="input input-block" required>'.(
								isset($values['message']) ? htmlspecialchars($values['message']) : ''
							).'</textarea>
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							'.($btnBack ? '<a href="/manage_page.php?action=addannouncement" class="btn btn-lg"><i class="icon icon-chevron-left"></i> Return</a>' : '').'
							<button type="submit" class="btn btn-lg">'.$btnAddEdit.'</button>
						</td>
					</tr>
				</table>
			</form>
		';
		
		if ($disptable) {
			$tpl_page .= '
				<h1>Edit/Delete Announcement</h1>
				<table class="table table-border text-center">
					<tr>
						<th>Date Added</th>
						<th>Subject</th>
						<th>Message</th>
						<th colspan="2">Edit/Delete</th>
					</tr>
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$message = htmlspecialchars($line['message']);
					
					$tpl_page .= '
						<tr>
							<td>'.date('d M Y, h:i A', $line['postedat']).'</td>
							<td>'.$line['subject'].'</td>
							<td>'.(strlen($message) > 100 ? substr($message, 0, 100). '...' : $message).'</td>
							<td>
								<a class="btn" href="?action=addannouncement&act=edit&id='.$line['id'].'">
									<i class="icon icon-pencil"></i> Edit
								</a>
							</td>
							<td>
								<a class="btn" href="?action=addannouncement&act=del&id='. $line['id'] . '" onclick="return confirm(\'Are you sure?\')">
									<i class="icon icon-remove"></i> Delete
								</a>
							</td>
						</tr>
					';
				}
			} else {
				$tpl_page .= '<tr><td colspan="5">No announcements yet</td></tr>';
			}
			
			$tpl_page .= '</table>';
		}
	}

	/* Edit Dwoo templates */
	function templates() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$files = array();

		$tpl_page .= '<h1>Template Editor</h1>';
		if ($dh = opendir(KU_TEMPLATEDIR)) {
			while (($file = readdir($dh)) !== false) {
				if ($file != '.' && $file != '..' && !is_dir(KU_TEMPLATEDIR.'/'.$file)) {
					$files[] = $file;
				}
			}
			closedir($dh);
		}
		sort($files);

		if(isset($_POST['templatedata']) && isset($_POST['template'])) {
			$this->CheckToken($_POST['token']);
			$file = basename($_POST['template']);
			if (in_array($file, $files)) {
				if(file_exists(KU_TEMPLATEDIR . '/'. $file)) {
					file_put_contents(KU_TEMPLATEDIR . '/'. $file, $_POST['templatedata']);
					$tpl_page .= '<div class="alert alert-green">Template saved</div>';
					if (isset($_POST['rebuild'])) {
						$this->rebuildall();
					}
					unset($_POST['template']);
					unset($_POST['templatedata']);
				}
			}
		}

		if(!isset($_POST['templatedata']) && !isset($_POST['template'])) {
			$tpl_page .= '
				<table class="table table-border table-hover text-center">
					<tr>
						<th>Template</th>
						<th>Edit</th>
					</tr>
			';
			
			foreach($files as $template) {
				$tpl_page .= '
					<tr>
						<td>'.$template.'</td>
						<td>
							<form method="post" action="?action=templates">
								<button type="submit" class="btn" name="template" value="'.$template.'">
									<i class="icon icon-pencil"></i> Edit
								</button>
							</form>
						</td>
					</tr>
				';
			}
			
			$tpl_page .= '</table>';
		}

		if(!isset($_POST['templatedata']) && isset($_POST['template'])) {
			$file = basename($_POST['template']);
			if (in_array($file, $files)) {
				if(file_exists(KU_TEMPLATEDIR . '/'. $file)) {
					$tpl_page .= '
						<form method="post" action="?action=templates">
							<input type="hidden" name="token" value="'.$_SESSION['token'].'">
							<input type="hidden" name="template" value="'.$file.'">
							
							<table class="table table-full table-post">
								<tr>
									<td class="text-right"><label class="label-required" for="templatedata">Post:</label></td>
									<td>
										<textarea class="input input-block" id="templatedata" name="templatedata">'.(
											htmlspecialchars(file_get_contents(KU_TEMPLATEDIR . '/'.$file))
										).'</textarea>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="text-center">
										<label class="btn btn-lg">
											<input type="checkbox" name="rebuild">
											Rebuild HTML after edit?
										</label>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="text-center">
										<a href="/manage_page.php?action=templates" class="btn btn-lg">
											<i class="icon icon-chevron-left"></i> Return
										</a>
										<button type="submit" class="btn btn-lg">
											<i class="icon icon-floppy-save"></i> Save
										</button>
									</td>
								</tr>
							</table>
						</form>
						
						<p>Note:</p>
						<ul>
							<li>Visit <a href="http://web.archive.org/web/20130404161246/http://wiki.dwoo.org/index.php/Main_Page">http://wiki.dwoo.org/</a> for syntax information.</li>
							<li>To access Kusaba variables, use <b>{%KU_VARNAME}</b>, for example <b>{%KU_BOARDSPATH}</b> would be replaced with <b>'.KU_BOARDSPATH.'</b>.</li>
							<li>Enclose text in {t}{/t} blocks to allow them to be translated for different languages.</li>
						</ul>
					';
				}
			}
		}
	}

	/* Add, edit, delete, and view news entries */
	function news() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		
		$disptable = true;
		$formval = 'add';
		$title = 'News Management';
		$notice = '';
		$btnAddEdit = '<i class="icon icon-plus"></i> Add';
		$btnBack = false;
		
		if(isset($_GET['act'])) {
			if ($_GET['act'] == 'edit') {
				if (isset($_POST['news'])) {
					$this->CheckToken($_POST['token']);
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "front` SET `subject` = " . $tc_db->qstr($_POST['subject']) . ", `message` = " . $tc_db->qstr($_POST['news']) . ", `email` = " . $tc_db->qstr($_POST['email']) . " WHERE `id` = " . $tc_db->qstr($_GET['id']) . " AND `page` = 0");
					
					$notice = '<div class="alert alert-green">News post edited</div>';
					management_addlogentry(_gettext('Edited a news entry'), 9);
				}
				
				$formval = 'edit&id='.$_GET['id'];
				$title .= ' - Edit';
				$btnAddEdit = '<i class="icon icon-floppy-save"></i> Save';
				$btnBack = true;
				
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "front` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				$values = $results[0];
				$disptable = false;
			} elseif ($_GET['act'] == 'del') {
				$results = $tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "front` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				
				$notice = '<div class="alert alert-green">News post deleted</div>';
				management_addlogentry(_gettext('Deleted a news entry'), 9);
			} elseif ($_GET['act'] == 'add') {
				if (isset($_POST['news']) && isset($_POST['subject']) && isset($_POST['email'])) {
					if (!empty($_POST['news']) || !empty($_POST['subject'])) {
						$this->CheckToken($_POST['token']);
						
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "front` ( `page`, `subject` , `message` , `timestamp` , `poster` , `email` ) VALUES ( '0', " . $tc_db->qstr($_POST['subject']) . " , " . $tc_db->qstr($_POST['news']) . " , '" . time() . "' , " . $tc_db->qstr($_SESSION['manageusername']) . " , " . $tc_db->qstr($_POST['email']) . " )");
						
						$notice = '<div class="alert alert-green">News entry successfully added</div>';
						management_addlogentry(_gettext('Added a news entry'), 9);
					} else {
						$notice = '<div class="alert alert-red">You must enter a subject as well as a post</p>';
					}
				}
			}
		}
		
		$tpl_page .= '
			<h1>'. $title . '</h1>'.$notice.'
			
			<form method="post" action="?action=news&act='.$formval.'">
				<input type="hidden" name="token" value="'.$_SESSION['token'].'">
				
				<table class="table table-post">
					<tr>
						<td class="text-right"><label class="label-required" for="subject">Subject:</label></td>
						<td>
							<input type="text" id="subject" class="input input-block" name="subject" value="'.(
								isset($values['subject']) ? $values['subject'] : ''
							).'" required autofocus>
						</td>
					</tr>
					<tr>
						<td class="text-right"><label class="label-required" for="news">Post:</label></td>
						<td>
							<textarea id="news" name="news" class="input input-block" required>'.(
								isset($values['message']) ? htmlspecialchars($values['message']) : ''
							).'</textarea>
						</td>
					</tr>
					<tr>
						<td class="text-right"><label for="email">E-mail:</label></td>
						<td>
							<input type="text" id="email" class="input input-block" name="email" value="'.(
								isset($values['email']) ? $values['email'] : ''
							).'">
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							'.($btnBack ? '<a href="/manage_page.php?action=news" class="btn btn-lg"><i class="icon icon-chevron-left"></i> Return</a>' : '').'
							<button type="submit" class="btn btn-lg">'.$btnAddEdit.'</button>
						</td>
					</tr>
				</table>
			</form>
		';
		
		if ($disptable) {
			$tpl_page .= '
				<h1>Edit/Delete News</h1>
				<table class="table table-border text-center">
					<tr>
						<th>Date Added</th>
						<th>Subject</th>
						<th>Message</th>
						<th colspan="2">Edit/Delete</th>
					</tr>
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "front` WHERE `page` = 0 ORDER BY `timestamp` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$message = htmlspecialchars($line['message']);
					
					$tpl_page .= '
						<tr>
							<td>'.date('d M Y, h:i A', $line['timestamp']).'</td>
							<td>'.$line['subject'].'</td>
							<td>'.(strlen($message) > 100 ? substr($message, 0, 100). '...' : $message).'</td>
							<td>
								<a class="btn" href="?action=news&act=edit&id='.$line['id'].'">
									<i class="icon icon-pencil"></i> Edit
								</a>
							</td>
							<td>
								<a class="btn" href="?action=news&act=del&id='. $line['id'] . '" onclick="return confirm(\'Are you sure?\')">
									<i class="icon icon-remove"></i> Delete
								</a>
							</td>
						</tr>
					';
				}
			} else {
				$tpl_page .= '<tr><td colspan="5">No news posts yet</td></tr>';
			}
			
			$tpl_page .= '</table>';
		}
	}

	/* Add, edit, or delete FAQ entries */
	function faq() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		
		$disptable = true;
		$formval = 'add';
		$title = 'FAQ Management';
		$notice = '';
		$btnAddEdit = '<i class="icon icon-plus"></i> Add';
		$btnBack = false;
		
		if(isset($_GET['act'])) {
			if ($_GET['act'] == 'edit') {
				if (isset($_POST['faq'])) {
					$this->CheckToken($_POST['token']);
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "front` SET `subject` = " . $tc_db->qstr($_POST['heading']) . ", `message` = " . $tc_db->qstr($_POST['faq']) . ", `order` = " . intval($_POST['order']) . " WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
					
					$notice = '<div class="alert alert-green">FAQ entry edited</div>';
					management_addlogentry(_gettext('Edited a FAQ entry'), 9);
				}
				$formval = 'edit&id='. $_GET['id'];
				$title .= ' - Edit';
				$btnAddEdit = '<i class="icon icon-floppy-save"></i> Save';
				$btnBack = true;
				
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "front` WHERE `id` = " . $tc_db->qstr($_GET['id']));
				$values = $results[0];
				$disptable = false;
			} elseif ($_GET['act'] == 'del') {
				$results = $tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "front` WHERE `id` = " . $tc_db->qstr($_GET['id']));
				$notice = '<div class="alert alert-green">FAQ entry deleted</div>';
				management_addlogentry(_gettext('Deleted a FAQ entry'), 9);
			} elseif ($_GET['act'] == 'add') {
				if (isset($_POST['faq']) && isset($_POST['heading']) && isset($_POST['order'])) {
					if (!empty($_POST['faq']) || !empty($_POST['heading'])) {
						$this->CheckToken($_POST['token']);
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "front` ( `page`, `subject` , `message` , `order` ) VALUES ( '1', " . $tc_db->qstr($_POST['heading']) . " , " . $tc_db->qstr($_POST['faq']) . " , " . intval($_POST['order']) . " )");
						
						$notice = '<div class="alert alert-green">FAQ entry successfully added</div>';
						management_addlogentry(_gettext('Added a FAQ entry'), 9);
					} else {
						$notice = '<div class="alert alert-red">You must enter a heading as well as a post</div>';
					}
				}
			}
		}
		
		$tpl_page .= '
			<h1>'. $title . '</h1>'.$notice.'
			
			<form method="post" action="?action=faq&act='. $formval . '">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				
				<table class="table table-full table-post">
					<tr>
						<td class="text-right"><label class="label-required" for="heading">Heading:</label></td>
						<td>
							<input type="text" id="heading" class="input input-block" name="heading" value="'.(
								isset($values['subject']) ? $values['subject'] : ''
							).'" required autofocus>
						</td>
					</tr>
					<tr>
						<td class="text-right"><label class="label-required" for="faq">Post:</label></td>
						<td>
							<textarea id="faq" name="faq" class="input input-block" required>'.(
								isset($values['message']) ? htmlspecialchars($values['message']) : ''
							).'</textarea>
						</td>
					</tr>
					<tr>
						<td class="text-right">
							<label for="order">
								Order:<br>
								<small><span class="text-red">Numbers only</span>. If left blank, it will appear at the very top of the list</small>
							</label>
						</td>
						<td>
							<input type="text" id="order" class="input input-block" name="order" value="'.(
								isset($values['order']) ? $values['order'] : ''
							).'">
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							'.($btnBack ? '<a href="/manage_page.php?action=faq" class="btn btn-lg"><i class="icon icon-chevron-left"></i> Return</a>' : '').'
							<button type="submit" class="btn btn-lg">'.$btnAddEdit.'</button>
						</td>
					</tr>
				</table>
			</form>
		';
		
		if ($disptable) {
			$tpl_page .= '
				<h1>Edit/Delete FAQ Entries</h1>
				<table class="table table-border text-center">
					<tr>
						<th>Order</th>
						<th>Heading</th>
						<th>Message</th>
						<th colspan="2">Edit/Delete</th>
					</tr>
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "front` WHERE `page` = 1 ORDER BY `order` ASC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$message = htmlspecialchars($line['message']);
					
					$tpl_page .= '
						<tr>
							<td>'. $line['order'] . '</td>
							<td>'. $line['subject'] . '</td>
							<td>'.(strlen($message) > 100 ? substr($message, 0, 100). '...' : $message).'</td>
							<td>
								<a href="?action=faq&act=edit&id='. $line['id'] . '" class="btn">
									<i class="icon icon-pencil"></i> Edit
								</a>
							</td>
							<td>
								<a href="?action=faq&act=del&id='. $line['id'] . '" class="btn" onclick="return confirm(\'Are you sure?\')">
									<i class="icon icon-remove"></i> Delete
								</a>
							</td>
						</tr>
					';
				}
			} else {
				$tpl_page .= '<tr><td colspan="5">No FAQ entries yet</td></tr>';
			}
			
			$tpl_page .= '</table>';
		}
	}

	/* Add, edit, or delete Rules Entries */
	function rules() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		
		$disptable = true;
		$formval = 'add';
		$title = 'Rules Management';
		$notice = '';
		$btnAddEdit = '<i class="icon icon-plus"></i> Add';
		$btnBack = false;
		
		if(isset($_GET['act'])) {
			if ($_GET['act'] == 'edit') {
				if (isset($_POST['rules'])) {
					$this->CheckToken($_POST['token']);
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "front` SET `subject` = " . $tc_db->qstr($_POST['heading']) . ", `message` = " . $tc_db->qstr($_POST['rules']) . ", `order` = " . intval($_POST['order']) . " WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
					
					$notice .= '<div class="alert alert-green">Rules entry edited</div>';
					management_addlogentry(_gettext('Edited a Rule entry'), 9);
				}
				$formval = 'edit&id='. $_GET['id'];
				$title .= ' - Edit';
				$btnAddEdit = '<i class="icon icon-floppy-save"></i> Save';
				$btnBack = true;
				
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "front` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				$values = $results[0];
				$disptable = false;
			} elseif ($_GET['act'] == 'del') {
				$results = $tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "front` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				
				$notice .= '<div class="alert alert-green">Rules entry deleted</div>';
				management_addlogentry(_gettext('Deleted a Rules entry'), 9);
			} elseif ($_GET['act'] == 'add') {
				if (isset($_POST['rules']) && isset($_POST['heading']) && isset($_POST['order'])) {
					if (!empty($_POST['rules']) || !empty($_POST['heading'])) {
						$this->CheckToken($_POST['token']);
						
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "front` ( `page`, `subject` , `message` , `order` ) VALUES ( '2', " . $tc_db->qstr($_POST['heading']) . " , " . $tc_db->qstr($_POST['rules']) . " , " . intval($_POST['order']) . " )");
						
						$notice .= '<div class="alert alert-green">Rules entry successfully added</div>';
						management_addlogentry(_gettext('Added a Rule entry'), 9);
					} else {
						$notice .= '<div class="alert alert-red">You must enter a heading as well as a post</div>';
					}
				}
			}
		}
		
		$tpl_page .= '
			<h1>'. $title . '</h1>'.$notice.'
			
			<form method="post" action="?action=rules&act='. $formval . '">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				
				<table class="table table-full table-post">
					<tr>
						<td class="text-right"><label class="label-required" for="heading">Heading:</label></td>
						<td>
							<input type="text" id="heading" class="input input-block" name="heading" value="'.(
								isset($values['subject']) ? $values['subject'] : ''
							).'" required autofocus>
						</td>
					</tr>
					<tr>
						<td class="text-right"><label class="label-required" for="rules">Post:</label></td>
						<td>
							<textarea id="rules" name="rules" class="input input-block" required>'.(
								isset($values['message']) ? htmlspecialchars($values['message']) : ''
							).'</textarea>
						</td>
					</tr>
					<tr>
						<td class="text-right">
							<label for="order">
								Order:<br>
								<small><span class="text-red">Numbers only</span>. If left blank, it will appear at the very top of the list</small>
							</label>
						</td>
						<td>
							<input type="text" id="order" class="input input-block" name="order" value="'.(
								isset($values['order']) ? $values['order'] : ''
							).'">
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							'.($btnBack ? '<a href="/manage_page.php?action=rules" class="btn btn-lg"><i class="icon icon-chevron-left"></i> Return</a>' : '').'
							<button type="submit" class="btn btn-lg">'.$btnAddEdit.'</button>
						</td>
					</tr>
				</table>
			</form>
		';
		
		if ($disptable) {
			$tpl_page .= '
				<h1>Edit/Delete Rule Entries</h1>
				<table class="table table-border text-center">
					<tr>
						<th>Order</th>
						<th>Heading</th>
						<th>Message</th>
						<th colspan="2">Edit/Delete</th>
					</tr>
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "front` WHERE `page` = 2 ORDER BY `order` ASC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$message = htmlspecialchars($line['message']);
					
					$tpl_page .= '
						<tr>
							<td>'. $line['order'] . '</td>
							<td>'. $line['subject'] . '</td>
							<td>'.(strlen($message) > 100 ? substr($message, 0, 100). '...' : $message).'</td>
							<td>
								<a href="?action=rules&act=edit&id='. $line['id'] . '" class="btn">
									<i class="icon icon-pencil"></i> Edit
								</a>
							</td>
							<td>
								<a href="?action=rules&act=del&id='. $line['id'] . '" class="btn" onclick="return confirm(\'Are you sure?\')">
									<i class="icon icon-remove"></i> Delete
								</a>
							</td>
						</tr>
					';
				}
			} else {
				$tpl_page .= '<tr><td colspan="5">No rule entries yet</td></tr>';
			}
			
			$tpl_page .= '</table>';
		}
	}

	function blotter() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		
		if (!KU_BLOTTER) exitWithErrorPage(_gettext('Blotter is disabled'));
		
		$act = 'add';
		$values = array();
		$disptable = true;
		$notice = '';
		$btnAddEdit = '<i class="icon icon-plus"></i> Add';
		$btnBack = false;
		
		if (isset($_GET['act'])) {
			switch($_GET['act']) {
				case 'add':
					if (isset($_POST['message'])) {
						$this->CheckToken($_POST['token']);
						$important = (isset($_POST['important'])) ? 1 : 0;
						$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "blotter` (`at`, `message`, `important`) VALUES ('" . time() . "', " . $tc_db->qstr($_POST['message']) . ", '" . $important . "')");
						
						$notice = '<div class="alert alert-green">Blotter entry added</div>';
						clearBlotterCache();
					}
					break;
				case 'del':
					if (is_numeric($_GET['id'])) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "blotter` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
						
						$notice = '<div class="alert alert-green">Blotter entry deleted</div>';
						clearBlotterCache();
					} else {
						exitWithErrorPage(_gettext('Invalid ID'));
					}
					break;
				case 'edit':
					if (is_numeric($_GET['id'])) {
						$act = 'edit&id=' .$_GET['id'];
						if (isset($_POST['message'])) {
							$this->CheckToken($_POST['token']);
							$important = (isset($_POST['important'])) ? 1 : 0;
							$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "blotter` SET `message` = " . $tc_db->qstr($_POST['message']) . ", `important` = '" . $important . "' WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
							
							$notice = '<div class="alert alert-green">Blotter entry updated</div>';
							clearBlotterCache();
						}
						
						$disptable = false;
						$btnAddEdit = '<i class="icon icon-floppy-save"></i> Save';
						$btnBack = true;
						
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "blotter` WHERE `id` = " . $tc_db->qstr($_GET['id']) . " LIMIT 1");
						$values = $results[0];
					} else {
						exitWithErrorPage(_gettext('Invalid ID'));
					}
					break;
				default:
					exitWithErrorPage(_gettext('Invalid value for \'act\''));
					break;
			}
		}
		
		$tpl_page .= '
			<h1>Blotter</h1>'.$notice.'
			
			<form action="?action=blotter&act=' .$act. '" method="post">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				
				<table class="table table-post">
					<tr>
						<td class="text-right">
							<label for="message" class="label-required">Message:</label>
						</td>
						<td>
							<input type="text" id="message" name="message" class="input input-block" value="' .(isset($values['message']) ? $values['message'] : ''). '" required>
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<label class="btn btn-lg">
								<input type="checkbox" id="important" name="important" '.(
									(isset($values['important']) && $values['important'] == 1) ? 'checked' : ''
								).'>
								Important Blotter?
							</label>
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							'.($btnBack ? '<a href="/manage_page.php?action=blotter" class="btn btn-lg"><i class="icon icon-chevron-left"></i> Return</a>' : '').'
							<button type="submit" class="btn btn-lg">'.$btnAddEdit.'</button>
						</td>
					</tr>
				</table>
			</form>
		';
		
		if ($disptable) {
			$tpl_page .= '
				<h1>Edit/Delete Blotter Entries</h1>
				<table class="table table-border text-center">
					<tr>
						<th>Date Added</th>
						<th>Message</th>
						<th>Important</th>
						<th colspan="2">Edit/Delete</th>
					</tr>
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "blotter` ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$message = htmlspecialchars($line['message']);
					
					$tpl_page .= '
						<tr>
							<td>'. date('d M Y, h:i A', $line['at']) . '</td>
							<td>'.(strlen($message) > 100 ? substr($message, 0, 100). '...' : $message).'</td>
							<td>'.(
								($line['important'] == 1) ? '<i class="icon icon-ok"></i> Yes' : '<i class="icon icon-remove"></i> No'
							).'</td>
							<td>
								<a href="?action=blotter&act=edit&id='. $line['id'] . '" class="btn">
									<i class="icon icon-pencil"></i> Edit
								</a>
							</td>
							<td>
								<a href="?action=blotter&act=del&id='. $line['id'] . '" class="btn" onclick="return confirm(\'Are you sure?\')">
									<i class="icon icon-remove"></i> Delete
								</a>
							</td>
						</tr>
					';
				}
			} else {
				$tpl_page .= '<tr><td colspan="5">No blotter entries</td></tr>';
			}
			
			$tpl_page .= '</table>';
		}

	}

	/* Display disk space used per board, and finally total in a large table */
	function spaceused() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>Disk space used</h1>';
		$spaceused_res = 0;
		$spaceused_src = 0;
		$spaceused_thumb = 0;
		$spaceused_total = 0;
		$files_res = 0;
		$files_src = 0;
		$files_thumb = 0;
		$files_total = 0;
		$tpl_page .= '
			<table class="table table-border text-center">
				<tr>
					<th>Board</th>
					<th>Area</th>
					<th>Files</th>
					<th>Space Used</th>
				</tr>
		';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results as $line) {
			list($spaceused_board_res, $files_board_res) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/res');
			list($spaceused_board_src, $files_board_src) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/src');
			list($spaceused_board_thumb, $files_board_thumb) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/thumb');

			$spaceused_board_total = $spaceused_board_res + $spaceused_board_src + $spaceused_board_thumb;
			$files_board_total = $files_board_res + $files_board_src + $files_board_thumb;

			$spaceused_res += $spaceused_board_res;
			$files_res += $files_board_res;

			$spaceused_src += $spaceused_board_src;
			$files_src += $files_board_src;

			$spaceused_thumb += $spaceused_board_thumb;
			$files_thumb += $files_board_thumb;

			$spaceused_total += $spaceused_board_total;
			$files_total += $files_board_total;

			$tpl_page .= '
				<tr>
					<td rowspan="4">/'.$line['name'].'/</td>
					<td>res/</td>
					<td>'. number_format($files_board_res) . '</td>
					<td class="text-right">'. ConvertBytes($spaceused_board_res) . '</td>
				</tr>
				<tr>
					<td>src/</td>
					<td>'. number_format($files_board_src) . '</td>
					<td class="text-right">'. ConvertBytes($spaceused_board_src) . '</td>
				</tr>
				<tr>
					<td>thumb/</td>
					<td>'. number_format($files_board_thumb) . '</td>
					<td class="text-right">'. ConvertBytes($spaceused_board_thumb) . '</td>
				</tr>
				<tr>
					<th>Total</th>
					<th>'. number_format($files_board_total) . '</th>
					<th class="text-right">'. ConvertBytes($spaceused_board_total) . '</th>
				</tr>
			';
		}
		
		$tpl_page .= '
			<tr>
				<td class="text-bold" rowspan="4">All boards</td>
				<td>res/</td>
				<td>'. number_format($files_res) . '</td>
				<td class="text-right">'. ConvertBytes($spaceused_res) . '</td>
			</tr>
			<tr>
				<td>src/</td>
				<td>'. number_format($files_src) . '</td>
				<td class="text-right">'. ConvertBytes($spaceused_src) . '</td>
			</tr>
			<tr>
				<td>thumb/</td>
				<td>'. number_format($files_thumb) . '</td>
				<td class="text-right">'. ConvertBytes($spaceused_thumb) . '</td>
			</tr>
			<tr>
				<th>Total</th>
				<th>'. number_format($files_total) . '</th>
				<th class="text-right">'. ConvertBytes($spaceused_total) . '</th>
			</tr>
			</table>
		';
		
		management_addlogentry(_gettext('Viewed disk space used'), 0);
	}

	function staff() { //183 lines
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		
		$notice = '';
		$dispMain = true;
		
		if (isset($_GET['add']) && !empty($_POST['username']) && !empty($_POST['password'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" .KU_DBPREFIX. "staff` WHERE `username` = " .$tc_db->qstr($_POST['username']));
			if (count($results) == 0) {
				if ($_POST['type'] < 3 && $_POST['type'] >= 0) {
					$this->CheckToken($_POST['token']);
					$salt = $this->CreateSalt();
					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" .KU_DBPREFIX. "staff` ( `username` , `password` , `salt` , `type` , `addedon` ) VALUES (" .$tc_db->qstr($_POST['username']). " , '" .md5($_POST['password'] . $salt). "' , '" .$salt. "' , '" .$_POST['type']. "' , '" .time(). "' )");
					$log = _gettext('Added'). ' ';
					switch ($_POST['type']) {
						case 0:
							$log .= _gettext('Janitor');
							break;
						case 1:
							$log .= _gettext('Administrator');
							break;
						case 2:
							$log .= _gettext('Moderator');
							break;
					}
					$log .= ' '. $_POST['username'];
					management_addlogentry($log, 6);
					
					$notice = '<div class="alert alert-green">Staff member successfully added</div>';
				} else {
					exitWithErrorPage('Invalid type');
				}
			} else {
				$notice = '<div class="alert alert-red">A staff member with that ID already exists</div>';
			}
		} elseif (isset($_GET['del']) && $_GET['del'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = " . $tc_db->qstr($_GET['del']) . "");
			if (count($results) > 0) {
				$username = $results[0]['username'];
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "staff` WHERE `id` = " . $tc_db->qstr($_GET['del']) . "");
				
				$notice = '<div class="alert alert-green">Staff successfully deleted</div>';
				
				management_addlogentry(_gettext('Deleted staff member') . ': '. $username, 6);
			} else {
				$notice = '<div class="alert alert-red">Invalid staff ID</div>';
			}
		} elseif (isset($_GET['edit']) && $_GET['edit'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = " . $tc_db->qstr($_GET['edit']) . "");
			if (count($results) > 0) {
				if (isset($_POST['submitting'])) {
					$this->CheckToken($_POST['token']);
					$username = $results[0]['username'];
					$type	= $results[0]['type'];
					$boards	= array();
					if (isset($_POST['modsallboards'])) {
						$newboards = array('allboards');
					} else {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY name FROM `" . KU_DBPREFIX . "boards`");
						foreach ($results as $line) {
							$boards = array_merge($boards, array($line['name']));
						}
						$changed_boards = array();
						$newboards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 8) == "moderate") {
								$changed_boards = array_merge($changed_boards, array(substr($postkey, 8)));
							}
						}
						while (list(, $thisboard_name) = each($boards)) {
							if (in_array($thisboard_name, $changed_boards)) {
								$newboards = array_merge($newboards, array($thisboard_name));
							}
						}
					}
					$logentry = _gettext('Updated staff member') . ' - ';
					if ($_POST['type'] == '1') {
						$logentry .= _gettext('Administrator');
					} elseif ($_POST['type'] == '2') {
						$logentry .= _gettext('Moderator');
					} elseif ($_POST['type'] == '0') {
						$logentry .= _gettext('Janitor');
					} else {
						exitWithErrorPage('Something went wrong.');
					}
					$logentry .= ': '. $username;
					if ($_POST['type'] != '1') {
						$logentry .= ' - '. _gettext('Moderates') . ': ';
						if (isset($_POST['modsallboards'])) {
							$logentry .= strtolower(_gettext('All boards'));
						} else {
							$logentry .= '/'. implode('/, /', $newboards) . '/';
						}
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = " . $tc_db->qstr(implode('|', $newboards)) . " , `type` = " .$_POST['type']. " WHERE `id` = " . $tc_db->qstr($_GET['edit']) . "");
					management_addlogentry($logentry, 6);
					
					$notice = '<div class="alert alert-green">Staff successfully updated</div>';
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . $_GET['edit'] . "'");
				$username = $results[0]['username'];
				$type	= $results[0]['type'];
				$boards	= explode('|', $results[0]['boards']);

				$tpl_page .= '<h1>Edit Staff</h1>'.$notice.'
					<form action="manage_page.php?action=staff&edit=' .$_GET['edit']. '" method="post">
						<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
						
						<table class="table table-post table-sm">
							<tr>
								<td class="text-right">Username:</td>
								<td><input type="text" class="input input-block" value="'.$username.'" disabled></td>
							</tr>
							<tr>
								<td class="text-right"><label for="type" class="label-required">Type:</label></td>
								<td>
									<select name="type" id="type" class="input input-block">
										<option value="1" '.($type == 1 ? 'selected' : '').'>Administrator</option>
										<option value="2" '.($type == 2 ? 'selected' : '').'>Moderator</option>
										<option value="0" '.($type == 0 ? 'selected' : '').'>Janitor</option>
									</select>
								</td>
							</tr>
							<tr>
								<td class="text-right">Moderates:</td>
								<td>
									<label class="btn text-bold">
										<input type="checkbox" name="modsallboards" '.($boards==array('allboards') ? 'checked' : '').'>
										All Boards
									</label>
								</td>
							</tr>
							<tr>
								<td></td>
								<td class="table-col-btn">
				';
				
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				foreach ($results as $line) {
					$tpl_page .= '
						<label class="btn">
							<input type="checkbox" name="moderate'. $line['name'] . '" '.(in_array($line['name'], $boards) ? 'checked' : '').'>
							/'.$line['name'].'/
						</label>
					';
				}
				
				$tpl_page .= '
								</td>
							</tr>
							<tr>
								<td class="text-center" colspan="2">
									<a href="?action=staff" class="btn btn-lg">
										<i class="icon icon-chevron-left"></i> Return
									</a>
									
									<button type="submit" class="btn btn-lg" name="submitting">
										<i class="icon icon-floppy-save"></i> Save
									</button>
								</td>
							</tr>
						</table>
					</form>
				';
				
				$dispMain = false;
			}
		}

		if ($dispMain) {
			$tpl_page .= '
				<h1>Staff</h1>'.$notice.'
				
				<form action="manage_page.php?action=staff&add" method="post">
					<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
					
					<table class="table table-post table-sm">
						<tr>
							<td class="text-right"><label for="username" class="label-required">Username:</label></td>
							<td>
								<input type="text" id="username" name="username" class="input input-block" required>
							</td>
						</tr>
						<tr>
							<td class="text-right"><label for="password" class="label-required">Password:</label></td>
							<td>
								<input type="password" id="password" name="password" class="input input-block" required>
							</td>
						</tr>
						<tr>
							<td class="text-right"><label for="type" class="label-required">Type:</label></td>
							<td>
								<select name="type" id="type" class="input input-block">
									<option value="1">Administrator</option>
									<option value="2">Moderator</option>
									<option value="0">Janitor</option>
								</select>
							</td>
						</tr>
						<tr>
							<td class="text-center" colspan="2">
								<button type="submit" class="btn btn-lg">
									<i class="icon icon-plus"></i> Add
								</button>
							</td>
						</tr>
					</table>
				</form>
				
				<h1>Staff List</h1>
				<table class="table table-border text-center">
					<tr>
						<th>Username</th>
						<th>Added On</th>
						<th>Last Active</th>
						<th>Moderating Boards</th>
						<th colspan="2">Edit/Delete</th>
					</tr>
			';
			
			$i = 1;
			while($i <= 3) {
				if ($i == 1) {
					$stafftype = 'Administrator';
					$numtype = 1;
				} elseif ($i == 2) {
					$stafftype = 'Moderator';
					$numtype = 2;
				} elseif ($i == 3) {
					$stafftype = 'Janitor';
					$numtype = 0;
				}
				
				$tpl_page .= '<tr><th colspan="6">'.$stafftype.'</th></tr>';
				
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '" .$numtype. "' ORDER BY `username` ASC");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$tpl_page .= '<tr><td>' .$line['username']. '</td><td>' .date('d M Y, h:i A', $line['addedon']). '</td><td>';
						if ($line['lastactive'] == 0) {
							$tpl_page .= _gettext('Never');
						} elseif ((time() - $line['lastactive']) > 300) {
							$tpl_page .= timeDiff($line['lastactive'], false);
						} else {
							$tpl_page .= _gettext('Online now');
						}
						$tpl_page .= '</td><td>';
						if ($line['boards'] != '' || $line['type'] == 1) {
							if ($line['boards'] == 'allboards' || $line['type'] == 1) {
								$tpl_page .=  _gettext('All boards') ;
							} else {
								$tpl_page .= '/'. implode('/, /', explode('|', $line['boards'])) . '/';
							}
						} else {
							$tpl_page .= _gettext('No boards');
						}
						$tpl_page .= '
							</td>
							<td>
								<a class="btn" href="?action=staff&edit='. $line['id'] . '">
									<i class="icon icon-pencil"></i> Edit
								</a>
							</td>
							<td>
								<a class="btn" href="?action=staff&del='. $line['id'] . '" onclick="return confirm(\'Are you sure?\')">
									<i class="icon icon-remove"></i> Delete
								</a>
							</td></tr>
						';
					}
				} else {
					$tpl_page .= '<tr><td colspan="6">'. _gettext('None') . '</td></tr>';
				}
				$i++;
			}
			$tpl_page .= '</table>';
		}
	}

	/* Display moderators and administrators actions which were logged */
	function modlog() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "modlog` WHERE `timestamp` < '" . (time() - KU_MODLOGDAYS * 86400) . "'");

		$tpl_page .= '
			<h1>ModLog</h1>
			
			<table class="table table-border table-hover">
				<tr>
					<th>Time</th>
					<th>User</th>
					<th>Action</th>
				</tr>
		';
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "modlog` ORDER BY `timestamp` DESC");
		foreach ($results as $line) {
			$tpl_page .= '
				<tr>
					<td class="text-center">'.date('d M Y, h:i A', $line['timestamp']).'</td>
					<td class="text-center">'.$line['user'].'</td>
					<td>'.$line['entry'].'</td>
				</tr>
			';
		}
		
		$tpl_page .= '</table>';
	}

	function proxyban() {
		global $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>Ban Proxy List</h1>';
		if (isset($_FILES['imagefile'])) {
			$bans_class = new Bans;
			$ips = 0;
			$successful = 0;
			$proxies = file($_FILES['imagefile']['tmp_name']);
			foreach($proxies as $proxy) {
				if (preg_match('/.[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+.*/', $proxy)) {
					$proxy = trim($proxy);
					$ips++;
					if ($bans_class->BanUser(preg_replace('/:.*/', '', $proxy), 'SERVER', 1, 0, '', 'IP from proxylist automatically banned', '', 0, 0, 1, true)) {
						$successful++;
					}
				}
			}
			management_addlogentry(sprintf(_gettext('Banned %d IP addresses using an IP address list.'), $successful), 8);
			$tpl_page .= '
				<div class="alert alert-green">'.$successful . ' of '. $ips . ' IP addresses banned.</div>
				<div class="text-center">
					<a href="?action=proxyban" class="btn btn-lg">
						<i class="icon icon-chevron-left"></i> Return
					</a>
				</div>
			';
		} else {
			$tpl_page .= '
				<form id="postform" action="'. KU_CGIPATH . '/manage_page.php?action=proxyban" method="post" enctype="multipart/form-data">
					<table class="table table-post table-sm">
						<tr>
							<td class="text-right"><label class="label-required">Proxy list:</label></td>
							<td>
								<label class="btn" for="image-file-input">
									<i class="icon icon-folder-close"></i> Browse
								</label>
								<input type="file" id="image-file-input" name="imagefile" hidden>
								
								<span id="image-file-desc">No file selected</span>
							</td>
						</tr>
						<tr>
							<td class="text-center" colspan="2">
								<button type="submit" class="btn btn-lg">
									<i class="icon icon-upload"></i> Upload
								</button>
							</td>
						</tr>
					</table>
				</form>
				
				<p>Note:</p>
				<ul>
					<li>The proxy list is assumed to be in plaintext <code>*.*.*.*:port</code> or <code>*.*.*.*</code> format, one IP per line.</li>
				</ul>
			';
		}
	}

	function sql() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>SQL Query</h1>';
		
		if (isset($_POST['query'])) {
			$this->CheckToken($_POST['token']);
			
			$result = $tc_db->Execute($_POST['query']);
			if ($result) {
				$tpl_page .= '<div class="alert alert-green">Query executed successfully</div>';
			} else {
				$tpl_page .= '<div class="alert alert-red">'.$tc_db->ErrorMsg().'</div>';
			}
			
			management_addlogentry(_gettext('Inserted SQL'), 0);
		}
		
		$tpl_page .= '
			<form method="post" action="?action=sql">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				
				<table class="table table-post">
					<tr>
						<td class="text-right">
							<label for="query" class="label-required">Command:</label>
						</td>
						<td>
							<textarea name="query" id="query" class="input input-block" required></textarea>
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<button type="submit" class="btn btn-lg">
								<i class="icon icon-play-circle"></i> Execute SQL
							</button>
						</td>
					</tr>
				</table>
			</form>
			
			<p>Note:</p>
			<ul>
				<li>SQL queries (e.g. <code>SELECT * FROM kusaba</code>) will not return any meaningful results. It is used to execute SQL commands.</li>
			</ul>
		';
	}

	function cleanup() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		$tpl_page .= '
			<h1>Cleanup</h1>
			<div class="alert alert-red">
				It is advised to NOT use this function. It appears to be broken and not fixed.
				Please read this <a href="http://kusabax.cultnet.net/sup/res/59150.html" target="_blank">support thread</a>.
			</div>
		';

		if (isset($_POST['run'])) {
			$tpl_page .= '<hr />'. _gettext('Deleting non-deleted replies which belong to deleted threads.') .'<hr />';
			$this->delorphanreplies(true);
			$tpl_page .= '<hr />'. _gettext('Deleting unused images.') .'<hr />';
			$this->delunusedimages(true);
			$tpl_page .= '<hr />'. _gettext('Removing posts deleted more than one week ago from the database.') .'<hr />';
			$results = $tc_db->GetAll("SELECT `name`, `type`, `id` FROM `" . KU_DBPREFIX . "boards`");
			foreach ($results AS $line) {
				if ($line['type'] != 1) {
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line['id'] . " AND `IS_DELETED` = 1 AND `deleted_timestamp` < " . (time() - 604800) . "");
				}
			}
			$tpl_page .= _gettext('Optimizing all tables in database.') .'<hr />';
			if (KU_DBTYPE == 'mysql' || KU_DBTYPE == 'mysqli') {
				$results = $tc_db->GetAll("SHOW TABLES");
							foreach ($results AS $line) {
									$tc_db->Execute("OPTIMIZE TABLE `" . $line[0] . "`");
							}
			}
			if (KU_DBTYPE == 'postgres7' || KU_DBTYPE == 'postgres8' || KU_DBTYPE == 'postgres') {
								$results = $tc_db->GetAll("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'");
								foreach ($results AS $line) {
										$tc_db->Execute("VACUUM ANALYZE `" . $line[0] . "`");
								}
			}
			$tpl_page .= _gettext('Cleanup finished.');
			management_addlogentry(_gettext('Ran cleanup'), 2);
		} else {
			$tpl_page .= '
				<form action="manage_page.php?action=cleanup" method="post" onsubmit="return confirm(\'Are you sure?\')">
					<button class="btn btn-lg" type="submit" name="run">
						<i class="icon icon-warning-sign"></i> Run Cleanup
					</button>
				</form>
			';
		}
	}

	/*
	* +------------------------------------------------------------------------------+
	* Boards Administration Pages
	* +------------------------------------------------------------------------------+
	*/

	function adddelboard() {
		global $tc_db, $tpl_page, $board_class;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$notice = '';
		
		if (isset($_POST['directory'])) {
			$this->CheckToken($_POST['token']);
			if (isset($_POST['add'])) {
				$notice = $this->addBoard($_POST['directory'], $_POST['desc']);
			} elseif (isset($_POST['del'])) {
				if (isset($_POST['confirmation'])) {
					$notice = $this->delBoard($_POST['directory'], $_POST['confirmation']);
				} else {
					$notice = $this->delBoard($_POST['directory']);
				}
			}
		}
		$tpl_page .= '
			<h1>'. _gettext('Add board') . '</h1>'.$notice.'
		
			<form action="manage_page.php?action=adddelboard" method="post">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				<input type="hidden" name="add" id="add" value="add">
				
				<table class="table table-half table-sm">
					<tr>
						<td class="text-right">
							<label for="directory" class="label-required">Directory:</label><br>
							<small>Only letter(s) of the board directory, <span class="text-red">no slashes!</span></small>
						</td>
						<td>
							<input type="text" name="directory" id="directory" class="input input-block" required>
						</td>
					</tr>
					<tr>
						<td class="text-right">
							<label for="desc" class="label-required">Description:</label>
						</td>
						<td>
							<input type="text" name="desc" id="desc" class="input input-block" required>
						</td>
					</tr>
					<tr>
						<td class="text-right">
							<label for="firstpostid">First Post ID:</label><br>
							<small>The ID of the first post in the board</small>
						</td>
						<td>
							<input type="text" name="firstpostid" id="firstpostid" class="input input-block" value="1">
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<button type="submit" class="btn btn-lg">
								<i class="icon icon-plus"></i> Add Board
							</button>
						</td>
					</tr>
				</table>
			</form>
			
			<h1>Delete board</h1>

			<form action="manage_page.php?action=adddelboard" method="post" onsubmit="return confirm(\'Are you sure?\')">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				<input type="hidden" name="del" value="del">
				<input type="hidden" name="confirmation" value="yes">
				
				<table class="table table-sm">
					<tr>
						<td class="text-right">
							<label class="label-required">Directory:</label>
						</td>
						<td>
							'.($this->MakeBoardListDropdown('directory', $this->BoardList($_SESSION['manageusername']))).'
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<button type="submit" class="btn btn-lg">
								<i class="icon icon-remove"></i> Delete Board
							</button>
						</td>
					</tr>
				</table>
			</form>
		';
	}

	function addBoard($dir, $desc) {
		global $tc_db;
		$this->AdministratorsOnly();
		
		$output = '';
		
		$dir = cleanBoardName($dir);
		if ($dir != '' && $desc != '') {
			if (strtolower($dir) != 'allboards') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($dir) . "");
				if (count($results) == 0) {
					if (mkdir(KU_BOARDSDIR . $dir, 0777) && mkdir(KU_BOARDSDIR . $dir . '/res', 0777) && mkdir(KU_BOARDSDIR . $dir . '/src', 0777) && mkdir(KU_BOARDSDIR . $dir . '/thumb', 0777)) {
						file_put_contents(KU_BOARDSDIR . $dir . '/.htaccess', 'DirectoryIndex '. KU_FIRSTPAGE . '');
						file_put_contents(KU_BOARDSDIR . $dir . '/src/.htaccess', 'AddType text/plain .ASM .C .CPP .CSS .JAVA .JS .LSP .PHP .PL .PY .RAR .SCM .TXT'. "\n" . 'SetHandler default-handler');
						if ($_POST['firstpostid'] < 1) {
							$_POST['firstpostid'] = 1;
						}
						$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "boards` ( `name` , `desc` , `createdon`, `start`, `image`, `includeheader` ) VALUES ( " . $tc_db->qstr($dir) . " , " . $tc_db->qstr($desc) . " , '" . time() . "', " . $_POST['firstpostid'] . ", '', '' )");
						$boardid = $tc_db->Insert_Id();
						$filetypes = $tc_db->GetAll("SELECT " . KU_DBPREFIX . "filetypes.id FROM " . KU_DBPREFIX . "filetypes WHERE " . KU_DBPREFIX . "filetypes.filetype = 'JPG' OR " . KU_DBPREFIX . "filetypes.filetype = 'GIF' OR " . KU_DBPREFIX . "filetypes.filetype = 'PNG';");
						foreach ($filetypes AS $filetype) {
							$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid` , `typeid` ) VALUES ( " . $boardid . " , " . $filetype['id'] . " );");
						}
						$board_class = new Board($dir);
						$board_class->RegenerateAll();
						unset($board_class);
						
						$output .= '
							<div class="alert alert-green">Board successfully added</div>
							<form action="?action=boardopts" method="post" class="text-center">
								<input type="hidden" name="board" value="'. $dir . '">
								<button class="btn btn-lg">
									<i class="icon icon-pencil"></i> Edit /'.$dir.'/ Options
								</button>
							</form>
						';
						
						management_addlogentry(_gettext('Added board') . ': /'. $dir . '/', 3);
					} else {
						$output .= '<div class="alert alert-red">Unable to create directories</div>';
					}
				} else {
					$output .= '<div class="alert alert-red">A board with that name already exists</div>';
				}
			} else {
				$output .= '<div class="alert alert-red">System reserved name, please pick another</div>';
			}
		} else {
			$output .= '<div class="alert alert-red">Please fill in all required fields</div>';
		}
		return $output;
	}

	function delboard($dir, $confirm = '') {
		global $tc_db;
		$this->AdministratorsOnly();

		$output = '';
		
		if (!empty($dir)) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($dir) . "");
			foreach ($results as $line) {
				$board_id = $line['id'];
				$board_dir = $line['name'];
			}
			if (count($results) > 0) {
				if (!empty($confirm)) {
					if (removeBoard($board_dir)) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $board_id . "'");
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "boards` WHERE `id` = '" . $board_id . "'");
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $board_id . "'");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						
						$output .= '<div class="alert alert-green">Board successfully deleted</div>';
						
						management_addlogentry(_gettext('Deleted board') .': /'. $dir . '/', 3);
					} else {
						// Error
						$output .= '<div class="alert alert-red">Unable to delete board</div>';
					}
				} else {
					// handled with js
					/*$output .= sprintf(_gettext('Are you absolutely sure you want to delete %s?'),'/'. $board_dir . '/') .
					'<br />
					<form action="manage_page.php?action=adddelboard" method="post">
          <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
					<input type="hidden" name="del" id="del" value="del" />
					<input type="hidden" name="directory" id="directory" value="'. $dir . '" />
					<input type="hidden" name="confirmation" id="confirmation" value="yes" />

					<input type="submit" value="'. _gettext('Continue') .'" />

					</form><br />';*/
				}
			} else {
				$output .= '<div class="alert alert-red">Board does not exist</div>';
			}
		}
		
		return $output;
	}
	
	/* Replace words in posts with something else */
	function wordfilter() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>Wordfilter</h1>';
		$showOriginal = true;
		
		if (isset($_POST['word'])) {
			$this->CheckToken($_POST['token']);
			if ($_POST['word'] != '' && $_POST['replacedby'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `word` = " . $tc_db->qstr($_POST['word']) . "");
				if (count($results) == 0) {
					$wordfilter_boards = array();

					foreach ($results as $line) {
						$wordfilter_word = $line['word'];
					}
					$wordfilter_boards = array();
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach ($_POST['wordfilter'] as $board) {
						$check = $tc_db->GetOne("SELECT `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($board));
						if (!empty($check)) {
							$wordfilter_boards[] = $board;
						}
					}

					$is_regex = (isset($_POST['regex'])) ? '1' : '0';

					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "wordfilter` ( `word` , `replacedby` , `boards` , `time` , `regex` ) VALUES ( " . $tc_db->qstr($_POST['word']) . " , " . $tc_db->qstr($_POST['replacedby']) . " , " . $tc_db->qstr(implode('|', $wordfilter_boards)) . " , '" . time() . "' , '" . $is_regex . "' )");

					$tpl_page .= '<div class="alert alert-green">Word successfully added</div>';
					management_addlogentry(sprintf(_gettext("Added word to wordfilter: %s - Changes to: %s - Boards: /%s/"),$_POST['word'], $_POST['replacedby'], implode('/, /', $wordfilter_boards)), 11);
				} else {
					$tpl_page .= '<div class="alert alert-red">That word already exists</div>';
				}
			} else {
				$tpl_page .= '<div class="alert alert-red">Please fill in all required fields</div>';
			}
			
		} elseif (isset($_GET['delword'])) {
			if ($_GET['delword'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['delword']) . "");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$del_word = $line['word'];
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['delword']) . "");
					
					$tpl_page .= '<div class="alert alert-green">Word successfully removed</div>';
					
					management_addlogentry(_gettext('Removed word from wordfilter') . ': '. $del_word, 11);
				} else {
					$tpl_page .= '<div class="alert alert-red">That ID does not exist</div>';
				}
				
			}
		} elseif (isset($_GET['editword'])) {
			if ($_GET['editword'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['editword']) . "");
				if (count($results) > 0) {
					if (!isset($_POST['replacedby'])) {
						$showOriginal = false;
						
						foreach ($results as $line) {
							$tpl_page .= '
								<form action="manage_page.php?action=wordfilter&editword='.$_GET['editword'].'" method="post">
									<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
									
									<table class="table table-post table-sm">
										<tr>
											<td class="text-right">
												<label>Word:</label>
											</td>
											<td>
												<input type="text" class="input input-block" value="'.$line['word'].'" disabled>
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<label for="replacedby" class="label-required">Replacement:</label>
											</td>
											<td>
												<input type="text" name="replacedby" class="input input-block" value="'.$line['replacedby'].'" required>
											</td>
										</tr>
										<tr>
											<td></td>
											<td>
												<label class="btn">
													<input type="checkbox" name="regex" '.($line['regex'] == '1' ? 'checked' : '').'>
													Is Regular Expression?
												</label>
											</td>
										</tr>
										<tr>
											<td class="text-right">Boards:</td>
											<td class="table-col-btn">
							';
							
							$array_boards = array();
							$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							$existingboards = ($line['boards'] == '' ? array() : explode('|', $line['boards']));
							
							foreach ($resultsboard as $lineboard) {
								$array_boards = array_merge($array_boards, array($lineboard['name']));
							}
							foreach ($array_boards as $this_board_name) {
								$tpl_page .= '
									<label class="btn">
										<input type="checkbox" name="wordfilter[]" value="'.$this_board_name.'" '.(
											in_array($this_board_name, $existingboards) ? 'checked' : ''
										).'>
										/'.$this_board_name.'/
									</label>
								';
							}
							$tpl_page .= '
											</td>
										</tr>
										<tr>
											<td class="text-center" colspan="2">
												<a href="?action=wordfilter" class="btn btn-lg">
													<i class="icon icon-chevron-left"></i> Return
												</a>
												
												<button class="btn btn-lg" type="submit">
													<i class="icon icon-floppy-save"></i>
													Save
												</button>
											</td>
										</tr>
									</table>
								</form>
							';
						}
					} else {
						$this->CheckToken($_POST['token']);
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['editword']) . "");
						if (count($results) > 0) {
							foreach ($results as $line) {
								$wordfilter_word = $line['word'];
							}
							$wordfilter_boards = array();
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");

							if (isset($_POST['wordfilter'])){
								foreach ($_POST['wordfilter'] as $board) {
									$check = $tc_db->GetOne("SELECT `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($board));
									if (!empty($check)) {
										$wordfilter_boards[] = $board;
									}
								}
							}

							$is_regex = (isset($_POST['regex'])) ? '1' : '0';

							$tc_db->Execute("UPDATE `". KU_DBPREFIX ."wordfilter` SET `replacedby` = " . $tc_db->qstr($_POST['replacedby']) . " , `boards` = " . $tc_db->qstr(implode('|', $wordfilter_boards)) . " , `regex` = '" . $is_regex . "' WHERE `id` = " . $tc_db->qstr($_GET['editword']) . "");

							$tpl_page .= '<div class="alert alert-green">Word successfully updated</div>';
							
							management_addlogentry(_gettext('Updated word on wordfilter') . ': '. $wordfilter_word, 11);
						} else {
							$tpl_page .= '<div class="alert alert-red">Unable to locate that word</div>';
						}
					}
				} else {
					$tpl_page .= '<div class="alert alert-red">That ID does not exist</div>';
				}
			}
		}
		
		if ($showOriginal) {
			$tpl_page .= '
				<form action="manage_page.php?action=wordfilter" method="post">
					<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
					
					<table class="table table-post table-sm">
						<tr>
							<td class="text-right">
								<label for="word" class="label-required">Word:</label>
							</td>
							<td>
								<input type="text" id="word" name="word" class="input input-block" required>
							</td>
						</tr>
						<tr>
							<td class="text-right">
								<label for="replacedby" class="label-required">Replacement:</label>
							</td>
							<td>
								<input type="text" name="replacedby" class="input input-block" required>
							</td>
						</tr>
						<tr>
							<td></td>
							<td>
								<label class="btn">
									<input type="checkbox" name="regex">
									Is Regular Expression?
								</label>
							</td>
						</tr>
						<tr>
							<td class="text-right">Boards:</td>
							<td class="table-col-btn">
			';
			
			$array_boards = array();
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY name FROM `" . KU_DBPREFIX . "boards`");
			$array_boards = array_merge($array_boards, $resultsboard);
			$tpl_page .= $this->MakeBoardListCheckboxes('wordfilter', $array_boards);
			
			$tpl_page .= '
							</td>
						</tr>
						<tr>
							<td class="text-center" colspan="2">
								<button type="submit" class="btn btn-lg">
									<i class="icon icon-plus"></i> Add Word
								</button>
							</td>
						</tr>
					</table>
				</form>
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter`");
			$tpl_page .= '
				<table class="table table-border text-center">
					<tr>
						<th>Word</th>
						<th>Replacement</th>
						<th>Boards</th>
						<th colspan="2">Edit/Delete</th>
					</tr>
			';
			
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '
						<tr>
							<td>'. $line['word'] . '</td>
							<td>'. $line['replacedby'] . '</td>
							<td>
					';
					
					if ($line['boards'] == '') {
						$tpl_page .= 'No boards';
					}
					else {
						$tpl_page .= '/'.(implode('/, ', explode('|', $line['boards']))).'/';
					}
					
					$tpl_page .= '
							</td>
							<td>
								<a href="manage_page.php?action=wordfilter&editword='. $line['id'] . '" class="btn">
									<i class="icon icon-pencil"></i> Edit
								</a>
							</td>
							<td>
								<a href="manage_page.php?action=wordfilter&delword='. $line['id'] . '" class="btn">
									<i class="icon icon-remove"></i> Delete
								</a>
							</td>
					';
				}

			}
			else {
				$tpl_page .= '<tr><td colspan="5">No wordfilters</td></tr>';
			}
			
			$tpl_page .= '</table>';
		}
	}

	/* Ad Management */
	function ads() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Ad Management') .'</h2><span style="font-size: 100%; font-weight: 600;">'. _gettext('Anything can go here, such as banners, links, etc. It doesn\'t have to be just ads.') .'</span>'. "\n";

		if (isset($_GET['edit']) && ($_GET['edit'] == 1 || $_GET['edit'] == 2)) {
			if (isset($_POST['code']) and !empty($_POST['code'])) {
        $this->CheckToken($_POST['token']);
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "ads` SET `disp` = " . $tc_db->qstr($_POST['disp']) . ", `code` = " . $tc_db->qstr($_POST['code']) . " WHERE `id` = " . $tc_db->qstr($_GET['edit']) . "");
				$tpl_page .= '<hr /><h3>'. _gettext('Ad Edited.') .'</h3><p style="text-align: center;">'.sprintf(_gettext('Click %shere%s to return to Ad Management.'),'<a href="?action=ads">','</a>') .'</p><hr />'. "\n";
				management_addlogentry(_gettext('Edited an ad'));
			}
			$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "ads` WHERE `id` = '" . $_GET['edit'] . "'");
			foreach ($results as $ad) {
				$tpl_page .= '<form action="?action=ads&edit='. $_GET['edit'] . '" method="post">'. "\n" .
              '<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />' . "\n" .
							'<label for="pos">'. _gettext('Position') .':</label>'. "\n" .
							'<input type="text" disabled="disabled" name="pos" value="'. $ad['position'] . '" /><br />'. "\n" .
							'<label for="code">'. _gettext('Code') .':</label>'. "\n" .
							'<textarea name="code" rows="10" cols="25">' . htmlspecialchars($ad['code']) . '</textarea>' . "\n" .
							'<label for="disp">'. _gettext('Display') .':</label>'. "\n" .
							'<input type="text" maxlength="1" name="disp" value="'. $ad['disp'] . '" /><div class="desc">'. _gettext('Put <strong>0</strong> for no display, <strong>1</strong> to display.') .'</div><br />'. "\n" .
							'<input type="submit" value="'. _gettext('Edit') . '" /><br />'. "\n";
			}
		} else {
			$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "ads`");
			if (count($results) > 0) {
				$tpl_page .= '<table border="1">'. "\n";
				$tpl_page .= '<tr><th>'. _gettext('Position') .'</th><th>'. _gettext('Display') .'</th><th>'. _gettext('Code') .'</th><th>&nbsp;</th></tr>'. "\n";
				foreach ($results as $line) {
					$find = array('<', '>');
					$replace = array ('&lt;', '&gt;');
					if ($line['disp'] == 0) {
						$disp = 'Not Displayed';
					} elseif ($line['disp'] == 1) {
						$disp = _gettext('Displayed');
					}
					$tpl_page .= '<tr><td>'. $line['position'] . '</td><td>'. $disp . '</td><td>'. str_replace($find, $replace, $line['code']) . '</td><td><a href="?action=ads&edit='. $line['id'] . '">'. _gettext('Edit') .'</a></td></tr>'. "\n";
				}
				$tpl_page .= '</table>'. "\n";
			} else {
				$tpl_page .= _gettext('There was an error during install, and the ads table didn\'t get populated.');
			}
		}
	}

	/* Add or delete Embed Entries */
	function embeds() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();
		$disptable = true; $formval = 'add'; $title = _gettext('Embed Management');
		if(isset($_GET['act'])) {
			if ($_GET['act'] == 'edit') {
				if (isset($_POST['embeds'])) {
          $this->CheckToken($_POST['token']);
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "embeds` SET `filetype` = " . $tc_db->qstr(trim($_POST['filetype'])) . ", `videourl` = " . $tc_db->qstr(trim($_POST['videourl'])) . ", `name` = " . $tc_db->qstr(trim($_POST['name'])) . ", `width` = " . $tc_db->qstr(trim($_POST['width'])) . ", `height` = " . $tc_db->qstr(trim($_POST['height'])) . ", `code` = " . $tc_db->qstr(trim($_POST['embeds'])) . " WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
					$tpl_page .= '<hr /><h3>'. _gettext('Embed Edited') .'</h3><hr />';
					management_addlogentry(_gettext('Edited an embed'), 9);
				}
				$formval = 'edit&amp;id='. $_GET['id']; $title .= ' - Edit';
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "embeds` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				$values = $results[0];
				$disptable = false;
			} elseif ($_GET['act'] == 'del') {
				$results = $tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "embeds` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				$tpl_page .= '<hr /><h3>'. _gettext('Embed deleted') .'</h3><hr />';
				management_addlogentry(_gettext('Deleted an Embed'), 9);
			} elseif ($_GET['act'] == 'add') {
				if (isset($_POST['embeds']) && isset($_POST['name']) && isset($_POST['filetype']) && isset($_POST['videourl'])) {
					if ($_POST['embeds'] != '') {
            $this->CheckToken($_POST['token']);
						$width = ($_POST['width'] != '') ? $_POST['width'] : KU_YOUTUBEWIDTH;
						$height = ($_POST['height'] != '') ? $_POST['height'] : KU_YOUTUBEHEIGHT;

						$tpl_page .= '<hr />';
						if ($_POST['embeds'] != '') {
							$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "embeds` ( `name` , `filetype` , `videourl` , `width` , `height` , `code` ) VALUES ( " . $tc_db->qstr(trim($_POST['name'])) . " , " . $tc_db->qstr(trim($_POST['filetype'])) . " , " . $tc_db->qstr(trim($_POST['videourl'])) . " , " . $tc_db->qstr(trim($width)) . " , " . $tc_db->qstr(trim($height)) . " , " . $tc_db->qstr(trim($_POST['embeds'])) . " )");
							$tpl_page .= '<h3>'. _gettext('Embed successfully added.') . '</h3>';
							management_addlogentry(_gettext('Added an Embed'), 9);
						} else {
							$tpl_page .= _gettext('You must enter code.');
						}
						$tpl_page .= '<hr />';
					}
				}
			}
		}
		$tpl_page .= '<h2>'. $title . '</h2><br />
			<form method="post" action="?action=embeds&amp;act='. $formval . '">
      <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
			<label for="name">'. _gettext('Site Name') . ':</label>
			<input type="text" id="name" name="name" value="'. (isset($values['name']) ? $values['name'] : ''). '" />
			<div class="desc">'. _gettext('Can not be left blank.') . '</div><br />

			<label for="filetype">'. _gettext('Filetype') . ':</label>
			<input type="text" id="filetype" name="filetype" maxlength="3" value="'. (isset($values['filetype']) ? $values['filetype'] : '') . '" />
			<div class="desc">'. _gettext('Can not be left blank, or longer than 3 characters') . '</div><br />

			<label for="videourl">'. _gettext('Video URL Start') . ':</label>
			<input type="text" id="videourl" name="videourl" value="'. (isset($values['videourl']) ? $values['videourl'] : '') . '" />
			<div class="desc">'. _gettext('Can not be left blank. This is what comes before the embed ID. Example: \'http://www.youtube.com/watch?v=\'') . '</div><br />

			<label for="embeds">'. _gettext('Code') . ':</label>
			<textarea id="embeds" name="embeds" rows="25" cols="80">' . (isset($values['code']) ? htmlspecialchars($values['code']) : '') . '</textarea><br />

			<label for="width">'. _gettext('Width') . ':</label>
			<input type="text" id="width" name="width" value="'. (isset($values['width']) ? $values['width'] : '') . '" />
			<div class="desc">'. _gettext('This can be left blank. It will be reset with the default width set in config.php') . '</div><br />

			<label for="height">'. _gettext('Height') . ':</label>
			<input type="text" id="height" name="height" value="'. (isset($values['height']) ? $values['height'] : '') . '" />
			<div class="desc">'. _gettext('This can be left blank. It will be reset with the default height set in config.php') . '</div><br />

			<div class="desc">'. _gettext('When adding an embed, please check <a href="http://www.kusabax.org/wiki/embedding">http://www.kusabax.org/wiki/embedding</a> and check if there is a tutorial image for the site you are adding. Put this image in the /inc/embedhelp/ folder, or create your own in this folder if one does not exist. The image must be the same as the site name in all lowercase.') . '</div><br />
			<input type="submit" value="'. _gettext('Edit') .'" />
			</form>';
		if ($disptable) {
			$tpl_page .= '<br /><hr /><h1>'. _gettext('Edit/Delete Embeds') .'</h1>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "embeds` ORDER BY `id` ASC");
			if (count($results) > 0) {
				$find = array('<', '>');
				$replace = array ('&lt;', '&gt;');
				$tpl_page .= '<table border="1" width="100%"><tr><th>'. _gettext('Site Name') .'</th><th>'. _gettext('Filetype') .'</th><th>'. _gettext('Video URL Start') .'</th><th>'. _gettext('Width') .'</th><th>'. _gettext('Height') .'</th><th>'. _gettext('Code') .'</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr><td>'. $line['name'] . '</td><td>'. $line['filetype'] . '</td><td>'. $line['videourl'] . '</td><td>'. $line['width'] . '</td><td>'. $line['height'] . '</td><td>'. str_replace($find, $replace, $line['code']) . '</td><td>[<a href="?action=embeds&amp;act=edit&amp;id='. $line['id'] . '">'. _gettext('Edit') .'</a>] [<a href="?action=embeds&amp;act=del&amp;id='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>';
				}
				$tpl_page .= '</table>';
			} else {
				$tpl_page .= _gettext('No Embeds yet.');
		}
		}
	}


	function movethread() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>Move Thread</h1>';

		if (isset($_POST['id']) && isset($_POST['board_from']) && isset($_POST['board_to'])) {
			$this->CheckToken($_POST['token']);
			// Get the IDs for the from and to boards
			$board_from_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board_from']) . "");
			$board_to_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board_to']) . "");
			$board_from = $_POST['board_from'];
			$board_to = $_POST['board_to'];
			$id = $tc_db->qstr($_POST['id']);

			if (isset($_POST['mf'])) {
				$image		= $tc_db->GetOne("SELECT `file` FROM " .KU_DBPREFIX. "posts WHERE `boardid` = " .$board_from_id. " AND `id` = " .$id);
				$filetype	= $tc_db->GetOne("SELECT `file_type` FROM " .KU_DBPREFIX. "posts WHERE `boardid` = " .$board_from_id. " AND `id` = " .$id);
				$from_pic	= KU_BOARDSDIR . $board_from . '/src/'. $image . '.'. $filetype;
				$from_thumb	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 's'. '.'. $filetype;
				$from_cat	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 'c'. '.'. $filetype;
				$to_pic	= KU_BOARDSDIR . $board_to . '/src/'. $image . '.'. $filetype;
				$to_thumb	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 's'. '.'. $filetype;
				$to_cat	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 'c'. '.'. $filetype;
				@rename($from_pic, $to_pic);
				@rename($from_thumb, $to_thumb);
				@rename($from_cat, $to_cat);
				@unlink($from_pic);
				@unlink($from_thumb);
				@unlink($from_cat);
			}

			$from_html = KU_BOARDSDIR . $board_from . '/res/'. $_POST['id'] . '.html';
			$from_html_50 = KU_BOARDSDIR . $board_from . '/res/'. $_POST['id'] . '+50.html';
			$from_html_100 = KU_BOARDSDIR . $board_from . '/res/'. $_POST['id'] . '-100.html';
			@unlink($from_html);
			@unlink($from_html_50);
			@unlink($from_html_100);

			$tc_db->Execute("START TRANSACTION");
			$new_id = $tc_db->GetOne("SELECT COALESCE(MAX(id),0) + 1 FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_to_id);
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `id` = " . $new_id . ", `boardid` = " .$board_to_id. " WHERE `boardid` = " .$board_from_id. " AND `id` = " . $id);
			processPost($new_id, $new_id, $id, $board_from, $board_to, $board_to_id);

			$results = $tc_db->GetAll("SELECT `id` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " .$board_from_id. " AND `parentid` = " . $id . " ORDER BY `id` ASC");
			foreach ($results as $line) {
				if (isset($_POST['mf'])) {
					$image		= $tc_db->GetOne("SELECT `file` FROM `" .KU_DBPREFIX. "posts` WHERE `boardid` = " .$board_from_id. " AND `id` = " .$line['id']);
					$filetype	= $tc_db->GetOne("SELECT `file_type` FROM `" .KU_DBPREFIX. "posts` WHERE `boardid` = " .$board_from_id. " AND `id` = " .$line['id']);
					$from_pic	= KU_BOARDSDIR . $board_from . '/src/'. $image . '.'. $filetype;
					$from_thumb	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 's'. '.'. $filetype;
					$from_cat	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 'c'. '.'. $filetype;
					$to_pic	= KU_BOARDSDIR . $board_to . '/src/'. $image . '.'. $filetype;
					$to_thumb	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 's'. '.'. $filetype;
					$to_cat	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 'c'. '.'. $filetype;
					@rename($from_pic, $to_pic);
					@rename($from_thumb, $to_thumb);
					@rename($from_cat, $to_cat);
					@unlink($from_pic);
					@unlink($from_thumb);
					@unlink($from_cat);
				}
				$insert_id = $tc_db->GetOne("SELECT COALESCE(MAX(id),0) + 1 FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_to_id);
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `id` = " . $insert_id . ", `boardid` = " .$board_to_id. " WHERE `boardid` = " .$board_from_id. " AND `id` = " . $line['id']);
				processPost($insert_id, $new_id, $id, $board_from, $board_to, $board_to_id);
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `parentid` = " . $new_id . " WHERE `boardid` = " . $board_to_id . " AND `id` = " . $insert_id);
			}

			$tc_db->Execute("COMMIT");

			$board_class = new Board($board_from);
			$board_class->RegenerateThreads();
			$board_class->RegeneratePages();
			unset($board_class);

			$board_class = new Board($board_to);
			$board_class->RegenerateThreads();
			$board_class->RegeneratePages();
			unset($board_class);

			$tpl_page .= '<div class="alert alert-green">Move complete</div>';
		}

		$tpl_page .= '
			<form action="?action=movethread" method="post">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				
				<table class="table table-post table-sm">
					<tr>
						<td class="text-right">
							<label for="id" class="label-required">Thread ID:</label>
						</td>
						<td>
							<input type="text" name="id" id="id" class="input input-block" required>
						</td>
					</tr>
					<tr>
						<td class="text-right">
							<label for="board_from" class="label-required">From:</label>
						</td>
						<td>
							'.$this->MakeBoardListDropdown('board_from', $this->BoardList($_SESSION['manageusername'])).'
						</td>
					</tr>
					<tr>
						<td class="text-right">
							<label for="board_to" class="label-required">To:</label>
						</td>
						<td>
							'.$this->MakeBoardListDropdown('board_to', $this->BoardList($_SESSION['manageusername'])).'
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<label class="btn btn-lg">
								<input type="checkbox" name="mf">
								Move Files Too?
							</label>
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<button type="submit" class="btn btn-lg">
								<i class="icon icon-transfer"></i>
								Move Thread
							</button>
						</td>
					</tr>
				</table>
			</form>
		';
	}

	/* Search for posts by IP */
	function ipsearch() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('IP Address Search') .'</h2><br />'. "\n";

		if (isset($_GET['ip']) && !empty($_GET['board'])) {
			if ($_GET['board'] == 'all') {
				$queryextra = "";
			} else {
				$queryextra = "`boardid` IN (" . $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "") . ") AND";
			}

			$results = $tc_db->GetAll("SELECT `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`ip` AS ip, `" . KU_DBPREFIX . "posts`.`message` AS message, `" . KU_DBPREFIX . "posts`.`file` AS file, `" . KU_DBPREFIX . "posts`.`file_type` AS file_type, `" . KU_DBPREFIX . "boards`.`name` AS boardname FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX . "boards` WHERE ".$queryextra." `ipmd5` = '" . md5($_GET['ip']) . "' AND `IS_DELETED` = 0 AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `boardid`");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<table border="1" width="100%">'. "\n" .
					'<tr><th width="10%">'. _gettext('Post Number') .'</th><th width="10%">'. _gettext('File') .'</th><th width="70%">'. _gettext('Message') .'</th><th width=10%">'. _gettext('IP Address') .'</th></tr>'. "\n";

					$real_parentid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];

					$tpl_page .= '<tr><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '">/'. $line['boardname'] . '/'. $line['id'] . '</td><td>'. (($line['file_type'] == 'jpg' || $line['file_type'] == 'gif' || $line['file_type'] == 'png') ? ('<a href="'. KU_WEBPATH .'/'. $line['boardname'] . '/src/'. $line['file'] . '.'. $line['file_type'] . '"><img border=0 src="'. KU_WEBPATH .'/'. $line['boardname'] . '/thumb/'. $line['file'] . 's.'. $line['file_type'] . '"></a>') : ('')) . '</td><td>'. $line['message'] . '</td><td>'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</tr>';
				}
				$tpl_page .= '</table>'. "\n";
			} else {
			$tpl_page .= _gettext('No results found for') .' '. $_GET['ip'] . '<br />'. "\n";
			}
		} else {
			$tpl_page .= '<form action="?" method="get">'. "\n" .
						'<input type="hidden" name="action" value="ipsearch" />'. "\n" .
						'<label for="board">'. _gettext('Board') . ':</label>'. "\n" .
						$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername']), true) . '<br />'. "\n" .
						'<label for="ip">'. _gettext('IP') . ':</label>'. "\n" .
						'<input type="text" name="ip" value="'. (isset($_GET['ip']) ? $_GET['ip'] : ''). '" /><br />'. "\n" .
						'<input type="submit" value="'. _gettext('IP Search') . '" />'. "\n";
		}
	}

	/* Search for text in posts */
	function search() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		if (isset($_GET['query'])) {
			$search_query = $_GET['query'];
			if (isset($_GET['s'])) {
				$s = $_GET['s'];
			} else {
				$s = 0;
			}
			$search_query_array = explode('KUSABA_AND', $search_query);
			$trimmed = trim($search_query);
			$limit = 10;
			if ($trimmed == '') {
				$tpl_page .= _gettext('Please enter a search query.');
				exit;
			}
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			$likequery = '';
			foreach ($search_query_array as $search_split) {
				$likequery .= "`message` LIKE " . $tc_db->qstr(str_replace('_', '\_', $search_split)) . " AND ";
			}
			$likequery = substr($likequery, 0, -4);
			$query = '';
			$query .= "SELECT `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`message` AS message, `" . KU_DBPREFIX . "boards`.`name` AS boardname FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX . "boards` WHERE `IS_DELETED` = 0 AND " . $likequery . " AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `timestamp` DESC";

			$numresults = $tc_db->GetAll($query);
			$numrows = count($numresults);
			if ($numrows == 0) {
				$tpl_page .= '<h4>'. _gettext('Results') . '</h4>';
				$tpl_page .= '<p>'. _gettext('Sorry, your search returned zero results.') . '</p>';
			} else {
				$query .= " LIMIT $limit OFFSET $s";
				$results = $tc_db->GetAll($query);
				$tpl_page .= '<p style="font-size: 1.5em;">'. _gettext('Results for') .': <strong>'. $search_query . '</strong></p>';
				$count = 1 + $s;
				foreach ($results as $line) {
					$tpl_page .= '<span style="font-size: 1.5em;">'. $count . '.</span> <span style="font-size: 1.3em;">'. _gettext('Board') .': /'. $line['boardname'] . '/, <a href="'.KU_BOARDSPATH . '/'. $line['boardname'] . '/res/';
					if ($line['parentid'] == 0) {
						$tpl_page .= $line['id'] . '.html">';
					} else {
						$tpl_page .= $line['parentid'] . '.html#'. $line['id'] . '">';
					}

					if ($line['parentid'] == 0) {
						$tpl_page .= _gettext('Thread') .' #'. $line['id'];
					} else {
						$tpl_page .= _gettext('Thread') .' #'. $line['parentid'] . ', Post #'. $line['id'];
					}
					$tpl_page .= '</a></span>';

					$regexp = '/(';
					foreach ($search_query_array as $search_word) {
						$regexp .= preg_quote($search_word) . '|';
					}
					$regexp = substr($regexp, 0, -1) . ')/';
					//$line['message'] = preg_replace_callback($regexp, array(&$this, 'search_callback'), stripslashes($line['message']));
					$line['message'] = stripslashes($line['message']);
					$tpl_page .= '<fieldset>'. $line['message'] . '</fieldset><br />';
					$count++;
				}
				$currPage = (($s / $limit) + 1);
				$tpl_page .= '<br />';
				if ($s >= 1) {
					$prevs = ($s - $limit);
					$tpl_page .= "&nbsp;<a href=\"?action=search&s=$prevs&query=" . urlencode($search_query) . "\">&lt;&lt; "._gettext('Prev')." 10</a>&nbsp&nbsp;";
				}
				$pages = intval($numrows / $limit);
				if ($numrows % $limit) {
					$pages++;
				}
				if (!((($s + $limit) / $limit) == $pages) && $pages != 1) {
					$news = $s + $limit;
					$tpl_page .= "&nbsp;<a href=\"?action=search&s=$news&query=" . urlencode($search_query) . "\">"._gettext('Next')." 10 &gt;&gt;</a>";
				}

				$a = $s + ($limit);
				if ($a > $numrows) {
					$a = $numrows;
				}
				$b = $s + 1;

				$tpl_page .= $this->search_results_display($a, $b, $numrows);
			}
		}

		$tpl_page .= '<form action="?" method="get">
		<input type="hidden" name="action" value="search" />
		<input type="hidden" name="s" value="0" />

		<strong>'. _gettext('Query') .'</strong>:<br /><input type="text" name="query" ';
		if (isset($_GET['query'])) {
			$tpl_page .= 'value="'. $_GET['query'] . '" ';
		}
		$tpl_page .= 'size="52" /><br />

		<input type="submit" value="'. _gettext('Search') .'" /><br /><br />

		<h1>'. _gettext('Search Help') .'</h1>

		'. _gettext('Separate search terms with the word <strong>KUSABA_AND</strong>') .' <br /><br />

		'. _gettext('To find a single phrase anywhere in a post\'s message, use:') .'<br />
		%'. _gettext('some phrase here') .'%<br /><br />

		'. _gettext('To find a phrase at the beginning of a post\'s message:') .'<br />
		'. _gettext('some phrase here') .'%<br /><br />

		'. _gettext('To find a phrase at the end of a post\'s message:') .'<br />
		%'. _gettext('some phrase here') .'<br /><br />

		'. _gettext('To find two phrases anywhere in a post\'s message, use:') .'<br />
		%'. _gettext('first phrase here') .'%KUSABA_AND%'. _gettext('second phrase here') .'%<br /><br />

		</form>';
	}
	function search_callback($matches) {
		print_r($matches);
		return '<strong>'. $matches[0] . '</strong>';
	}

	function search_results_display($a, $b, $numrows) {
		return '<p>'. _gettext('Results') . ' <strong>'. $b . '</strong> to <strong>'. $a . '</strong> of <strong>'. $numrows . '</strong></p>'. "\n" .
		'<hr />';
	}
	// Credits to Eman for this code
	function viewthread() {
		global $tc_db, $tpl_page;
		$tpl_page .= '<h2>'. _gettext('View Threads (including deleted)') .'</h2>';
		$board = isset($_GET['board']) ? $_GET['board'] : '';
		$thread = isset($_GET['thread']) ? $_GET['thread'] : '';
		if (!$thread ) {
			$thread = "0";
		}
		if (!$board) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name`, `id` FROM `". KU_DBPREFIX . "boards` ORDER BY `name` ASC");
			$tpl_page .= "
				<style type=\"text/css\">
				input {
					display: inline !important;
					width: auto !important;
					float: none !important;
					margin-bottom: 0px !important;
				}
				th,td {
					font-size: 14px !important;
				}
				</style>";
			$tpl_page .= '<form method="get" action=""><input type="hidden" name="action" value="viewthread" />'. _gettext('Select Board') .': <select name="board">';
			foreach ($results as $line) {
				$name = $line['name'];
				$id =	$line['id'];
				$tpl_page .= "<option value=\"$name\">/$name/</option>";
			}
			$tpl_page .= '</select>&nbsp;<input type=submit value="'. _gettext('Go') .'" />';
		} else {
			$board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `". KU_DBPREFIX . "boards` WHERE `name` = ".$tc_db->qstr($board));
			$tpl_page .= "
				<style type=\"text/css\">
				input {
					display: inline !important;
					width: auto !important;
					float: none !important;
					margin-bottom: 0px !important;
				}
				th,td {
					font-size: 14px !important;
				}
				</style>
				<form method=\"get\" action=\"\">
				<input type=\"hidden\" name=\"action\" value=\"viewthread\" />
				<input type=\"hidden\" name=\"board\" value=\"$board\" />";
			if ($thread == "0" ) {
				$tpl_page .= "<h2>". sprintf(_gettext('All threads on /%s/'), $board) ."</h2>";
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = $board_id AND (`id` = ".$tc_db->qstr($thread)." OR `parentid` = ".$tc_db->qstr($thread).") ORDER BY `id` DESC LIMIT 500");
			} else {
				$tpl_page .= "<h2>". sprintf(_gettext('Thread %s on /%s/'), $thread, $board) ."</h2>";
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = $board_id AND (`id` = ".$tc_db->qstr($thread)." OR `parentid` = ".$tc_db->qstr($thread).") ORDER BY `id` ASC");
			}
			$time = round(microtime(), 5);
			$first = "1";
			foreach ($results as $line) {
				$bans = "";
				$id = $line['id'];
				$ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
				$filename = $line['file'];
				$file_original = $line['file_original'];
				$filetype = $line['file_type'];
				$filesize_formatted = $line['file_size_formatted'];
				$image_w = $line['image_w'];
				$image_h = $line['image_h'];
				$message = $line['message'];
				$name = $line['name'];
				$tripcode = $line['tripcode'];
				$timestamp = date(r, $line['timestamp']);
				$subject = $line['subject'];
				$posterauthority = $line['posterauthority'];
				$deleted = isset($line['IS_DELETED']) ? $line['IS_DELETED'] : $line['is_deleted'] ;
				if ($thread == "0") {
					$view = "<a href=\"?action=viewthread&board=$board&thread=$id\">[". _gettext('View') ."]</a>";
				} else {
					$view = "";
				}
				if ($name == "") {
					$name = _gettext('Anonymous');
				} else {
					$name = "<font color=\"blue\">$name</font>";
				}
				if ($tripcode != "") {
					$tripcode = "<font color=\"green\">!$tripcode</font>";
				}
				if ($subject != "") {
					$subject = "<font color=\"red\">$subject</font> - ";
				}
				if ($posterauthority == "1") {
					$posterauthority = "<font color=\"purple\"><strong>##Admin##</strong></font>";
				} elseif ($posterauthority == "2") {
					$posterauthority = "<font color=\"red\"><strong>##Mod##</strong></font>";
				} else {
					$posterauthority = "";
				}
				if ($deleted == "1") {
					$deleted = "<font color=green><blink><strong>". _gettext('DELETED') ."</strong></blink></font> - ";
				} else {
					$deleted = "";
					if ($first == "1") {
						$bans = "</td><td width=80px style=\"text-align: right; vertical-align: top;\">[<a href=\"manage_page.php?action=&boarddir=$board&delthreadid=$id\">D</a> <a href=\"manage_page.php?action=delposts&boarddir=$board&delthreadid=$id&postid=$id\">&amp;</a> <a href=\"manage_page.php?action=bans&banboard=$board&banpost=$id\">B</a>]";
					} else {
						$bans = "</td><td width=80px style=\"text-align: right; vertical-align: top;\">[<a href=\"manage_page.php?action=delposts&boarddir=$board&delpostid=$id\">D</a> <a href=\"manage_page.php?action=delposts&boarddir=$board&delpostid=$id&postid=$id\">&amp;</a> <a href=\"manage_page.php?action=bans&banboard=$board&banpost=$id\">B</a>]";
					}
				}


				if ($bans == "") {
				$bans = "</td><td>&nbsp;</td>";
				}
				$tpl_page .= "
					<table style=\"text-align: left; width: 100%;\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\">
						<tbody>
							<tr>
								<td style=\"vertical-align: top;\">$deleted$subject$name$tripcode $posterauthority $timestamp ". _gettext('No.') ." $id ". _gettext('IP') .": $ip $view $bans
								</td>
							</tr>
				";
				if ($filename != "") {
					$tpl_page .= "
						<tr>
							<td colspan=\"2\" style=\"vertical-align: top;\">". _gettext('File') .": <a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=_new>$filename.$filetype</a> -( $filesize_formatted, {$image_w}x{$image_h}, $file_original.$filetype )</td>
						</tr>
					";
				}
				$tpl_page .= "
				</tbody></table>
								<table style=\"text-align: left; width: 100%;\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\">
						<tbody>
				<tr>";


				if ($filename != "") {
					$tpl_page .= "
						<td style=\"vertical-align: top; width: 200px;\"><center><a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=\"_new\"><img src=\"". KU_WEBPATH ."/$board/thumb/{$filename}s.$filetype\" border=\"0\"></a></center></td>
					";
				}
				if ($message == "") {
					$message = "&nbsp;";
				}
				$tpl_page .= "<td style=\"vertical-align: top; height: 100%;\">$message</td></tr></tbody></table><br />";
				$first = "0";
			}
			$time2 = round(microtime(), 5);
			$generation = $time2 - $time;
			$generation = abs($generation);
			$tpl_page .= '
			'. _gettext('Render Time') .':'. $generation .' '._gettext('Seconds').'
			<!--<h2>'. _gettext('Ban') .'</h2>
			'. _gettext('Reason') .': <input type="text" name="banreason" value="'. _gettext('You Are Banned') .'" />&nbsp;&nbsp;
			'. _gettext('Duration') .': <input type="text" name="banduration" value="0" />&nbsp;&nbsp;
			'. _gettext('Appeal') .': <input type="text" name="banappeal" value="0" />&nbsp;&nbsp;
			<input type="submit" value="'. _gettext('Submit') .'" />-->
			</form>';
		}
	}

	/* View a thread marked as deleted */
	function viewdeletedthread() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('View deleted thread') . '</h2><br />'. "\n";

		if (isset($_GET['board']) && isset($_GET['threadid']) && $_GET['threadid'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
			foreach ($results as $line) {
				$board_id = $line['id'];
				$board_dir = $line['name'];
			}
			if (count($results) > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `id` = " . $tc_db->qstr($_GET['threadid']) . "");
				foreach ($results as $line) {
					$thread_isdeleted = $line['IS_DELETED'];
					$thread_parentid = $line['parentid'];
				}
				if ($thread_isdeleted == 1 && $thread_parentid == 0) {
					foreach ($results as $line) {
						if ($line['name'] != '') {
							$name = $line['name'];
						} else {
							$name =  _gettext('Anonymous') .' ';
						}
						$tpl_page .= '<div style="width: 75%; border: 1px solid #CCC; padding: 5px;">'. "\n";
						$tpl_page .= $name . $line['tripcode'] . formatDate($line['postedat']) . ' | '. _gettext('No.') .' '. $line['id'] . ' | '. _gettext('IP') .': '. md5_decrypt($line['ip'], KU_RANDOMSEED) . '<br />'. "\n";
						if (isset($line['filename'])) {
							$tpl_page .= '<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank">'. $line['filename'] . '.'. $line['filetype'] . '</a><br />'. "\n" .
										'<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank"><img src="'. KU_WEBPATH . '/'. $_GET['board'] . '/thumb/'. $line['filename'] . 's.'. $line['filetype'] . '" border="0" alt="'. $line['filename'] . '.'. $line['filetype'] . '" /></a>'. "\n";
						}
						$tpl_page .= $line['message'];
						$tpl_page .= '</div><br />';
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `parentid` = " . $tc_db->qstr($_GET['threadid']) . "");
					foreach ($results as $line) {
						if ($line['name'] != '') {
							$name = $line['name'] . ' ';
						} else {
							$name =  _gettext('Anonymous') .' ';
						}
						$tpl_page .= '<div style="width: 75%; border: 1px solid #CCC; padding: 5px;">'. "\n";
						$tpl_page .= $name . $line['tripcode'] . formatDate($line['postedat']) . ' | No. '. $line['id'] . ' | IP: '. md5_decrypt($line['ip'], KU_RANDOMSEED) . '<br />'. "\n";
						if ($line['filename'] != '') {
							$tpl_page .= '<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank">'. $line['filename'] . '.'. $line['filetype'] . '</a><br />'. "\n" .
										'<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank"><img src="'. KU_WEBPATH . '/'. $_GET['board'] . '/thumb/'. $line['filename'] . 's.'. $line['filetype'] . '" border="0" alt="'. $line['filename'] . '.'. $line['filetype'] . '" /></a>'. "\n";
						}
						$tpl_page .= $line['message'];
						$tpl_page .= '</div><br />';
					}
				} else {
					$tpl_page .=  _gettext('That\'s either not a thread, or it\'s not deleted.') ;
				}
			}
		} else {

		$tpl_page .= '<form method="get" action="?">'. "\n" .
					'<input type="hidden" name="action" value="viewthread" />'. "\n" .
					'<label for="board">'. _gettext('Board') . ':</label>'. "\n" .
					$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) . "\n" .
					'<br />'. "\n" .
					'<label for="threadid">'. _gettext('Thread') . ':</label>'. "\n" .
					'<input type="text" name="threadid" /><br />'. "\n" .
					'<input type="submit" value="'. _gettext('View deleted thread') . '" />'. "\n" .
					'</form>';
		}
	}

	/* Add, view, and delete filetypes */
	function editfiletypes() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h1>Edit Filetypes</h1>';
		$showAddBtn = true;
		
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addfiletype') {
				if (isset($_POST['filetype']) || isset($_POST['image'])) {
					$this->CheckToken($_POST['token']);
					if ($_POST['filetype'] != '' && $_POST['image'] != '') {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "filetypes` ( `filetype` , `mime` , `image` , `image_w` , `image_h` ) VALUES ( " . $tc_db->qstr($_POST['filetype']) . " , " . $tc_db->qstr($_POST['mime']) . " , " . $tc_db->qstr($_POST['image']) . " , " . $tc_db->qstr($_POST['image_w']) . " , " . $tc_db->qstr($_POST['image_h']) . " )");
						
						$tpl_page .= '<div class="alert alert-green">Filetype added</div>';
					}
				} else {
					$showAddBtn = false;
					
					$tpl_page .= '
						<form action="?action=editfiletypes&do=addfiletype" method="post">
							<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
							
							<table class="table table-half table-sm">
								<tr>
									<td class="text-right">
										<label for="filetype" class="label-required">Filetype:</label><br>
										<small class="text-red">
											Must be lowercase.
										</small>
									</td>
									<td>
										<input type="text" name="filetype" id="filetype" class="input input-block" required>
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label for="mime">MIME type:</label><br>
										<small>
											The MIME type which must be present with an image uploaded in this type. Leave blank to disable.
										</small>
									</td>
									<td>
										<input type="text" name="mime" id="mime" class="input input-block">
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label for="image">Image:</label><br>
										<small>
											The image which will be used, found in <span class="text-red">inc/filetypes</span>.
										</small>
									</td>
									<td>
										<input type="text" name="image" id="image" class="input input-block" value="generic.png">
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label for="image_w">Image width:</label><br>
										<small>
											The width of the image. Needs to be set to prevent the page from jumping around while images load.
										</small>
									</td>
									<td>
										<input type="text" name="image_w" id="image_w" class="input input-block" value="48">
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label for="image_h">Image height:</label><br>
										<small>
											The height of the image. Needs to be set to prevent the page from jumping around while images load.
										</small>
									</td>
									<td>
										<input type="text" name="image_h" id="image_h" class="input input-block" value="48">
									</td>
								</tr>
								<tr>
									<td class="text-center" colspan="2">
										<button class="btn btn-lg" type="submit">
											<i class="icon icon-plus"></i> Add
										</button>
									</td>
								</tr>
							</table>
						</form>
					';
				}
				
			}
			if ($_GET['do'] == 'editfiletype' && $_GET['filetypeid'] > 0) {
				if (isset($_POST['filetype'])) {
					if ($_POST['filetype'] != '' /*&& $_POST['image'] != ''*/) {
						$this->CheckToken($_POST['token']);
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "filetypes` SET `filetype` = " . $tc_db->qstr($_POST['filetype']) . " , `mime` = " . $tc_db->qstr($_POST['mime']) . " , `image` = " . $tc_db->qstr($_POST['image']) . " , `image_w` = " . $tc_db->qstr($_POST['image_w']) . " , `image_h` = " . $tc_db->qstr($_POST['image_h']) . " WHERE `id` = " . $tc_db->qstr($_GET['filetypeid']) . "");
						if (KU_APC) {
							apc_delete('filetype|'. $_POST['filetype']);
						}
						
						$tpl_page .= '<div class="alert alert-green">Filetype updated</div>';
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = " . $tc_db->qstr($_GET['filetypeid']) . "");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$showAddBtn = false;
							
							$tpl_page .= '
								<form action="?action=editfiletypes&do=editfiletype&filetypeid='. $_GET['filetypeid'] . '" method="post">
									<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
									
									<table class="table table-half table-sm">
										<tr>
											<td class="text-right">
												<label for="filetype" class="label-required">Filetype:</label><br>
												<small class="text-red">
													Must be lowercase.
												</small>
											</td>
											<td>
												<input type="text" name="filetype" id="filetype" class="input input-block" value="'. $line['filetype'] . '" required>
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<label for="mime">MIME type:</label><br>
												<small>
													The MIME type which must be present with an image uploaded in this type. Leave blank to disable.
												</small>
											</td>
											<td>
												<input type="text" name="mime" id="mime" class="input input-block" value="'. $line['mime'] . '">
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<label for="image">Image:</label><br>
												<small>
													The image which will be used, found in <span class="text-red">inc/filetypes</span>.
												</small>
											</td>
											<td>
												<input type="text" name="image" id="image" class="input input-block" value="'. $line['image'] . '">
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<label for="image_w">Image width:</label><br>
												<small>
													The width of the image. Needs to be set to prevent the page from jumping around while images load.
												</small>
											</td>
											<td>
												<input type="text" name="image_w" id="image_w" class="input input-block"  value="'. $line['image_w'] . '">
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<label for="image_h">Image height:</label><br>
												<small>
													The height of the image. Needs to be set to prevent the page from jumping around while images load.
												</small>
											</td>
											<td>
												<input type="text" name="image_h" id="image_h" class="input input-block"  value="'. $line['image_h'] . '">
											</td>
										</tr>
										<tr>
											<td class="text-center" colspan="2">
												<a href="?action=editfiletypes&do=addfiletype" class="btn btn-lg">
													<i class="icon icon-chevron-left"></i> Return
												</a>
												<button class="btn btn-lg" type="submit">
													<i class="icon icon-floppy-save"></i> Save
												</button>
											</td>
										</tr>
									</table>
								</form>
							';
						}
					} else {
						$tpl_page .= '<div class="alert alert-red">Unable to locate a filetype with that ID</div>';
					}
				}
				
			}
			if ($_GET['do'] == 'deletefiletype' && $_GET['filetypeid'] > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = " . $tc_db->qstr($_GET['filetypeid']) . "");
				
				$tpl_page .= '<div class="alert alert-green">Filetype deleted</div>';
			}
		}
		
		if ($showAddBtn) {
			$tpl_page .= '
				<div class="text-center">
					<a href="?action=editfiletypes&do=addfiletype" class="btn btn-lg">
						<i class="icon icon-chevron-left"></i> Return
					</a>
				</div>
			';
		}
		
		$tpl_page .= '
			<table class="table table-border text-center">
				<tr>
					<th>ID</th>
					<th>Filetype</th>
					<th>Image</th>
					<th colspan="2">Edit/Delete</th>
				</tr>
		';
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '
					<tr>
						<td>'. $line['id'] . '</td>
						<td>'. $line['filetype'] . '</td>
						<td>'. $line['image'] . '</td>
						<td>
							<a href="?action=editfiletypes&do=editfiletype&filetypeid='. $line['id'] . '" class="btn">
								<i class="icon icon-pencil"></i> Edit
							</a>
						</td>
						<td>
							<a href="?action=editfiletypes&do=deletefiletype&filetypeid='. $line['id'] . '" class="btn" onclick="return confirm(\'Are you sure?\')">
								<i class="icon icon-remove"></i> Delete
							</a>
						</td>
					</tr>
				';
			}
		} else {
			$tpl_page .= '<tr><td colspan="5">There are currently no filetypes</td></tr>';
		}
		
		$tpl_page .= '</table>';
	}

	/* Add, view, and delete sections */
	function editsections() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>Edit Sections</h1>';
		
		$showAddBtn = true;
		
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addsection') {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
						$this->CheckToken($_POST['token']);
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "sections` ( `name` , `abbreviation` , `order` , `hidden` ) VALUES ( " . $tc_db->qstr($_POST['name']) . " , " . $tc_db->qstr($_POST['abbreviation']) . " , " . $tc_db->qstr($_POST['order']) . " , '" . (isset($_POST['hidden']) ? '1' : '0') . "' )");
						
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						
						$menu_class = new Menu();
						$menu_class->Generate();
						
						$tpl_page .= '<div class="alert alert-green">Section added</div>';
					}
				} else {
					$showAddBtn = false;
		
					$tpl_page .= '
						<form action="?action=editsections&do=addsection" method="post">
							<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
							
							<table class="table table-half table-sm">
								<tr>
									<td class="text-right">
										<label for="name" class="label-required">Name:</label><br>
										<small>The name of the section</small>
									</td>
									<td>
										<input type="text" name="name" id="name" class="input input-block" required>
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label for="abbreviation" class="label-required">Abbreviation:</label><br>
										<small>Max 10 characters</small>
									</td>
									<td>
										<input type="text" name="abbreviation" id="abbreviation" class="input input-block" maxlength="10" required>
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label for="order">Order:</label><br>
										<small>Max 10 characters</small>
									</td>
									<td>
										<input type="text" name="order" id="order" class="input input-block">
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<small>If checked, this section will be collapsed by default when a user visits the site.</small>
									</td>
									<td>
										<label class="btn">
											<input type="checkbox" name="hidden">
											Collapsed by default?
										</label>
									</td>
								</tr>
								<tr>
									<td class="text-center" colspan="2">
										<button class="btn btn-lg" type="submit">
											<i class="icon icon-plus"></i> Add
										</button>
									</td>
								</tr>
							</table>
						</form>
					';
				}
				
			}
			if ($_GET['do'] == 'editsection' && $_GET['sectionid'] > 0) {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
            $this->CheckToken($_POST['token']);
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "sections` SET `name` = " . $tc_db->qstr($_POST['name']) . " , `abbreviation` = " . $tc_db->qstr($_POST['abbreviation']) . " , `order` = " . $tc_db->qstr($_POST['order']) . " , `hidden` = '" . (isset($_POST['hidden']) ? '1' : '0') . "' WHERE `id` = '" . $_GET['sectionid'] . "'");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						
						$tpl_page .= '<div class="alert alert-green">Section updated</div>';
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` WHERE `id` = " . $tc_db->qstr($_GET['sectionid']) . "");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$showAddBtn = false;
							
							$tpl_page .= '
								<form action="?action=editsections&do=editsection&sectionid='. $_GET['sectionid'] . '" method="post">
									<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
									<input type="hidden" name="id" value="'. $_GET['sectionid'] . '">
									
									<table class="table table-half table-sm">
										<tr>
											<td class="text-right">
												<label for="name" class="label-required">Name:</label><br>
												<small>The name of the section</small>
											</td>
											<td>
												<input type="text" name="name" id="name" class="input input-block" value="'. $line['name'] . '" required>
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<label for="abbreviation" class="label-required">Abbreviation:</label><br>
												<small>Max 10 characters</small>
											</td>
											<td>
												<input type="text" name="abbreviation" id="abbreviation" class="input input-block" maxlength="10" value="'. $line['abbreviation'] . '" required>
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<label for="order">Order:</label><br>
												<small>Max 10 characters</small>
											</td>
											<td>
												<input type="text" name="order" id="order" class="input input-block" value="'. $line['order'] . '">
											</td>
										</tr>
										<tr>
											<td class="text-right">
												<small>If checked, this section will be collapsed by default when a user visits the site.</small>
											</td>
											<td>
												<label class="btn">
													<input type="checkbox" name="hidden" '.($line['hidden'] == 0 ? '' : 'checked').'>
													Collapsed by default?
												</label>
											</td>
										</tr>
										<tr>
											<td class="text-center" colspan="2">
												<a href="?action=editsections&do=addsection" class="btn btn-lg">
													<i class="icon icon-chevron-left"></i> Return
												</a>
												
												<button class="btn btn-lg" type="submit">
													<i class="icon icon-plus"></i> Add
												</button>
											</td>
										</tr>
									</table>
								</form>
							';
						}
					} else {
						$tpl_page .= '<div class="alert alert-red">Unable to locate a section with that ID</div>';
					}
				}
				
			}
			if ($_GET['do'] == 'deletesection' && isset($_GET['sectionid'])) {
				if ($_GET['sectionid'] > 0) {
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "sections` WHERE `id` = " . $tc_db->qstr($_GET['sectionid']) . "");
					require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
					$menu_class = new Menu();
					$menu_class->Generate();
					
					$tpl_page .= '<div class="alert alert-red">Section deleted</div>';
				}
			}
		}
		
		if ($showAddBtn) {
			$tpl_page .= '
				<div class="text-center">
					<a href="?action=editsections&do=addsection" class="btn btn-lg">
						<i class="icon icon-chevron-left"></i> Return
					</a>
				</div>
			';
		}
		
		$tpl_page .= '
			<table class="table table-border text-center">
				<tr>
					<th>ID</th>
					<th>Name</th>
					<th>Abbreviation</th>
					<th>Order</th>
					<th colspan="2">Edit/Delete</th>
				</tr>
		';
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '
					<tr>
						<td>'. $line['id'] . '</td>
						<td>'. $line['name'] . '</td>
						<td>'. $line['abbreviation'] . '</td>
						<td>'. $line['order'] . '</td>
						<td>
							<a href="?action=editsections&do=editsection&sectionid='. $line['id'] . '" class="btn">
								<i class="icon icon-pencil"></i> Edit
							</a>
						</td>
						<td>
							<a href="?action=editsections&do=deletesection&sectionid='. $line['id'] . '" class="btn" onclick="return confirm(\'Are you sure?\')">
								<i class="icon icon-remove"></i> Delete
							</a>
						</td>
					</tr>
				';
			}
		} else {
			$tpl_page .= '<tr><td colspan="6">There are currently no sections</td></tr>';
		}
		
		$tpl_page .= '</table>';
	}

	/* Rebuild all boards */
	function rebuildall() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>Rebuild All HTML files</h1>';
		
		$time_start = time();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		
		foreach ($results as $line) {
			$board_class = new Board($line['name']);
			$board_class->RegenerateAll();
			$tpl_page .= 'Regenerated /' . $line['name'] . '/<br>';
			unset($board_class);
			flush();
		}
		
		require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
		
		$menu_class = new Menu();
		$menu_class->Generate();
		
		$tpl_page .= '
			Regenerated menu pages<br><br>
			Rebuild complete. Took <b>' . (time() - $time_start) . '</b> seconds.
			
			<hr>
			
			<p class="text-red text-right">
				<b>Warning, for development only!</b>
				Continuously rebuilds pages every 5 seconds.
			</p>
			
			<p class="text-right">
				<button id="rebuild-continuous-trigger" class="btn btn-lg">
					<i class="icon icon-refresh"></i>
					Continuous Rebuild
				</button>
			</p>
			
			<div id="rebuild-continuous-log" hidden></div>
		';
		
		// prevent log overflow from continuous rebuild
		if (isset($_GET['continuous']) && $_GET['continuous'] === 'yes') {
			$tpl_page = 'OK';
		}
		else {
			management_addlogentry('Rebuilt all boards and threads', 2);
		}
		
		unset($board_class);
	}

	/*
	* +------------------------------------------------------------------------------+
	* Boards Pages
	* +------------------------------------------------------------------------------+
	*/

	function boardopts() {
		global $tc_db, $tpl_page;
		
		$this->useOldCss = false;
		$this->AdministratorsOnly();

		$tpl_page .= '<h1>Board Options</h1>';
		
		if (isset($_GET['updateboard']) && isset($_POST['order']) && isset($_POST['maxpages']) && isset($_POST['maxage']) && isset($_POST['messagelength'])) {
			$this->CheckToken($_POST['token']);
			if (!$this->CurrentUserIsModeratorOfBoard($_GET['updateboard'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$boardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['updateboard']) . " LIMIT 1");
			if ($boardid != '') {
				if ($_POST['order'] >= 0 && $_POST['maxpages'] >= 0 && $_POST['markpage'] >= 0 && $_POST['maxage'] >= 0 && $_POST['messagelength'] >= 0 && ($_POST['defaultstyle'] == '' || in_array($_POST['defaultstyle'], explode(':', KU_STYLES)) || in_array($_POST['defaultstyle'], explode(':', KU_TXTSTYLES)))) {
					$filetypes = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 9) == 'filetype_') {
							$filetypes[] = substr($postkey, 9);
						}
					}
					$updateboard_enablecatalog = isset($_POST['enablecatalog']) ? '1' : '0';
					$updateboard_enablenofile = isset($_POST['enablenofile']) ? '1' : '0';
					$updateboard_redirecttothread = isset($_POST['redirecttothread']) ? '1' : '0';
					$updateboard_enablereporting = isset($_POST['enablereporting']) ? '1' : '0';
					$updateboard_enablecaptcha = isset($_POST['enablecaptcha']) ? '1' : '0';
					$updateboard_forcedanon = isset($_POST['forcedanon']) ? '1' : '0';
					$updateboard_trial = isset($_POST['trial']) ? '1' : '0';
					$updateboard_popular = isset($_POST['popular']) ? '1' : '0';
					$updateboard_enablearchiving = isset($_POST['enablearchiving']) ? '1' : '0';
					$updateboard_showid = isset($_POST['showid']) ? '1' : '0';
					$updateboard_compactlist = isset($_POST['compactlist']) ? '1' : '0';
					$updateboard_locked = isset($_POST['locked']) ? '1' : '0';

					if (($_POST['type'] == '0' || $_POST['type'] == '1' || $_POST['type'] == '2' || $_POST['type'] == '3') && ($_POST['uploadtype'] == '0' || $_POST['uploadtype'] == '1' || $_POST['uploadtype'] == '2')) {
						if (!($_POST['uploadtype'] != '0' && $_POST['type'] == '3')) {
							if(count($_POST['allowedembeds']) > 0) {
								$updateboard_allowedembeds = '';

								$results = $tc_db->GetAll("SELECT `filetype` FROM `" . KU_DBPREFIX . "embeds`");
								foreach ($results as $line) {
									if(in_array($line['filetype'], $_POST['allowedembeds'])) {
										$updateboard_allowedembeds .= $line['filetype'].',';
									}
								}
								if ($updateboard_allowedembeds != '') {
									$updateboard_allowedembeds = substr($updateboard_allowedembeds, 0, -1);
								}
							}
							$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "boards` SET `type` = " . $tc_db->qstr($_POST['type']) . " , `uploadtype` = " . $tc_db->qstr($_POST['uploadtype']) . " , `order` = " . $tc_db->qstr(intval($_POST['order'])) . " , `section` = " . $tc_db->qstr(intval($_POST['section'])) . " , `desc` = " . $tc_db->qstr($_POST['desc']) . " , `locale` = " . $tc_db->qstr($_POST['locale']) . " , `showid` = '" . $updateboard_showid . "' , `compactlist` = '" . $updateboard_compactlist . "' , `locked` = '" . $updateboard_locked . "' , `maximagesize` = " . $tc_db->qstr($_POST['maximagesize']) . " , `messagelength` = " . $tc_db->qstr($_POST['messagelength']) . " , `maxpages` = " . $tc_db->qstr($_POST['maxpages']) . " , `maxage` = " . $tc_db->qstr($_POST['maxage']) . " , `markpage` = " . $tc_db->qstr($_POST['markpage']) . " , `maxreplies` = " . $tc_db->qstr($_POST['maxreplies']) . " , `image` = " . $tc_db->qstr($_POST['image']) . " , `includeheader` = " . $tc_db->qstr($_POST['includeheader']) . " , `redirecttothread` = '" . $updateboard_redirecttothread . "' , `anonymous` = " . $tc_db->qstr($_POST['anonymous']) . " , `forcedanon` = '" . $updateboard_forcedanon . "' , `embeds_allowed` = " . $tc_db->qstr($updateboard_allowedembeds) . " , `trial` = '" . $updateboard_trial . "' , `popular` = '" . $updateboard_popular . "' , `defaultstyle` = " . $tc_db->qstr($_POST['defaultstyle']) . " , `enablereporting` = '" . $updateboard_enablereporting . "', `enablecaptcha` = '" . $updateboard_enablecaptcha . "' , `enablenofile` = '" . $updateboard_enablenofile . "' , `enablearchiving` = '" . $updateboard_enablearchiving . "', `enablecatalog` = '" . $updateboard_enablecatalog . "' , `loadbalanceurl` = " . $tc_db->qstr($_POST['loadbalanceurl']) . " , `loadbalancepassword` = " . $tc_db->qstr($_POST['loadbalancepassword']) . " WHERE `name` = " . $tc_db->qstr($_GET['updateboard']) . "");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $boardid . "'");
							foreach ($filetypes as $filetype) {
								$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid`, `typeid` ) VALUES ( '" . $boardid . "', " . $tc_db->qstr($filetype) . " )");
							}
							require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
							$menu_class = new Menu();
							$menu_class->Generate();
							if (isset($_POST['submit_regenerate'])) {
								$board_class = new Board($_GET['updateboard']);
								$board_class->RegenerateAll();
							}
							$tpl_page .= '
								<div class="alert alert-green">Update successful</div>
								<div class="text-center">
									<a href="?action=boardopts" class="btn btn-lg">
										<i class="icon icon-chevron-left"></i> Return
									</a>
								</div>
							';
							management_addlogentry(_gettext('Updated board configuration') . " - /" . $_GET['updateboard'] . "/", 4);
						} else {
							$tpl_page .= '
								<div class="alert alert-red">Sorry, embed may only be enabled on normal imageboards</div>
								<div class="text-center">
									<a href="?action=boardopts" class="btn btn-lg">
										<i class="icon icon-chevron-left"></i> Return
									</a>
								</div>
							';
						}
					} else {
						$tpl_page .= '
							<div class="alert alert-red">Sorry, a generic error has occurred</div>
							<div class="text-center">
								<a href="?action=boardopts" class="btn btn-lg">
									<i class="icon icon-chevron-left"></i> Return
								</a>
							</div>
						';
					}
				} else {
					$tpl_page .= '
						<div class="alert alert-red">Integer values must be entered correctly</div>
						<div class="text-center">
							<a href="?action=boardopts" class="btn btn-lg">
								<i class="icon icon-chevron-left"></i> Return
							</a>
						</div>
					';
				}
			} else {
				$tpl_page .= '
					<div class="alert alert-red">Unable to locate a board named /'.$_GET['updateboard'].'/</div>
					<div class="text-center">
						<a href="?action=boardopts" class="btn btn-lg">
							<i class="icon icon-chevron-left"></i> Return
						</a>
					</div>
				';
			}
		} elseif (isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board']) . "");
			if (count($resultsboard) > 0) {
				foreach ($resultsboard as $lineboard) {
					$tpl_page .= '
						<form action="?action=boardopts&updateboard='.urlencode($_POST['board']).'" method="post">
							<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
							<table class="table table-post">
					';
					
					/* Directory */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								Directory:<br>
								<small>The directory of the board</small>
							</td>
							<td>
								<input type="text" value="'.$_POST['board'].'" class="input input-block" disabled>
							</td>
						</tr>
					';

					/* Description */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="desc">Description:</label><br>
								<small>The name of the board</small>
							</td>
							<td>
								<input type="text" name="desc" id="desc" value="'.$lineboard['desc'].'" class="input input-block">
							</td>
						</tr>
					';
					
					/* Locale */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="locale">Locale:</label><br>
								<small>Leave blank to use the default locale in configuration</small>
							</td>
							<td>
								<input type="text" name="locale" id="locale" value="'.$lineboard['locale'].'" class="input input-block">
							</td>
						</tr>
					';

					/* Board type */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="type">Board type:</label><br>
								<small>
									The type of posts which will be accepted on this board<br>
									<b class="text-red">Default: Normal imageboard</b>
								</small>
							</td>
							<td>
								<select name="type" id="type" class="input input-block">
									<option value="0" '.($lineboard['type'] == '0' ? 'selected' : '').'>Normal imageboard</option>
									<option value="1" '.($lineboard['type'] == '1' ? 'selected' : '').'>Text board</option>
									<option value="2" '.($lineboard['type'] == '2' ? 'selected' : '').'>Oekaki imageboard</option>
									<option value="3" '.($lineboard['type'] == '3' ? 'selected' : '').'>Upload imageboard</option>
								</select>
								
								<small>
									Normal imageboard: Image and extended format posts<br>
									Text board: No images<br>
									Oekaki imageboard: Allow users to draw images and use them in their posts<br>
									Upload imageboard: Styled more towards file uploads
								</small>
							</td>
						</tr>
					';

					/* Upload type */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="uploadtype" id="uploadtype">Upload type:</label><br>
								<small>
									Whether or not to allow embedding of videos<br>
									<b class="text-red">Default: No embedding</b>
								</small>
							</td>
							<td>
								<select name="uploadtype" id="uploadtype" class="input input-block">
									<option value="0" '.($lineboard['uploadtype'] == '0' ? 'selected' : '').'>No embedding</option>
									<option value="1" '.($lineboard['uploadtype'] == '1' ? 'selected' : '').'>Images and embedding</option>
									<option value="2" '.($lineboard['uploadtype'] == '2' ? 'selected' : '').'>Embedding only</option>
								</select>
							</td>
						</tr>
					';

					/* Order */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="order" id="order">Order:</label><br>
								<small>
									Order to show board in menu list, in ascending order<br>
									<b class="text-red">Default: 0</b>
								</small>
							</td>
							<td>
								<input type="text" name="order" id="order" value="'.$lineboard['order'].'" class="input input-block">
							</td>
						</tr>
					';

					/* Section */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="section" id="section">Section:</label><br>
								<small>The section the board is in. If set to <b>Select a Section</b>, it will not be shown in the menu.</small>
							</td>
							<td>
								'.$this->MakeSectionListDropdown('section', $lineboard['section']).'
							</td>
						</tr>
					';

					/* Load balancer URL */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="loadbalanceurl">Load balance URL:</label><br>
								<small>The full URL to the load balance script for this board. The script will handle file uploads and creation of thumbnails. Only one script per board, and there must be a <code>src</code> and <code>thumb</code> folder in the same folder as the script. Set to nothing to disable.</small>
							</td>
							<td>
								<input type="text" name="loadbalanceurl" id="loadbalanceurl" class="input input-block" value="'.$lineboard['loadbalanceurl'].'">
							</td>
						</tr>
					';

					/* Load balancer password */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="loadbalancepassword">Load balance password:</label><br>
								<small>The password for load balance script. The script must have this same password entered at the top, in the configuration area.</small>
							</td>
							<td>
								<input type="text" name="loadbalancepassword" id="loadbalancepassword" class="input input-block" value="'.$lineboard['loadbalancepassword'].'">
							</td>
						</tr>
					';

					/* Allowed filetypes */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								Allowed filetypes:<br>
								<small>Allowed filetypes for users to upload</small>
							</td>
							<td class="table-col-btn">
					';
					
					$filetypes = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype` FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
					foreach ($filetypes as $filetype) {
						$filetype_isenabled = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $lineboard['id'] . "' AND `typeid` = '" . $filetype['id'] . "' LIMIT 1");
						
						$tpl_page .= '
							<label class="btn">
								<input type="checkbox" name="filetype_'. $filetype['id'] . '" '.($filetype_isenabled == 1 ? 'checked' : '').'>
								'. strtoupper($filetype['filetype']) . '
							</label>
						';
					}
					
					$tpl_page .= '
							</td>
						</tr>
					';

					/* Allowed embeds */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								Allowed embeds:<br>
								<small>Allowed embed sites. Make sure embedding is enabled.</small>
							</td>
							<td class="table-col-btn">
					';
					
					$embeds = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype`, `name` FROM `" . KU_DBPREFIX . "embeds` ORDER BY `filetype` ASC");
					$embedsAllowed = explode(',', $lineboard['embeds_allowed']);
					foreach ($embeds as $embed) {
						$tpl_page .= '
							<label class="btn">
								<input type="checkbox" name="allowedembeds[]" value="'. $embed['filetype'] . '" '
									.(in_array($embed['filetype'], $embedsAllowed) ? 'checked' : '').
								'>
								'. $embed['name'] . '
							</label>
						';
					}
					
					$tpl_page .= '
							</td>
						</tr>
					';

					/* Maximum image size */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="maximagesize">Maximum image size:</label><br>
								<small>
									Maximum size of uploaded images, in bytes.<br>
									<b class="text-red">Default: 1024000</b>
								</small>
							</td>
							<td>
								<input type="text" name="maximagesize" id="maximagesize" class="input input-block" value="'.$lineboard['maximagesize'].'">
							</td>
						</tr>
					';

					/* Maximum message length */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="messagelength">Maximum message length:</label><br>
								<small class="text-red text-bold">Default: 8192</small>
							</td>
							<td>
								<input type="text" name="messagelength" id="messagelength" class="input input-block" value="'.$lineboard['messagelength'].'">
							</td>
						</tr>
					';

					/* Maximum board pages */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="maxpages">Maximum board pages:</label><br>
								<small class="text-red text-bold">Default: 11</small>
							</td>
							<td>
								<input type="text" name="maxpages" id="maxpages" class="input input-block" value="'.$lineboard['maxpages'].'">
							</td>
						</tr>
					';

					/* Maximum thread age */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="maxage">Maximum thread age (Hours):</label><br>
								<small class="text-red text-bold">Default: 0</small>
							</td>
							<td>
								<input type="text" name="maxage" id="maxage" class="input input-block" value="'.$lineboard['maxage'].'">
							</td>
						</tr>
					';

					/* Mark page */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="maxage">Mark page:</label><br>
								<small>
									Threads which reach this page or further will be marked to be deleted in two hours.<br>
									<b class="text-red">Default: 9</b>
								</small>
							</td>
							<td>
								<input type="text" name="markpage" id="markpage" class="input input-block" value="'.$lineboard['markpage'].'">
							</td>
						</tr>
					';

					/* Maximum thread replies */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="maxreplies">Maximum thread replies:</label><br>
								<small>
									The number of replies a thread can have before autosaging to the back of the board.<br>
									<b class="text-red">Default: 200</b>
								</small>
							</td>
							<td>
								<input type="text" name="maxreplies" id="maxreplies" class="input input-block" value="'.$lineboard['maxreplies'].'">
							</td>
						</tr>
					';

					/* Header image */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="image">Header image:</label><br>
								<small>
									Overrides the header image set in the config file. Leave blank to use configured global header image. Needs to be a full url including <code>http://</code>. Set to none to show no header image.
								</small>
							</td>
							<td>
								<input type="text" name="image" id="image" class="input input-block" value="'.$lineboard['image'].'">
							</td>
						</tr>
					';

					/* Include header */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="includeheader">Include header:</label><br>
								<small>Raw HTML which will be inserted at the top of each page of the board</small>
							</td>
							<td>
								<textarea name="includeheader" id="includeheader" class="input input-block">'.htmlspecialchars($lineboard['includeheader']).'</textarea>
							</td>
						</tr>
					';

					/* Anonymous */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="anonymous">Anonymous:</label><br>
								<small>Name to display when a name is not attached to a post.</small>
							</td>
							<td>
								<input type="text" name="anonymous" id="anonymous" class="input input-block" value="'. $lineboard['anonymous'] . '">
							</td>
						</tr>
					';

					/* Locked */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>Only moderators of the board and admins can make new posts/replies.</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="locked" '.($lineboard['locked'] == '1' ? 'checked' : '').'>
									Board is locked?
								</label>
							</td>
						</tr>
					';

					/* Show ID */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>If enabled, each post will display the poster\'s ID, which is a representation of their IP address.</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="showid" '.($lineboard['showid'] == '1' ? 'checked' : '').'>
									Show User ID?
								</label>
							</td>
						</tr>
					';

					/* Compact text boards */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small><b class="text-red">Text boards only</b>. If enabled, the list of threads displayed on the front page will be formatted differently to be compact.</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="compactlist" '.($lineboard['compactlist'] == '1' ? 'checked' : '').'>
									Compact?
								</label>
							</td>
						</tr>
					';

					/* Enable reporting */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									Reporting allows users to report posts, adding the post to the report list.<br>
									<b class="text-red">Default: Yes</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="enablereporting" '.($lineboard['enablereporting'] == '1' ? 'checked' : '').'>
									Enable reporting?
								</label>
							</td>
						</tr>
					';

					/* Enable captcha */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									Enable/disable captcha system for this board.<br>
									<b class="text-red">Default: No</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="enablecaptcha" '.($lineboard['enablecaptcha'] == '1' ? 'checked' : '').'>
									Enable Captcha?
								</label>
							</td>
						</tr>
					';

					/* Enable archiving */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									Enable/disable thread archiving for this board (not available if load balancer is used). If enabled, when a thread is pruned or deleted through this panel with the archive checkbox checked, the thread and its images will be moved into the arch directory, found in the same directory as the board. To function properly, you must create and set proper permissions to <code>/boardname/arch</code>, <code>/boardname/arch/res</code>, <code>/boardname/arch/src</code>, and <code>/boardname/arch/thumb</code>.<br>
									<b class="text-red">Default: No</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="enablearchiving" '.($lineboard['enablearchiving'] == '1' ? 'checked' : '').'>
									Enable archiving?
								</label>
							</td>
						</tr>
					';

					/* Enable catalog */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									If set to yes, a <code>catalog.html</code> file will be built with the other files, displaying the original picture of every thread in a box. This will only work on normal/oekaki imageboards.<br>
									<b class="text-red">Default: Yes</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="enablecatalog" '.($lineboard['enablecatalog'] == '1' ? 'checked' : '').'>
									Enable catalog?
								</label>
							</td>
						</tr>
					';

					/* Enable "no file" posting */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									If set to yes, new threads will not require an image to be posted.
									<b class="text-red">Default: No</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="enablenofile" '.($lineboard['enablenofile'] == '1' ? 'checked' : '').'>
									Enable posting without file?
								</label>
							</td>
						</tr>
					';

					/* Redirect to thread */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									If set to yes, users will be redirected to the thread they replied to/posted after posting. If set to no, users will be redirected to the first page of the board.<br>
									<b class="text-red">Default: No</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="redirecttothread" '.($lineboard['redirecttothread'] == '1' ? 'checked' : '').'>
									Redirect to thread after posting?
								</label>
							</td>
						</tr>
					';

					/* Forced anonymous */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									If set to yes, users will not be allowed to enter a name, making everyone appear as Anonymous.<br>
									<b class="text-red">Default: No</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="forcedanon" '.($lineboard['forcedanon'] == '1' ? 'checked' : '').'>
									Forced Anonymous?
								</label>
							</td>
						</tr>
					';

					/* Trial */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									If set to yes, this board will appear in italics in the menu.<br>
									<b class="text-red">Default: No</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="trial" '.($lineboard['trial'] == '1' ? 'checked' : '').'>
									Trial board?
								</label>
							</td>
						</tr>
					';

					/* Popular */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<small>
									If set to yes, this board will appear in bold in the menu.<br>
									<b class="text-red">Default: No</b>
								</small>
							</td>
							<td>
								<label class="btn">
									<input type="checkbox" name="popular" '.($lineboard['popular'] == '1' ? 'checked' : '').'>
									Popular board?
								</label>
							</td>
						</tr>
					';

					/* Default style */
					$tpl_page .= '
						<tr>
							<td class="text-right">
								<label for="defaultstyle">Default style:</label><br>
								<small>
									The style which will be set when the user first visits the board.<br>
									<b class="text-red">Default: Use Default</b>
								</small>
							</td>
							<td>
								<select name="defaultstyle" id="defaultstyle" class="input input-block">
									<option value="" '.($lineboard['defaultstyle'] == '' ? 'selected' : '').'>Use Default</option>
					';
					
					$styles = explode(':', KU_STYLES);
					foreach ($styles as $stylesheet) {
						$tpl_page .= '
							<option value="'. $stylesheet . '" '.($lineboard['defaultstyle'] == $stylesheet ? 'selected' : '').'>
								'.ucfirst($stylesheet).'
							</option>
						';
					}
					
					$stylestxt = explode(':', KU_TXTSTYLES);
					foreach ($stylestxt as $stylesheet) {
						$tpl_page .= '
							<option value="'. $stylesheet . '" '.($lineboard['defaultstyle'] == $stylesheet ? 'selected' : '').'>
								'.ucfirst($stylesheet).'
							</option>
						';
					}
					
					$tpl_page .= '
								</select>
							</td>
						</tr>
					';

					/* Submit form */
					$tpl_page .= '
								<tr>
									<td class="text-center" colspan="2">
										<button class="btn btn-lg" type="submit" name="submit_regenerate">
											Update and regenerate
										</button>
										
										<button class="btn btn-lg" type="submit" name="submit_noregenerate">
											Update without regenerating
										</button>
									</td>
								</tr>
							</table>
						</form>
					';
				}
			} else {
				$tpl_page .= '<div class="alert alert-red">Unable to locate a board named /'.$_POST['updateboard'].'/</div>';
			}
		} else {
			$tpl_page .= '
				<form action="?action=boardopts" method="post">
					<table class="table table-post table-sm">
						<tr>
							<td class="text-right">
								<label for="board" class="label-required">
									Board:
								</label>
							</td>
							<td>'.$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])).'</td>
						</tr>
						<tr>
							<td class="text-center" colspan="2">
								<button class="btn btn-lg" type="submit">
									<i class="icon icon-pencil"></i> Configure
								</button>
							</td>
						</tr>
					</table>
				</form>
			';
		}
	}

	function unstickypost() {
		global $tc_db, $tpl_page, $board_class;
		$this->ModeratorsOnly();

		$tpl_page .= '<h1>Manage Stickies</h1>';
		
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
						$sticky_board_id = $line['id'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $sticky_board_id ." AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `stickied` = '0' WHERE `boardid` = " . $sticky_board_id ." AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
						$board_class = new Board($sticky_board_name);
						$board_class->RegenerateAll();
						unset($board_class);
						
						$tpl_page .= '<div class="alert alert-green">Thread successfully un-stickied</div>';
						
						management_addlogentry(_gettext('Unstickied thread') . ' #' . intval($_GET['postid']) . ' - /' . $sticky_board_name . '/', 5);
					} else {
						$tpl_page .= '<div class="alert alert-red">Invalid thread ID</div>';
					}
				} else {
					$tpl_page .= '<div class="alert alert-red">Invalid board directory</div>';
				}
			}
		}
		$tpl_page .= $this->stickyforms();
	}

	function stickypost() {
		global $tc_db, $tpl_page, $board_class;
		$this->ModeratorsOnly();

		$tpl_page .= '<h1>Manage Stickies</h1>';
		
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
						$sticky_board_id = $line['id'];
					}
					$result = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $sticky_board_id . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					if ($result > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `stickied` = '1' WHERE `boardid` = " . $sticky_board_id . " AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
						$board_class = new Board($sticky_board_name);
						$board_class->RegenerateAll();
						unset($board_class);
						
						$tpl_page .= '<div class="alert alert-green">Thread successfully stickied</div>';
						
						management_addlogentry(_gettext('Stickied thread') . ' #' . intval($_GET['postid']) . ' - /' . $sticky_board_name . '/', 5);
					} else {
						$tpl_page .= '<div class="alert alert-red">Invalid thread ID</div>';
					}
				} else {
					$tpl_page .= '<div class="alert alert-red">Invalid board directory</div>';
				}
			}
		}
		
		$tpl_page .= $this->stickyforms();
	}

	/* Create forms for stickying a post */
	function stickyforms() {
		global $tc_db;
		
		$this->useOldCss = false;

		$output = '
			<table class="table table-border">
				<tr>
					<th>Sticky</th>
					<th>Unsticky</th>
				</tr>
				<tr>
					<td style="width:50%;vertical-align:top">
						<form action="manage_page.php" method="get">
							<input type="hidden" name="action" value="stickypost">
							
							<table class="table table-post table-no-border">
								<tr>
									<td class="text-right">
										<label class="label-required" for="board">Board:</label>
									</td>
									<td>
										'.$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])).'
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label class="label-required" for="postid">Thread ID:</label>
									</td>
									<td>
										<input type="text" name="postid" id="postid" class="input input-block" required>
									</td>
								</tr>
								<tr>
									<td class="text-center" colspan="2">
										<button class="btn btn-lg" type="submit">
											<i class="icon icon-pushpin"></i> Sticky
										</button>
									</td>
								</tr>
							</table>
						</form>
					</td>
					<td>
						<table class="table table-post table-no-border">
		';
		
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `name`, `id` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '
					<tr>
						<td class="text-right">/'. $line_board['name'] . '/ :</td>
						<td class="table-col-btn">
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line_board['id'] . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `stickied` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '
						<a class="btn" href="?action=unstickypost&board='. $line_board['name'] . '&postid='. $line['id'] . '">#'. $line['id'] . '</a>
					';
				}
			} else {
				$output .= '<a href="javascript:void(0)" class="btn">No stickied threads</a>';
			}
			
			$output .= '
						</td>
					</tr>
			';
		}
		
		$output .= '
						</table>
					</td>
				</tr>
			</table>
		';

		return $output;
	}

	function lockpost() {
		global $tc_db, $tpl_page, $board_class;
		$this->ModeratorsOnly();

		$tpl_page .= '<h1>Manage Locked Threads</h1>';
		
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$lock_board_name = $line['name'];
						$lock_board_id = $line['id'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lock_board_id . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `locked` = '1' WHERE `boardid` = " . $lock_board_id . " AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
						$board_class = new Board($lock_board_name);
						$board_class->RegenerateAll();
						unset($board_class);
						
						$tpl_page .= '<div class="alert alert-green">Thread successfully locked</div>';
						
						management_addlogentry(_gettext('Locked thread') . ' #'. intval($_GET['postid']) . ' - /'. intval($_GET['board']) . '/', 5);
					} else {
						$tpl_page .= '<div class="alert alert-red">Invalid thread ID</div>';
					}
				} else {
					$tpl_page .= '<div class="alert alert-red">Invalid board directory</div>';
				}
			}
		}
		$tpl_page .= $this->lockforms();
	}

	function unlockpost() {
		global $tc_db, $tpl_page, $board_class;

		$tpl_page .= '<h1>Manage Locked Threads</h1>';
		
		if ($_GET['postid'] > 0 && $_GET['board'] != '') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
			if (count($results) > 0) {
				if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line) {
					$lock_board_name = $line['name'];
					$lock_board_id = $line['id'];
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lock_board_id . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
				if (count($results) > 0) {
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `locked` = '0' WHERE `boardid` = " . $lock_board_id . " AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					$board_class = new Board($lock_board_name);
					$board_class->RegenerateAll();
					unset($board_class);
					
					$tpl_page .= '<div class="alert alert-green">Thread successfully unlocked</div>';
					
					management_addlogentry(_gettext('Unlocked thread') . ' #'. intval($_GET['postid']) . ' - /'. intval($_GET['board']) . '/', 5);
				} else {
					$tpl_page .= '<div class="alert alert-red">Invalid thread ID</div>';
				}
			} else {
				$tpl_page .= '<div class="alert alert-red">Invalid board directory</div>';
			}
		}
		$tpl_page .= $this->lockforms();
	}

	function lockforms() {
		global $tc_db;
		
		$this->useOldCss = false;

		$output = '
			<table class="table table-border">
				<tr>
					<th style="width:50%">Lock</th>
					<th>Unlock</th>
				</tr>
				<tr>
					<td style="width:50%;vertical-align:top">
						<form action="manage_page.php" method="get">
							<input type="hidden" name="action" value="lockpost">
							
							<table class="table table-post table-no-border">
								<tr>
									<td class="text-right">
										<label class="label-required" for="board">Board:</label>
									</td>
									<td>
										'.$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])).'
									</td>
								</tr>
								<tr>
									<td class="text-right">
										<label class="label-required" for="postid">Thread ID:</label>
									</td>
									<td>
										<input type="text" name="postid" id="postid" class="input input-block" required>
									</td>
								</tr>
								<tr>
									<td class="text-center" colspan="2">
										<button class="btn btn-lg" type="submit">
											<i class="icon icon-lock"></i> Lock
										</button>
									</td>
								</tr>
							</table>
						</form>
					</td>
					<td>
						<table class="table table-post table-no-border">
		';
		
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '
					<tr>
						<td class="text-right">/'. $line_board['name'] . '/ :</td>
						<td class="table-col-btn">
			';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line_board['id'] . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `locked` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '
						<a class="btn" href="?action=unlockpost&board='. $line_board['name'] . '&postid='. $line['id'] . '">#'. $line['id'] . '</a>
					';
				}
			} else {
				$output .= '<a href="javascript:void(0)" class="btn">No locked threads</a>';
			}
			
			$output .= '
						</td>
					</tr>
			';
		}
		
		$output .= '
						</table>
					</td>
				</tr>
			</table>
		';

		return $output;
	}

	/* Delete a post, or multiple posts */
	function delposts($multidel=false) {
		global $tc_db, $tpl_page, $board_class;
		
		$this->useOldCss = false;
		
		$tpl_page .= '<h1>Delete Thread/Post</h1>';

    $isquickdel = false;
    if (isset($_POST['boarddir']) || isset($_GET['boarddir'])) {
      if (!isset($_GET['boarddir']) && isset($_POST['boarddir'])) {
        $this->CheckToken($_POST['token']);
      }
      if (isset($_GET['boarddir'])) {
				$isquickdel = true;
				$_POST['boarddir'] = $_GET['boarddir'];
				if (isset($_GET['delthreadid'])) {
					$_POST['delthreadid'] = $_GET['delthreadid'];
				}
				if (isset($_GET['delpostid'])) {
					$_POST['delpostid'] = $_GET['delpostid'];
				}
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['boarddir']) . "");
			if (count($results) > 0) {
				if (!$this->CurrentUserIsModeratorOfBoard($_POST['boarddir'], $_SESSION['manageusername'])) {
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line) {
					$board_id = $line['id'];
					$board_dir = $line['name'];
				}
				if (isset($_GET['cp'])) {
					$cp = '&amp;cp=y&amp;instant=y';
				}
				if (isset($_POST['delthreadid'])) {
					if ($_POST['delthreadid'] > 0) {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_id . " AND `IS_DELETED` = '0' AND `id` = " . $tc_db->qstr($_POST['delthreadid']) . " AND `parentid` = '0'");
						if (count($results) > 0) {
							if (isset($_POST['fileonly'])) {
								foreach ($results as $line) {
									if (!empty($line['file'])) {
										$del = unlink(KU_ROOTDIR . $_POST['boarddir'] . '/src/'. $line['file'] . '.'. $line['file_type']);
										if ($del) {
											@unlink(KU_ROOTDIR . $_POST['boarddir'] . '/thumb/'. $line['file'] . 's.'. $line['file_type']);
											@unlink(KU_ROOTDIR . $_POST['boarddir'] . '/thumb/'. $line['file'] . 'c.'. $line['file_type']);
											$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `file` = 'removed', `file_md5` = '' WHERE `boardid` = " . $board_id . " AND `id` = ".$_POST['delthreadid']." LIMIT 1");
											
											$tpl_page .= '<div class="alert alert-green">File successfully deleted</div>';
										} else {
											$tpl_page .= '<div class="alert alert-red">The file has already been deleted</div>';
										}
									} else {
										$tpl_page .= '<div class="alert alert-red">The thread does not have a file associated with it</div>';
									}
								}
							} else {
								foreach ($results as $line) {
									$delthread_id = $line['id'];
								}
								$post_class = new Post($delthread_id, $board_dir, $board_id);
								if (isset($_POST['archive'])) {
									$numposts_deleted = $post_class->Delete(true);
								} else {
									$numposts_deleted = $post_class->Delete();
								}
								$board_class = new Board($board_dir);
								$board_class->RegenerateAll();
								unset($board_class);
								unset($post_class);
								
								$tpl_page .= '<div class="alert alert-green">Thread '.$delthread_id.' successfully deleted</div>';
								
								management_addlogentry(_gettext('Deleted thread') . ' #<a href="?action=viewthread&thread='. $delthread_id . '&board='. $_POST['boarddir'] . '">'. $delthread_id . '</a> ('. $numposts_deleted . ' replies) - /'. $board_dir . '/', 7);
								
								// todo
								/*if (!empty($_GET['postid'])) {
									$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_CGIPATH . '/manage_page.php?action=bans&banboard='. $_GET['boarddir'] . '&banpost='. $_GET['postid'] . $cp . '"><a href="'. KU_CGIPATH . '/manage_page.php?action=bans&banboard='. $_GET['boarddir'] . '&banpost='. $_GET['postid'] . $cp . '">'. _gettext('Redirecting') . '</a> to ban page...';
								} elseif ($isquickdel) {
									$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '/"><a href="'. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '/">'. _gettext('Redirecting') . '</a> back to board...';
								}*/
							}
						} else {
							$tpl_page .= '<div class="alert alert-red">Invalid thread ID '.$delpost_id.'</div>';
						}
					}
				} elseif (isset($_POST['delpostid'])) {
					if ($_POST['delpostid'] > 0) {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_id . " AND `IS_DELETED` = '0' AND `id` = " . $tc_db->qstr($_POST['delpostid']) . "");
						if (count($results) > 0) {
							if (isset($_POST['fileonly'])) {
								foreach ($results as $line) {
									if (!empty($line['file'])) {
										$del = @unlink(KU_ROOTDIR . $_POST['boarddir'] . '/src/'. $line['file'] . '.'. $line['file_type']);
										if ($del) {
											@unlink(KU_ROOTDIR . $_POST['boarddir'] . '/thumb/'. $line['file'] . 's.'. $line['file_type']);
											@unlink(KU_ROOTDIR . $_POST['boarddir'] . '/thumb/'. $line['file'] . 'c.'. $line['file_type']);
											$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `file` = 'removed', `file_md5` = '' WHERE `boardid` = " . $board_id . " AND `id` = ".$_POST['delpostid']." LIMIT 1");
											
											$tpl_page .= '<div class="alert alert-green">File successfully deleted</div>';
										} else {
											$tpl_page .= '<div class="alert alert-red">The file has already been deleted</div>';
										}
									} else {
										$tpl_page .= '<div class="alert alert-red">The thread does not have a file associated with it</div>';
									}
								}
							} else {
								foreach ($results as $line) {
									$delpost_id = $line['id'];
									$delpost_parentid = $line['parentid'];
								}
								$post_class = new Post($delpost_id, $board_dir, $board_id);
								$post_class->Delete();
								$board_class = new Board($board_dir);
								$board_class->RegenerateThreads($delpost_parentid);
								$board_class->RegeneratePages();
								unset($board_class);
								unset($post_class);
								
								$tpl_page .= '<div class="alert alert-green">Post '.$delpost_id.' successfully deleted</div>';
								
								management_addlogentry(_gettext('Deleted post') . ' #<a href="?action=viewthread&thread='. $delpost_parentid . '&board='. $_POST['boarddir'] . '#'. $delpost_id . '">'. $delpost_id . '</a> - /'. $board_dir . '/', 7);
								
								// todo
								/*if ($_GET['postid'] != '') {
									$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_CGIPATH . '/manage_page.php?action=bans&banboard='. $_GET['boarddir'] . '&banpost='. $_GET['postid'] . $cp . '"><a href="'. KU_CGIPATH . '/manage_page.php?action=bans&banboard='. $_GET['boarddir'] . '&banpost='. $_GET['postid'] . '">'. _gettext('Redirecting') . '</a> to ban page...';
								} elseif ($isquickdel) {
									$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '/res/'. $delpost_parentid . '.html"><a href="'. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '/res/'. $delpost_parentid . '.html">'. _gettext('Redirecting') . '</a> back to thread...';
								}*/
							}
						} else {
							$tpl_page .= '<div class="alert alert-red">Invalid thread ID '.$delpost_id.'</div>';
						}
					}
				}
			} else {
				$tpl_page .= '<div class="alert alert-red">Invalid board directory</div>';
			}
		}
		
		if (!$multidel) {
			$tpl_page .= '
				<table class="table table-border table-board-option">
					<tr>
						<th style="width:50%">Delete Thread</th>
						<th>Delete Post</th>
					</tr>
					<tr>
						<td style="width:50%;vertical-align:top">
							<form action="manage_page.php?action=delposts" method="post" onsubmit="return confirm(\'Are you sure?\')">
								<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
								
								<table class="table table-post table-no-border">
									<tr>
										<td class="text-right">
											<label class="label-required" for="boarddir">Board:</label>
										</td>
										<td>
											'.$this->MakeBoardListDropdown('boarddir', $this->BoardList($_SESSION['manageusername'])).'
										</td>
									</tr>
									<tr>
										<td class="text-right">
											<label class="label-required" for="delthreadid">Thread ID:</label>
										</td>
										<td>
											<input type="text" name="delthreadid" id="delthreadid" class="input input-block">
										</td>
									</tr>
									<tr>
										<td></td>
										<td>
											<label class="btn">
												<input type="checkbox" name="fileonly">
												Delete File Only?
											</label>
										</td>
									</tr>
									<tr>
										<td class="text-center" colspan="2">
											<button class="btn btn-lg" type="submit">
												<i class="icon icon-remove"></i> Delete Thread
											</button>
										</td>
									</tr>
								</table>
							</form>
						</td>
						<td>
							<form action="manage_page.php?action=delposts" method="post" onsubmit="return confirm(\'Are you sure?\')">
								<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
								
								<table class="table table-post table-no-border">
									<tr>
										<td class="text-right">
											<label class="label-required" for="boarddir">Board:</label>
										</td>
										<td>
											'.$this->MakeBoardListDropdown('boarddir', $this->BoardList($_SESSION['manageusername'])).'
										</td>
									</tr>
									<tr>
										<td class="text-right">
											<label class="label-required" for="delpostid">Post ID:</label>
										</td>
										<td>
											<input type="text" name="delpostid" id="delpostid" class="input input-block">
										</td>
									</tr>
									<tr>
										<td></td>
										<td>
											<label class="btn">
												<input type="checkbox" name="archive">
												Archive Post Only?
											</label>
										</td>
									</tr>
									<tr>
										<td></td>
										<td>
											<label class="btn">
												<input type="checkbox" name="fileonly">
												Delete File Only?
											</label>
										</td>
									</tr>
									<tr>
										<td class="text-center" colspan="2">
											<button class="btn btn-lg" type="submit">
												<i class="icon icon-remove"></i> Delete Post
											</button>
										</td>
									</tr>
								</table>
							</form>
						</td>
					</tr>
				</table>
			';
		}
	}

	/*
	* +------------------------------------------------------------------------------+
	* Moderation Pages
	* +------------------------------------------------------------------------------+
	*/

		/* View and delete reports */
	function reports() {
		global $tc_db, $tpl_page;
		$this->ModeratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Reports') . '</h2><br />';
		if (isset($_GET['clear'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "reports` WHERE `id` = " . $tc_db->qstr($_GET['clear']) . " LIMIT 1");
			if (count($results) > 0) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = " . $tc_db->qstr($_GET['clear']));
				$tpl_page .= 'Report successfully cleared.<hr />';
			}
		}
		$query = "SELECT * FROM `" . KU_DBPREFIX . "reports` WHERE `cleared` = 0";
		if (!$this->CurrentUserIsAdministrator()) {
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			if (!empty($boardlist)) {
				$query .= ' AND (';
				foreach ($boardlist as $board) {
					$query .= ' `board` = \''. $board['name'] .'\' OR';
				}
				$query = substr($query, 0, -3) . ')';
			} else {
				$tpl_page .= _gettext('You do not moderate any boards.');
			}
		}
		$resultsreport = $tc_db->GetAll($query);
		if (count($resultsreport) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>Board</th><th>Post</th><th>File</th><th>Message</th><th>Reason</th><th>Reporter IP</th><th>Action</th></tr>';
			foreach ($resultsreport as $linereport) {
				$reportboardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($linereport['board']) . "");
				$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $reportboardid . " AND `id` = " . $tc_db->qstr($linereport['postid']) . "");
				foreach ($results as $line) {
					if ($line['IS_DELETED'] == 0) {
						$tpl_page .= '<tr><td>/'. $linereport['board'] . '/</td><td><a href="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/res/';
						if ($line['parentid'] == '0') {
							$tpl_page .= $linereport['postid'];
							$post_threadorpost = 'thread';
						} else {
							$tpl_page .= $line['parentid'];
							$post_threadorpost = 'post';
						}
						$tpl_page .= '.html#'. $linereport['postid'] . '">'. $line['id'] . '</a></td><td>';
						if ($line['file'] == 'removed') {
							$tpl_page .= 'removed';
						} elseif ($line['file'] == '') {
							$tpl_page .= 'none';
						} elseif ($line['file_type'] == 'jpg' || $line['file_type'] == 'gif' || $line['file_type'] == 'png') {
							$tpl_page .= '<a href="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/src/'. $line['file'] . '.'. $line['file_type'] . '"><img src="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/thumb/'. $line['file'] . 's.'. $line['file_type'] . '" border="0"></a>';
						} else {
							$tpl_page .= '<a href="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/src/'. $line['file'] . '.'. $line['file_type'] . '">File</a>';
						}
						$tpl_page .= '</td><td>';
						if ($line['message'] != '') {
							$tpl_page .= stripslashes($line['message']);
						} else {
							$tpl_page .= '&nbsp;';
						}
						$tpl_page .= '</td><td>';
						if ($linereport['reason'] != '') {
							$tpl_page .= htmlspecialchars(stripslashes($linereport['reason']));
						} else {
							$tpl_page .= '&nbsp;';
						}
						$tpl_page .= '</td><td>'. md5_decrypt($linereport['ip'], KU_RANDOMSEED) . '</td><td><a href="?action=reports&clear='. $linereport['id'] . '">Clear</a>&nbsp;&#91;<a href="?action=delposts&boarddir='. $linereport['board'] . '&del'. $post_threadorpost . 'id='. $line['id'] . '" title="Delete" onclick="return confirm(\'Are you sure you want to delete this thread/post?\');">D</a>&nbsp;<a href="'. KU_CGIPATH . '/manage_page.php?action=delposts&boarddir='. $linereport['board'] . '&del'. $post_threadorpost . 'id='. $line['id'] . '&postid='. $line['id'] . '" title="Delete &amp; Ban" onclick="return confirm(\'Are you sure you want to delete and ban this poster?\');">&amp;</a>&nbsp;<a href="?action=bans&banboard='. $linereport['board'] . '&banpost='. $line['id'] . '" title="Ban">B</a>&#93;</td></tr>';
					} else {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = 1 WHERE id = " . $linereport['id'] . "");
					}
				}
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= 'No reports to show.';
		}
	}

	/* Addition, modification, deletion, and viewing of bans */
	function bans() {
		global $tc_db, $tpl_page, $bans_class;
		
		$this->ModeratorsOnly();
		$reason = KU_BANREASON;
		$ban_ip = ''; $ban_hash = ''; $ban_parentid = 0; $multiban = Array();
		if (isset($_POST['modban']) && is_array($_POST['post']) && $_POST['board']) {
			$ban_board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board']) . "");
			if (!empty($ban_board_id)) {
				foreach ( $_POST['post'] as $post ) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $ban_board_id . "' AND `id` = " . intval($post) . "");
					if (count($results) > 0) {
						$multiban[] = md5_decrypt($results[0]['ip'], KU_RANDOMSEED);
						$multiban_hash[] = $results[0]['file_md5'];
						$multiban_parentid[] = $results[0]['parentid'];
					}
				}
			}
		}
		if (isset($_GET['banboard']) && isset($_GET['banpost'])) {
			$ban_board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['banboard']) . "");
			$ban_board = $_GET['banboard'];
			$ban_post_id = $_GET['banpost'];
			if (!empty($ban_board_id)) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $ban_board_id . "' AND `id` = " . $tc_db->qstr($_GET['banpost']) . "");
				if (count($results) > 0) {
					$ban_ip = md5_decrypt($results[0]['ip'], KU_RANDOMSEED);
					$ban_hash = $results[0]['file_md5'];
					$ban_parentid = $results[0]['parentid'];
				} else {
					$tpl_page.= _gettext('A post with that ID does not exist.') . '<hr />';
				}
			}
		}
		$instantban = false;
		if ((isset($_GET['instant']) || isset($_GET['cp'])) && $ban_ip) {
			if (isset($_GET['cp'])) {
					$ban_reason = "You have been banned for posting Child Pornography. Your IP has been logged, and the proper authorities will be notified.";
			} else {
				if($_GET['reason']) {
					$ban_reason = urldecode($_GET['reason']);
				} else {
					$ban_Reason = KU_BANREASON;
				}
			}
			$instantban = true;
		}
		$tpl_page .= '<h2>'. _gettext('Bans') . '</h2><br />';
		if (((isset($_POST['ip']) || isset($_POST['multiban'])) && isset($_POST['seconds']) && (!empty($_POST['ip']) || (empty($_POST['ip']) && !empty($_POST['multiban'])))) || $instantban) {
			if ($_POST['seconds'] >= 0 || $instantban) {
				$banning_boards = array();
				$ban_boards = '';
				if (isset($_POST['banfromall']) || $instantban) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards`");
					foreach ($results as $line) {
						if (!$this->CurrentUserIsModeratorOfBoard($line['name'], $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $line['name'] . '/: '. _gettext('You can only make bans applying to boards you moderate.'));
						}
					}
				} else {
					if (empty($_POST['bannedfrom'])) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
					if(isset($_POST['deleteposts'])) {
						$_POST['deletefrom'] = $_POST['bannedfrom'];
					}
					foreach($_POST['bannedfrom'] as $board) {
						if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $board . '/: '. _gettext('You can only make bans applying to boards you moderate.'));
						}
					}
					$ban_boards = implode('|', $_POST['bannedfrom']);
				}
				$ban_globalban = (isset($_POST['banfromall']) || $instantban) ? 1 : 0;
				$ban_allowread = ($_POST['allowread'] == 0 || $instantban) ? 0 : 1;
				if (isset($_POST['quickbanboardid'])) {
					$ban_board_id = $_POST['quickbanboardid'];
				}
				if(isset($_POST['quickbanboard'])) {
					$ban_board = $_POST['quickbanboard'];
				}
				if(isset($_POST['quickbanpostid'])) {
					$ban_post_id = $_POST['quickbanpostid'];
				}
				$ban_ip = ($instantban) ? $ban_ip : $_POST['ip'];
				$ban_duration = ($_POST['seconds'] == 0 || $instantban) ? 0 : $_POST['seconds'];
				$ban_type = ($_POST['type'] <= 2 && $_POST['type'] >= 0) ? $_POST['type'] : 0;
				$ban_reason = ($instantban) ? $ban_reason : $_POST['reason'];
				$ban_note = ($instantban) ? '' : $_POST['staffnote'];
				$ban_appealat = 0;
				if (KU_APPEAL != '' && !$instantban) {
					$ban_appealat = intval($_POST['appealdays'] * 86400);
					if ($ban_appealat > 0) $ban_appealat += time();
				}
				if (isset($_POST['multiban']))
					$ban_ips = unserialize($_POST['multiban']);
				else 
					$ban_ips = Array($ban_ip);
				$i = 0;
				foreach ($ban_ips as $ban_ip) {
					$ban_msg = '';
					$whitelist = $tc_db->GetAll("SELECT `ipmd5` FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = 2");
					if (in_array(md5($ban_ip), $whitelist)) {
						exitWithErrorPage(_gettext('That IP is on the whitelist'));
					}
					if ($bans_class->BanUser($ban_ip, $_SESSION['manageusername'], $ban_globalban, $ban_duration, $ban_boards, $ban_reason, $ban_note, $ban_appealat, $ban_type, $ban_allowread)) {
						$regenerated = array();
						if (((KU_BANMSG != '' || $_POST['banmsg'] != '') && isset($_POST['addbanmsg']) && (isset($_POST['quickbanpostid']) || isset($_POST['quickmultibanpostid']))) || $instantban ) {
							$ban_msg = ((KU_BANMSG == $_POST['banmsg']) || empty($_POST['banmsg'])) ? KU_BANMSG : $_POST['banmsg'];
							if (isset($ban_post_id))
								$postids = Array($ban_post_id);
							else
								$postids = unserialize($_POST['quickmultibanpostid']);
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `parentid`, `message` FROM `".KU_DBPREFIX."posts` WHERE `boardid` = " . $tc_db->qstr($ban_board_id) . " AND `id` = ".$tc_db->qstr($postids[$i])." LIMIT 1");
								
							foreach($results AS $line) {
								$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `message` = ".$tc_db->qstr($line['message'] . $ban_msg)." WHERE `boardid` = " . $tc_db->qstr($ban_board_id) . " AND `id` = ".$tc_db->qstr($postids[$i]));
								clearPostCache($postids[$i], $ban_board_id);
								if ($line['parentid']==0) {
									if (!in_array($postids, $regenerated)) {
										$regenerated[] = $postids[$i];
									}
								} else {
									if (!in_array($line['parentid'], $regenerated)) {
										$regenerated[] = $line['parentid'];
									}
								}
							}
						}
						$tpl_page .= _gettext('Ban successfully placed.')."<br />";
					} else {
						exitWithErrorPage(_gettext('Sorry, a generic error has occurred.'));
					}

					$logentry = _gettext('Banned') . ' '. $ban_ip;
					$logentry .= ($ban_duration == 0) ? ' '. _gettext('without expiration') : ' '. _gettext('until') . ' '. date('F j, Y, g:i a', time() + $ban_duration);
					$logentry .= ' - '. _gettext('Reason') . ': '. $ban_reason . (($ban_note) ? (" (".$ban_note.")") : ("")). ' - '. _gettext('Banned from') . ': ';
					$logentry .= ($ban_globalban == 1) ? _gettext('All boards') . ' ' : '/'. implode('/, /', explode('|', $ban_boards)) . '/ ';
					management_addlogentry($logentry, 8);
					$ban_ip = '';
					$i++;
				}
				if (count($regenerated) > 0) {
					$board_class = new Board($ban_board);
					foreach($regenerated as $thread) {
						$board_class->RegenerateThreads($thread);
					}
					$board_class->RegeneratePages();
					unset($board_class);
				}

				if(isset($_POST['deleteposts'])) {
					$tpl_page .= '<br />';
					$this->deletepostsbyip(true);
				}

				if ((isset($_GET['instant']) && !isset($_GET['cp']))) {
					die("success");
				}

				if (isset($_POST['banhashtime']) && $_POST['banhashtime'] !== '' && ($_POST['hash'] !== '' || isset($_POST['multibanhashes'])) && $_POST['banhashtime'] >= 0) {
					if (isset($_POST['multibanhashes']))
						$banhashes = unserialize($_POST['multibanhashes']);
					else
						$banhashes = Array($_POST['hash']);
					foreach ($banhashes as $banhash){
						$results = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `".KU_DBPREFIX."bannedhashes` WHERE `md5` = ".$tc_db->qstr($banhash)." LIMIT 1");
						if ($results == 0) {
							$tc_db->Execute("INSERT INTO `".KU_DBPREFIX."bannedhashes` ( `md5` , `bantime` , `description` ) VALUES ( ".$tc_db->qstr($banhash)." , ".$tc_db->qstr($_POST['banhashtime'])." , ".$tc_db->qstr($_POST['banhashdesc'])." )");
							management_addlogentry('Banned md5 hash '. $banhash . ' with a description of '. $_POST['banhashdesc'], 8);
						}
					}
				}
				if (!empty($_POST['quickbanboard']) && !empty($_POST['quickbanthreadid'])) {
					$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_BOARDSPATH . '/'. $_POST['quickbanboard'] . '/';
					if ($_POST['quickbanthreadid'] != '0') $tpl_page .= 'res/'. $_POST['quickbanthreadid'] . '.html';
					$tpl_page .= '"><a href="'. KU_BOARDSPATH . '/'. $_POST['quickbanboard'] . '/';
					if ($_POST['quickbanthreadid'] != '0') $tpl_page .= 'res/'. $_POST['quickbanthreadid'] . '.html';
					$tpl_page .= '">'. _gettext('Redirecting') . '</a>...';
				}
			} else {
				$tpl_page .= _gettext('Please enter a positive amount of seconds, or zero for a permanent ban.');
			}
			$tpl_page .= '<hr />';
		} elseif (isset($_GET['delban']) && $_GET['delban'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['delban']) . "");
			if (count($results) > 0) {
				$unban_ip = md5_decrypt($results[0]['ip'], KU_RANDOMSEED);
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['delban']) . "");
				$bans_class->UpdateHtaccess();
				$tpl_page .= _gettext('Ban successfully removed.');
				management_addlogentry(_gettext('Unbanned') . ' '. $unban_ip, 8);
			} else {
				$tpl_page .= _gettext('Invalid ban ID');
			}
			$tpl_page .= '<br /><hr />';
		} elseif (isset($_GET['delhashid'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = " . $tc_db->qstr($_GET['delhashid']) . "");
			if (count($results) > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = " . $tc_db->qstr($_GET['delhashid']) . "");
				$tpl_page .= _gettext('Hash removed from ban list.') . '<br /><hr />';
			}
		}

		flush();

		$isquickban = false;

		$tpl_page .= '<form action="manage_page.php?action=bans" method="post" name="banform">';

		if ((!empty($ban_ip) && isset($_GET['banboard']) && isset($_GET['banpost'])) || (!empty($multiban) && isset($_POST['board']) && isset($_POST['post'])))  {
			$isquickban = true;
			$tpl_page .= '<input type="hidden" name="quickbanboard" value="'. (isset($_GET['banboard']) ? $_GET['banboard'] : $_POST['board']) . '" />';
			if(!empty($multiban)) {
				$tpl_page .= '<input type="hidden" name="quickbanboardid" value="'. $ban_board_id . '" /><input type="hidden" name="quickmultibanthreadid" value="'. htmlspecialchars(serialize($multiban_parentid)) . '" /><input type="hidden" name="quickmultibanpostid" value="'. htmlspecialchars(serialize($_POST['post'])) . '" />';
			} else {
				$tpl_page .= '<input type="hidden" name="quickbanboardid" value="'. $ban_board_id . '" /><input type="hidden" name="quickbanthreadid" value="'. $ban_parentid . '" /><input type="hidden" name="quickbanpostid" value="'. $_GET['banpost'] . '" />';
			}
		} elseif (isset($_GET['ip'])) {
			$ban_ip = $_GET['ip'];
		}

		$tpl_page .= '<fieldset>
		<legend>'. _gettext('IP address and ban type') . '</legend>
		<label for="ip">'. _gettext('IP') . ':</label>';
		if (!$multiban) {
			$tpl_page .= '<input type="text" name="ip" id="ip" value="'. $ban_ip . '" />
			<br /><label for="deleteposts">'. _gettext('Delete all posts by this IP') . ':</label>
			<input type="checkbox" name="deleteposts" id="deleteposts" />';
		}
		else {
			$tpl_page .= '<input type="hidden" name="multiban" value="'.htmlspecialchars(serialize($multiban)).'">
			<input type="hidden" name="multibanhashes" value="'.htmlspecialchars(serialize($multiban_hash)).'">	Multiple IPs
			<br /><label for="deleteposts">'. _gettext('Delete all posts by these IPs') . ':</label>
			<input type="checkbox" name="deleteposts" id="deleteposts" />';
		}

		$tpl_page .= '<br />
		<label for="allowread">'. _gettext('Allow read') . ':</label>
		<select name="allowread" id="allowread"><option value="1">'._gettext('Yes').'</option><option value="0">'._gettext('No').'</option></select>
		<div class="desc">'. _gettext('Whether or not the user(s) affected by this ban will be allowed to read the boards.') . '<br /><strong>'. _gettext('Warning') . ':</strong> '. _gettext('Selecting "No" will prevent any reading of any page on the level of the boards on the server. It will also act as a global ban.') . '</div><br />

		<label for="type">'. _gettext('Type') . ':</label>
		<select name="type" id="type"><option value="0">'. _gettext('Single IP') . '</option><option value="1">'. _gettext('IP Range') . '</option><option value="2">'. _gettext('Whitelist') . '</option></select>
		<div class="desc">'. _gettext('The type of ban. A single IP can be banned by providing the full address. A whitelist ban prevents that IP from being banned. An IP range can be banned by providing the IP range you would like to ban, in this format: 123.123.12') . '</div><br />';

		if ($isquickban && KU_BANMSG != '') {
			$tpl_page .= '<label for="addbanmsg">'. _gettext('Add ban message') . ':</label>
			<input type="checkbox" name="addbanmsg" id="addbanmsg" checked="checked" />
			<div class="desc">'. _gettext('If checked, the configured ban message will be added to the end of the post.') . '</div><br />
			<label for="banmsg">'. _gettext('Ban message') . ':</label>
			<input type="text" name="banmsg" id="banmsg" value="'. htmlspecialchars(KU_BANMSG) . '" size='. strlen(KU_BANMSG) . '" />';
		}

		$tpl_page .='</fieldset>
		<fieldset>
		<legend> '. _gettext('Ban from') . '</legend>
		<label for="banfromall"><strong>'. _gettext('All boards') . '</strong></label>
		<input type="checkbox" name="banfromall" id="banfromall" /><br /><hr /><br />' .
		$this->MakeBoardListCheckboxes('bannedfrom', $this->BoardList($_SESSION['manageusername'])) .
		'</fieldset>';

		if (isset($ban_hash)) {
			$tpl_page .= '<fieldset>
			<legend>'. _gettext('Ban file') . '</legend>
			<input type="hidden" name="hash" value="'. $ban_hash . '" />

			<label for="banhashtime">'. _gettext('Ban file hash for') . ':</label>
			<input type="text" name="banhashtime" id="banhashtime" />
			<div class="desc">'. _gettext('The amount of time to ban the hash of the image which was posted under this ID. Leave blank to not ban the image, 0 for an infinite global ban, or any number of seconds for that duration of a global ban.') . '</div><br />

			<label for="banhashdesc">'. _gettext('Ban file hash description') . ':</label>
			<input type="text" name="banhashdesc" id="banhashdesc" />
			<div class=desc">'. _gettext('The description of the image being banned. Not applicable if the above box is blank.') . '</div>
			</fieldset>';
		}

		$tpl_page .= '<fieldset>
		<legend>'. _gettext('Ban duration, reason, and appeal information') . '</legend>
		<label for="seconds">'. _gettext('Seconds') . ':</label>
		<input type="text" name="seconds" id="seconds" />
		<div class="desc">'. _gettext('Presets') . ':&nbsp;<a href="#" onclick="document.banform.seconds.value=\'3600\';return false;">1hr</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'86400\';return false;">1d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'259200\';return false;">3d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'604800\';return false;">1w</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'1209600\';return false;">2w</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'2592000\';return false;">30d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'31536000\';return false;">1yr</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'0\';return false;">'. _gettext('never') .'</a></div><br />

		<label for="reason">'. _gettext('Reason') . ':</label>
		<input type="text" name="reason" id="reason" value="'. $reason . '" />
		<div class="desc">'. _gettext('Presets') .':&nbsp;<a href="#" onclick="document.banform.reason.value=\''. _gettext('Child Pornography') .'\';return false;">CP</a>&nbsp;<a href="#" onclick="document.banform.reason.value=\''. _gettext('Proxy') .'\';return false;">'. _gettext('Proxy') .'</a></div><br />

		<label for="staffnote">'. _gettext('Staff Note') . '</label>
		<input type="text" name="staffnote" id="staffnote" />
		<div class="desc">'. _gettext('Presets') . ':&nbsp;<a href="#" onclick="document.banform.staffnote.value=\''. _gettext('Child Pornography') .'\';return false;">CP</a> || '. _gettext('This message will be shown only on this page and only to staff, not to the user.') .'</div><br />';

		if (KU_APPEAL != '') {
			$tpl_page .= '<label for="appealdays">'. _gettext('Appeal (days)') . ':</label>
			<input type="text" name="appealdays" id="appealdays" value="5" />
			<div class="desc">'. _gettext('Presets') . ':&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'0\';return false;">'. _gettext('No Appeal') .'</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'5\';return false;">5 '. _gettext('days') .'</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'10\';return false;">10 '. _gettext('days') .'</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'30\';return false;">30 '. _gettext('days') .'</a></div><br />';
		}

		$tpl_page .= '</fieldset>
		<input type="submit" value="'. _gettext('Add ban') . '" /><br />

		</form>
		<hr /><br />';

		for ($i = 2; $i >= 0; $i--) {
			switch ($i) {	
				case 2:
					$tpl_page .= '<strong>'. _gettext('Whitelisted IPs') . ':</strong><br />';
					break;
				case 1:
					$tpl_page .= '<br /><strong>'. _gettext('IP Range Bans') . ':</strong><br />';
					break;
				case 0:
					if (!empty($ban_ip))
						$tpl_page .= '<br /><strong>'. _gettext('Previous bans on this IP') . ':</strong><br />';
					else
						$tpl_page .= '<br /><strong>'. _gettext('Single IP Bans') . ':</strong><br />';
					break;
			}
			if (isset($_GET['allbans'])) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' AND `by` != 'SERVER' ORDER BY `id` DESC");
				$hiddenbans = 0;
			} elseif (isset($_GET['limit'])) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' ORDER BY `id` DESC LIMIT ".intval($_GET['limit']));
				$hiddenbans = 0;
			} else {
				if (!empty($ban_ip) && $i == 0) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `ipmd5` = '" . md5($ban_ip) . "' AND `type` = '" . $i . "' AND `by` != 'SERVER' ORDER BY `id` DESC");
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' AND `by` != 'SERVER' ORDER BY `id` DESC LIMIT 15");
					// Get the number of bans in the database of this type
					$hiddenbans = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "'");
					// Subtract 15 from the count, since we only want the number not shown
					$hiddenbans = $hiddenbans[0][0] - 15;
				}
			}
			if (count($results) > 0) {
				$tpl_page .= '<table border="1" width="100%"><tr><th>';
				$tpl_page .= ($i == 1) ? _gettext('IP Range') : _gettext('IP Address');
				$tpl_page .= '</th><th>'. _gettext('Boards') . '</th><th>'. _gettext('Reason') . '</th><th>'. _gettext('Staff Note') . '</th><th>'. _gettext('Date added') . '</th><th>'. _gettext('Expires/Expired') . '</th><th>'. _gettext('Added By') . '</th><th>&nbsp;</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr><td><a href="?action=bans&ip='. md5_decrypt($line['ip'], KU_RANDOMSEED) . '">'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';
					if ($line['globalban'] == 1) {
						$tpl_page .= '<strong>'. _gettext('All boards') . '</strong>';
					} elseif (!empty($line['boards'])) {
						$tpl_page .= '<strong>/'. implode('/</strong>, <strong>/', explode('|', $line['boards'])) . '/</strong>&nbsp;';
					}
					$tpl_page .= '</td><td>';
					$tpl_page .= (!empty($line['reason'])) ? htmlentities(stripslashes($line['reason'])) : '&nbsp;';
					$tpl_page .= '</td><td>';
					$tpl_page .= (!empty($line['staffnote'])) ? htmlentities(stripslashes($line['staffnote'])) : '&nbsp;';
					$tpl_page .= '</td><td>'. date("F j, Y, g:i a", $line['at']) . '</td><td>';
					$tpl_page .= ($line['until'] == 0) ? '<strong>'. _gettext('Does not expire') . '</strong>' : date("F j, Y, g:i a", $line['until']);
					$tpl_page .= '</td><td>'. $line['by'] . '</td><td>[<a href="manage_page.php?action=bans&delban='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>';
				}
				$tpl_page .= '</table>';
				if ($hiddenbans > 0) {
					$tpl_page .= sprintf(_gettext('%s bans not shown.'), $hiddenbans) .
					' <a href="?action=bans&allbans=1">'. _gettext('View all bans') . '</a>'.' <a href="?action=bans&limit=100">View last 100 bans</a>';
				}
			} else {
				$tpl_page .= _gettext('There are currently no bans');
			}
		}
		$tpl_page .= '<br /><br /><strong>'. _gettext('File hash bans') . ':</strong><br /><table border="1" width="100%"><tr><th>'. _gettext('Hash') . '</th><th>'. _gettext('Description') . '</th><th>'. _gettext('Ban time') . '</th><th>&nbsp;</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `".KU_DBPREFIX."bannedhashes` ". ((!isset($_GET['allbans'])) ? ("LIMIT 5") : ("")));
		if (count($results) == 0) {
			$tpl_page .= '<tr><td colspan="4">'. _gettext('None') . '</td></tr>';
		} else {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>'. $line['md5'] . '</td><td>'. $line['description'] . '</td><td>';
				$tpl_page .= ($line['bantime'] == 0) ? '<strong>'. _gettext('Does not expire') . '</strong>' : $line['bantime'] . ' seconds';
				$tpl_page .= '</td><td>[<a href="?action=bans&delhashid='. $line['id'] . '">x</a>]</td></tr>';
			}
		}
		$tpl_page .= '</table>';
	}

	function appeals() {
		global $tc_db, $tpl_page, $bans_class;
		$this->ModeratorsOnly();
		$tpl_page .= '<h2>'. _gettext('Appeals') . '</h2><br />';
		$ban_ip = '';
		if (isset($_GET['accept'])) {
			if ($_GET['accept'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['accept']) . "");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "banlist` SET `expired` = 1, `appealat` = -4 WHERE `id` = " . $tc_db->qstr($_GET['accept']) . "");
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Ban successfully removed.');
					management_addlogentry('Accepted appeal #'.$_GET['accept'].' from: '. $unban_ip, 8);
				} else {
					$tpl_page .= _gettext('Invalid ID');
				}
				$tpl_page .= '<hr />';
			}
		} elseif (isset($_GET['deny'])) {
			if ($_GET['deny'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['deny']) . "");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "banlist` SET `appealat` = -2 WHERE `id` = " . $tc_db->qstr($_GET['deny']) . "");
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Appeal successfully denied.');
					management_addlogentry(_gettext('Denied the ban appeal for') . ' '. $unban_ip, 8);
				} else {
					$tpl_page .= _gettext('Invalid ID');
				}
				$tpl_page .= '<hr />';
			}
		}
		flush();

		for ($i = 1; $i >= 0; $i--) {
			if ($i == 1) {
				$tpl_page .= '<strong>'. _gettext('IP Range bans') . ':</strong><br />';
			} else {
				$tpl_page .= '<br /><strong>'. _gettext('Single IP Bans') . ':</strong><br />';
			}

			if ($ban_ip != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `ipmd5` = '" . md5($ban_ip) . "' AND `type` = '" . $i . "' AND `expired` = 0 ORDER BY `id` DESC");
			} else {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' AND `appealat` = -1 AND `expired` = 0 ORDER BY `id` DESC");
			}
			if (count($results) > 0) {
				$tpl_page .= '<table border="1" width="100%"><tr><th>';
				if ($i == 1) {
					$tpl_page .= 'IP Range';
				} else {
					$tpl_page .= 'IP Address';
				}
				$tpl_page .= '</th><th>Boards</th><th>Reason</th><th>Staff Note</th><th>Date Added</th><th>Expires</th><th>Added By</th><th>Appeal Message</th><th>Deny</th><th>Accept</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr>';
					$tpl_page .= '<td><a href="?action=bans&ip='. md5_decrypt($line['ip'], KU_RANDOMSEED) . '">'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';
					if ($line['globalban'] == '1') {
						$tpl_page .= '<strong>'. _gettext('All boards') . '</strong>';
					} else {
						if ($line['boards'] != '') {
							$tpl_page .= '<strong>/'. implode('/</strong>, <strong>/', explode('|', $line['boards'])) . '/</strong>&nbsp;';
						}
					}
					$tpl_page .= '</td><td>';
					if ($line['reason'] != '') {
						$tpl_page .= htmlentities(stripslashes($line['reason']));
					} else {
						$tpl_page .= '&nbsp;';
					}
					$tpl_page .= '</td><td>';
					if ($line['staffnote'] != '') {
						$tpl_page .= htmlentities(stripslashes($line['staffnote']));
					} else {
						$tpl_page .= '&nbsp;';
					}
					$tpl_page .= '</td><td>'. date("F j, Y, g:i a", $line['at']) . '</td><td>';
					if ($line['until'] == '0') {
						$tpl_page .= '<strong>'. _gettext('Does not expire') . '</strong>';
					} else {
						$tpl_page .= date("F j, Y, g:i a", $line['until']);
					}
					$tpl_page .= '</td><td>'. $line['by'] . '</td>
					<td>'.$line['appeal'].'</td>
					<td><a href="manage_page.php?action=appeals&deny='. $line['id'] . '">:(</a></td>
					<td><a href="manage_page.php?action=appeals&accept='. $line['id'] . '">:)</a></td>';
					$tpl_page .= '</tr>';
				}
				$tpl_page .= '</table>';
				if ($hiddenbans>0) {
					$tpl_page .= sprintf(_gettext('%s bans not shown.'), $hiddenbans) .
					' <a href="?action=bans&allbans=1">'. _gettext('View all bans') . '</a>'.' <a href="?action=bans&limit=100">View last 100 bans</a>';
				}
			} else {
				$tpl_page .= _gettext('There are currently no bans.');
			}
		}
	}

	/* Search for all posts by a selected IP address and delete them */
	function deletepostsbyip($from_ban = false) {
		global $tc_db, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		if (!$from_ban) {
			$tpl_page .= '<h2>'. _gettext('Delete all posts by IP') . '</h2><br />';
		}
		if (isset($_POST['ip']) || isset($_POST['multiban'])) {
			if ($_POST['ip'] != '' || !empty($_POST['multiban'])) {
        if (!$from_ban) {
          $this->CheckToken($_POST['token']);
        }
				$deletion_boards = array();
				$deletion_new_boards = array();
				$board_ids = '';
				if (isset($_POST['banfromall'])) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
					foreach ($results as $line) {
						if (!$this->CurrentUserIsModeratorOfBoard($line['name'], $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $line['name'] . '/: '. _gettext('You can only delete posts from boards you moderate.'));
						}
						$delete_boards[$line['id']] = $line['name'];
						$board_ids .= $line['id'] . ',';
					}
				} else {
					if (empty($_POST['deletefrom'])) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
					foreach($_POST['deletefrom'] as $board) {
						if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $board . '/: '. _gettext('You can only delete posts from boards you moderate.'));
						}
						$id = $tc_db->GetOne("SELECT `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($board));
						$board_ids .= $tc_db->qstr($id) . ',';
						$delete_boards[$id] = $board;
					}
				}
				$board_ids = substr($board_ids, 0, -1);

				$i = 0;
				if (isset($_POST['multiban']))
					$ips = unserialize($_POST['multiban']);
				else
					$ips = Array($_POST['ip']);
				foreach  ($ips as $ip) {
					$i = 0;				
					$post_list = $tc_db->GetAll("SELECT `id`, `boardid` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` IN (" . $board_ids . ") AND `IS_DELETED` = '0' AND `ipmd5` = '" . md5($ip) . "'");
					if (count($post_list) > 0) {
						foreach ($post_list as $post) {
							$i++;
							$post_class = new Post($post['id'], $delete_boards[$post['boardid']], $post['boardid']);
							$post_class->Delete();
							$boards_deleted[$post['boardid']] = $delete_boards[$post['boardid']];
							unset($post_class);
						}

						$tpl_page .= _gettext('All threads/posts by that IP in selected boards successfully deleted.') . '<br /><strong>'. $i . '</strong> posts were removed.<br />';
						management_addlogentry(_gettext('Deleted posts by ip') . ' '. $ip, 7);
					}
					else {
						$tpl_page .= _gettext('No posts for that IP found');
					}
					if (isset($boards_deleted)) {
						foreach ($boards_deleted as $board) {
							$board_class = new Board($board);
							$board_class->RegenerateAll();
							unset($board_class);
						}
					}
				}
				$tpl_page .= '<hr />';
			}
		}
		if (!$from_ban) {
			$tpl_page .= '<form action="?action=deletepostsbyip" method="post">
      <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
			<fieldset><legend>IP</legend>
			<label for="ip">'. _gettext('IP') .':</label>
			<input type="text" id="ip" name="ip"';
			if (isset($_GET['ip'])) {
				$tpl_page .= ' value="'. $_GET['ip'] . '"';
			}
			$tpl_page .= ' /></fieldset><br /><fieldset>
			<legend>'. _gettext('Boards') .'</legend>

			<label for="banfromall"><strong>'. _gettext('All boards') .'</strong></label>
			<input type="checkbox" id="banfromall" name="banfromall" /><br /><hr /><br />' .
			$this->MakeBoardListCheckboxes('deletefrom', $this->BoardList($_SESSION['manageusername'])) .
			'<br /></fieldset>

			<input type="submit" value="'. _gettext('Delete posts') .'" />

			</form>';
		}
	}

	/* View recently uploaded images */
	function recentimages() {
		global $tc_db, $tpl_page;
		$this->ModeratorsOnly();

		if (!isset($_SESSION['imagesperpage'])) {
			$_SESSION['imagesperpage'] = 50;
		}

		if (isset($_GET['show'])) {
			if ($_GET['show'] == '25' || $_GET['show'] == '50' || $_GET['show'] == '75' || $_GET['show'] == '100') {
				$_SESSION['imagesperpage'] = $_GET['show'];
			}
		}

		$tpl_page .= '<h2>'. _gettext('Recently uploaded images') . '</h2><br />
		'._gettext('Number of images to show per page').': <a href="?action=recentimages&show=25">25</a>, <a href="?action=recentimages&show=50">50</a>, <a href="?action=recentimages&show=75">75</a>, <a href="?action=recentimages&show=100">100</a> '._gettext('(note that this is a rough limit, more may be shown)').'<br />';
		if (isset($_POST['clear'])) {
			if ($_POST['clear'] != '') {
				$clear_decrypted = md5_decrypt($_POST['clear'], KU_RANDOMSEED);
				if ($clear_decrypted != '') {
					$clear_unserialized = unserialize($clear_decrypted);

					foreach ($clear_unserialized as $clear_sql) {
						$tc_db->Execute($clear_sql);
					}
					$tpl_page .= _gettext('Successfully marked previous images as reviewed.').'<hr />';
				}
			}
		}

		$dayago = (time() - 86400);
		$imagesshown = 0;
		$reviewsql_array = array();

		if ($imagesshown <= $_SESSION['imagesperpage']) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `" . KU_DBPREFIX . "boards`.`name` AS `boardname`, `" . KU_DBPREFIX . "posts`.`boardid` AS boardid, `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`file` AS file, `" . KU_DBPREFIX . "posts`.`file_type` AS file_type, `" . KU_DBPREFIX . "posts`.`thumb_w` AS thumb_w, `" . KU_DBPREFIX . "posts`.`thumb_h` AS thumb_h FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX ."boards` WHERE (`file_type` = 'jpg' OR `file_type` = 'gif' OR `file_type` = 'png') AND `reviewed` = 0 AND `IS_DELETED` = 0 AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `timestamp` DESC LIMIT " . intval($_SESSION['imagesperpage']));
			if (count($results) > 0) {
				$reviewsql = "UPDATE `" . KU_DBPREFIX . "posts` SET `reviewed` = 1 WHERE ";
				$tpl_page .= '<table border="1">'. "\n";
				foreach ($results as $line) {
					$reviewsql .= '(`boardid` = '.$line['boardid'] .' AND `id` = '. $line['id'] . ') OR ';
					$real_parentid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];
					$tpl_page .= '<tr><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '">/'. $line['boardname'] . '/'. $line['id'] . '</td><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '"><img src="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/thumb/'. $line['file'] . 's.'. $line['file_type'] . '" width="'. $line['thumb_w'] . '" height="'. $line['thumb_h'] . '" border="0"></a></td></tr>';
				}
				$tpl_page .= '</table>';

				$reviewsql = substr($reviewsql, 0, -3);
				$reviewsql_array[] = $reviewsql;
				$imagesshown += count($results);
			}
		}

		if ($imagesshown > 0) {
			$tpl_page .= '<br /><br />'. sprintf(_gettext('%s images shown.'), $imagesshown). '<br />';
			$tpl_page .= '<form action="?action=recentimages" method="post">
			<input type="hidden" name="clear" value="'. md5_encrypt(serialize($reviewsql_array), KU_RANDOMSEED) . '" />
			<input type="submit" value="'. _gettext('Clear All On Page As Reviewed') .'" />
			</form><br />';
		} else {
			$tpl_page .= '<br /><br />'. _gettext('No recent images currently need review.') ;
		}
	}

	/* View recently posted posts */
	function recentposts() {
		global $tc_db, $tpl_page;
		$this->ModeratorsOnly();

		if (!isset($_SESSION['postsperpage'])) {
			$_SESSION['postsperpage'] = 50;
		}

		if (isset($_GET['show'])) {
			if ($_GET['show'] == '25' || $_GET['show'] == '50' || $_GET['show'] == '75' || $_GET['show'] == '100') {
				$_SESSION['postsperpage'] = $_GET['show'];
			}
		}

		$tpl_page .= '<h2>'. _gettext('Recent posts') . '</h2><br />
		'._gettext('Number of posts to show per page').': <a href="?action=recentposts&show=25">25</a>, <a href="?action=recentposts&show=50">50</a>, <a href="?action=recentposts&show=75">75</a>, <a href="?action=recentposts&show=100">100</a> '._gettext('(note that this is a rough limit, more may be shown)').'<br />';
		if (isset($_POST['clear'])) {
			if ($_POST['clear'] != '') {
				$clear_decrypted = md5_decrypt($_POST['clear'], KU_RANDOMSEED);
				if ($clear_decrypted != '') {
					$clear_unserialized = unserialize($clear_decrypted);

					foreach ($clear_unserialized as $clear_sql) {
						$tc_db->Execute($clear_sql);
					}
					$tpl_page .= _gettext('Successfully marked previous posts as reviewed.').'<hr />';
				}
			}
		}

		$dayago = (time() - 86400);
		$postsshown = 0;
		$reviewsql_array = array();

		$boardlist = $this->BoardList($_SESSION['manageusername']);
		if ($postsshown <= $_SESSION['postsperpage']) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `" . KU_DBPREFIX . "boards`.`name` AS `boardname`, `" . KU_DBPREFIX . "posts`.`boardid` AS boardid, `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`message` AS message, `" . KU_DBPREFIX . "posts`.`ip` AS ip FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX ."boards` WHERE `" . KU_DBPREFIX . "posts`.`timestamp` > " . $dayago . " AND `reviewed` = 0 AND `IS_DELETED` = 0 AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `timestamp` DESC LIMIT " . intval($_SESSION['postsperpage']));
			if (count($results) > 0) {
				$reviewsql = "UPDATE `" . KU_DBPREFIX . "posts` SET `reviewed` = 1 WHERE ";
				$tpl_page .= '<table border="1" width="100%">'. "\n";
				$tpl_page .= '<tr><th width="75px">'._gettext('Post Number').'</th><th>'._gettext('Post Message').'</th><th width="100px">'._gettext('Poster IP').'</th></tr>'. "\n";
				foreach ($results as $line) {
					$reviewsql .= '(`boardid` = '.$line['boardid'] .' AND `id` = '. $line['id'] . ') OR ';
					$real_parentid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];
					$tpl_page .= '<tr><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '">/'. $line['boardname'] . '/'. $line['id'] . '</td><td>'. stripslashes($line['message']) . '</td><td>'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</tr>';
				}
				$tpl_page .= '</table>';

				$reviewsql = substr($reviewsql, 0, -3) . ' LIMIT '. count($results);
				$reviewsql_array[] = $reviewsql;
				$postsshown += count($results);
			}
		}

		if ($postsshown > 0) {
			$tpl_page .= '<br /><br />'. sprintf(_gettext('%s posts shown.'), $postsshown) .'<br />
			<form action="?action=recentposts" method="post">
			<input type="hidden" name="clear" value="'. md5_encrypt(serialize($reviewsql_array), KU_RANDOMSEED) . '" />
			<input type="submit" value="'. _gettext('Clear All On Page As Reviewed') .'" />
			</form><br />';
		} else {
			$tpl_page .= '<br /><br />'. _gettext('No recent posts currently need review.') ;
		}
	}


	/*
	* +------------------------------------------------------------------------------+
	* Misc Functions
	* +------------------------------------------------------------------------------+
	*/

	/* Show APC info */
	function apc() {
		global $tpl_page;

		if (KU_APC) {
			$apc_info_system = apc_cache_info();
			$apc_info_user = apc_cache_info('user');
			//print_r($apc_info_user);
			$tpl_page .= '<h2>APC</h2><h3>'. _gettext('System (File cache)') .'</h3><ul>';
			$tpl_page .= '<li>Start time: <strong>'. date("y/m/d(D)H:i", $apc_info_system['start_time']) . '</strong></li>';
			$tpl_page .= '<li>Hits: <strong>'. $apc_info_system['num_hits'] . '</strong></li>';
			$tpl_page .= '<li>Misses: <strong>'. $apc_info_system['num_misses'] . '</strong></li>';
			$tpl_page .= '<li>Entries: <strong>'. $apc_info_system['num_entries'] . '</strong></li>';
			$tpl_page .= '</ul><br /><h3>User (kusaba)</h3><ul>';
			$tpl_page .= '<li>Start time: <strong>'. date("y/m/d(D)H:i", $apc_info_user['start_time']) . '</strong></li>';
			$tpl_page .= '<li>Hits: <strong>'. $apc_info_user['num_hits'] . '</strong></li>';
			$tpl_page .= '<li>Misses: <strong>'. $apc_info_user['num_misses'] . '</strong></li>';
			$tpl_page .= '<li>Entries: <strong>'. $apc_info_user['num_entries'] . '</strong></li>';
			$tpl_page .= '</ul><br /><br /><a href="?action=clearcache">Clear APC cache</a>';
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}

	/* Clear the APC cache */
	function clearcache() {
		global $tpl_page;

		if (KU_APC) {
			apc_clear_cache();
			apc_clear_cache('user');
			$tpl_page .= 'APC cache cleared.';
			management_addlogentry(_gettext('Cleared APC cache'), 0);
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}

	/* Generate a list of boards a moderator controls */
	function BoardList($username) {
		global $tc_db, $tpl_page;

		$staff_boardsmoderated = array();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
			foreach ($resultsboard as $lineboard) {
					$staff_boardsmoderated = array_merge($staff_boardsmoderated, array(array( 'name' => $lineboard['name'], 'id' => $lineboard['id'])));
			}
		} else {
			if ($results[0][0] != '') {
				foreach ($results as $line) {
					$array_boards = explode('|', $line['boards']);
				}
				foreach ($array_boards as $this_board_name) {
					$this_board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($this_board_name) . "");
					$staff_boardsmoderated = array_merge($staff_boardsmoderated, array(array('name' => $this_board_name, 'id' => $this_board_id)));
				}
			}
		}

		return $staff_boardsmoderated;
	}

	/* Generate a list of boards in query format */
	function sqlboardlist() {
		global $tc_db, $tpl_page;

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		$sqlboards = '';
		foreach ($results as $line) {
			$sqlboards .= 'posts_'. $line['name'] . ', ';
		}

		return substr($sqlboards, 0, -2);
	}

	/* Generate a dropdown box from a supplied array of boards */
	function MakeBoardListDropdown($name, $boards, $all = false) {
		$output = '<select class="input input-block" name="'.$name.'" id="'.$name.'" required><option value="">'. _gettext('-- Select a Board --') .'</option>';
		if (!empty($boards)) {
			if ($all) {
				$output .= '<option value="all">'. _gettext('All Boards') .'</option>';
			}
			foreach ($boards as $board) {
				$output .= '<option value="'. $board['name'] . '">/'. $board['name'] . '/</option>';
			}
		}
		$output .= '</select>';

		return $output;
	}

	/* Generate a series of checkboxes from a supplied array of boards */
	function MakeBoardListCheckboxes($boxname, $boards) {
		$output = '';

		if (!empty($boards)) {
			foreach ($boards as $board) {
				$output .= '
					<label class="btn">
						<input type="checkbox" name="'.$boxname.'[]" value="'.$board['name'].'">
						/'.$board['name'].'/
					</label>
				';
			}
		}

		return $output;
	}

	/* Generate a dropdown box for all sections */
	function MakeSectionListDropDown($name, $selected) {
		global $tc_db;

		$output = '<select class="input input-block" name="'. $name . '"><option value="">'. _gettext('-- Select a Section --') .'</option>';
		$results = $tc_db->GetAll("SELECT `id`, `name` FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
		if(count($results) > 0) {
			foreach ($results as $section) {
				$output .= '
					<option value="'. $section['id'] . '"'. ($section['id'] == $selected ? 'selected' : '') . '>'. $section['name'] . '</option>
				';
			}
		}
		$output .= '</select>';

		return $output;
	}

	/* Delete files without their md5 stored in the database */
	function delunusedimages($verbose = false) {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<strong>'. _gettext('Looking for unused images in') .' /'. $lineboard['name'] . '/</strong><br />';
			}
			$file_md5list = array();
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `file_md5` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lineboard['id'] . " AND `IS_DELETED` = 0 AND `file` != '' AND `file` != 'removed' AND `file_md5` != ''");
			foreach ($results as $line) {
				$file_md5list[] = $line['file_md5'];
			}
			$dir = './'. $lineboard['name'] . '/src';
			$files = glob("$dir/{*.jpg, *.png, *.gif, *.swf}", GLOB_BRACE);
			if (is_array($files)) {
				foreach ($files as $file) {
					if (in_array(md5_file(KU_BOARDSDIR . $lineboard['name'] . '/src/'. basename($file)), $file_md5list) == false) {
						if (time() - filemtime(KU_BOARDSDIR . $lineboard['name'] . '/src/'. basename($file)) > 120) {
							if ($verbose == true) {
								$tpl_page .= sprintf(_gettext('A live record for %s was not found; the file has been removed.'), $file).'<br />';
							}
							unlink(KU_BOARDSDIR . $lineboard['name'] . '/src/'. basename($file));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/'. substr(basename($file), 0, -4) . 's'. substr(basename($file), strlen(basename($file)) - 4));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/'. substr(basename($file), 0, -4) . 'c'. substr(basename($file), strlen(basename($file)) - 4));
						}
					}
				}
			}
		}

		return true;
	}

	/* Delete replies currently not marked as deleted who belong to a thread which is marked as deleted */
	function delorphanreplies($verbose = false) {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<strong>'. _gettext('Looking for orphans in') .' /'. $lineboard['name'] . '/</strong><br />';
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `parentid` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lineboard['id'] . " AND `parentid` != '0' AND `IS_DELETED` = 0");
			foreach ($results as $line) {
				$exists_rows = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lineboard['id'] . " AND `id` = '" . $line['parentid'] . "' AND `IS_DELETED` = 0", 1);
				if ($exists_rows[0] == 0) {
					$post_class = new Post($line['id'], $lineboard['name'], $lineboard['id']);
					$post_class->Delete;
					unset($post_class);

					if ($verbose) {
						$tpl_page .= sprintf(_gettext('Reply #%1$s\'s thread (#%2$s) does not exist! It has been deleted.'),$line['id'],$line['parentid']).'<br />';
					}
				}
			}
		}

		return true;
	}
	
	/* How could kusaba team forgot to label this? */
	function spam() {
		global $tpl_page;
		
		$this->useOldCss = false;
		$spam = KU_ROOTDIR . 'spam.txt';
		
		$tpl_page .= '<h1>Spamfilter</h1>';
		
		if (!empty($_POST['spam'])) {
			$this->CheckToken($_POST['token']);
			file_put_contents($spam, $_POST['spam']);
			
			$tpl_page .= '<div class="alert alert-green">Spamfilter successfully edited</div>';
		}
		
		$content = htmlspecialchars(file_get_contents(KU_ROOTDIR . 'spam.txt'));

		$tpl_page .= '
			<form action="?action=spam" method="post">
				<input type="hidden" name="token" value="' . $_SESSION['token'] . '">
				<table class="table table-post table-sm">
					<tr>
						<td class="text-right">
							<label for="spam" class="label-required">Content:</label>
						</td>
						<td>
							<textarea name="spam" id="spam" class="input input-block" required>'.htmlspecialchars($content).'</textarea>
						</td>
					</tr>
					<tr>
						<td class="text-center" colspan="2">
							<button class="btn btn-lg" type="submit">
								<i class="icon icon-floppy-save"></i> Save
							</button>
						</td>
					</tr>
				</table>
			</form>
		';
	}
	/* Gets the IP address of a post */
	function getip() {
		global $tc_db, $smarty, $tpl_page;
		if(!$this->CurrentUserIsModerator() && !$this->CurrentUserIsAdministrator()) {
			die();
		}
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['boarddir']));
		if (count($results) > 0) {
			if (!$this->CurrentUserIsModeratorOfBoard($_GET['boarddir'], $_SESSION['manageusername'])) {
				die();
			}
			$ip = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $tc_db->qstr($results[0]['id']) . " AND `id` = " . $tc_db->qstr($_GET['id']));
			die("dnb-".$_GET['boarddir']."-".$_GET['id']."-".(($ip[0]['parentid'] == 0) ? ("y") : ("n"))."=".md5_decrypt($ip[0]['ip'], KU_RANDOMSEED));
		}
		die();
	}
	
	/*
	* +------------------------------------------------------------------------------+
	* Custom pages
	* +------------------------------------------------------------------------------+
	*/
	function custom_editConfiguration () {
		global $tpl_page;
		
		// use new css, restrict to admin
		$this->useOldCss = false;
		$this->AdministratorsOnly();
		
		// use autoloader
		require KU_ROOTDIR.'/custom/php/autoload.php';
		
		// initiate config
		$config = new \Custom\Config(KU_ROOTDIR);
		$isSave = false;
		
		// respond if it was a save
		if (isset($_POST['save'])) {
			foreach ($_POST as $key => $value) {
				if ($key != 'save') {
					$config->set($key, $value);
				}
			}
			
			$config->save();
			$isSave = true;
		}
		
		// get the instance for looping
		$configInstance = $config->getInstance();
		
		// spit out the html
		$tpl_page .= '<h1>Edit Configuration</h1>';
		
		if ($isSave) {
			$tpl_page .= '<div class="alert alert-green">Configuration saved</div>';
		}
		
		$tpl_page .= '
			<form action="manage_page.php?action=custom_editConfiguration" method="post">
				<table class="table table-half table-sm">
					<tr>
						<td class="text-center" colspan="2">
							These configuration are for the new features which are being implemented.
						</td>
					</tr>
		';
		
		foreach ($configInstance as $key => $value) {
			$tpl_page .= '
				<tr>
					<td class="text-right">
						<label class="label-required custom-edit-configuration-helper">'.$key.'</label>
					</td>
					<td>
						<input type="text" class="input input-block" name="'.$key.'" value="'.$value.'" required>
					</td>
				</tr>
			';
		}
		
		$tpl_page .= '
					<tr>
						<td class="text-center" colspan="2">
							<button class="btn btn-lg" type="submit" name="save">
								<i class="icon icon-floppy-save"></i> Save
							</button>
						</td>
					</tr>
				</table>
			</form>
		';
		
		$tpl_page .= "
			<script>
				var helpText = {
					'protectEnable': 'Enable or disable the password protection. It is advised change the password when you enable this. Only use true or false.',
					'protectPassword': 'The password for the board protection.',
					'protectDuration': 'The duration for which the password remains valid for the user. If zero, user has to login again when the browser is closed. Value is in seconds, and as a protip, 1 day = 86400 seconds.'
				};
			</script>
		";
	}
	
}
