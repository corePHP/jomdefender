<?php
if (!defined('_JEXEC')) {
	die('Direct Access to this location is not allowed.');
}
/**
 * @version		$Id: jomdefender.php 1 2009-10-27 20:56:04Z rafael $
 * @package		jomDefender
 * @copyright	Copyright (C) 2010 'corePHP' / corephp.com. All rights reserved.
 * @license		{http://www.gnu.org/licenses/gpl-2.0.html} GNU/GPL, see LICENSE.txt
 */

global $JomDefender_plugin, $JomDefender_params;

// Import library dependencies
jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');

$JomDefender_application = JFactory::getApplication();
$JomDefender_plugin = JPluginHelper::getPlugin('system', 'jomdefender');
$JomDefender_params      = new JRegistry( $JomDefender_plugin->params );

class plgSystemJomDefender extends JPlugin {
	/**
	 * Name of plugin, namespace
	 *
	 * @var string
	 **/
	var $name = 'jomdefender';

	/**
	 * String of get variables used for
	 * caching purposes
	 *
	 * @var string
	 **/
	var $request = '';

	/**
	 * This variable contains the cached
	 * version of the current page
	 *
	 * @var bool|string
	 **/
	var $haveCache = false;

	/**
	 * True if the application is loading a Site page
	 *
	 * @var bool
	 **/
	var $_is_site = false;

	/**
	 * True if the application is loading a Admin page
	 *
	 * @var bool
	 **/
	var $_is_admin = false;

	/**
	 * To disable the whole plugin
	 * This is set by the parameter 'enable_disable'
	 *
	 * @var bool
	 **/
	var $_disabled = false;

	/**
	 * Constructor
	 *
	 * @param subject {{@internal Missing Description}}}
	 */
	public function __construct(&$subject, $config) {
		global $JomDefender_plugin, $JomDefender_params;

		parent::__construct($subject, $config);

		// Check to see if plugin should be disabled
		$this->_params = $JomDefender_params;
		if ($this->_params->get('enable_disable', 0)
				&& $_string = $this->_params
						->get('enable_disable_string', '')) {
			foreach ($_REQUEST as $key => $val) {
				if ($key == $_string) {
					$this->_disabled = true;
					return;
				}
			}
		}

		// Initialize Variables
		$this->_plugin = 'JomDefender_plugin';
		$this->_application = JFactory::getApplication();
		$this->_session = JFactory::getSession();
		$this->_user = JFactory::getUser();
		$this->_config = JFactory::getConfig();

		$this->_is_site = $this->_application->isSite();
		$this->_is_admin = $this->_application->isAdmin();
	}

