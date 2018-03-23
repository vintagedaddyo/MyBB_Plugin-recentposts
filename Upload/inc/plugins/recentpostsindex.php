<?php

/*
 * MyBB: Recent Posts Forum Index
 *
 * File: recentpostsindex.php
 * 
 * Authors: borbole & Vintagedaddyo
 *
 * MyBB Version: 1.8
 *
 * Plugin Version: 1.1
 * 
 */

//Trying to access directly the file, are we :D

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

//Hooking into index_start with our function
$plugins->add_hook("index_start", "recentpostsindex_box");


//Show some info about our mod
function recentpostsindex_info()
{
    global $lang;

    $lang->load("recentpostsindex");
    
    $lang->recentpostsindex_Desc = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float:right;">' .
        '<input type="hidden" name="cmd" value="_s-xclick">' . 
        '<input type="hidden" name="hosted_button_id" value="AZE6ZNZPBPVUL">' .
        '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' .
        '<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">' .
        '</form>' . $lang->recentpostsindex_Desc;

    return Array(
        'name' => $lang->recentpostsindex_Name,
        'description' => $lang->recentpostsindex_Desc,
        'website' => $lang->recentpostsindex_Web,
        'author' => $lang->recentpostsindex_Auth,
        'authorsite' => $lang->recentpostsindex_AuthSite,
        'version' => $lang->recentpostsindex_Ver,
        'codename' => $lang->recentpostsindex_CodeName,
        'compatibility' => $lang->recentpostsindex_Compat
    );
}

//Activate it
function recentpostsindex_activate()
{
	global $db, $lang;

    $lang->load("recentpostsindex");
	
	//Insert the mod settings in the forumhome settinggroup. It looks beter there :D
	
	$query = $db->simple_select("settinggroups", "gid", "name='forumhome'");
	$gid = $db->fetch_field($query, "gid");
	
	
	$setting = array(
		'name' => 'enable',
		'title' => $lang->recentpostsindex_option_1_Title,
		'description' => $lang->recentpostsindex_option_1_Description,
		'optionscode' => 'yesno',
		'value' => '1',
		'disporder' => '90',
		'gid' => intval($gid)
	);
	$db->insert_query('settings',$setting);
	
	$setting = array(
		"name" => "limit_posts_nr",
		"title" => $lang->recentpostsindex_option_2_Title,
		"description" => $lang->recentpostsindex_option_2_Description,
		"optionscode" => "text",
		"value" => "5",
		"disporder" => "91",
		"gid" => intval($gid),
		);

	$db->insert_query("settings", $setting);	
	
	rebuild_settings();
	
   //Add our custom var in the index template to display the latest posts box

   require_once MYBB_ROOT."/inc/adminfunctions_templates.php";

   find_replace_templatesets("index", "#".preg_quote('{$header}') . "#i", '{$header}' . "\n" . '{$recentposts}');

}


//Don't want to use it anymore? Let 's deactivate it then and drop the settings and the custom var as well

function recentpostsindex_deactivate()
{
	global $db;
	
$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='enable'");	
$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='limit_posts_nr'");

rebuild_settings();	
	
require_once MYBB_ROOT."/inc/adminfunctions_templates.php";

find_replace_templatesets("index", "#".preg_quote('{$header}' . "\n" . '{$recentposts}') . "#i", '{$header}',0);

}


//Insert our function 

