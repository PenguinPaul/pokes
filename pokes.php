<?php
define("IN_MYBB",1);
require_once("global.php");

if($mybb->user['uid'] == 0)
{
	error_no_permission();
}

$usergroups = explode(",",$mybb->settings['pokes_usergroups']);

if($mybb->settings['poke_indis'] == 0)
{
	if(in_array($mybb->user['usergroup'],$usergroups))
		error_no_permission();
} else {
	if(!in_array($mybb->user['usergroup'],$usergroups))
		error_no_permission();
}

if($mybb->input['action'] == "poke")
{

	verify_post_check($mybb->input['my_post_key']);

	if(isset($mybb->input['puid']))
	{
		$query = $db->simple_select("pokes","*","fromid='{$mybb->user['uid']}' AND toid='".intval($mybb->input['puid'])."'");

		if($db->num_rows($query) != 0)
		{
			poke_error("You have already poked this user and they have not responded to your poke yet.");
		} else {
			pokeuser(intval($mybb->input['puid']),$mybb->user['uid']);

			if(isset($mybb->input['pokeback']))
			{
				$pid = intval($mybb->input['pokeback']);
				$query = $db->simple_select("pokes","*","pid='{$pid}'");
				$pbarr = $db->fetch_array($query);
				if($pbarr['toid'] == $mybb->user['uid'])
				{
					$db->delete_query("pokes","pid='{$pid}'");
				}
			}

			redirect("pokes.php","Poke successful!");
		}
	} else {
		poke_error("Invalid uid");
	}
}

if($mybb->input['action'] == "quickpoke")
{
	verify_post_check($mybb->input['my_post_key']);

	$query = $db->simple_select("users","uid","username='".$db->escape_string($mybb->input['username'])."'");

	if($query)
	{
		$arr = $db->fetch_array($query);
		header("Location: pokes.php?action=poke&puid={$arr['uid']}&my_post_key={$mybb->post_code}&noredirect");
	} else {
		poke_error("Invalid username.");
	}
}

if($mybb->input['action'] == "delete")
{
	verify_post_check($mybb->input['my_post_key']);
	$pid = intval($mybb->input['pid']);
	$query = $db->simple_select("pokes","*","pid='{$pid}'");
	$parr = $db->fetch_array($query);
	if($parr['toid'] != $mybb->user['uid'])
	{
		poke_error("You cannot delete that poke.");
	}
	
	$db->delete_query("pokes","pid='{$pid}'");
	redirect("pokes.php?deleted{$eurl}","Poke deleted.");		
}

$allpokes = "";

$query = $db->simple_select("pokes","*","toid='{$mybb->user['uid']}'");

while($poke = $db->fetch_array($query))
{
	$user = get_user($poke['fromid']);

	$user['avatar'] = htmlspecialchars_uni($user['avatar']);

	$avatar_dimensions = explode("|", $user['avatardimensions']);

	if($avatar_dimensions[0] && $avatar_dimensions[1])
	{
		if($avatar_dimensions[0] > 80 || $avatar_dimensions[1] > 80)
		{
			require_once MYBB_ROOT."inc/functions_image.php";
			$scaled_dimensions = scale_image($avatar_dimensions[0], $avatar_dimensions[1], 80, 80);
			$avatar_width_height = "width=\"{$scaled_dimensions['width']}\" height=\"{$scaled_dimensions['height']}\"";
		}
		else
		{
			$avatar_width_height = "width=\"{$avatar_dimensions[0]}\" height=\"{$avatar_dimensions[1]}\"";	
		}
	}

	$allpokes .= "
  <tr>
    <td rowspan=\"2\" class=\"trow".($poke['status']+1)."\" style=\"white-space: nowrap\"><a href=\"javascript:void(0);\" onclick=\"window.opener.location.href='".get_profile_link($user['uid'])."';\">
<img src=\"{$user['avatar']}\" alt=\"Avatar of {$user['username']}\" {$avatar_width_height}/></a></td>
    <td class=\"trow1\" style=\"white-space: nowrap\">{$user['username']} poked you on ".date("F j, Y, \@ g:i a",$poke['time'])."</td>

  </tr>
  <tr>
    <td class=\"trow".($poke['status']+1)."\" style=\"white-space: nowrap\"><a href=\"pokes.php?action=poke&amp;puid={$poke['fromid']}&amp;my_post_key={$mybb->post_code}&amp;pokeback={$poke['pid']}\">Poke Back &#9758;</a> | <a href=\"pokes.php?action=delete&amp;pid={$poke['pid']}&amp;my_post_key={$mybb->post_code}\">Delete Poke</a></td>
  </tr>";
}

if($allpokes != "")
{
	$uarr['status'] = '0';
	$db->update_query("pokes",$uarr,"toid='{$mybb->user['uid']}'");
} else {
	$allpokes = "You have no pokes.";
}


$allpokes = "
<form action=\"pokes.php\">
Poke a user: <input type=\"text\" class=\"textbox\" id=\"username\" name=\"username\" size=\"35\" maxlength=\"30\" />
<input type=\"hidden\" name=\"action\" value=\"quickpoke\" />
<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />
&nbsp;<input type=\"submit\" value=\"Poke\" />
</form>

<script type=\"text/javascript\" src=\"jscripts/autocomplete.js?ver=1400\"></script>
<script type=\"text/javascript\">
<!--
	if(use_xmlhttprequest == \"1\")
	{
		new autoComplete(\"username\", \"xmlhttp.php?action=get_users\", {valueSpan: \"username\"});
	}
// -->
</script>
<br /><br />
".$allpokes;


$title = "Pokes - {$mybb->settings['bbname']}";


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
				<div><strong>Pokes</strong></div>
			</td>
		</tr>
		<tr>
			<td class=\"trow2\">
				<div style=\"overflow: auto; height: 300px;\">
					<table width=\"100%\" cellspacing=\"{$theme['borderwidth']}\" cellpadding=\"{$theme['tablespace']}\" border=\"0\" align=\"center\" class=\"tborder\" style=\"border: 0;\">
						{$allpokes}
					</table>
				</div>
			</td>
		</tr>
		</table>
	</body>
</html>";

output_page($page);

?>
