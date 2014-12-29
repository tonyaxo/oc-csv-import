<?php
################################################################################################
#  CSV Import Module for Opencart 1.5.1.x, 1.6.1.x  From http://bogatyrev.me		  		   #
#  Version: 0.58																	  		   #
################################################################################################
class ControllerModuleCsvImport extends Controller {

	private $error = array();

	const CRON_USER_NAME = 'csv_import_cron';
	const CRON_USER_GROUP = 'csv_import_cron';
	const CRON_USER_PASS = '123456';
	
	const CRON_IMPORT_FILENAME = 'import.csv';
	
	const CATEGORY_DELIMITER = '>';

	/** @const */
	private static $unique = array('product_id', 'model', 'sku', 'upc', 'ean',	'jan', 'isbn', 'mpn', 'keyword');	//!< unique fields
	private static $log_file = 'csv_import.log';

	/*
	 * Entry point of module
	 */
	public function index() {
		error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

		// Log
		$log = new Log(self::$log_file);
		$this->log = $log;

		$this->load->language('catalog/product');
		$this->load->language('module/csv_import');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {

			$validate = $this->validate();
			if ($validate) {
				$this->model_setting_setting->editSetting('csv_import', $this->request->post);
			}

			if (isset($this->request->post['import'])) {
				$this->log->write('Starting import...'."\n");

				if ($validate) {
					$this->import();
				} else {
					echo json_encode($this->error);
				}
				exit;
			}
			if ($validate) {
				$this->session->data['success'] = $this->language->get('text_success');
				$this->redirect($this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'));
			}
		}

		// Cron
		if (!$this->user->isLogged() || !isset($this->request->get['token'])) {
			$this->log->write('Starting import with cron...');

			if (($this->request->server['REQUEST_METHOD'] != 'GET') || (isset($this->request->get['username']) && isset($this->request->get['password']) && !$this->user->login($this->request->get['username'], $this->request->get['password']))) {
				$this->log->write("\t\t".'-> Authentication failed.');
				$this->redirect($this->url->link('common/login'));
			}
			$this->session->data['token'] = md5(mt_rand());

			$to_check = array(
				'csv_import_file',
				'csv_import_email',
				'csv_import_report',
				'csv_import_fields',
				'import_key'
			);

			$params = array();
			foreach ($to_check as $conf) {
				$params[$conf] = $this->config->get($conf);
			}

			if ($this->validate($params)) {
				$this->import($cron = true);
			} else {
				$this->log->write('Import failed: '.$this->error['warning']);
			}

			exit;
		}

		$text_strings = array(
				'heading_title',

				'text_enabled',
				'text_disabled',
				'text_content_top',
				'text_content_bottom',
				'text_column_left',
				'text_column_right',
				'text_import_file',
				'text_update_if_exists',
				'text_missing_if_exists',
				'text_donothing',
				'text_delete_if_not_exists',
				'text_hide_if_not_exists',
				'text_import_processing',
				'text_days',
				'text_month',
				'text_weekday',
				'text_exit_on_import',
				'text_import_results',
				'text_product_error',
				'text_product_name',
				'text_product_status',
				'text_import_success',
				'text_import_error',
				'text_dropped',
				'text_import_confirm',
				'text_import_time',
				'text_import_memory',

				'entry_layout',
				'entry_limit',
				'entry_image',
				'entry_position',
				'entry_status',
				'entry_sort_order',

				'button_save',
				'button_cancel',
				'button_add_module',
				'button_remove',
				'button_upload',
				'button_print',
				'button_check_import',
				'button_import',
				'button_add_field',
				
				'tab_general',
				'tab_products',
				'tab_categories',

				'entry_import_file',
				'entry_email_report',
				'entry_fields',
				'entry_clear_p2c',
				'entry_add_to_parent',

				'entry_import_key',
				'entry_category_key',
				'entry_if_exists',
				'entry_if_not_exists',
				'entry_create_category',
				
				'entry_skip_first',
				'entry_csv_delimiter',
				'entry_csv_enclosure',

				'warring_category_not_found',

		);

		foreach ($text_strings as $text) {
			$this->data[$text] = $this->language->get($text);
		}
		//END LANGUAGE

		$config_data = array(
				'csv_import_file' ,
				'csv_import_email',
				'csv_import_report',
				'csv_import_fields',
				'csv_import_clear_p2c',
				'csv_import_add_to_parent',
				'import_skip_first',
				'import_delimiter',
				'import_enclosure',
				'csv_import_create_category',
				
		);

		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$this->data[$conf] = $this->request->post[$conf];
			} else {
				$this->data[$conf] = $this->config->get($conf);
			}
		}