	/**
	 * Plugin method with the same name as the event will be called automatically.
	 */
	function onAfterInitialise() {
		// If plugin is disabled abort everything
		if ($this->_disabled) {
			return;
		}

		if ($this->_params->get('cron_url')
				&& $this->_params->get('file_integrity')
				&& isset($_REQUEST[$this->_params->get('cron_url')])) {
			$this->check_file_integrity();
			die('1');
		}

		// Filter IP addresses
		if ($this->_params->get('allow_deny', 0)) {
			$where = $this->_params->get('restrict_for');
			// Make sure we are only testing when we need to
			if ('both' == $where || ('site' == $where && $this->_is_site)
					|| ('admin' == $where && $this->_is_admin)) {
				$ips = explode(',', $this->_params->get('ip_list'));
				$_client = $this->get_ip();
				$_clientl = ip2long($_client);
				$exists = false;

				// Check to see if IP exists in IP list
				foreach ($ips as $ip) {
					$ip = trim($ip);
					if (ip2long($ip) == $_clientl) {
						$exists = true;
					}
				}

				// These two checks will throw error as use would not be allowed
				$error = false;
				if ($exists && 'deny' == $this->_params->get('allow_deny')) {
					$error = true;
				} elseif (!$exists
						&& 'allow' == $this->_params->get('allow_deny')) {
					$error = true;
				}

				if (true == $error) {
					jimport('joomla.error.error');
					JError::raiseError(
							$this->_params->get('ip_error_num', 122112),
							$this->_params->get('ip_error_msg', 'Error'));
				}
			}

			unset($where, $ips, $_client, $_clientl, $exists);
		}

		if ($this->_params->get('use_cache') && $this->_is_site
				&& $this->_user->guest) {
			jimport('joomla.cache.cache');
			$this->_cache = JCache::getInstance();
			$this->_cache
					->setLifeTime(
							($this->_params->get('cache_time')) ? ($this
											->_params->get('cache_time') * 60)
									: 3600);

			foreach ((array) $_REQUEST as $key => $val)
				$this->request .= "&{$key}={$val}";
		}

		if ($this->_params->get('remove_joomla_header')
				&& 1 == (int) $this->_config->get('gzip')) {


			// New value for X-Content-Encoded-By header
			$encodedby = $this->_params
					->get('remove_joomla_header_txt', 'Company');

			// Clean it up
			$encodedby = preg_replace('/[^a-zA-Z0-9.-\s]/', '', $encodedby);

			// Retrieve the JResponse class script
			$filename = JPATH_ROOT . '/libraries/joomla/environment/response.php';
			$handle = fopen($filename, 'r');
			$response = fread($handle, filesize($filename));

			// Make safe for eval()
			$response = str_replace('<?php', '', $response);

			// Identify the specific selection of code  to replace - safe for future versions
			$regexmatch = '/JResponse::setHeader\(\'X-Content-Encoded-By.*\);/';
			preg_match($regexmatch, $response, $codepos, PREG_OFFSET_CAPTURE);

			// Prepare the replacement
			$statement = explode('\'', $codepos[0][0]);
			$statement = $statement[0] . '\'' . $statement[1] . '\', \''
					. $encodedby . '\'' . $statement[4];

			// Replace the target code with the new code
			$response = str_replace($codepos[0][0], $statement, $response);

			// Preempt the class
			eval($response);
		}
	}

