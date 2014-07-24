<?php
	// deploy.php by Kruithne (https://github.com/Kruithne/)
	// See LICENSE or README for information and stuff!

	function cleanup()
	{
		global $temp_dir;
		// Remove temporary directory
		debug('Deleting temporary directory...');
		foreach (scandir($temp_dir) as $temp_file)
		{
			if ($temp_file == '.' || $temp_file == '..')
				continue;

			$temp_file_path = $temp_dir . DIRECTORY_SEPARATOR . $temp_file;

			debug('Deleting temp file: ' . $temp_file_path);
			unlink($temp_file_path);
		}
		rmdir($temp_dir);
	}

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
		{
			cleanup();
			die();
		}
	}

	/**
	 * Make sure there are no double directory separators in a string.
	 * @param string $input Input string to clean.
	 * @return string Cleaned string.
	 */
	function smoothSeparators($input)
	{
		return str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $input);
	}

	/**
	 * Check if a module is active for this runtime.
	 * @param string $module Name of the module to check for.
	 * @return bool True if the module exists and is active.
	 */
	function moduleIsActive($module)
	{
		global $modules;
		return array_key_exists($module, $modules) && $modules[$module]['active'];
	}

	/**
	 * Retrieve an option parsed from the config file.
	 * @param string $key Key to retrieve.
	 * @return string|null Value or NULL if it doesn't exist.
	 */
	function getOption($key)
	{
		global $options;
		if (!array_key_exists($key, $options))
			return NULL;

		$value = $options[$key][1];
		return strlen($value) > 0 ? $value : NULL;
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

	function ignoreMap($ele)
	{
		global $directory;
		return $directory . DIRECTORY_SEPARATOR . $ele;
	}

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
			if ($file == '.' || $file == '..' || $file == '.sass-cache')
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

	error_reporting(E_ERROR | E_PARSE);

	/* GENERAL SETTINGS */
	$run_options = getopt("", Array("debug", "fingerprint", "sass", "uglify", "force", "less", "config:"));
	$options_filename = hasArgument('config') ? $run_options['config'] : 'options.ini';

	// Spawn temporary directory.
	$temp_dir = 'deploy_tmp';
	if (!mkdir($temp_dir))
		output('ERROR: Unable to create temp directory at execution location.', true);

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

	$option_arguments = getOption('options');
	if ($option_arguments !== NULL)
	{
		$option_argument_parts = explode(',', str_replace(Array(' ', '-'), '', $option_arguments));
		foreach ($option_argument_parts as $part)
			$run_options[$part] = false;
	}

	$modules = Array(
		'Sass' => Array(
			'active' => hasArgument('sass'),
			'version_check' => 'sass -v',
			'version_match' => 'Sass',
			'extensions' => Array('scss', 'sass'),
			'new_extension' => 'css',
			'compile' => 'sass %s %s'
		),
		'Less' => Array(
			'active' => hasArgument('less'),
			'version_check' => 'lessc -v',
			'version_match' => 'lessc',
			'extensions' => Array('less'),
			'compile' => 'less %s > %s'
		),
		'UglifyJS' => Array(
			'active' => hasArgument('uglify'),
			'version_check' => 'uglifyjs -V',
			'version_match' => 'uglify-js',
			'extensions' => Array('js'),
			'new_extension' => 'js',
			'compile' => 'uglifyjs --output %2$s %1$s'
		)
	);

	$has_modules = false;

	// Check all modules needed are installed.
	foreach ($modules as $module_name => $module)
	{
		// If the module is not active, skip the version check.
		if ($module['active'] == false)
			continue;

		$has_modules = true;
		$version_check = exec($module['version_check']);
		if (strpos($version_check, $module['version_match']) === 0)
			output('Detected ' . $module_name . ' version: ' . $version_check);
		else
			output('ERROR: ' . $module_name . ' parameter was included but no install of ' . $module_name . ' was found!', true);
	}

	output('Checking files for upload...');

	$directory = getOption('upload_dir');

	// Check the upload directory has been specified and exists.
	if ($directory == NULL || !file_exists($directory))
		output('ERROR: Invalid upload_dir in configuration file.', true);

	// Trim off any trailing directory separators.
	$directory = rtrim($directory, DIRECTORY_SEPARATOR);

	$ignored = Array();
	$ignore_string = getOption('ignore');

	if ($ignore_string != null)
		$ignored = array_map("ignoreMap", explode(',', $ignore_string));

	unset($ignore_string);

	$files = Array();

	explore($directory); // Start the exploration.
	debug('File exploring complete');

	output('Connecting to remote host...');

	// Connect to remote host.
	$connection = ssh2_connect(getOption('host'), getOption('port'));
	if (!$connection)
		output('ERROR: Unable to connect to host, check config file!', true);

	// Fingerprint checking.
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

	// Authentication
	$username = getOption('user');
	if ($username === NULL)
		output('ERROR: No login username specified in options file.', true);

	output('Logging in as ' . $username . '...');

	$password = getOption('password');
	if ($password !== NULL)
	{
		// We have a password, authenticate with that.
		if (ssh2_auth_password($connection, $username, $password))
			debug('Successfully logged in using plaintext authentication.');
		else
			output('ERROR: Unable to authenticate using username/password. Check config!', true);
	}
	else
	{
		// No password, check for SSH keys for authentication.
		debug('No password in config, using SSH key authentication...');

		$public_key = getOption('public_key');
		if ($public_key === NULL)
			output('ERROR: No public key specified in config file.', true);

		$private_key = getOption('priv_key');
		if ($private_key === NULL)
			output('ERROR: No private key specified in config file.', true);

		$private_key_pass = getOption('priv_key_pass');

		if (ssh2_auth_pubkey_file($connection, $username, $public_key, $private_key, $private_key_pass))
			debug('Key-pair authentication successful!');
		else
			output('ERROR: Unable to authenticate with remote host using key-pair');
	}

	debug('Requesting SFTP subsystem from remote host...');
	$sftp = ssh2_sftp($connection);

	output('Uploading changed files...');
	$file_checks = Array();
	$new_file_checks = Array();

	$check_file = hasArgument('force') ? NULL : file_get_contents('file_data');
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

	$remote_location = getOption('remote_dir');
	if ($remote_location === NULL)
		output('ERROR: No remote directory specified.', true);

	$file_register = Array();

	foreach ($files as $file)
	{
		$hash = md5_file($file);

		$file_register[] = $file; // Mark this in the register.
		debug('Marking file in register: ' . $file);

		if (array_key_exists($file, $file_checks) && $file_checks[$file] == $hash)
		{
			debug('Hash match, skipping ' . $file);
			$new_file_checks[] = $file . chr(31) . $hash; // Store the hash.
		}
		else
		{
			debug('Hash mis-match, uploading file ' . $file);
			$upload_file = $file;
			$file_name = substr($file, strlen($directory));
			$upload_file_name = $file_name;

			if ($has_modules)
			{
				$file_name_parts = explode('.', $file_name);
				$ext = array_pop($file_name_parts);

				foreach ($modules as $module_name => $module)
				{
					// Skip module if it's not active.
					if (!$module['active'])
						continue;

					// Check this module wants the file extension.
					if (in_array($ext, $module['extensions']))
					{
						if (array_key_exists('new_extension', $module))
							$upload_file_name = implode('.', $file_name_parts) . '.' . $module['new_extension'];

						$upload_file = $temp_dir . $upload_file_name;
						$cmd = sprintf($module['compile'], $file, $upload_file);
						debug('Module compile command: ' . $cmd);
						exec($cmd);
					}
				}
			}

			$remote_file = smoothSeparators($remote_location . $upload_file_name);

			// Make any required directories to upload the file.
			debug('Making directories for ' . $remote_file);
			ssh2_sftp_mkdir($sftp, dirname($remote_file), 0777, true);

			if (ssh2_scp_send($connection, $upload_file, $remote_file))
			{
				$new_file_checks[] = $file . chr(31) . $hash; // Store the hash.
				output('Successfully uploaded file ' . $remote_file);
			}
			else
			{
				output('Failed to upload file ' . $remote_file);
			}
		}
	}

	output('Deleting missing files...');
	// Delete missing files from server.
	foreach ($file_checks as $check_file => $check_hash)
	{
		debug('Checking register for file: ' . $check_file);
		// Check if the file exists in the register.
		if (!in_array($check_file, $file_register))
		{
			// File was not found in the register, which means it's missing.
			// Attempt to unlink the remote version (may fail if different modules are used).

			debug('File not found, creating assumed remote file name for ' . $check_file);

			$file_name = substr($check_file, strlen($directory));
			$upload_file_name = $file_name;

			if ($has_modules)
			{
				$file_name_parts = explode('.', $file_name);
				$ext = array_pop($file_name_parts);

				foreach ($modules as $module_name => $module)
				{
					// Skip module if it's not active.
					if (!$module['active'])
						continue;

					// Check this module wants the file extension.
					if (in_array($ext, $module['extensions']))
						if (array_key_exists('new_extension', $module))
							$upload_file_name = implode('.', $file_name_parts) . '.' . $module['new_extension'];
				}
			}

			$remote_file = smoothSeparators($remote_location . $upload_file_name);

			output('Deleting old file: ' . $remote_file);
			if (!ssh2_exec($connection, 'rm ' . $remote_file))
				output('UNABLE to delete remote file: ' . $remote_file);
		}
	}

	cleanup();

	debug('Storing latest file checksum data.');
	file_put_contents('file_data', implode(chr(30), $new_file_checks)); // Store new hashes.
	unset($new_file_checks);
	unset($file_checks);

	// Update the raw options array with any changes we might have made.
	foreach ($options as $key => $value)
		$options_raw[$value[0]] = $key . '=' . $value[1];

	file_put_contents($options_filename, implode("\r\n", $options_raw)); // Store the new options in the file.

	output('COMPLETE!');
?>