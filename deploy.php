<?php
	// deploy.php by Kruithne (https://github.com/Kruithne/)
	// See LICENSE or README for information and stuff!

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

	/* Options Processing */
	$options_file = file_get_contents('options.ini');

	// No options file exists, create a new one and cancel the script.
	if ($options_file === FALSE)
	{
		file_put_contents('options.ini', "# Server host\r\nhost=myhost.example.net\r\n\r\n# Server port\r\nport=22\r\n\r\n# Host fingerprint. Run with -fingerprint arg to grab fingerprint automatically.\r\nfingerprint=\r\n\r\n# SSH username\r\nuser=myusername\r\n\r\n# SSH public key file\r\npublic_key=/home/username/.ssh/id_rsa.pub\r\n\r\n# SSH private key file\r\npriv_key=/home/username/.ssh/id_rsa\r\n\r\n# Password for private key file, leave blank if none.\r\npriv_key_pass=\r\n");
		output('ERROR: No options.ini found, creating a template one, go edit it now!', true);
	}
?>