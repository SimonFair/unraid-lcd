<?php
/*
	lcdproc.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2007-2009 Seth Mos <seth.mos@dds.nl>
	Copyright (C) 2009 Scott Ullrich
	Copyright (C) 2011 Michele Di Maria
	Copyright (C) 2015 ESF, LLC
	Copyright (C) 2023 Simon Fairweather Modifications to use with Unraid.
	
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
#require_once("config.inc");
#require_once("functions.inc");

/* LCDproc */
define('LCDPROC_RCFILE', '/usr/local/etc/rc.d/lcdproc.sh');
define('LCDPROC_CLIENT', '/tmp/lcdclient.sh');

define('LCDPROC_CONFIG','/usr/local/etc/LCDd.conf');

define('LCDPROC_HOST','127.0.0.1');
define('LCDPROC_PORT','13666');

function lcd_manager_log($m, $type = "NOTICE") {
	global $plugin;

	if ($type == "DEBUG" ) return NULL;
	$m		= print_r($m,true);
	$m		= str_replace("\n", " ", $m);
	$m		= str_replace('"', "'", $m);
	$cmd	= "/usr/bin/logger ".'"'.$m.'"'." -t".$plugin;
	exec($cmd);
}

function save_ini_file($file, $array) {
	global $plugin;

	$res = array();
	foreach($array as $key => $val) {
		if(is_array($val)) {
			$res[] = PHP_EOL."[$key]";
			foreach($val as $skey => $sval) $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
		} else {
			$res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
		}
	}

	/* Write changes to tmp file. */
	file_put_contents($file, implode(PHP_EOL, $res));

	/* Write changes to flash. */
	$file_path = pathinfo($file);
	if ($file_path['extension'] == "cfg") {
		file_put_contents("/boot/config/plugins/".$plugin."/".basename($file), implode(PHP_EOL, $res));
	}
}

function lcdproc_notice ($msg) {
	syslog(LOG_NOTICE, "lcdproc: {$msg}");
}

function lcdproc_warn ($msg) {
	syslog(LOG_WARNING, "lcdproc: {$msg}");
}

function lcdproc_write_config($file, $text) {
	$handle = fopen($file, 'w');
	if (!$handle) {
		lcdproc_warn("Could not open {$file} for writing.");
		exit;
	}
	fwrite($handle, $text);
	fclose($handle);
}

function lcdproc_write_script($file, $text) {
	$handle = fopen($file, 'wx');
	if (!$handle) {
		lcdproc_warn("Could not open {$file} for writing.");
		exit;
	}
	fwrite($handle, $text);
	fclose($handle);
	chmod($file, 0755);
}


function sync_package_lcdproc_screens() {
	sync_package_lcdproc();
}

