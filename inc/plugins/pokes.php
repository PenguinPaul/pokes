<?php
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Nope.  MyBB Labs plugins are secure.");
}

$plugins->add_hook("global_start", "pokes");
$plugins->add_hook("member_profile_end", "pokeprofile");


function pokes_info()
{
	return array(
		"name"			=> "Pokes",
		"description"	=> "Facebook-like pokes for your forum.",
		"website"		=> "https://github.com/PenguinPaul/pokes",
		"author"		=> "Paul H.",
		"authorsite"	=> "http://www.paulhedman.com",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

function pokes_install()
{
	global $db;
	
	$db->write_query("CREATE TABLE `".TABLE_PREFIX."pokes` (
	  `pid` int(11) NOT NULL auto_increment,
	  `fromid` int(11) NOT NULL,
	  `toid` int(11) NOT NULL,
	  `where` text NOT NULL,
	  `status` int(11) NOT NULL,
	  `time` int(11) NOT NULL,
	  PRIMARY KEY  (`pid`)
	) ENGINE=MyISAM;");


	$group = array(
		'gid'			=> 'NULL',
		'name'			=> 'pokesgroup',
		'title'			=> 'Pokes Settings',
		'description'	=> 'Settings for the Pokes plugin.',
		'disporder'		=> "1",
		'isdefault'		=> 'no',
	);

	$db->insert_query('settinggroups', $group);

	$gid = $db->insert_id();
	
	$setting = array(
		'name'			=> 'pokes_usergroups',
		'title'			=> 'Usergroups',
		'description'	=> 'Usergroups that can/cannot (depending on the next setting) use the plugin',
		'optionscode'	=> 'text',
		'value'			=> '5,7',
		'disporder'		=> 1,
		'gid'			=> intval($gid),
	);

	$db->insert_query('settings', $setting);

	$setting = array(
		'name'			=> 'pokes_indis',
		'title'			=> 'Usergroup include/disclude',
		'description'	=> 'Can the above usergroups use (yes) or not use (no) use this plugin?',
		'optionscode'	=> 'yesno',
		'value'			=> '0',
		'disporder'		=> 2,
		'gid'			=> intval($gid),
	);

	$db->insert_query('settings', $setting);

	rebuild_settings();
}

function pokes_is_installed()
{
	global $db;
	return $db->table_exists("pokes");
}

function pokes_activate()
{
	global $db;

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";

	find_replace_templatesets('member_profile',
		'#' . preg_quote('{$formattedname}</strong></span>') . '#',
		'{$formattedname}</strong></span>{$pokeuser}'
	);
	
	find_replace_templatesets('header_welcomeblock_member',
		'#' . preg_quote('{$lang->welcome_pms_usage}') . '#',
		'{$lang->welcome_pms_usage} | {$pokes}'
	);
}

function pokes_deactivate()
{
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";

	find_replace_templatesets('member_profile',
		'#' . preg_quote('{$pokeuser}') . '#',
		''
	);
	
	find_replace_templatesets('header_welcomeblock_member',
		'#' . preg_quote(' | {$pokes}') . '#',
		''
	);
}

function pokes_uninstall()
{
	global $db;
	$db->drop_table("pokes");
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN ('pokes_usergroups','pokes_indis')");
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='pokesgroups'");
	rebuild_settings(); 
}

function pokes()
{
	global $pokes,$db,$mybb;

	$usergroups = explode(",",$mybb->settings['pokes_usergroups']);

	if($mybb->settings['poke_indis'] == 0)
	{
		if(in_array($mybb->user['usergroup'],$usergroups))
			return;
	} else {
		if(!in_array($mybb->user['usergroup'],$usergroups))
			return;
	}

	$query = $db->simple_select("pokes","*","toid='{$mybb->user['uid']}' ORDER BY status DESC");

	$pnum = 0;
	$wnum = 0;
	$tnum = 0;

	while($pokecounter = $db->fetch_array($query))
	{
		if(!$puser)
		{
			$puser = get_user($pokecounter['fromid']);
		}

		if($pokecounter['status'] == 1)
		{
			$pnum++;
		} else {
			$wnum++;
		}

		$tnum++;
	}

	if($pnum != 0)
	{

		if($wnum != 0)
		{
			$waiting = " ({$wnum} others waiting)";
		}
		
		if($pnum == 1)
		{	
			$pokes = " <a href=\"#\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/pokes.php', 'pokes', 500, 350);\">You have {$pnum} new poke from {$puser['username']}!{$waiting}</a>";
		} elseif($pnum == 2) {
			$pokes = " <a href=\"#\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/pokes.php', 'pokes', 500, 350);\">You have {$pnum} new pokes from {$puser['username']} and 1 other user!{$waiting}</a>";
		} else {
			$pokes = " <a href=\"#\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/pokes.php', 'pokes', 500, 350);\">You have {$pnum} new pokes from {$puser['username']} and ".($pnum-1)." other users!{$waiting}</a>";
		}
	} else {

		if($wnum != 0)
		{
			$waiting = " ({$wnum} waiting)";
		}

		$pokes = "<a href=\"#\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/pokes.php', 'pokes', 500, 350);\">You have no new pokes{$waiting}.</a>";
	}
}

function pokeprofile()
{
	global $pokeuser,$mybb,$memprofile;
	if($memprofile['uid'] != $mybb->user['uid'])
	{
		$pokeuser = " <span class=\"smalltext\">(<a href=\"#\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/pokes.php?action=poke&amp;puid={$memprofile['uid']}&amp;my_post_key={$mybb->post_code}', 'pokes', 500, 350);\">Poke {$memprofile['username']}</a>)</span>";
	}
}

function pokeuser($toid,$fromid)
{
	global $db,$mybb;
	if($toid == $fromid)
	{
		error("You cannot poke yourself.");
	}
	$iarr['pid'] = null;
	$iarr['fromid'] = $fromid;
	$iarr['toid'] = $toid;
	$iarr['status'] = '1';
	$iarr['time'] = TIME_NOW;
	$db->insert_query("pokes",$iarr);
}

function poke_error($errormsg)
{
	global $headerinclude, $theme;
	$title = "Error - Pokes - {$mybb->settings['bbname']}";

	$page = "<html>
		<head>
		<title>{$title}</title>
		<meta http-equiv=\"refresh\" content=\"60; URL=pokes.php\" />
		{$headerinclude}
		<style type=\"text/css\">
		body {
			text-align: left;
		}
		</style>
		</head>
		<body style=\"margin:0; padding: 4px; top: 0; left: 0;\">
			<table width=\"100%\" cellspacing=\"{$theme['borderwidth']}\" cellpadding=\"{$theme['tablespace']}\" border=\"0\" align=\"center\" class=\"tborder\">
			<tr>
				<td class=\"thead\">
					<div class=\"float_right\" style=\"margin-top: 3px;\"><span class=\"smalltext\"><a href=\"#\" onclick=\"window.close();\">Close</a></span></div>
					<div><strong>Pokes Error</strong></div>
				</td>
			</tr>
			<tr>
				<td class=\"trow2\">
					<div style=\"overflow: auto; height: 300px;\">
						<table width=\"100%\" cellspacing=\"{$theme['borderwidth']}\" cellpadding=\"{$theme['tablespace']}\" border=\"0\" align=\"center\" class=\"tborder\" style=\"border: 0;\">
							<h3>Error</h3><br />
							{$errormsg}<br />
							<a href=\"pokes.php\">Back</a>
						</table>
					</div>
				</td>
			</tr>
			</table>
		</body>
	</html>";

	output_page($page);
	exit;
}

?>
