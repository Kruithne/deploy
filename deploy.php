<?php
	// deploy.php by Kruithne (https://github.com/Kruithne/)
	// See LICENSE or README for information and stuff!

	error_reporting(E_ERROR | E_PARSE);

	/* GENERAL SETTINGS */
	$options_filename = 'options.ini';
	$run_options = getopt("", Array("debug", "fingerprint"));

	/**
	 * Check if we have an argument passed to the script.
	 * @param string $arg Argument to check for.
	 * @return bool True if we have the argument.
	 */
	function hasArgument($arg)
	{
		global $run_options;
		return array_key_exists($arg, $run_options);
	}

	/**
	 * Check if the script is running in debug mode.
	 * @return bool True if we're in debug mode.
	 */
	function isDebugging()
	{
		return hasArgument('debug');
	}

	/**
	 * Output a message if debugging is enabled.
	 * @param $msg
	 */
	function debug($msg)
	{
		if (isDebugging())
			output('[DEBUG]: ' . $msg);
	}

	/**
	 * Output a message.
	 * @param string $msg Message to output
	 * @param bool $die Terminate the script after outputting.
	 */
	function output($msg, $die = false)
	{
		echo $msg . PHP_EOL;

		if ($die)
			die();
	}

	debug('DEBUG ENABLED');

	// Check we have SSH2 installed and set-up
	if (!function_exists('ssh2_connect'))
		output('ERROR: php_ssh2 not found, please install it!', true);

	/* OPTIONS PROCESSING */
	$options_file = file_get_contents($options_filename);

	// No options file exists, create a new one and cancel the script.
	if ($options_file === FALSE)
	{
		file_put_contents($options_filename, "# Server host\r\nhost=myhost.example.net\r\n\r\n# Server port\r\nport=22\r\n\r\n# Host fingerprint. Run with --fingerprint arg to grab fingerprint automatically.\r\nfingerprint=\r\n\r\n# SSH username\r\nuser=myusername\r\n\r\n# SSH password. Leave blank to use SSH key authentication.\r\npassword=\r\n\r\n# SSH public key file\r\npublic_key=/home/username/.ssh/id_rsa.pub\r\n\r\n# SSH private key file\r\npriv_key=/home/username/.ssh/id_rsa\r\n\r\n# Password for private key file, leave blank if none.\r\npriv_key_pass=\r\n\r\n# Upload directory; All sub-files and directories will be uploaded to the host.\r\nupload_dir=/home/username/myproject/\r\n\r\n# Remote directory; All files/directories will be uploaded to here.\r\nremote_dir=/home/remote_username/stuff/myproject/\r\n\r\n# Files/directories to ignore within the upload_dir, seperated by comma.\r\nignore=random/Something.txt");
		output('ERROR: No options.ini found, creating a template one, go edit it now!', true);
	}

	$options_raw = explode("\r\n", $options_file);
	$options = Array();

	$line_index = 0;
	foreach ($options_raw as $option_line)
	{
		if (strlen($option_line) > 0 && $option_line[0] != '#')
		{
			$split = explode("=", $option_line);
			$options[$split[0]] = Array($line_index, $split[1]);
		}
		$line_index++;
	}
	unset($line_index);

	/**
	 * Retrieve an option parsed from the config file.
	 * @param string $key Key to retrieve.
	 * @return string|null Value or NULL if it doesn't exist.
	 */
	function getOption($key)
	{
		global $options;
		return array_key_exists($key, $options) ? $options[$key][1] : NULL;
	}

	/**
	 * Set an option to be saved in the config file after the script runs.
	 * @param string $key Key to store the option by.
	 * @param string $value Value of the option.
	 */
	function setOption($key, $value)
	{
		global $options;
		if (array_key_exists($key, $options))
			$options[$key][1] = $value;
	}

	/* END OPTIONS PROCESSING */

	/* FILE PROCESSING */

	output('Checking files for upload...');

	$directory = getOption('upload_dir');

	// Check the upload directory has been specified and exists.
	if ($directory == NULL || !file_exists($directory))
		output('ERROR: Invalid upload_dir in configuration file.', true);

	$ignored = Array();
	$ignore_string = getOption('ignore');

	function ignoreMap($ele)
	{
		global $directory;
		return $directory . DIRECTORY_SEPARATOR . $ele;
	}

	if ($ignore_string != null)
		$ignored = array_map("ignoreMap", explode(',', $ignore_string));

	unset($ignore_string);

	$files = Array();

	/**
	 * Explore a directory, placing all files into the $files array and
	 * initiating itself on any directories found. Anything found in the
	 * $ignored array will be skipped over.
	 * @param string $dir Directory to explore.
	 */
	function explore($dir)
	{
		global $files, $ignored;

		debug('Exploring directory ' . $dir);
		foreach (scandir($dir) as $file)
		{
			if ($file == '.' || $file == '..')
				continue;

			$path = $dir . DIRECTORY_SEPARATOR . $file;

			if (in_array($path, $ignored))
			{
				debug('Skipping ignored file ' . $path);
			}
			else
			{
				if (is_dir($path))
				{
					explore($path);
				}
				else
				{
					debug('Found file ' . $path);
					$files[] = $path;
				}
			}
		}
	}

	explore($directory); // Start the exploration.
	debug('File exploring complete');

	output('Connecting to remote host...');

	$connection = ssh2_connect(getOption('host'), getOption('port'));
	if (!$connection)
		output('ERROR: Unable to connect to host, check config file!', true);

	$server_fingerprint = ssh2_fingerprint($connection, SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);
	if (hasArgument('fingerprint'))
	{
		setOption('fingerprint', $server_fingerprint);
		output('Caching server fingerprint in options file. Remove --fingerprint flag for future executions.');
	}
	else
	{
		$fingerprint = getOption('fingerprint');
		if (strcmp($fingerprint, $server_fingerprint) !== 0)
			output('ERROR: Unable to verify server fingerprint. Run with --fingerprint flag once to skip the check and cache the remote hosts fingerprint.', true);
		else
			debug('Cached fingerprint matches remote host fingerprint.');
	}
	unset($server_fingerprint);

	debug('Sorting files for upload...');
	$upload_files = Array();
	$file_checks = Array();
	$new_file_checks = Array();

	$check_file = file_get_contents('file_data');
	if ($check_file !== NULL)
	{
		debug('Checksum file exists, loading from file now...');
		foreach (explode(chr(30), $check_file) as $check_file_line)
		{
			$split = explode(chr(31), $check_file_line);
			$file_checks[$split[0]] = $split[1];
		}
	}
	else
	{
		debug('No checksum file exists, a new one will be created!');
	}

	foreach ($files as $file)
	{
		$hash = md5_file($file);
		if (array_key_exists($file, $file_checks) && $file_checks[$file] == $hash)
		{
			debug('Hash match, skipping ' . $file);
			$new_file_checks[] = $file . chr(31) . $hash; // Store the hash.
		}
		else
		{
			debug('Hash mis-match, uploading file ' . $file);
			$upload_files[] = $file;
		}
	}

	debug('Storing latest file checksum data.');
	file_put_contents('file_data', implode(chr(30), $new_file_checks)); // Store new hashes.
	unset($new_file_checks);
	unset($file_checks);

	/* END FILE PROCESSING */

	/* POST-RUN OPTION PROCESSING */

	// Update the raw options array with any changes we might have made.
	foreach ($options as $key => $value)
		$options_raw[$value[0]] = $key . '=' . $value[1];

	file_put_contents($options_filename, implode("\r\n", $options_raw)); // Store the new options in the file.

	/* END POST_RUN OPTION PROCESSING */
?>