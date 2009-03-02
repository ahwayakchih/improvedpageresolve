<?php
	Class extension_improvedpageresolve extends Extension{
	
		public function about(){
			return array('name' => 'Improved Page Resolve',
						 'version' => '1.0',
						 'release-date' => '2009-03-02',
						 'author' => array('name' => 'Marcin Konicki',
										   'website' => 'http://ahwayakchih.neoni.net',
										   'email' => 'ahwayakchih@neoni.net'),
						 'description' => 'Pass parameters to index if none of them selects valid page.'
			);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPrePageResolve',
					'callback' => '__pagePreResolve'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendParamsResolve',
					'callback' => '__pageParamsResolve'
				),
			);
		}

		public function __pagePreResolve($ctx) {
			// context array contains: &$row, $page

			$page = trim($ctx['page'], '/');
			if (!empty($ctx['row']) || empty($page)) return;

			$Frontend = Frontend::instance();

			// Based on code found in Frontpage->resolvePage function (symphony/lib/toolkit/class.frontpage.php)
			$pathArr = preg_split('/\//', $page, -1, PREG_SPLIT_NO_EMPTY);

			$valid_page_path = array();
			$page_extra_bits = array();
	
			$handle = array_pop($pathArr);

			do{
				$path = implode('/', $pathArr);
				$sql = "SELECT * FROM `tbl_pages`
					WHERE `path` ".($path ? " = '$path'" : 'IS NULL')." 
					AND `handle` = '$handle' LIMIT 1";
				if($row = $Frontend->Database->fetchRow(0, $sql)){
					array_push($pathArr, $handle);
					$valid_page_path = $pathArr;

					break 1;	

				}else
					$page_extra_bits[] = $handle;
			}while($handle = array_pop($pathArr));
			
			if(empty($valid_page_path)){
				$row = $Frontend->Database->fetchRow(0, "SELECT `tbl_pages`.* FROM `tbl_pages`, `tbl_pages_types` 
															  WHERE `tbl_pages_types`.page_id = `tbl_pages`.id 
															  AND tbl_pages_types.`type` = 'index' 
															  LIMIT 1");

				if(empty($row['params'])) $row = array();
			}

			if(!empty($row['params'])){
				$schema = preg_split('/\//', $row['params'], -1, PREG_SPLIT_NO_EMPTY);
				if(count($schema) < count($page_extra_bits)) $row = array();
				else if(!empty($page_extra_bits)){
					// There is no way to tell Frontpage to set _env['url'] values
					// (_env is private, and is overwritten with NULL values right after delegate returns).
					// We also can't set Frontpage->_param directly, because it is recreated later (after FrontendPageResolved delegate).
					// Nor we can store params localy, because extension seems to be recreated every time delegate is called.
					// So we store params in global place ($Frontend :) and inject params when FrontendParamsResolve delegate is called.
					$Frontend->__indexisdefault['params'] = array_combine($schema, array_pad(array_reverse($page_extra_bits), count($schema), NULL));
				}
			}

			$ctx['row'] = $row;
		}

		public function __pageParamsResolve($ctx) {
			// context array contains: &$params
			$Frontend = Frontend::instance();

			if (!isset($Frontend->__indexisdefault)) return;

			if(!empty($Frontend->__indexisdefault['params'])) {
				$ctx['params'] = array_merge($ctx['params'], $Frontend->__indexisdefault['params']);
			}

			unset($Frontend->__indexisdefault);
		}
	}
?>