		if (isset($this->request->post['csv_import_email'])) {
			$this->data['csv_import_email'] = $this->request->post['csv_import_email'];
		} elseif (!empty($this->data['csv_import_email'])) {
			$this->data['csv_import_email'] = $this->config->get('csv_import_email');
		} elseif ($this->config->get('config_email')) {
			$this->data['csv_import_email'] = $this->config->get('config_email');
		} else {
			$this->data['csv_import_email'] = '';
		}

		if (isset($this->request->post['csv_import_fields'])) {
			$this->data['csv_import_fields'] = $this->request->post['csv_import_fields'];
		} elseif ($this->config->get('csv_import_fields')) {
			$this->data['csv_import_fields'] = $this->config->get('csv_import_fields');
		} else {
			$this->data['csv_import_fields'] = array();
		}

		if (isset($this->request->post['import_key'])) {
			$this->data['import_key'] = $this->request->post['import_key'];
		} elseif ($this->config->get('import_key')) {
			$this->data['import_key'] = $this->config->get('import_key');
		} else {
			$this->data['import_key'] = 'product_id';
		}
		
		if (isset($this->request->post['category_key'])) {
			$this->data['category_key'] = $this->request->post['category_key'];
		} elseif ($this->config->get('category_key')) {
			$this->data['category_key'] = $this->config->get('category_key');
		} else {
			$this->data['category_key'] = 'name';
		}

		if (isset($this->request->post['if_exists'])) {
			$this->data['if_exists'] = $this->request->post['if_exists'];
		} elseif ($this->config->get('if_exists')) {
			$this->data['if_exists'] = $this->config->get('if_exists');
		} else {
			$this->data['if_exists'] = 0;
		}

		if (isset($this->request->post['if_not_exists'])) {
			$this->data['if_not_exists'] = $this->request->post['if_not_exists'];
		} elseif ($this->config->get('if_not_exists')) {
			$this->data['if_not_exists'] = $this->config->get('if_not_exists');
		} else {
			$this->data['if_not_exists'] = 0;
		}

