<?php
	// deploy.php by Kruithne (https://github.com/Kruithne/)
	// See LICENSE or README for information and stuff!

	/* GENERAL SETTINGS */
	$options_filename = 'options.ini';

	/**
	 * Check if the script is running in debug mode.
	 * @return bool True if we're in debug mode.
	 */
	function isDebugging()
	{
		return getopt('debug');
	}

	/**
	 * Output a message if debugging is enabled.
	 * @param $msg
	 */
	function debug($msg)
	{
		if (isDebugging())
			output($msg);
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

	/* OPTIONS PROCESSING */
	$options_file = file_get_contents($options_filename);

	// No options file exists, create a new one and cancel the script.
	if ($options_file === FALSE)
	{
		file_put_contents($options_filename, "# Server host\r\nhost=myhost.example.net\r\n\r\n# Server port\r\nport=22\r\n\r\n# Host fingerprint. Run with -fingerprint arg to grab fingerprint automatically.\r\nfingerprint=\r\n\r\n# SSH username\r\nuser=myusername\r\n\r\n# SSH public key file\r\npublic_key=/home/username/.ssh/id_rsa.pub\r\n\r\n# SSH private key file\r\npriv_key=/home/username/.ssh/id_rsa\r\n\r\n# Password for private key file, leave blank if none.\r\npriv_key_pass=\r\n");
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

	/* POST-RUN OPTION PROCESSING */

	// Update the raw options array with any changes we might have made.
	foreach ($options as $key => $value)
		$options_raw[$value[0]] = $key . '=' . $value[1];

	file_put_contents($options_filename, implode("\r\n", $options_raw)); // Store the new options in the file.

	/* END POST_RUN OPTION PROCESSING */
?>