<?php
	// deploy.php by Kruithne (https://github.com/Kruithne/)
	// See LICENSE or README for information and stuff!

	$debugging = getopt('debug'); // Sets debug to on if the -debug arg is passed to the script.

	/**
	 * Check if the script is running in debug mode.
	 * @return bool True if we're in debug mode.
	 */
	function isDebugging()
	{
		global $debugging;
		return $debugging;
	}

	/**
	 * @param string $msg Message to output
	 * @param bool $debug Is the message debugging?
	 */
	function output($msg, $debug = true)
	{
		if ($debug)
		{
			if (!isDebugging())
				return;

			echo '[DEBUG]: ';
		}
		echo $msg . PHP_EOL;
	}
?>