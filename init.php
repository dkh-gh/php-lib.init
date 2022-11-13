<?php

	/*
	 * TODO:
	 *  - auto repairing init.php
	 */

	$_lib = [
		'path' => explode('/', __FILE__),
		'lib_path' => '/',
		'modules' => [],
		'debug_mode' => false,
		'log_mode' => true,
	];
	for($i = 0; $i < count($_lib['path'])-2; $i++) {
		if($_lib['path'][$i] != '')
			$_lib['lib_path'] .= $_lib['path'][$i].'/';
	}
	unset($_lib['path']);
	$_lib['funcs'] = [
		'debug' => function($module_name, $text) {
			$now = DateTime::createFromFormat('U.u', microtime(true));
			$timestamp = $now->format("\U\TC H:m:s.u d.m.Y");
			$date_today = $now->format("Y_m_d");
			$module_version = '?';
			if(isset($GLOBALS['_lib']['modules']['init']['version']))
				$module_version = $GLOBALS['_lib']['modules']['init']['version'];
			if($GLOBALS['_lib']['debug_mode']) {
				print('<div style="padding: 3px 2px;"><span style="font-family: sans-serif; float: left; font-size: 12px;display: table-cell;">['.$timestamp.' <span style="color: red; "> v'.$module_version.' '.$module_name.
							'</span>]: </span><span style="display: table-cell; color: darkblue; font-family: sans-serif; font-size: 12px; margin: 0;">'.
							$text.'</span></div>');
			}
			if($GLOBALS['_lib']['log_mode']) {
				file_put_contents($GLOBALS['_lib']['lib_path'].'init/logs_'.$date_today.'.log', $timestamp.' v'.$module_version.' '.$module_name.' > '.$text."\n", FILE_APPEND);
			}
		},
		'get_file_data' => function($file_name, $mode) {
			if( file_exists($GLOBALS['_lib']['lib_path'].$file_name) )
				$file_data = file_get_contents($GLOBALS['_lib']['lib_path'].$file_name);
			else {
				$file_data = false;
				return $file_data;
			}
			if($mode == 'line') {
				$file_data = str_replace(["\n", "\t"], '', $file_data);
			}
			elseif($mode == 'requires') {
				$file_data = explode("\n", $file_data);
				$file_data_tmp = [];
				for($i = 0; $i < count($file_data); $i++) {
					if($file_data[$i] != '') {
						$file_data[$i] = explode(':', $file_data[$i]);
						array_push($file_data_tmp, $file_data[$i]);
					}
				}
				$file_data = $file_data_tmp;
			}
			return $file_data;
		},
		'get_module_data' => function($module_name) {
			$GLOBALS['_lib']['funcs']['debug']('get_module_data', 'trying to get data from module '.$module_name);
			$module_data = false;
			if( file_exists($GLOBALS['_lib']['lib_path'].$module_name.'/config.json') ) {
				$file_data = $GLOBALS['_lib']['funcs']['get_file_data']($module_name.'/config.json', 'line');
				$module_data = json_decode($file_data, true);
				$GLOBALS['_lib']['funcs']['debug']('get_module_data', 'module: '.$module_name.'; data: '.$file_data);
			}
			else {
				$GLOBALS['_lib']['funcs']['debug']('get_module_data', 'wrong module: '.$module_name.'. deleting.');
				$GLOBALS['_lib']['funcs']['delete_dir']($GLOBALS['_lib']['lib_path'].$module_name);
			}
			return $module_data;
		},
		'add_module' => function($module_name) {
			$module_data = false;
			$module_data = $GLOBALS['_lib']['funcs']['get_module_data']($module_name);
			$GLOBALS['_lib']['funcs']['debug']('add_module', 'module: '.$module_name);
			if($module_data) {
				$GLOBALS['_lib']['modules'][$module_name] = $module_data;
				$GLOBALS['_lib']['funcs']['debug']('add_module', 'added: '.$module_name);
				return true;
			}
			else
				return false;
		},
		'init_modules' => function() {
			$GLOBALS['_lib']['funcs']['debug']('===', '===');
			$dir_list = scandir($GLOBALS['_lib']['lib_path']);
			foreach($dir_list as $module) {
				if(true
				&& $module != 'requirements.json'
				&& $module != 'requirements.json.old'
				&& $module != '..'
				&& $module != '.'
				) {
					$GLOBALS['_lib']['funcs']['debug']('init_modules', $module);
					$GLOBALS['_lib']['funcs']['add_module']($module);
				}
			}
			$GLOBALS['_lib']['funcs']['check_modules']();
			$GLOBALS['_lib']['funcs']['including_modules']();
			if(file_exists($GLOBALS['_lib']['lib_path'].'requirements.json'))
				$GLOBALS['_lib']['funcs']['install_global_requirements']();
		},
		'install_global_requirements' => function() {
			if(file_exists($GLOBALS['_lib']['lib_path'].'requirements.json')) {
				$global_requirements = file_get_contents($GLOBALS['_lib']['lib_path'].'requirements.json');
				if($global_requirements) {
					$global_requirements = json_decode($global_requirements, true);
					if($global_requirements) {
						for($i=0; $i < count($global_requirements); $i++) { 
							if(true
								&& isset($global_requirements[$i])
								&& isset($global_requirements[$i]['name'])
								&& isset($global_requirements[$i]['source'])
								&& isset($global_requirements[$i]['url'])
								&& isset($global_requirements[$i]['branch'])
							) {
								if($global_requirements[$i]['source'] == 'github')
									$GLOBALS['_lib']['funcs']['install_module_github']($global_requirements[$i]);
							}
						}
						rename($GLOBALS['_lib']['lib_path'].'requirements.json', $GLOBALS['_lib']['lib_path'].'requirements.json.bak');
					}
					else
						return false;
				}
				else
					return false;
			}
			else
				return false;
		},
		'check_modules' => function() {
			$GLOBALS['_lib']['funcs']['debug']('check_modules', 'modules array count: '.count($GLOBALS['_lib']['modules']));
			for($i = 0; $i < count(array_keys($GLOBALS['_lib']['modules'])); $i++){
				$GLOBALS['_lib']['funcs']['debug']('check_modules', 'updating module '.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
				$module_data = $GLOBALS['_lib']['funcs']['update_module']($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
			}
			for($i = 0; $i < count(array_keys($GLOBALS['_lib']['modules'])); $i++){
				// $GLOBALS['_lib']['funcs']['debug']('check_modules', 'clearing module '.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
				$module_data = $GLOBALS['_lib']['funcs']['clear_module']($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
			}
			for($i = 0; $i < count(array_keys($GLOBALS['_lib']['modules'])); $i++){
				// $GLOBALS['_lib']['funcs']['debug']('check_modules', 'checking requirements for '.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
				$module_data = $GLOBALS['_lib']['funcs']['check_module_requirements']($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
			}
		},
		'update_module' => function($module_name) {
			$GLOBALS['_lib']['funcs']['debug']('update_module', $module_name);
			if($GLOBALS['_lib']['modules'][$module_name]['source']['source'] == 'github') {
				$GLOBALS['_lib']['funcs']['debug']('update_module', 'for module '.$module_name.' looking new version on github.');
				$remote_module_file = fopen("https://raw.githubusercontent.com/".
					$GLOBALS['_lib']['modules'][$module_name]['source']['url'].
					"/".$GLOBALS['_lib']['modules'][$module_name]['source']['branch'].
					"/config.json", "rb");
				$remote_module_data = stream_get_contents($remote_module_file);
				fclose($remote_module_file);
				if($remote_module_data) {
					$remote_module_data = json_decode($remote_module_data, true);
					if($remote_module_data) {
						if($remote_module_data['version'] > $GLOBALS['_lib']['modules'][$module_name]['version']) {
							$GLOBALS['_lib']['funcs']['debug']('update_module', 'module '.$module_name.' need update: version '.$GLOBALS['_lib']['modules'][$module_name]['version'].' -> '.$remote_module_data['version'].'.');
							$GLOBALS['_lib']['funcs']['install_module_github']($remote_module_data);
						}
						else {
							$module_structure_full = true;
							for($i = 0; $i < count($GLOBALS['_lib']['modules'][$module_name]['structure']); $i++)
								if(!file_exists($GLOBALS['_lib']['lib_path'].$module_name.'/'.$GLOBALS['_lib']['modules'][$module_name]['structure'][$i]))
									$module_structure_full = false;
							if(!$module_structure_full) {
								$GLOBALS['_lib']['funcs']['debug']('update_module', 'module '.$GLOBALS['_lib']['modules'][$module_name]['name'].' damaged, need reinstall.');
								$GLOBALS['_lib']['funcs']['install_module_github']($remote_module_data);
							}
							else
								$GLOBALS['_lib']['funcs']['debug']('update_module', 'for module '.$module_name.' updating not needed: local v '.$GLOBALS['_lib']['modules'][$module_name]['version'].', remote v '.$remote_module_data['version'].'.');
						}
					}
				}
			}
		},
		'clear_module' => function($module_name) {
			$GLOBALS['_lib']['funcs']['debug']('clear_module', $module_name);
			$dir_list = scandir($GLOBALS['_lib']['lib_path'].$module_name);
			foreach($dir_list as $elem) {
				if(true
				&& $elem != '..'
				&& $elem != '.') {
					$removing_module = false;
					if(!in_array($elem, $GLOBALS['_lib']['modules'][$module_name]['structure'])) {
						if(!isset($GLOBALS['_lib']['modules'][$module_name]['ignoring']))
							$removing_module = true;
						else
							if(!in_array($elem, $GLOBALS['_lib']['modules'][$module_name]['ignoring']))
								$removing_module = true;
					}
					if($removing_module) {
						if(is_dir($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem)) {
							$GLOBALS['_lib']['funcs']['debug']('clear_module', 'deleting: '.$GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem.';');
							$GLOBALS['_lib']['funcs']['delete_dir']($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem);
						}
						elseif(file_exists($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem) && substr($elem, -4) != '.log') {
							$GLOBALS['_lib']['funcs']['debug']('clear_module', 'deleting: '.$GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem.';');
							unlink($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem);
						}
					}
				}
			}
		},
		'check_module_requirements' => function($module_name) {
			$GLOBALS['_lib']['funcs']['debug']('check_module_requirements', 'checking '.$module_name);
			for($i = 0; $i < count($GLOBALS['_lib']['modules'][$module_name]['requirements']); $i++) {
				$GLOBALS['_lib']['funcs']['debug']('check_module_requirements', $module_name.' require '.$GLOBALS['_lib']['modules'][$module_name]['requirements'][$i]['name'].' from '.$GLOBALS['_lib']['modules'][$module_name]['requirements'][$i]['source']);
				if($GLOBALS['_lib']['modules'][$module_name]['requirements'][$i]['source'] == "github") {
					$remote_module_file = fopen("https://raw.githubusercontent.com/".
					$GLOBALS['_lib']['modules'][$module_name]['requirements'][$i]['url'].
					"/".$GLOBALS['_lib']['modules'][$module_name]['requirements'][$i]['branch'].
					"/config.json", "rb");
					$remote_module_data = stream_get_contents($remote_module_file);
					fclose($remote_module_file);
					if($remote_module_data) {
						$remote_module_data = json_decode($remote_module_data, true);
						if($remote_module_data) {
							if(!isset($GLOBALS['_lib']['modules'][$remote_module_data['name']]['version'])) {
								$GLOBALS['_lib']['modules'][$remote_module_data['name']] = [];
								$GLOBALS['_lib']['modules'][$remote_module_data['name']]['version'] = -1;
							}
							if($remote_module_data['version'] > $GLOBALS['_lib']['modules'][$remote_module_data['name']]['version']) {
								$GLOBALS['_lib']['funcs']['debug']('update_module', 'module '.$module_name.' need update: version '.$GLOBALS['_lib']['modules'][$remote_module_data['name']]['version'].' -> '.$remote_module_data['version'].'.');
								$GLOBALS['_lib']['funcs']['install_module_github']($remote_module_data);
							}
							else $GLOBALS['_lib']['funcs']['debug']('update_module', 'for module '.$module_name.' updating not needed: local v '.$GLOBALS['_lib']['modules'][$remote_module_data['name']]['version'].', remote v '.$remote_module_data['version'].'.');
						}
					}
				}
			}
		},
		'install_module_github' => function($remote_module_data) {
			$GLOBALS['_lib']['funcs']['debug']('install_module_github', 'installing module '.$remote_module_data['name'].' v '.$remote_module_data['version']);
			if(!isset($remote_module_data['url']))
				$remote_module_data = $remote_module_data['source'];
			$remote_module_file_load = fopen("https://raw.githubusercontent.com/".
				$remote_module_data['url'].
				"/".$remote_module_data['branch'].
				"/config.json", "rb");
			$remote_module_file_loaded = stream_get_contents($remote_module_file_load);
			fclose($remote_module_file_load);
			if($remote_module_file_loaded) {
				$remote_module_data_loaded = json_decode($remote_module_file_loaded, true);
				if($remote_module_data_loaded) {
					if(is_dir($GLOBALS['_lib']['lib_path'].$remote_module_data_loaded['name'])) {
						$dir_list = scandir($GLOBALS['_lib']['lib_path'].$remote_module_data_loaded['name']);
						for($i = 0; $i < count($dir_list); $i++) {
							$removing_element = true;
							if(false
							|| $dir_list[$i] == '..'
							|| $dir_list[$i] == '.')
								$removing_element = false;
							if(isset($dir_list[$i], $remote_module_data_loaded['ignoring']))
								if(in_array($dir_list[$i], $remote_module_data_loaded['ignoring']))
									$removing_element = false;
							if($removing_element) {
								$elem_path = $GLOBALS['_lib']['lib_path'].$remote_module_data_loaded['name'].'/'.$dir_list[$i];
								if(is_dir($elem_path)) {
									$GLOBALS['_lib']['funcs']['debug']('install_module_github', 'removing old dir '.$elem_path);
									$GLOBALS['_lib']['funcs']['delete_dir']($elem_path);
								}
								elseif(file_exists($elem_path)) {
									$GLOBALS['_lib']['funcs']['debug']('install_module_github', 'removing old file '.$elem_path);
									unlink($elem_path);
								}
							}
						}
					}
					if(!is_dir($GLOBALS['_lib']['lib_path'].$remote_module_data_loaded['name']))
						mkdir($GLOBALS['_lib']['lib_path'].$remote_module_data_loaded['name']);
					for($i = 0; $i < count($remote_module_data_loaded['structure']); $i++) {
						$installing_file = true;
						if(isset($remote_module_data_loaded['ignoring']))
							if(in_array($remote_module_data_loaded['structure'][$i], $remote_module_data_loaded['ignoring']))
								$installing_file = false;
						if($installing_file) {
							try {
								$remote_module_file_load = fopen("https://raw.githubusercontent.com/".
									$remote_module_data['url'].
									"/".$remote_module_data['branch'].
									"/".$remote_module_data_loaded['structure'][$i], "rb");
								$remote_module_file_loaded = stream_get_contents($remote_module_file_load);
								fclose($remote_module_file_load);
								if($remote_module_file_loaded) {
									file_put_contents($GLOBALS['_lib']['lib_path'].$remote_module_data_loaded['name'].'/'.$remote_module_data_loaded['structure'][$i], $remote_module_file_loaded);
								}
							}
							catch (Exception $e) {
								$GLOBALS['_lib']['funcs']['debug']('install_module_github', 'can`t get file '.$remote_module_data_loaded['structure'][$i].' from github. module can be crashed');
							}
						}
					}
					$GLOBALS['_lib']['funcs']['add_module']($remote_module_data_loaded['name']);
					$GLOBALS['_lib']['funcs']['debug']('install_module_github', $remote_module_data_loaded['name'].' v '.$remote_module_data_loaded['version'].' installed from github.');
				}
			}
			return true;
		},
		'including_modules' => function() {
			for($i = 0; $i < count(array_keys($GLOBALS['_lib']['modules'])); $i++) {
				if(isset($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['include_needed'])) {
					if($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['include_needed']) {
						$GLOBALS['_lib']['funcs']['debug']('including_modules', 'including '.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
						include $GLOBALS['_lib']['lib_path'].$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name'].'/'.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name'].'.php';
					}
				}
			}
		},
		'delete_dir' => function($dir_path) {
			if (! is_dir($dir_path))
				throw new InvalidArgumentException("$dir_path must be a directory");
			if (substr($dir_path, strlen($dir_path) - 1, 1) != '/')
				$dir_path .= '/';
			$files_for_delete = glob($dir_path . '*', GLOB_MARK);
			foreach ($files_for_delete as $file_for_delete) {
				if (is_dir($file_for_delete))
					self::deleteDir($file_for_delete);
				else
					unlink($file_for_delete);
			}
			rmdir($dir_path);
		},
	];

	$_lib['funcs']['init_modules']();

?>