function sync_package_lcdproc() {
	global $g;
	global $config;
	global $input_errors;
	$cfg = parse_ini_file("/boot/config/plugins/lcd_manager/lcd_manager.cfg") ;
	$lcdproc_config = $config['installedpackages']['lcdproc']['config'][0];
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];
	$lcdproc_config['driver'] = $cfg["DRIVER"];
	$lcdproc_config['size'] = $cfg["SIZE"] ;
	$ip=$cfg["HOST"] ;
	$port=$cfg["PORT"] ;
	$realport = $cfg["DEV"] ;


		$config_text = "[server]\n";
		$config_text .= "Driver={$lcdproc_config['driver']}\n";
		$config_text .= "Bind=$ip\n";
		$config_text .= "Port=$port\n";
		$config_text .= "ReportLevel=2\n";
		$config_text .= "ReportToSyslog=yes\n";
		if ($lcdproc_config['driver'] == "asustor") {
			$config_text .= "WaitTime=10\n";
			$config_text .= "TitleSpeed=4\n";
		} else {
			$config_text .= "WaitTime=5\n";
		}
		$config_text .= "User=nobody\n";
		$config_text .= "ServerScreen=no\n";
		$config_text .= "Foreground=no\n";
		$config_text .= "DriverPath=/lib/lcdproc/\n";
		$config_text .= "GoodBye=\"Thanks for using\"\n";
		$config_text .= "GoodBye=\"unRAID          \"\n";
		if ($lcdproc_config['driver'] == "asustor") {
			$config_text .= "Heartbeat=off\n";
			$config_text .= "Backlight=open\n";
		} else {
			$config_text .= "Heartbeat=open\n";
			$config_text .= "Backlight=open\n";
		}
		if ($lcdproc_config['driver'] == "asustor") {
			$config_text .= "ToggleRotateKey=Menu\n";
			$config_text .= "PrevScreenKey=Escape\n";
			$config_text .= "NextScreenKey=Enter\n";
		} else {
			/* FIXME: Specific to the pyramid project */
			$config_text .= "ToggleRotateKey=Enter\n";
			$config_text .= "PrevScreenKey=Left\n";
			$config_text .= "NextScreenKey=Right\n";
		}
		$config_text .= "ScrollUpKey=Up\n";
		$config_text .= "ScrollDownKey=Down\n";
		if ($lcdproc_config['driver'] != "asustor") {
			/* FIXME: pyramid test menu */
			$config_text .= "[menu]\n";
			$config_text .= "MenuKey=Escape\n";
			$config_text .= "EnterKey=Enter\n";
			$config_text .= "UpKey=Up\n";
			$config_text .= "DownKey=Down\n";
		}

		/* lcdproc default driver definitions */
		switch ($lcdproc_config['driver']) {
			case "asustor":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size=16x2\n";
				$config_text .= "Speed=115200\n";
				break;
			case "SureElec":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "driverpath =/lib/lcdproc/\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Edition=2\n";
				$config_text .= "Contrast=200\n";
				$config_text .= "Brightness=480\n";
				$config_text .= "Speed=19200\n";
				break;
			case "nexcom":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "driverpath =/usr/local/lib/lcdproc/\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			case "bayrad":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Speed=9600\n";
				break;
			case "picolcd":
				 $config_text .= "[{$lcdproc_config['driver']}]\n";
				 $config_text .= "driverpath=/usr/local/lib/lcdproc/\n";
				 $config_text .= "Device={$realport}\n";
				 $config_text .= "Size={$lcdproc_config['size']}\n";
				 $config_text .= "KeyTimeout=500\n";
				 $config_text .= "Brightness=1000\n";
				 $config_text .= "Blacklight_Timer=60\n";
				 $config_text .= "Contrast=1000\n";
				 $config_text .= "Keylights=on\n";
				 $config_text .= "Key0Light=on\n";
				 $config_text .= "Key1Light=off\n";
				 $config_text .= "Key2Light=off\n";
				 $config_text .= "Key3Light=off\n";
				 $config_text .= "Key4Light=off\n";
				 $config_text .= "Key5Light=off\n";
				 break;
			case "CFontz":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Contrast=350\n";
				$config_text .= "Brightness=1000\n";
				$config_text .= "OffBrightness=50\n";
				$config_text .= "Speed=9600\n";
				$config_text .= "NewFirmware=no\n";
				$config_text .= "Reboot=no\n";
				break;
			case "CFontz633":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Contrast=350\n";
				$config_text .= "Brightness=1000\n";
				$config_text .= "OffBrightness=50\n";
				$config_text .= "Speed=19200\n";
				$config_text .= "NewFirmware=yes\n";
				$config_text .= "Reboot=yes\n";
				break;
			case "CFontzPacket":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Model=635\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Contrast=350\n";
				$config_text .= "Brightness=1000\n";
				$config_text .= "OffBrightness=50\n";
				$config_text .= "Speed=115200\n";
				$config_text .= "NewFirmware=yes\n";
				$config_text .= "Reboot=yes\n";
				break;
			case "curses":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Foreground=blue\n";
				$config_text .= "Background=cyan\n";
				$config_text .= "Backlight=red\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "TopLeftX=7\n";
				$config_text .= "TopLeftY=7\n";
				$config_text .= "UseACS=no\n";
				break;
			case "CwLynx":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Model=12232\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Speed=19200\n";
				$config_text .= "Reboot=no\n";
				break;
			case "pyramid":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			case "ea65":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "OffBrightness=0\n";
				$config_text .= "Brightness=500\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			case "icp_a106":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "OffBrightness=0\n";
				$config_text .= "Brightness=500\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			default:
				lcdproc_warn("The chosen lcdproc driver is not a valid choice");
				unset($lcdproc_config['driver']);
		}


		$ini = explode('/n',$config_text) ;
		file_put_contents('/boot/config/plugins/lcd_manager/LCDd.conf',$config_text) ;

	}
?>
