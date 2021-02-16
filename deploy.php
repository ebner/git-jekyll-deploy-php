<?php
/**
 * Git/Jekyll deploy script
 *
 * Author: Hannes Ebner <hannes@ebner.se>, 2014
 *
 * Clones a Git repository and builds and deploys the contained Jekyll site.
 *
 * To be used as post-commit hook.
 *
 * Possible sources of failure:
 *
 * - The public key of www-data has to have read access to the source repository.
 * - The connection will fail if the SSH server is not trusted, to avoid this the
 *   server should be access from the command line at least once to get its
 *   fingerprint into the local SSH configuration.
 *
 * License
 *
 * Hannes Ebner licenses this work under the terms of the Apache License 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. See the LICENSE file distributed with this work for the full License.
 */

// goes into the "token"-URL parameter, recommended to use "uuid" command
define('ACCESS_TOKEN', '9d9b0532-afb4-11e3-a057-3c970e88a290');

// which repository to fetch
define('SOURCE_REPOSITORY', 'git@bitbucket.org:org/repo.git');

// which directory to deploy to
define('DEPLOY_TARGET', '/var/www/site/');

// a directory to to clone to, will be removed after deployment
define('LOCAL_CACHE', '/tmp/gjds-'.md5(SOURCE_REPOSITORY).'/');

// which branch to deploy
define('BRANCH', 'deploy');

// Time limit in seconds for each command
if (!defined('TIME_LIMIT')) define('TIME_LIMIT', 60);

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Git/Jekyll deploy script</title>
</head>
<body>
<?php
if (!isset($_GET['token']) || $_GET['token'] !== ACCESS_TOKEN) {
	die('Access denied');
}

if (isset($_GET['async'])) {
        ob_end_clean();
        header("Connection: close");
        ignore_user_abort(true);
        ob_start();
        $size = ob_get_length();
        header("Content-Length: $size");
        http_response_code(202);
        ob_end_flush();
        flush();
}
?>

<pre>
<?php

putenv('LANG=en_US.UTF-8');

$commands = array();

if (!is_dir(sprintf('%s/%s', LOCAL_CACHE, '.git'))) {
	// clone the repository into the LOCAL_CACHE
	$commands[] = sprintf('git clone --depth=1 --branch %s %s %s', BRANCH, SOURCE_REPOSITORY, LOCAL_CACHE);
} else {
	// fetch updates to previously cloned LOCAL_CACHE
	$commands[] = sprintf('git  --git-dir="%s.git" --work-tree="%s" fetch origin %s', LOCAL_CACHE, LOCAL_CACHE, BRANCH);
}

// use Jekyll to build and deploy to target directory
$commands[] = sprintf('jekyll build --source %s --destination %s', LOCAL_CACHE, DEPLOY_TARGET);

// cleanup
$commands[] = sprintf('rm -rf %s', LOCAL_CACHE);

// run commands
foreach ($commands as $command) {
	set_time_limit(TIME_LIMIT); // Reset the time limit for each command
	$tmp = array();
	exec($command.' 2>&1', $tmp, $return_code); // Execute the command
	// Output the result
	printf('$ %s <br/>%s<br/>', htmlentities(trim($command)), htmlentities(trim(implode("\n", $tmp))));
	flush();

	if ($return_code !== 0) {
		printf('Error encountered! Script stopped to prevent data loss.');
		break;
	}
}
?>

Done.
</pre>
</body>
</html>