	/**
	 * After component has run
	 */
	function onAfterDispatch() {
		// If plugin is disabled abort everything...
		// and add header code to allow disabling of plugin
		if ($this->_disabled) {
			global $JomDefender_params;

			$doc = JFactory::getDocument();
			$disable_string = $JomDefender_params
					->get('enable_disable_string');

			$script = "window.addEvent('domready', function() { try {
				el = document.getElementById( 'paramsninja_parameter' ).name= '{$disable_string}';
				} catch(e) {} });";
			$doc->addScriptDeclaration($script);

			return;
		}

		// Run this after the component has ran to avoid any data loss
		if ($this->_params->get('psw_prompt') && $this->_is_admin) {
			$this->check_authentication();

			$expire_time = $this->_params->get('psw_time');
			$expired_time = $this->_session
					->get('psw_expired', 0, $this->name)
					+ ($expire_time * 60);
			$authenticated = $this->_session
					->get('psw_auth', null, $this->name);
			$now = strtotime('now');

			// If not authenticated
			if (!$authenticated) {
				$this->show_login_prompt();
			} elseif ($expire_time && $expired_time < $now) {
				$this->show_login_prompt();
			}
		}
	}

	/**
	 * Runs after page is fully loaded by Joomla and ready to output to the browser.
	 */
	function onAfterRender() {
		// If plugin is disabled abort everything
		if ($this->_disabled) {
			return;
		}

		$app = JFactory::getApplication();

		// Get the body one and work with it
		$data = $app->getBody();

		if ($this->_params->get('use_cache') && $this->_is_site
				&& $this->_user->guest) {
			// Use smart cache. Check to see if the old page is the same size as the new one
			if ($this->_params->get('smart_cache')) {
				$prevcache = $this->_cache
						->get(md5($this->request), $this->name . '_smart');

				if (md5($data) != md5($prevcache)) {
					$this->haveCache = false;
					$this->_cache
							->store($data, md5($this->request),
									$this->name . '_smart');
					$this->_cache->remove(md5($this->request), $this->name);
				}
			}

			// Get cached page if any
			$this->haveCache = $this->_cache
					->get(md5($this->request), $this->name);
			if ($this->haveCache) {
				JResponse::setBody($this->haveCache);
				return;
			}
		}

		// Remove template generator tag <meta name="generator" content="BLAH" />
		if ($this->_params->get('template_generator_php') && $this->_is_site) {
			// Replacing:
			// <meta name="generator" content="Joomla! 1.5 - Open Source Content Management" />
			// <meta content="Joomla! 1.5 - Open Source Content Management" name="generator" />

			// Preg_replace way
			$_data = preg_replace(
					"/\s+?<meta.*?\sname=\"generator\"\s.*?\/?>/", '', $data);

			if ($_data) {
				$data = $_data;
			}
			unset($_data);
		}

		// Remove all instances of the word Joomla! - This is just effed up man...
		// If anyone things of a better way let me know.
		// It is all about having the Joomla word repeat inside of the html tags
		if ($this->_params->get('remove_joomla') && $this->_is_site && $this->_user->authorise('can.edit', 'com_content') === 0) {
			if (preg_match(
					'/(?:(?<=\>)|(?<=\/\>))(\s*?.*?)(joomla(!)?)(\s*?.*?)(?=\<\/?)/i',
					$data)) {
				for ($i = 0; $i < 3; $i++) {
					$data = preg_replace(
							'/(?:(?<=\>)|(?<=\/\>))(\s*?.*?)(joomla(!)?)(\s*?.*?)(?=\<\/?)/i',
							'$1$4', $data);
				}
			}

			if (preg_match(
					'/(?:(?<=\>)|(?<=\/\>))(\s*?.*?)(joomla(!)?)(\s*?.*?)(?=\<\/?)/i',
					$data)) {
				for ($i = 0; $i < 3; $i++) {
					$data = preg_replace(
							'/(?:(?<=\>)|(?<=\/\>))(\s*?.*?)(joomla(!)?)(\s*?.*?)(?=\<\/?)/i',
							'$1$4', $data);
				}
			}
		}

		// Display page execution time
		if ($this->_params->get('display_timer') && $this->_is_site) {
			$data = preg_replace('/<\/.*?body>/',
					'<!-- Execution time (seconds): '
							. plgSystemJomDefender::timer_stop() . ' -->$0',
					$data);
		}

		// Remove white space in HTML
		if ($this->_params->get('remove_space') && $this->_is_site) {
			$data = preg_replace(
					'/(?:(?<=\>)|(?<=\/\>))([\t\r\n]+)(?=\<\/?)/', '', $data);
			$data = $this->MinifyHTML( $data );
		}

		// Insert modified page into cache
		if ($this->_params->get('use_cache') && $this->_is_site
				&& $this->_user->guest) {
			$this->_cache->store($data, md5($this->request), $this->name);
		}

		// Set the body
		$app->setBody($data);
	}

	function MinifyHTML($str) {
		$protected_parts = array('<pre>,</pre>','<textarea>,</textarea>', '<,>');
		$extracted_values = array();
		$i = 0;
		foreach ($protected_parts as $part) {
		    $finished = false;
		    $search_offset = $first_offset = 0;
		    $end_offset = 1;
		    $startend = explode(',', $part);
		    if (count($startend) === 1) $startend[1] = $startend[0];
		    $len0 = strlen($startend[0]); $len1 = strlen($startend[1]);
		    while ($finished === false) {
		        $first_offset = strpos($str, $startend[0], $search_offset);

		        if ($first_offset === false) $finished = true;
		        else {
		            $search_offset = strpos($str, $startend[1], $first_offset + $len0);
		            $extracted_values[$i] = substr($str, $first_offset + $len0, $search_offset - $first_offset - $len0);
		            $str = substr($str, 0, $first_offset + $len0).'$$#'.$i.'$$'.substr($str, $search_offset);
		            $search_offset += $len1 + strlen((string)$i) + 5 - strlen($extracted_values[$i]);
		            ++$i;
		        }
		    }
		}
		$str = preg_replace("/\s/", " ", $str);
		$str = preg_replace("/\s{2,}/", " ", $str);
		$replace = array('> <'=>'><', ' >'=>'>','< '=>'<','</ '=>'</');
		$str = str_replace(array_keys($replace), array_values($replace), $str);

		for ($d = 0; $d < $i; ++$d)
		    $str = str_replace('$$#'.$d.'$$', $extracted_values[$d], $str);

		return $str;
	}

	/** Authentication plugin **/
	function onUserLogin($response, $options) {
		if (!$this->_params->get('logout_referrer')) {
			return true;
		}

		if (0
				!== strpos(
						str_replace('https://', 'http://',
								$_SERVER['HTTP_REFERER']),
						str_replace('https://', 'http://', JURI::root()))) {
			$app = JFactory::getapplication();
			$app->redirect('');
		}

		return true;
	}

	/** Logout user trigger **/
	function onUserLogout($parameters, $options) {
		if (!$this->_params->get('logout_referrer')) {
			return true;
		}

		if (0
				!== strpos(
						str_replace('https://', 'http://',
								$_SERVER['HTTP_REFERER']),
						str_replace('https://', 'http://', JURI::root()))) {
			$app = JFactory::getapplication();
			$app->redirect('');
		}

		return true;
	}

	/**
	 * Check provided password against the password that is saved
	 * if passwords match let user go by and store necessary information to ask again.
	 *
	 * @return true/false if authentication success/failure
	 */
	function check_authentication() {
		$psw = JFactory::getApplication()->input->get( 'psw', null, 'STRING' );

		if (!$psw) {
			return false;
		}
		if ($this->_params->get('psw') != $psw) {
			return false;
		}

		// If user has correct password
		$expired = strtotime('now');
		$this->_session->set('psw_expired', $expired, $this->name);
		$this->_session->set('psw_auth', true, $this->name);

		return true;
	}

	/**
	 * Show login prompt.
	 */
	function show_login_prompt() {
		$config = JFactory::getConfig();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-gb" lang="en-gb" dir="ltr" >
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <meta name="robots" content="nofollow" />
  <title><?php echo $config->get('config.sitename'); ?></title>
<script language="javascript" type="text/javascript">
	function setFocus() {
		document.login.password.select();
		document.login.password.focus();
	}
</script>
<style type="text/css">
	#wrapper {
		height:250px;
		left:50%;
		margin:-125px 0 0 -172px;
		overflow:hidden;
		padding:0;
		position:absolute;
		top:50%;
		width:344px;
	}
	#container {
		color:#DDDDDD;
		height:216px;
		padding:16px 0 0;
		text-align:center;
		width:344px;
	}
	#password {
		color:#C63434;
		padding:2px;
		width:140px;
	}
	#submit {
		display:none;
	}