function recentpostsindex_box()
{
	global $db, $mybb, $lang, $theme, $recentposts;
	
	//Enable it

    if($mybb->settings['enable'] == 1 )
	{
	    //Load the language files and set up the table for the recent posts box

	    $lang->load('recentpostsindex');

	    $recentposts .= '
		<table border="0" cellspacing="' . $theme['borderwidth'] . '" cellpadding="' . $theme['tablespace'] . '" class="tborder">
            <tbody>
                <tr>
                   <td class="thead" colspan="4" align="center">
                       <strong>' . $lang->recentpostname . '</strong>
				   </td>
               </tr>
               <tr>
                   <td class="tcat" width="45%"><span class="smalltext"><strong>' . $lang->recentpoststitle . '</strong></span></td>
                   <td class="tcat" align="center" width="15%"><span class="smalltext"><strong>' . $lang->poster . '</strong></span></td>
                   <td class="tcat" align="center" width="15%"><span class="smalltext"><strong>' . $lang->lastposttime . '</strong></span></td>
                   <td class="tcat" align="center" width="20%"><span class="smalltext"><strong>' . $lang->postforum . '</strong></span></td>
               </tr>
           ';

	    //Preserve the forum viewing permissions intact
		
		$fids = "";
        $unviewablefids = get_unviewable_forums();
		
        if($unviewablefids)
        {
            $fids = "WHERE t.fid NOT IN ({$unviewablefids})";
        }
        
		//Exclude inactive forums from showing up
		
		$inactivefids = get_inactive_forums();
	    if ($inactivefids)
		{
		    $fids .= " WHERE t.fid NOT IN ($inactivefids)";
	    }		
		
		
        //Run the query to get the most recent posts along with their posters, time and forums
		
	   $query = $db->query("
	   SELECT t.tid, t.fid, t.subject, t.lastpost, 
	   t.lastposter, t.lastposteruid, f.name,
	   u.usergroup, u.displaygroup
	   FROM ".TABLE_PREFIX."threads AS t
       INNER JOIN ".TABLE_PREFIX."forums as f
	   ON (f.fid = t.fid)
	   LEFT JOIN " . TABLE_PREFIX . "users AS u 
	   ON (t.lastposteruid = u.uid)
	   {$fids}
	   AND t.visible = '1'
	   GROUP BY t.tid
	   ORDER BY t.lastpost DESC 
	   LIMIT " . $mybb->settings['limit_posts_nr']);
	
	    while($row = $db->fetch_array($query))
	    {
		   $recentposts .= '
		   <tr>';
		   
		   //Trim the thread titles if they are over 49 characters
		   
		   $subject = htmlspecialchars_uni($row['subject']);
		   
		   if (strlen($subject) > 49)
		   {
	          $subject = substr($subject, 0, 49) . "..."; 
		   }
		   
		   //Trim the usernames if they are over 9 characters
		   
		   if (strlen($row['lastposter']) > 9)
		   {
	          $row['lastposter'] = substr($row['lastposter'], 0, 9) . "..."; 
		   }
		   
		    //Trim the forum names if they are over 19 characters so everything will be in porpotion

		   if (strlen($row['name']) > 19)
		   {
	          $row['name'] = substr($row['name'], 0, 19) . "..."; 
		   }
		   
		   //Get the date and time of the most recent posts
		   
		   $lastpostdate = my_date($mybb->settings['dateformat'], $row['lastpost']);
		   $lastposttime = my_date($mybb->settings['timeformat'], $row['lastpost']);
		   
		   //Get the usernames and make them pretty too with the group styling
		   
		   $username = build_profile_link(format_name($row['lastposter'],$row['usergroup'],$row['displaygroup']), $row['lastposteruid']);
		   
		   //Display them all trimmed up and pretty :D
		   
		   $recentposts .= '
		   <td class="trow1" width="45%">
		      <a href="showthread.php?tid=' . $row['tid'] . '&amp;action=lastpost">' . $subject .'</a> 
		   </td>
		   
		   <td class="trow1" align="center" width="15%">
		      ' . $username . '
		   </td>
		   
		   <td class="trow1" align="center" width="15%">
		   ' .$lastpostdate . ' ' . $lastposttime . '
		   </td>
		   
		   <td class="trow1" align="center" width="20%">
		       <a href="forumdisplay.php?&amp;fid=' . $row['fid'] . '">' . $row['name'] . '</a>
		    </td>
		  </tr>';
	    }
          
		  //End of mod. I hope you enjoy it as much as I did coding it :)
		  
          $recentposts .= "</tbody></table><br /><br />";

   }

}

?>