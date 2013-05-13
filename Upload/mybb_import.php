<?php
/*
	Dolphin 7.1 to MyBB 1.6 
	Merge/export script 1.0
	
	Last updated 24 April 2013
	
	Created by Jared Williams
	http://www.jaredwilliams.com.au
	
	Copyright Jared Williams 2013
*/

//Setup...
$categories 	= array();
$forums 		= array();
$topics 		= array();
$users 			= array();
$userfields 	= array();

//Dolphin connection...
$dolphin = array(
	'host'		=> 'localhost',
	'username'	=> 'root',
	'password'	=> '',
	'database'	=> ''
);

//MyBB connection...
$mybb = array(
	'host'		=> 'localhost',
	'username'	=> 'root',
	'password'	=> '',
	'database'	=> '',
	'prefix'	=> 'mybb_',
	'usergroup'	=> 2,
	'passwordtemp'	=> 'hello123'
);

//Outputting to screen...
function output($message) {
	echo $message.'<br />';
}

//Formatting for insertion...
function format($data) {
	//Escape...
	foreach ($data as $key => &$value) {
		$data[$key] = mysql_real_escape_string($value);
	}

	return "('".implode("','", $data)."')";
}

//Connect...
$link = mysql_connect($dolphin['host'], $dolphin['username'], $dolphin['password']) or die('Could not connect to database: ' . mysql_error());

//Database...
mysql_select_db($dolphin['database']);

output('Connected to database '.$dolphin['database']);
output('Starting extraction...');

//Get all categories...
$query = mysql_query("SELECT * FROM `bx_forum_cat`") or die(mysql_error());
if ($query) {
	while ($result = mysql_fetch_array($query)) {
		$data['categories'][] = array(
			'id'			=> $result['cat_id'],
			'name' 			=> $result['cat_name'],
			'order'			=> $result['cat_order'],
			'type'			=> 'c',
			'pid'			=> 0,
			'active'		=> 1,
			'open'			=> 1,
			'allowmycode'	=> 1,
			'allowsmilies'	=> 1,
			'allowimgcode'	=> 1,
			'allowvideocode'=> 1,
			'allowtratings'	=> 1,
			'showinjump'	=> 1
		);
	}
}

output('Found '.count($data['categories']).' categories');

//Get all forums...
$query = mysql_query("SELECT * FROM `bx_forum`") or die(mysql_error());
if ($query) {
	while ($result = mysql_fetch_array($query)) {
		$data['forums'][] = array(
			'id'			=> $result['forum_id'],
			'name' 			=> $result['forum_title'],
			'desc'			=> $result['forum_desc'],
			'categoryid'	=> $result['cat_id'],
			'order'			=> $result['forum_order'],
			'type'			=> 'f',
			'active'		=> 1,
			'open'			=> 1,
			'allowmycode'	=> 1,
			'allowsmilies'	=> 1,
			'allowimgcode'	=> 1,
			'allowvideocode'=> 1,
			'allowtratings'	=> 1,
			'usepostcounts'	=> 1,
			'showinjump'	=> 1
		);
	}
}

output('Found '.count($data['forums']).' forums');

//Get all threads/topics...
$query = mysql_query("SELECT * FROM `bx_forum_topic` AS t LEFT JOIN `Profiles` AS u ON t.`first_post_user` = u.`NickName`") or die(mysql_error());
if ($query) {
	while ($result = mysql_fetch_array($query)) {
		$data['threads'][] = array(
			'id'			=> $result['id'],
			'forumid'		=> $result['forum_id'],
			'name' 			=> $result['topic_title'],
			'author_id' 	=> $result['ID'],
			'author_name'	=> $result['first_post_user'],
			'when'			=> $result['when'],
			'visible'		=> 1
		);
	}
}

output('Found '.count($data['threads']).' threads');

//Get all posts...
$query = mysql_query("SELECT * FROM `bx_forum_post` AS p LEFT JOIN `Profiles` AS u ON p.`user` = u.`NickName`") or die(mysql_error());
if ($query) {
	while ($result = mysql_fetch_array($query)) {
		$data['posts'][] = array(
			'id'			=> $result['post_id'],
			'threadid'		=> $result['topic_id'],
			'forumid'		=> $result['forum_id'],
			'author_id' 	=> $result['ID'],
			'author_name' 	=> $result['user'],
			'when'			=> $result['when'],
			'message' 		=> strip_tags($result['post_text']), //Dolphin stores all posts in HTML, which most forums turn off
			'visible'		=> 1
		);
	}
}

output('Found '.count($data['posts']).' posts');

//Get all users...
$query = mysql_query("SELECT * FROM `Profiles`") or die(mysql_error());
if ($query) {
	while ($result = mysql_fetch_array($query)) {
		$data['users'][] = array(
			'id'		=> $result['ID'],
			'username' 	=> $result['NickName'], //NOTE: Remember users will need to reset passwords!
			'password'	=> md5($mybb['passwordtemp']),
			'email' 	=> $result['Email'],
			'when'		=> strtotime($result['DateReg']),
		);
		
		$data['userfields'][] = array(
			'userid'	=> $result['ID'],
			'location'	=> $result['City'].' '.$result['Country'],
			'bio'		=> strip_tags($result['DescriptionMe']),
			'gender'	=> ucfirst($result['Sex'])
		);
	}
}