</style>
</head>
<body onload="javascript:setFocus()">
	<div id="wrapper">
		<div id="container">
			<form action="" method="post" name="login">
				<input type="password" name="psw" value="" id="password" autocomplete="off" />
				<input type="submit" name="submit" value="CONFIRM" id="submit" />
			</form>
		</div>
	</div>
</body>
</html>
<?php
		die();
	}

	/**
	 * Function will return a random salt
	 *
	 * @param length int Length of salt, max 32
	 * @return string Random salt
	 **/
	function random_salt($length = 5) {
		return substr(md5(uniqid(rand(), true)), 0, $length);
	}

	/**
	 * PHP 4 standard microtime start capture.
	 *
	 * @access private
	 * @since 0.71
	 * @global int $timestart Seconds and Microseconds added together from when function is called.
	 * @return bool Always returns true.
	 */
	function timer_start() {
		global $jd_timestart;

		$mtime = explode(' ', microtime());
		$mtime = $mtime[1] + $mtime[0];
		$jd_timestart = $mtime;

		return true;
	}

	/**
	 * Return and/or display the time from the page start to when function is called.
	 *
	 * You can get the results and print them by doing:
	 * <code>
	 * $nTimePageTookToExecute = timer_stop();
	 * echo $nTimePageTookToExecute;
	 * </code>
	 *
	 * Or instead, you can do:
	 * <code>
	 * timer_stop(1);
	 * </code>
	 * which will do what the above does. If you need the result, you can assign it to a variable, but
	 * most cases, you only need to echo it.
	 *
	 * @since 0.71
	 * @global int $timestart Seconds and Microseconds added together from when timer_start() is called
	 * @global int $timeend  Seconds and Microseconds added together from when function is called
	 *
	 * @param int $display Use '0' or null to not echo anything and 1 to echo the total time
	 * @param int $precision The amount of digits from the right of the decimal to display. Default is 3.
	 * @return float The "second.microsecond" finished time calculation
	 */
	function timer_stop($display = 0, $precision = 3) {
		global $jd_timestart, $jd_timeend;

		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$mtime = $mtime[1] + $mtime[0];
		$jd_timeend = $mtime;
		$timetotal = $jd_timeend - $jd_timestart;
		$r = number_format($timetotal, $precision);

		if ($display) {
			echo $r;
		}

		return $r;
	}

	/**
	 * Validates IP Address
	 */
	function valid_ip($ip) {
		if (!empty($ip) && ip2long($ip) != -1) {
			// This is not necessary for our application
			// $reserved_ips = array(
			// 	array( '0.0.0.0', '2.255.255.255' ),
			// 	array( '10.0.0.0', '10.255.255.255' ),
			// 	array( '127.0.0.0', '127.255.255.255' ),
			// 	array( '169.254.0.0', '169.254.255.255' ),
			// 	array( '172.16.0.0', '172.31.255.255' ),
			// 	array( '192.0.2.0', '192.0.2.255' ),
			// 	array( '192.168.0.0', '192.168.255.255' ),
			// 	array( '255.255.255.0', '255.255.255.255' )
			// );
			//
			// foreach ( $reserved_ips as $r ) {
			// 	$min = ip2long( $r[0] );
			// 	$max = ip2long( $r[1] );
			// 	if ( ( ip2long( $ip ) >= $min ) && ( ip2long( $ip ) <= $max ) ) {
			// 		return false;
			// 	}
			// }

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Accurately returns clients IP address
	 */
	function get_ip() {
		if (isset($_SERVER['HTTP_CLIENT_IP'])
				&& $this->valid_ip($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		}

		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
				if ($this->valid_ip(trim($ip))) {
					return $ip;
				}
			}
		}

		if (isset($_SERVER['HTTP_X_FORWARDED'])
				&& $this->valid_ip($_SERVER['HTTP_X_FORWARDED'])) {
			return $_SERVER['HTTP_X_FORWARDED'];
		} elseif (isset($_SERVER['HTTP_X_FORWARDED'])
				&& $this->valid_ip($_SERVER['HTTP_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif (isset($_SERVER['HTTP_X_FORWARDED'])
				&& $this->valid_ip($_SERVER['HTTP_FORWARDED'])) {
			return $_SERVER['HTTP_FORWARDED'];
		} elseif (isset($_SERVER['HTTP_X_FORWARDED'])
				&& $this->valid_ip($_SERVER['HTTP_X_FORWARDED'])) {
			return $_SERVER['HTTP_X_FORWARDED'];
		} else {
			return $_SERVER['REMOTE_ADDR'];
		}
	}

	/**
	 * Checks all files metadata and compares it to previously gathered data
	 */
	function check_file_integrity() {
		// Set vars
		$this->_db = JFactory::getDBO();
		$this->email_body = '';
		$this->excluded_dirs = explode("\r\n",
				$this->_params->get('exclude_directories'));

		// Clean up
		foreach ($this->excluded_dirs as &$value) {
			$value = str_replace(array('/', '\\'), '/', rtrim($value, '/\\'));
		}



		// Set env vars
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		ignore_user_abort(true);
		ini_set('memory_limit', '512M');
		set_time_limit(900); // 15 minutes
		// error_reporting( E_ALL );
		// ini_set( 'display_errors', 1 );
		$this->check_db();

		$files = $this->check_all_files(JPATH_ROOT);

		// Let's delete old records, these records are already outdated because either the file
		// doesn't exists on the system anymore, or the admin decided to exclude a folder
		$query = "DELETE FROM `#__jd_file_integrity`
			WHERE `modified_time` < {$_SERVER['REQUEST_TIME']}";
		$this->_db->setQuery($query);
		$this->_db->query();

		if ($this->email_body) {
			$this->send_email($this->email_body);
		}
	}

	/**
	 * Loops through all files
	 */
	function check_all_files($dir) {
		$files = array();


		if ($handle = @opendir($dir)) {
			while (false !== ($file = readdir($handle))) {
				if ('.htaccess' == $file || '.' != substr($file, 0, 1)) {
					if (is_dir($dir . '/' . $file)) {

						if (in_array(
								str_replace(JPATH_ROOT, '', $dir . '/' . $file),
								$this->excluded_dirs)) {

							continue;
						}

						$files[] = $this->check_all_files($dir . '/' . $file);
					} else {
						$this->check_file($dir . '/' . $file);
					}
				}
			}
		}



		return $files;
	}

	/**
	 * Check a specific file with data in the DB
	 * If file doesn't exist in DB create it
	 */
	function check_file($file) {
		$now = $_SERVER['REQUEST_TIME'];
		$fdata = array('size' => sprintf('%u', filesize($file)),
				'mtime' => filemtime($file), 'owner' => fileowner($file),
				'group' => filegroup($file),
				'mode' => substr(sprintf('%o', fileperms($file)), -4),
				'file_hash' => md5_file($file),
				'location' => str_replace(JPATH_ROOT, '', $file),
				'id' => md5(str_replace(JPATH_ROOT, '', $file)));

		// Check to see if record exists
	 	$query = "SELECT *
			FROM `#__jd_file_integrity`
				WHERE `id` = " . $this->_db->Quote($fdata['id']);
		$this->_db->setQuery($query);
		$row = $this->_db->loadObject();

		if ($row)
		{
			$query = "UPDATE `#__jd_file_integrity`
				SET `size` = {$fdata['size']},
				`mtime` = {$fdata['mtime']},
				`owner` = {$fdata['owner']},
				`group` = {$fdata['group']},
				`mode` = {$fdata['mode']},
				`file_hash` = '{$fdata['file_hash']}',
				`modified_time` = {$now}
					WHERE `id` = '{$fdata['id']}'";
			$this->_db->setQuery($query);
			$this->_db->query();

			// Check for any file differences to send an email about
			$send_email = false;
			$email_content = '';
			// size
			if ($row->size != $fdata['size']) {
				$send_email = true;
				$email_content .= "\tSize - Previous: "
						. round(($row->size / 1024), 4) . "KB - Now: "
						. round(($fdata['size'] / 1024), 4) . "KB\n";
			}

			// mtime
			if ($row->mtime != $fdata['mtime']) {
				$send_email = true;
				$email_content .= "\tLast modified time - Previous: "
						. date('Y-m-d H:i:s', $row->mtime) . " - Now: "
						. date('Y-m-d H:i:s', $fdata['mtime']) . "\n";
			}

			// owner
			if ($row->owner != $fdata['owner']) {
				$send_email = true;
				$email_content .= "\tOwner - Previous: " . $row->owner
						. " - Now: " . $fdata['owner'] . "\n";
			}

			// group
			if ($row->group != $fdata['group']) {
				$send_email = true;
				$email_content .= "\tGroup - Previous: " . $row->group
						. " - Now: " . $fdata['group'] . "\n";
			}

			// mode
			if ($row->mode != $fdata['mode']) {
				$send_email = true;
				$email_content .= "\tPermissions - Previous: " . $row->mode
						. " - Now: " . $fdata['mode'] . "\n";
			}

			// file_hash
			if ($row->file_hash != $fdata['file_hash']) {
				$send_email = true;
				$email_content .= "\tFile contents - Previous hash: "
						. $row->file_hash . " - New hash: "
						. $fdata['file_hash'] . "\n";
			}

			if ($send_email) {
				$this->email_body .= "File changed -\n\tLocation: {$fdata['location']}\n"
						. $email_content . "\n";
			}
		} else {
			// File doesn't exists, simply insert it and add it to the email
			$query = "INSERT INTO `#__jd_file_integrity`
				(`id`, `size`, `mtime`, `owner`,
					`group`, `mode`, `file_hash`,
					`location`, `created_time`, `modified_time`)
				VALUES
				('{$fdata['id']}', '{$fdata['size']}', '{$fdata['mtime']}', '{$fdata['owner']}',
					'{$fdata['group']}', '{$fdata['mode']}', '{$fdata['file_hash']}',
					'{$fdata['location']}', '{$now}', '{$now}')";
			$this->_db->setQuery($query);
			$this->_db->query();

			// Email message
			$this->email_body .= "New file found: {$fdata['location']}\n\tSize: "
					. round(($fdata['size'] / 1024), 4)
					. "KB\n\tModified time: "
					. date('Y-m-d H:i:s', $fdata['mtime'])
					. "\n\tPermissions: {$fdata['mode']}"
					. "\n\tFile hash: {$fdata['file_hash']}\n\n";
		}

		// myPrint( array( $row, $fdata, $this->email_body ) );die();
	}

	/**
	 * Send out an email containing all of the found data
	 */
	function send_email($body) {
		$app = JFactory::getApplication();

		//jimport('joomla.utilities.utility');
		jimport('joomla.mail.mail');
		$mailer = JFactory::getMailer();

		$recipients = explode("\r\n", $this->_params->get('admin_emails'));

		foreach($recipients as $recipient)
		{
			$mailfrom = $app->getCfg('mailfrom');
			$fromname = $app->getCfg('fromname');
			$subject = 'jomDefender Cron Job Results ' . date('Y-m-d');

			$sender = array(
			    $mailfrom,
			    $fromname
			);
			$mailer->setSubject($subject);
			$mailer->setBody($body);
			$mailer->setSender($sender);
			$mailer->addRecipient($recipient);
			$send = $mailer->Send();
		}
	}

	/**
	 * Check DB tables
	 */
	function check_db() {
		$query = "DESCRIBE #__jd_file_integrity";
		$this->_db->setQuery($query);
		try {
			$this->_db->loadResult();
		} catch ( Exception $e ) {
			// Create table
			//if (!$this->_db->loadResult()) {
				$query = "CREATE TABLE IF NOT EXISTS `#__jd_file_integrity` (
				  `id` varchar(32) NOT NULL COMMENT 'A hash of the path to the file',
				  `size` int(11) unsigned NOT NULL COMMENT 'The size of the file',
				  `mtime` int(11) unsigned NOT NULL COMMENT 'The file modification time',
				  `owner` int(11) unsigned NOT NULL COMMENT 'The user id of the owner of the file',
				  `group` int(11) unsigned NOT NULL COMMENT 'The group id of the owner of the file',
				  `mode` int(4) NOT NULL COMMENT 'The current permissions of the file',
				  `file_hash` varchar(32) NOT NULL COMMENT 'A hash of the contents of the file',
				  `location` text NOT NULL COMMENT 'The location of the file going from the Joomla root, not the complete absolute path',
				  `created_time` int(11) NOT NULL COMMENT 'The created time of the row',
				  `modified_time` int(11) NOT NULL COMMENT 'The modified time of the row',
				  PRIMARY KEY (`id`)
				) CHARSET=utf8";
				$this->_db->setQuery($query);
				$this->_db->query();
			//}
		}
	}
}

// Start timer - This must be here to get the most accurate reading
if ($JomDefender_params->get('display_timer')
		&& $JomDefender_application->isSite()) {
	plgSystemJomDefender::timer_start();
}

if (!function_exists('myPrint')) {
	/**
	 * Function for printing data
	 * @return
	 */
	function myPrint($var, $pre = true) {
		if ($pre) {
			echo '<pre>';
		}
		print_r($var);
		if ($pre) {
			echo '</pre>';
		}
	}
}