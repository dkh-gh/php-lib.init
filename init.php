<?php
	
	$_lib = [
		'path' => explode('/', __FILE__),
		'lib_path' => '/',
		'modules' => [],
		'debug_mode' => true,
		'log_mode' => false,
	];
	for($i = 0; $i < count($_lib['path'])-2; $i++) {
		if($_lib['path'][$i] != '')
			$_lib['lib_path'] .= $_lib['path'][$i].'/';
	}
	unset($_lib['path']);
	$_lib['funcs'] = [
		'debug' => function($module_name, $text) {
			if($GLOBALS['_lib']['debug_mode']) {
				print('<div><div style="font-family: sans-serif; float: left; font-size: 12px;">debug [<span style="color: red; ">'.$module_name.'</span>]: </div><div style="color: darkblue; font-family: sans-serif;"><pre style="font-family: sans-serif; font-size: 12px; margin: 0;">'.
							$text.'</pre></div></div>');
			}
			if($GLOBALS['_lib']['log_mode']) {}
		},
		'get_file_data' => function($file_name, $mode) {
			if( file_exists($GLOBALS['_lib']['lib_path'].$file_name) )
				$file_data = file_get_contents($GLOBALS['_lib']['lib_path'].$file_name);
			else {
				$file_data = false;
				return $file_data;
			}
			if($mode == 'line') {
				$file_data = str_replace("\n", '', $file_data);
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
			$GLOBALS['_lib']['funcs']['debug']('get_module_data', 'module: '.$module_name);
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
			// $GLOBALS['_lib']['funcs']['debug']('add_module', 'module: '.$module_name.'; data: '.$module_data);
			if($module_data) {
				$GLOBALS['_lib']['modules'][$module_name] = $module_data;
				$GLOBALS['_lib']['funcs']['debug']('add_module', 'added: '.$module_name);
				return true;
			}
			else
				return false;
		},
		'init_modules' => function() {
			$dir_list = scandir($GLOBALS['_lib']['lib_path']);
			foreach($dir_list as $module) {
				if(true
				&& $module != 'index.php'
				&& $module != '..'
				&& $module != '.') {
					$GLOBALS['_lib']['funcs']['debug']('init_modules', $module);
					$GLOBALS['_lib']['funcs']['add_module']($module);
				}
			}
			$GLOBALS['_lib']['funcs']['check_modules']();
		},
		'check_modules' => function() {
			$GLOBALS['_lib']['funcs']['debug']('check_modules', 'start');
			$GLOBALS['_lib']['funcs']['debug']('check_modules', 'modules array count: '.count($GLOBALS['_lib']['modules']));
			for($i = 0; $i < count(array_keys($GLOBALS['_lib']['modules'])); $i++){
				$GLOBALS['_lib']['funcs']['debug']('check_modules', 'updating module '.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
				$module_data = $GLOBALS['_lib']['funcs']['update_module']($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
			}
			for($i = 0; $i < count(array_keys($GLOBALS['_lib']['modules'])); $i++){
				$GLOBALS['_lib']['funcs']['debug']('check_modules', 'clearing module '.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
				$module_data = $GLOBALS['_lib']['funcs']['clear_module']($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
			}
			for($i = 0; $i < count(array_keys($GLOBALS['_lib']['modules'])); $i++){
				$GLOBALS['_lib']['funcs']['debug']('check_modules', 'checking requirements for '.$GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
				$module_data = $GLOBALS['_lib']['funcs']['check_module_requirements']($GLOBALS['_lib']['modules'][array_keys($GLOBALS['_lib']['modules'])[$i]]['name']);
			}
		},
		'update_module' => function($module_name) {
			$GLOBALS['_lib']['funcs']['debug']('update_module', $module_name);
			if($GLOBALS['_lib']['modules'][$module_name]['source']['source'] == 'github') {
				$GLOBALS['_lib']['funcs']['debug']('update_module', 'looking new version in github.');
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
							$GLOBALS['_lib']['funcs']['debug']('update_module', 'need update ver'.$GLOBALS['_lib']['modules'][$module_name]['version'].' > ver'.$remote_module_data['version'].'.');
							for($i = 0; $i < count($remote_module_data['structure']); $i++) {
								$GLOBALS['_lib']['funcs']['debug']('update_module', 'updating '.$remote_module_data['structure'][$i]);
								$remote_module_file_load = fopen("https://raw.githubusercontent.com/".
									$GLOBALS['_lib']['modules'][$module_name]['source']['url'].
									"/".$GLOBALS['_lib']['modules'][$module_name]['source']['branch'].
									"/".$remote_module_data['structure'][$i], "rb");
								$remote_module_data_load = stream_get_contents($remote_module_file_load);
								fclose($remote_module_file_load);
								if(file_exists($GLOBALS['_lib']['lib_path'].$module_name.'/'.$remote_module_data['structure'][$i]))
									unlink($GLOBALS['_lib']['lib_path'].$module_name.'/'.$remote_module_data['structure'][$i]);
								file_put_contents($GLOBALS['_lib']['lib_path'].$module_name.'/'.$remote_module_data['structure'][$i], $remote_module_data_load);
							}
						}
						else $GLOBALS['_lib']['funcs']['debug']('update_module', 'updating not needed.');
					}
				}
			}
		},
		'clear_module' => function($module_name) {
			$GLOBALS['_lib']['funcs']['debug']('clear_module', $module_name.': '.var_export($GLOBALS['_lib']['modules'][$module_name]['structure'], true));
			$dir_list = scandir($GLOBALS['_lib']['lib_path'].$module_name);
			foreach($dir_list as $elem) {
				if(true
				&& $elem != 'index.php'
				&& $elem != '..'
				&& $elem != '.') {
					if(!in_array($elem, $GLOBALS['_lib']['modules'][$module_name]['structure'])) {
						$GLOBALS['_lib']['funcs']['debug']('clear_module', 'deleting: '.$GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem.';');
						if(is_dir($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem))
							$GLOBALS['_lib']['funcs']['delete_dir']($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem);
						elseif(file_exists($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem))
							unlink($GLOBALS['_lib']['lib_path'].$module_name.'/'.$elem);
					}
				}
			}
		},
		'check_module_requirements' => function($module_name) {
			for($i = 0; $i < count($GLOBALS['_lib']['modules'][$module_name]['requires']); $i++) {
				$GLOBALS['_lib']['funcs']['debug']('check_module_requirements', $module_name.' require '.$GLOBALS['_lib']['modules'][$module_name]['requires'][$i]);
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


	// $_lib['funcs']['debug']('ROOT', var_export($_lib, true));
	// var_dump($_lib);

?>