		if (isset($this->request->post['csv_import_report'])) {
			$this->data['csv_import_report'] = $this->request->post['csv_import_report'];
		}

 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		$this->data['token'] = $this->session->data['token'];

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_module'),
			'href'      => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('module/csv_import', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('module/csv_import', 'token=' . $this->session->data['token'], 'SSL');

		$this->data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');


		//This code handles the situation where you have multiple instances of this module, for different layouts.
		$this->data['modules'] = array();

		if (isset($this->request->post['csv_import_module'])) {
			$this->data['modules'] = $this->request->post['csv_import_module'];
		} elseif ($this->config->get('csv_import_module')) {
			$this->data['modules'] = $this->config->get('csv_import_module');
		}

		if (!empty($this->data['csv_import_file']) && (!file_exists(DIR_DOWNLOAD.$this->data['csv_import_file']) || !is_readable(DIR_DOWNLOAD.$this->data['csv_import_file']))) {
			$this->error['warning'] = sprintf($this->language->get('error_file'), $this->data['csv_import_file']);
		}

		$this->load->model('module/csv_import');

		$importKeys = array();
		$product_fields = $this->model_module_csv_import->getProductFieds();

		$product_fields['category_id'] = 'category_id';
		$product_fields['option'] = 'option';
		foreach ($product_fields as $key => $field) {
			$name = $this->language->get('entry_'.$key);
			$product_fields[$key] = ($pos = utf8_strpos($name, ':')) ? utf8_substr($name, 0, $pos) : $name;

			if (in_array($key, $this->data['csv_import_fields']) && in_array($key, self::$unique)) {
				$importKeys[$key] = ($pos = utf8_strpos($name, ':')) ? utf8_substr($name, 0, $pos) : $name;
			}
		}
		$this->data['product_fields'] = $product_fields;

		$this->data['import_keys'] = $importKeys;
		
		$category_keys = array();
		$category_fields = $this->model_module_csv_import->getCategoryFieds();
		foreach ($category_fields as $key => $field) {
			$name = $this->language->get('entry_'.$key);
			$category_fields[$key] = ($pos = utf8_strpos($name, ':')) ? utf8_substr($name, 0, $pos) : $name;

			$category_keys[$key] = ($pos = utf8_strpos($name, ':')) ? utf8_substr($name, 0, $pos) : $name;
		}
		$this->data['category_fields'] = $category_fields;
		$this->data['category_keys'] = $category_keys;

		$this->load->model('design/layout');

		$this->data['layouts'] = $this->model_design_layout->getLayouts();

		$this->template = 'module/csv_import.tpl';
		$this->children = array(
			'common/header',
			'common/footer',
		);

		$this->response->setOutput($this->render());
	}