output('Found '.count($data['users']).' users');

output('Extraction complete');

//Connect...
$link = mysql_connect($mybb['host'], $mybb['username'], $mybb['password']) or die('Could not connect to database: ' . mysql_error());

//Database...
mysql_select_db($mybb['database']);

output('Connected to database '.$mybb['database']);

output('Starting deleting of current forum data...');

//Truncate the tables... TODO: Option for this?
mysql_query("TRUNCATE TABLE `".$mybb['prefix']."forums`");
mysql_query("TRUNCATE TABLE `".$mybb['prefix']."threads`");
mysql_query("TRUNCATE TABLE `".$mybb['prefix']."posts`");
mysql_query("TRUNCATE TABLE `".$mybb['prefix']."users`");
mysql_query("TRUNCATE TABLE `".$mybb['prefix']."userfields`");

output('Deletion complete');

output('Starting import...');

//Find new starting ID for all categories (since cats and forums share the same table)...
$newid = 0;
for ($i=0; $i<count($data['forums']); $i++) {
	$forumid = $data['forums'][$i]['id'];
	
	//Our newest ID...
	if ($forumid > $newid) {
		$newid = $forumid;
	}
}
//Make sure the ID starts after the last one...
$newid++;

//Update forums...
$newcats = array();
for ($i=0; $i<count($data['forums']); $i++) {
	//The current (old) ID...
	$oldid = $data['forums'][$i]['categoryid'];

	//If no new ID has been generated...
	if (!array_key_exists($oldid, $newcats)) {
		//Update the new category this forum (and other child forums) will use...
		for ($ci=0; $ci<count($data['categories']); $ci++) {
			//If we found the linked category...
			if ($data['categories'][$ci]['id'] == $oldid) {
				//Update it with a new ID...
				$data['categories'][$ci]['id'] = $newid;
			}
		}
		
		//Save the new ID for other child forums to use...
		$newcats[$oldid] = $newid;

		//The next category should be a step up...
		$newid++;
	}
	
	//Update this forum to match the new category ID we generated...
	$data['forums'][$i]['categoryid'] = $newcats[$oldid];
}

//Insert categories...
for ($i=0; $i<count($data['categories']); $i++) {
	$categories[] = format($data['categories'][$i]);
}
$query = mysql_query("INSERT INTO `".$mybb['prefix']."forums` (fid, name, disporder, type, pid, active, open, allowmycode, allowsmilies, allowimgcode, allowvideocode, allowtratings, showinjump) VALUES ".implode(',', $categories)."") or die(mysql_error());
			
output('Imported '.count($categories).' categories');

//Insert forums...
for ($i=0; $i<count($data['forums']); $i++) {
	$forums[] = format($data['forums'][$i]);
}
$query = mysql_query("INSERT INTO `".$mybb['prefix']."forums` (fid, name, description, pid, disporder, type, active, open, allowmycode, allowsmilies, allowimgcode, allowvideocode, allowtratings, usepostcounts, showinjump) VALUES ".implode(',', $forums)."") or die(mysql_error());

output('Imported '.count($forums).' forums');

//Insert threads...
for ($i=0; $i<count($data['threads']); $i++) {
	$threads[] = format($data['threads'][$i]);
}
$query = mysql_query("INSERT INTO `".$mybb['prefix']."threads` (tid, fid, subject, uid, username, dateline, visible) VALUES ".implode(',', $threads)."") or die(mysql_error());

output('Imported '.count($threads).' threads');

//Insert posts...
for ($i=0; $i<count($data['posts']); $i++) {
	$posts[] = format($data['posts'][$i]);
}
$query = mysql_query("INSERT INTO `".$mybb['prefix']."posts` (pid, tid, fid, uid, username, dateline, message, visible) VALUES ".implode(',', $posts)."") or die(mysql_error());

output('Imported '.count($posts).' posts');

//Insert users...
for ($i=0; $i<count($data['users']); $i++) {
	//First user always admin...
	if ($i == 0)		$usergroup = 4;
	else				$usergroup = $mybb['usergroup'];
	
	$users[] = format(array_merge($data['users'][$i], array('usergroup' => $usergroup)));
}
$query = mysql_query("INSERT INTO `".$mybb['prefix']."users` (uid, username, password, email, regdate, usergroup) VALUES ".implode(',', $users)."") or die(mysql_error());

output('Imported '.count($users).' users');

//Insert user fields...
for ($i=0; $i<count($data['userfields']); $i++) {
	$userfields[] = format($data['userfields'][$i]);
}
$query = mysql_query("INSERT INTO `".$mybb['prefix']."userfields` (ufid, fid1, fid2, fid3) VALUES ".implode(',', $userfields)."") or die(mysql_error());
			
output('Imported '.count($userfields).' user fields');

output('Import complete');

output('All users have a temporary password of '.$mybb['passwordtemp'].' so should be directed to reset their passwords');

output('Make sure you rebuild the forum cache and THEN rebuild all statistics through the Admin CP');
?>