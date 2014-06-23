deploy - PHP deployment script
==============================

deploy is a simple PHP script designed to make syncing your project to a remote host from a development or staging server
much simpler.

Features
--------

* Automatic upload of files to a supplied remote host via SFTP.
* Caches file checksums and only uploads changed files to save time and bandwidth.
* Supports both plaintext and key-pair authentication through SSH.
* [Optional] Compiles *.scss/*.sass files with Sass during upload.
* [Optional] Compiles *.less files with less during upload.
* [Optional] Minifies *.js files with UglifyJS during upload.


Getting Started
---------------

Simply grab the *deploy.php* file and run it once using the following command.
```
php deploy.php
```
deploy will show an error and create   a template options file which is self explanatory, go through and edit the configuration as needed and then run the script again using the same command above!


Enabling Features
----------------

Extra features can be enabled by passing long-option arguments to the script, below is a simple example of passing one of these flags to the script!
```
php deploy.php --debug
```
Below you can find a list of flags and what they do.

* **--debug**: Enables debugging output, can be quite spammy!
* **--fingerprint**: Automatically grab the remote hosts fingerprint and store it in configuration during key-pair authentication.
* **--sass**: Compile any *.scss/*.sass files using Sass during upload. (Requires Sass installed)
* **--less**: Compile any *.less files using Less during upload. (Requires Less installed)
* **--uglify**: Minify any *.js files using UglifyJS during upload. (Requires UglifyJS installed)
* **--force**: Invalidate the file cache and upload every file regardless. Equivalent to deleting *file_data*.
* **--config <file>**: Path to an alternative config file to use, making switching between set-ups easier.

You can send arguments into the script from the configuration file by adding the value *options* to the configuration file. With the example below added to your configuration file the script will act as if the Sass and UglifyJS arguments had been passed in.
```
options=sass,uglify
```