	/*
	 * Upload import file on server
	 */
	public function upload() {
		$this->language->load('sale/order');

		$json = array();
		$module_name = 'module/'.pathinfo(__FILE__, PATHINFO_FILENAME);

		if (!$this->user->hasPermission('modify', $module_name)) {
      		$json['error'] = $this->language->get('error_permission');
    	}

		if (!isset($json['error'])) {
			if (!empty($this->request->files['file']['name'])) {
				$filename = basename(html_entity_decode($this->request->files['file']['name'], ENT_QUOTES, 'UTF-8'));

				if ((utf8_strlen($filename) < 1) || (utf8_strlen($filename) > 128)) {
					$json['error'] = $this->language->get('error_filename');
				}

				// Allowed file extension types
				$allowed = array(
					'csv',
					'xls',
					'xlsx',
					'zip',
				);

				if (!in_array(substr(strrchr($filename, '.'), 1), $allowed)) {
					$json['error'] = $this->language->get('error_filetype');
				}

				// Allowed file mime types
				$allowed = array(
					'text/csv',
					'application/vnd.ms-excel',
					'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'application/octet-stream',
					'application/x-zip',
					'application/x-zip-compressed',
					'application/x-gzip',
					'application/x-gzip-compressed',
				);

				if (!in_array($this->request->files['file']['type'], $allowed)) {
					$json['error'] = $this->language->get('error_filetype');
				}

				if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
					$json['error'] = $this->language->get('error_upload_' . $this->request->files['file']['error']);
				}

				if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
					$json['error'] = $this->language->get('error_upload_' . $this->request->files['file']['error']);
				}
			} else {
				$json['error'] = $this->language->get('error_upload');
			}
		}

		if (!isset($json['error'])) {
			if (is_uploaded_file($this->request->files['file']['tmp_name']) && file_exists($this->request->files['file']['tmp_name'])) {

				$json['filename'] = $filename;
				$json['mask'] = $filename;

				move_uploaded_file($this->request->files['file']['tmp_name'], DIR_DOWNLOAD .'import/'. $filename);
				chmod(DIR_DOWNLOAD .'import/'. $filename, 0777);
			}

			$json['success'] = $this->language->get('text_upload');
		}

		$this->response->setOutput(json_encode($json));
	}


	/*
	 * This function is called to ensure that the settings chosen by the admin user are allowed/valid.
	 */
	private function validate($params = array()) {
		// TODO rewrite
		// if (!$this->user->hasPermission('modify', 'module/csv_import')) {
			// $this->error['warning'] = $this->language->get('error_permission');
		// }

		if (empty($params)) {
			$params = $this->request->post;
		}

		if (!is_file(DIR_DOWNLOAD.'import/'.$params['csv_import_file']) || !is_readable(DIR_DOWNLOAD.'import/'.$params['csv_import_file'])) {
			$this->error['warning'] = sprintf($this->language->get('error_file'), $params['csv_import_file']);
		}

		if (!isset( $params['csv_import_fields']) || empty( $params['csv_import_fields'])) {
			$this->error['warning'] = $this->language->get('error_import_fields');
		}

		if (!isset($params['import_key']) || empty($params['import_key'])) {
			$this->error['warning'] = $this->language->get('error_import_key');
		} else {
			if (!in_array($params['import_key'], self::$unique)) {
				$this->error['warning'] = sprintf($this->language->get('error_key_not_unique'),  $this->language->get('entry_'.$params['import_key']));
			}
		}

		if (isset($params['csv_import_report'])) {
			$emails = explode(',', $params['csv_import_email']);
			if (is_array($emails)) {
				foreach ($emails as $mail) {
					if (filter_var(trim($mail), FILTER_VALIDATE_EMAIL) === false) {
						$this->error['warning'] = $this->language->get('error_import_email');
					}
				}
			} else {
				if (filter_var($params['csv_import_email'], FILTER_VALIDATE_EMAIL) === false) {
					$this->error['warning'] = $this->language->get('error_import_email');
				}	
			}			
		}

		// if (!in_array($params['import_key'], $params['csv_import_fields'])) {
			// $this->error['warning'] = $this->language->get('error_import_key_not_exists');
		// }

		if (!$this->error) {
			return TRUE;
		} else {
			$params['csv_import_report'] = (isset($params['csv_import_report'])) ? 1:0;
			return FALSE;
		}
	}

	private function validateImport() {
		if (!$this->user->hasPermission('modify', 'module/csv_import')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
	}

	/*
	 * This function make import from file to store
	 * @param cron - true/false indicate that module run on cron
	 */
	protected function import($cron = false) {

		$start_memory_usage = memory_get_usage();
		$start_time = microtime(true); 
		
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('module/csv_import');
		
		// Processing Zip file
		if ($isZip) {
			if (!is_file(DIR_DOWNLOAD.'import/'. $file) || !is_readable(DIR_DOWNLOAD.'import/'. $file)) {
				$this->error['warning'] = "no access";
				$this->log->write('Import failed: Can\'t read file '.DIR_DOWNLOAD.'import/'. $file );

				return false;
			}
			
			// Unzip file entry
			$zip = new ZipArchive;
			$res = $zip->open(DIR_DOWNLOAD.'import/'. $file);
			if ($res !== true) {
				$this->error['warning'] = "cant open zip";
				$this->log->write('Import failed: Can\'t open file (ERROR: ' . $res . ') '.DIR_DOWNLOAD.'import/'. $file );
				
				return false;
			}
			if ($zip->numFiles != 1) {
				echo $this->error['warning'] = "too few files in archive";
				$this->log->write('Import failed: too few files in archive '.DIR_DOWNLOAD.'import/'. $file );
				
				return false;
			}
			
			$zipFile = $file;
			
			// Get single file
			$file = $zip->getNameIndex(0);
			if ($file == false) {
				$this->error['warning'] = 'zip no files with index 0';
				$this->log->write('Import failed: no files with index 0 in '.DIR_DOWNLOAD.'import/'. $zipFile );
				
				return false;
			}
			
			if (!$zip->extractTo(DIR_DOWNLOAD.'import/', array($file))) {
				$this->error['warning'] = "cant extract from zip";
				$this->log->write('Import failed: Can\'t extract file '.DIR_DOWNLOAD.'import/'. $file );
				
				return false;
			}
			chmod(DIR_DOWNLOAD.'import/'. $file, 0777);
			$zip->close();		
		}
		
		if (!is_file(DIR_DOWNLOAD.'import/'.$file) || !is_readable(DIR_DOWNLOAD.'import/'.$file)) {
			$this->error['warning'] = "no access";
			$this->log->write('Import failed: Can\'t read file '.DIR_DOWNLOAD.'import/'.$file );

			return false;
		}

		if (($handle = fopen(DIR_DOWNLOAD.'import/'.$file, "r")) === FALSE) {
			$this->error['warning'] = "open file error";
			$this->log->write('Import failed: Can\'t open file '.DIR_DOWNLOAD.'import/'.$file );

			return false;
		}
		
		// Set cron flag
		$this->model_module_csv_import->setCron($cron);
		
		$this->model_module_csv_import->setCsvOptions(array(
			'delimiter' => html_entity_decode($this->config->get('import_delimiter')),
			'enclosure' => html_entity_decode($this->config->get('import_enclosure')),
		));
		
		// Set CSV file handle for model
		if (!$this->model_module_csv_import->setHandle($handle)) {
			$this->error['warning'] = 'Invalid CSV file handle';
			$this->log->write('Import failed: Invalid CSV file handle');

			return false;
		}
		
		// Lock file before read
		if (!flock($handle, LOCK_SH)) {
			$this->error['warning'] = "lock file error";
			$this->log->write('Import failed: Can\'t lock file '.DIR_DOWNLOAD.'import/'.$file );

			return false;
		}
		
		set_time_limit(0);
		$this->cache->delete('product');
		
		// Import
		while (($item = $this->model_module_csv_import->getItem() !== false) {
			// Skip empty CSV lines
			if (!$item) {
				continue;
			}
			
			// Check import key in item data
			if (!isset($item[$importKey]) || empty($item[$importKey])) {
				// TODO Mark this product as skiped
				continue;
			}
			
			// Try to find product by import key
			$result = $this->model_module_csv_import->findProductBy($importKey, $item[$importKey]);
			
			if (empty($result)) {
				// Initialize new new base product by CSV values
				$baseProduct = $this->model_module_csv_import->getBaseProduct($item);
				
				// TODO !----------- trigger beforeAdd -----------!
				
				$this->model_catalog_product->addProduct($baseProduct);
				// IMPORTANT $baseProduct must contain only base fields
				// Get new product Id
				$productId = $this->db->getLastId();
			} else {
				// Get existing product Id
				$productId = $result['product_id'];
			}
			
			// Get product data
			$product = $this->model_catalog_product->getProduct($productId);
			
			/* Update additional product parameters if it exist in import fields */			
			$product = $this->model_module_csv_import->extendProduct($product, $item);
			
			// TODO !----------- trigger beforeUpdate -------------!
			
			// Update product data from CSV
			$this->model_catalog_product->editProduct($productId, $product);
			
			$product = null;
		}
		
		// Unlock & close file handle
		if (!flock($handle, LOCK_UN)) {
			$this->log->write('Import warring: Can\'t unlock file '.DIR_DOWNLOAD.'import/'.$file );
		}
		fclose($handle);
		if ($isZip) {
			unlink(DIR_DOWNLOAD.'import/'. $file);
		}
		
		$end_time = microtime(true);
		$end_memory_usage = memory_get_usage();
		$total_memory_usage = number_format(($end_memory_usage - $start_memory_usage)/1024, 0, '.', ' ');
		$execution_time = round(($end_time-$start_time),5);
		
		if(function_exists('memory_get_peak_usage')){
			$get_memory_peak_usage = number_format(memory_get_peak_usage()/1024, 0, '.', ' ');
		}		

		// Email report
		if ($import_report && $import_email) {

			$temp_dir = sys_get_temp_dir();
			$results = array (
				'success' => $this->products_changed,
				'warrings' => $this->products_warnings,
				'exclude' => $this->products_excluded
			);

			$mail = new Mail();

			foreach ($results as $status => $result) {
				$filename = $temp_dir.'/'.$status.'.csv';
				if (!touch($filename)) {
					break;
				}
				if (($f = fopen($filename, 'w')) == false) {
					break;
				}
				foreach ($result as $fields) {
					fputcsv($f, $fields);
				}
				fclose($f);
				$mail->addAttachment($filename);
			}

			$total = count($this->products_changed) + count($this->products_excluded);

			$mail->protocol = $this->config->get('config_mail_protocol');
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->hostname = $this->config->get('config_smtp_host');
			$mail->username = $this->config->get('config_smtp_username');
			$mail->password = $this->config->get('config_smtp_password');
			$mail->port = $this->config->get('config_smtp_port');
			$mail->timeout = $this->config->get('config_smtp_timeout');
			$mail->setTo($import_email);
	  		$mail->setFrom($this->config->get('config_email'));
	  		$mail->setSender($this->language->get('email_sender'));
	  		$mail->setSubject(html_entity_decode($this->language->get('email_subject'), ENT_QUOTES, 'UTF-8'));
	  		$mail->setHtml(html_entity_decode(sprintf($this->language->get('email_text'), $total, count($this->products_changed), count($this->products_excluded), count($this->products_warnings)), ENT_QUOTES, 'UTF-8'));
      		$mail->send();

			$this->log->write('Import result sending to: '.$import_email);
    	}
		
		if (!$cron) {
			echo json_encode(array (
				'success' => $this->products_changed,
				'warnings' => $this->products_warnings,
				'exclude' => $this->products_excluded,
				'time' =>  $execution_time,
				'memory_total' => $total_memory_usage,
				'memory_peak' => $get_memory_peak_usage,
			));
		}

		$this->log->write('Import complete: '."\n\t\t".'-> success: '.count($this->products_changed)."\n\t\t".'-> exclude: '.count($this->products_excluded));

		return true;
	}

	public function install() {
		// TODO create csv_import_triggers folders
		// Log
		$log = new Log(self::$log_file);
		$this->log = $log;

		$this->log->write('Installation starting...'."\n\t\t".'-> Create tables.');

		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "csv_import_logs`");
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "csv_import_logs` (
			`import_id` int(11) NOT NULL auto_increment,
			`status` int(1) NOT NULL default '0',
			`error` VARCHAR(1024) default NULL,
			`updated` int(11) NOT NULL default '0',
			`inserted` int(11) NOT NULL default '0',
			`deleted` int(11) NOT NULL default '0',
			`deactive` int(11) NOT NULL default '0',
			`date` date NOT NULL,
			PRIMARY KEY (`import_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		// Import folder
		if (!file_exists(DIR_DOWNLOAD.'import/')) {
			if (is_writable(DIR_DOWNLOAD)) {
				if (mkdir(DIR_DOWNLOAD.'import/')) {
					$this->log->write("\t\t".'-> Create import/ folder.');
				} else {
					$this->log->write('Warring: Folder import/ not exists, creation failed.');
				}
			} else {
				$this->log->write('Warring: Folder import/ not exists, creation failed.');
			}
		} else if(!is_writable() || !is_readable) {
			if (chmod(DIR_DOWNLOAD.'import/', 0777)) {
				$this->log->write("\t\t".'-> Change rights for folder import/ to 0777.');
			} else {
				$this->log->write('Warring: Can\'t change rights for folder import/ to 0777.');
			}
		}

		// Cron access to module
		$home_controller = DIR_APPLICATION.'controller/common/home.php';
		$this->log->write('Patching controller/common/home.php ...');

		if (!is_file($home_controller) || !is_writable($home_controller) || !is_readable($home_controller)) {
			$this->log->write("\n\t\t".'-> Warring: Can\'t access controller/common/home.php. Cron import not available!');
		} else {
			$content = file_get_contents($home_controller);

			$count = 0;
			$content = str_ireplace('\'module/csv_import\',', '', $content, $count);
			$content = str_ireplace('$ignore = array(', '$ignore'." = array(\r\n'module/csv_import',", $content, $count);

			if ($count != 3) {
				$this->log->write("\n\t\t".'-> Warring: Can\'t patch controller/common/home.php. Cron import not available!');
			} else {
				file_put_contents($home_controller, $content);
				$this->log->write("\n\t\t".'-> controller/common/home.php successful patched.');
			}
		}

		// Add user for cron
		$this->load->model('user/user');
		$this->load->model('user/user_group');

		$user_info = $this->model_user_user->getUserByUsername((self::CRON_USER_NAME));
		$module_name = 'module/'.pathinfo(__FILE__, PATHINFO_FILENAME);

		if ($user_info) {
			$this->log->write('Create user: '.self::CRON_USER_NAME."\n\t\t".'-> already exists.');

			$user_group_id = $user_info['user_group_id'];
			$user_group = $this->model_user_user_group->getUserGroup($user_group_id);

			if (!in_array($module_name, $user_group['permission']['access'])) {
				$this->model_user_user_group->addPermission($user_info['user_id'], 'access', $module_name);
			}
			if (!in_array('extension/module', $user_group['permission']['access'])) {
				$this->model_user_user_group->addPermission($user_info['user_id'], 'access', 'extension/module');
			}
			if (!in_array($module_name, $user_group['permission']['modify'])) {
				$this->model_user_user_group->addPermission($user_info['user_id'], 'modify', $module_name);
			}
		} else {
			$cron_group = array(
				'name' => (self::CRON_USER_GROUP),
				'permission' => array (
					'access' =>
						array (
							0 => 'extension/module',
							1 => $module_name,
						),
					'modify' =>
						array (
							0 => $module_name,
						),
				)
			);
			$cron_user = array (
				'username' => (self::CRON_USER_NAME),
				'password' => (self::CRON_USER_PASS),
				'firstname' => (self::CRON_USER_NAME),
				'lastname' => (self::CRON_USER_NAME),
				'status' => 1,
				'user_group_id' => 0,
				'email' => '',
			);

			$user_groups = $this->model_user_user_group->getUserGroups();

			$user_group_id = 0;
			foreach ($user_groups as $group) {
				if ($group['name'] == $cron_group['name']) {
					$user_group_id = $group['user_group_id'];
					break;
				}
			}

			if (!$user_group_id) {
				$this->log->write('Create user group: '.self::CRON_USER_GROUP."\n\t\t".' -> complete.');

				$this->model_user_user_group->addUserGroup($cron_group);

				$user_groups = $this->model_user_user_group->getUserGroups();

				foreach ($user_groups as $group) {
					if ($group['name'] == $cron_group) {
						$user_group_id = $group['user_group_id'];
						break;
					}
				}
			} else {
				$this->log->write('Create user group: '.self::CRON_USER_GROUP."\n\t\t".' -> already exists.');
			}
			$cron_user['user_group_id'] = $user_group_id;
			$this->model_user_user->addUser($cron_user);

			$this->log->write('Create user: '.self::CRON_USER_NAME."\n\t\t".' -> complete.');
		}
		$this->log->write('Installation complete.');
	}

	public function uninstall() {
		// Log
		$log = new Log(self::$log_file);
		$this->log = $log;

		$this->log->write('Uninstallation starting...');

		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "csv_import_logs`");

		$this->log->write("\n\t\t".'-> Drop tables.');
    }


}
?>
