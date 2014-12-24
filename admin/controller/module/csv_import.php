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
	
	protected $products_changed = null;
	protected $products_warnings = null;
	protected $products_excluded = null;

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

		$import_keys = array();
		$product_fields = $this->model_module_csv_import->getProductFieds();

		$product_fields['category_id'] = 'category_id';
		$product_fields['option'] = 'option';
		foreach ($product_fields as $key => $field) {
			$name = $this->language->get('entry_'.$key);
			$product_fields[$key] = ($pos = utf8_strpos($name, ':')) ? utf8_substr($name, 0, $pos) : $name;

			if (in_array($key, $this->data['csv_import_fields']) && in_array($key, self::$unique)) {
				$import_keys[$key] = ($pos = utf8_strpos($name, ':')) ? utf8_substr($name, 0, $pos) : $name;
			}
		}
		$this->data['product_fields'] = $product_fields;

		$this->data['import_keys'] = $import_keys;
		
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
					'xlsx'
				);

				if (!in_array(substr(strrchr($filename, '.'), 1), $allowed)) {
					$json['error'] = $this->language->get('error_filetype');
				}

				// Allowed file mime types
				$allowed = array(
					'text/csv',
					'application/vnd.ms-excel',
					'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'application/octet-stream'
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
	 * This function add option
	 * @param options - array of all store options
	 * @param product - link to product
	 */
	protected function add_option(&$options, &$product) {
		// TODO multi options type
		$language = $this->config->get('config_language_id');

		$sign = '+';
		list($type, $name, $value, $price) = explode(':', $product['option'], 4);
		$price = (int)$price;
		if ($price < 0) {
			$sign = '-';
			$price = -$price;
		}

		$name = htmlspecialchars($name);
		$value = htmlspecialchars($value);

		$option_exists = false;
		$option_index = 0;

		// Option exists?
		foreach ($options as $index => &$option) {

			if ($option['option_description'][$language]['name'] == $name && $option['type'] == $type) {
				$option_exists = true;

				$option_value_exists = false;
				$option_value_index = 0;
				// Option value exists?
				foreach ($option['option_value'] as $key => $op_value) {
					if (isset($op_value['option_value_description'][$language]) && $op_value['option_value_description'][$language]['name'] == $value) {

						$option_value_exists = true;
						$option_value_index = $key;
						break;
					}
				}
				$option_index = $index;
				break;
			}
		}
		unset($option);

		if ($option_exists == false) {
			$new_option = array (
				'option_description' => array (
					$language => array(
						'name' => $name
					)
				),
				'type' => 'select',
				'sort_order' => 0,
				'option_value' => array	(
					array (
						'option_value_id' => '',
						'option_value_description' => array	(
							$language => array (
								'name' => $value
							)
						),
						'image' => '',
						'sort_order' => 0
					)
				)
			);
			$this->model_catalog_option->addOption($new_option);
			$new_op = $this->model_catalog_option->getOptions(array('filter_name' => $name));

			$new_op = current($new_op);

			$new_option['option_id'] = $new_op['option_id'];
			$option_id = $new_option['option_id'];
			unset($new_op);

			$option_values = $this->model_catalog_option->getOptionValues($new_option['option_id']);
			$new_option['option_value'] = array();
			$next_value_id = 0;
			foreach ($option_values as $op) {
				if ($next_value_id < (int)$op['option_value_id']) {
					$next_value_id = (int)$op['option_value_id'];
				}
				$new_option['option_value'][] = array(
					'option_value_id' => $op['option_value_id'],
					'image' => $op['image'],
					'sort_order' => $op['sort_order'],
					'option_value_description' => array(
						$language => array (
							'name' => $op['name']
						)
					)
				);
			}
			$new_option['next_value_id'] = ++$next_value_id;
			$new_option['option_description'] = array(
				$language => array(
					'name' => $name
				)
			);
			unset($new_option['name']);
			$options[] = $new_option;

			// Add to product
			if (isset($product['product_option'])) {
				$product['product_option'][0] = array (
					'product_option_id' => '',
					'name' => $name,
					'option_id' => $option_id,
					'type' => $type,
					'required' => 0,
					'product_option_value' => array (
						array (
							'option_value_id' => $new_option['option_value'][0]['option_value_id'],
							'product_option_value_id' => '',
							'quantity' => $product['quantity'],
							'subtract' => 0,
							'price_prefix' => $sign,
							'price' => $price,
							'points_prefix' => '+',
							'points' => 0,
							'weight_prefix' => '+',
							'weight' => 0
						)
					)
				);
			}
			unset($new_option);

		} else {
			$option_value_id = $options[$option_index]['next_value_id'];
			if ($option_value_exists == false) {
				// Add new value to global options
				$options[$option_index]['option_value'][] = array(
					'option_value_id' => $options[$option_index]['next_value_id'],
					'image' => '', // TODO redo
					'sort_order' => 0,
					'option_value_description' => array(
						$language => array(
							'name' => $value
						)
					)
				);
				$options[$option_index]['next_value_id']++;
			} else {
				// Upadate value in global options
				$option_value_id = $options[$option_index]['option_value'][$option_value_index]['option_value_id'];
			}

			// Add to product

			$op_exists = false;
			foreach ($product['product_option'] as &$option) {
				if ($option['option_id'] == $options[$option_index]['option_id']) {

					$value_exists = false;
					foreach ($option['product_option_value'] as  &$option_value) {
						// $tmp = $this->model_catalog_option->getOptionValue($option_value['option_value_id']);
						$val = '';
						foreach ($options[$option_index]['option_value'] as $option_val) {
							if ($option_value['option_value_id'] == $option_val['option_value_id']) {
								$val = $option_val['option_value_description'][$language]['name'];
							}
						}
						if ($val == $value) {
							$value_exists = true;
							$option_value['quantity'] = $product['quantity'];
							$option_value['price'] = $price;
							break;
						}
					}

					unset($option_value);
					if (!$value_exists) {
						$option['product_option_value'][] = array (
							'option_value_id' => $option_value_id,
							'product_option_value_id' => '',
							'quantity' => $product['quantity'],
							'subtract' => 0,
							'price_prefix' => $sign,
							'price' => $price,
							'points_prefix' => '+',
							'points' => 0,
							'weight_prefix' => '+',
							'weight' => 0
						);
					}
					$op_exists = true;
					break;
				}
			}
			unset($option);

			if (!$op_exists) {
				$product['product_option'][0] = array (
					'product_option_id' => '',
					'name' => $name,
					'option_id' => $options[$option_index]['option_id'],
					'type' => $type,
					'required' => 0,
					'product_option_value' => array (
						array (
							'option_value_id' => $options[$option_index]['option_value'][$option_value_index]['option_value_id'],
							'product_option_value_id' => '',
							'quantity' => $product['quantity'],
							'subtract' => 0,
							'price_prefix' => $sign,
							'price' => $price,
							'points_prefix' => '+',
							'points' => 0,
							'weight_prefix' => '+',
							'weight' => 0
						)
					)
				);
			}
			unset($option);
		}
	}

	protected function addManufacturer(&$product, &$manufacturers)
	{
		
		if (!is_int($product['manufacturer_id'])) {
			$manufacturer_id = array_search($product['manufacturer_id'], $manufacturers);
			
			if ($manufacturer_id !== false) {
				$product['manufacturer_id'] = $manufacturer_id;
			} else {
				$this->model_catalog_manufacturer->addManufacturer(array(
					'name' => $product['manufacturer_id'],
					'sort_order' => 0
				));
				$manufacturer_id = $this->db->getLastId();	
				$manufacturers[$manufacturer_id] = $product['manufacturer_id'];
				$product['manufacturer_id'] = $manufacturer_id;
				
			}
		} else {
			if (!isset($manufacturers[$product['manufacturer_id']])) {
				$product['manufacturer_id'] = '';
				$this->products_warnings[] = array (
					'key' => isset($product[$import_key]) ?  $product[$import_key] : $product['product_description'][$language][$import_key] ,
					'name' => isset($product['product_description'][$language]['name']) ? $product['product_description'][$language]['name'] : '',
					'error' => 'Manufacturer id not found!',
					'status' => $text_warring
				);
			}
		}
		
		return $product['manufacturer_id'];	
	}
	
	/*
	 * This function make import from file to store
	 * @param cron - true/false indicate that module run on cron
	 */
	protected function import($cron = false) {

		$start_memory_usage = memory_get_usage();
		$start_time = microtime(true);

	
		// Load config
		$file = ($cron) ? self::CRON_IMPORT_FILENAME : $this->config->get('csv_import_file');
		$language = $this->config->get('config_language_id');
		$import_key = $this->config->get('import_key');
		$category_key = $this->config->get('category_key');
		$import_report = $this->config->get('csv_import_report');
		$import_email = $this->config->get('csv_import_email');
		$import_fields = $this->config->get('csv_import_fields');
		$clear_p2c = $this->config->get('csv_import_clear_p2c');
		$add_to_parent = $this->config->get('csv_import_add_to_parent');

		// Load text
		$text_add = $this->language->get('text_add');
		$text_update = $this->language->get('text_update');
		$text_missing = $this->language->get('text_missing');
		$text_hide = $this->language->get('text_hide');
		$text_delete = $this->language->get('text_delete');
		$text_exclude = $this->language->get('text_exclude');
		$text_warring = $this->language->get('text_warring');
		$warring_category_not_found = $this->language->get('warring_category_not_found');

		$this->products_changed = array();
		$products_missing = array();
		$this->products_excluded = array();
		
		// flags
		$import_options = in_array('option', $import_fields);
		$import_categories = in_array('category_id', $import_fields);
		$import_manufacturers = in_array('manufacturer_id', $import_fields);

		// TODO move to validate
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
		if (!flock($handle, LOCK_SH)) {
			$this->error['warning'] = "lock file error";
			$this->log->write('Import failed: Can\'t lock file '.DIR_DOWNLOAD.'import/'.$file );

			return false;
		}

		set_time_limit(0); 

		$this->load->model('module/csv_import');
		$csv_options = array(
			'delimiter' => html_entity_decode($this->config->get('import_delimiter')),
			'enclosure' => html_entity_decode($this->config->get('import_enclosure')),
			'fields' 	=> $import_fields,
		);

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		
		if ($import_manufacturers) {
			$this->load->model('catalog/manufacturer');
			$manufacturer = $this->model_catalog_manufacturer->getManufacturers();
			
			$manufacturers = array();
			foreach($manufacturer as $item) {
				$manufacturers[$item['manufacturer_id']] = $item['name'];
			}
			unset($manufacturer);
		}
		
		if ($import_options) {
			// Get all options
			$this->load->model('catalog/option');
			$options = $this->model_catalog_option->getOptions(array());

			foreach ($options as &$option) {
				$option_values = $this->model_catalog_option->getOptionValues($option['option_id']);
				$option['option_value'] = array();
				$next_value_id = 0;
				foreach ($option_values as $op) {
					if ($next_value_id < (int)$op['option_value_id']) {
						$next_value_id = (int)$op['option_value_id'];
					}
					$option['option_value'][] = array(
						'option_value_id' => $op['option_value_id'],
						'image' => $op['image'],
						'sort_order' => $op['sort_order'],
						'option_value_description' => array(
							$language => array (
								'name' => $op['name']
							)
						)
					);
				}
				$option['next_value_id'] = ++$next_value_id;
				$option['option_description'] = array(
					$option['language_id'] => array(
						'name' => $option['name']
					)
				);
				unset($option['name']);
			}
			unset($option);
		}
		
		// Prepare products
		if ($clear_p2c && $import_categories) {
			$this->model_module_csv_import->unlinkP2C();
		}

		$this->cache->delete('product');
		while (($product = $this->model_module_csv_import->getProduct($handle, $csv_options)) !== FALSE) {
			if (!$product) continue;
			$product_data = array(); 

			// Link product to category
			if (isset($product['category_id']) && !empty($product['category_id'])) {
				$category = false;

				if (is_int($product['category_id'])) {
					$category = $this->model_catalog_category->getCategory($product['category_id']);
				} else {
					$category_path = strtolower($product['category_id']);
					if ($add_to_parent) {
						$categories = explode(self::CATEGORY_DELIMITER, $category_path); 
						$path_from = array();
						foreach ($categories as $cat) {
							$path_from[] = $cat;
							$category_path = implode(self::CATEGORY_DELIMITER, $path_from);
							$category_id = $this->model_module_csv_import->getCategoryIdByPath($category_path, $category_key, self::CATEGORY_DELIMITER);
							if ($category_id === false) {
								$category = false;
								break;
							} else {
								$category[] = $category_id;
							}
						}
					} else {
						$category = $this->model_module_csv_import->getCategoryIdByPath($category_path, $category_key, self::CATEGORY_DELIMITER);
					}
				}

				if ($category === false) {
					$this->products_warnings[] = array (
							'key' => isset($product[$import_key]) ?  $product[$import_key] : $product['product_description'][$language][$import_key] ,
							'name' => isset($product['product_description'][$language]['name']) ? $product['product_description'][$language]['name'] : '',
							'error' => $category_path. ' - '. $warring_category_not_found,
							'status' => $text_warring
						);
				}
			}

			if (in_array($import_key, $csv_options['fields'])) {
				$product_info = array();

				if (isset($product['product_id']) && !empty($product['product_id'])) {
					$product_info = $this->model_catalog_product->getProduct($product['product_id']);
				} else {

					$product_data['filter_'.$import_key] = $product[$import_key]; // key exists

					$result = $this->model_module_csv_import->getProducts($product_data);

					if (count($result) > 0) {
						foreach ($result as $info) {
							if ($info[$import_key] == $product[$import_key]) {
								$product_info = $info;
								break;
							}
						}
					}
				}

				if (!$product_info) {
					// New product
					$this->model_module_csv_import->loadTrigger('onBeforeProductInsert', $product);

					$product = array_merge($this->model_module_csv_import->getDefaultProduct(), $product);

					// Option
					if (isset($product['option']) && !empty($product['option'])) {
						$this->add_option($options, $product);
					}

					// Seo url
					if (isset($product['keyword']) && empty($product['keyword'])) {
						$product['keyword'] = $this->model_module_csv_import->getSeoUrl($product['product_description'][$language]['name']); //TODO redo
					} elseif (!isset($product['keyword'])) {
						$product['keyword'] = $this->model_module_csv_import->getSeoUrl($product['product_description'][$language]['name']); //TODO redo
					}
					
					// Category
					if (isset($product['category_id'])) {
						if (is_array($category)) {
							$product['product_category'] = $category;
						} elseif (is_int($category)) {
							$product['product_category'][] = $category;
						}
					}
					
					// Status
					if (!isset($product['status'])) {
						$product['status'] = '1';
					}
					
					// Manufacturer
					if (isset($product['manufacturer_id'])) {
						$this->addManufacturer($product, $manufacturers);
					}
					
					$this->model_catalog_product->addProduct($product);
					// TODO REDO get new product_id
					$result = $this->model_module_csv_import->getProducts($product_data);

					if (count($result) > 0) {
						foreach ($result as $info) {
							if ($info[$import_key] == $product[$import_key]) {
								$product_info = $info;
								break;
							}
						}
					}

					$this->products_changed[$product_info['product_id']] = array(
							'key' => isset($product[$import_key]) ?  $product[$import_key] : $product['product_description'][$language][$import_key],
							'status' => $text_add,
							'name' => isset($product['product_description'][$language]['name']) ? $product['product_description'][$language]['name'] : ''
						);
				} else {
					// Existing product
					$product_info = $this->model_catalog_product->getProduct($product_info['product_id']);
					$product_description = array();

					foreach ($product_info as $field => $value) {
						switch ($field) {
						case 'name':case 'meta_description':case 'meta_keyword':
						case 'description':case 'tag':

							$product_description[$language][$field] = $value;
							unset($product_info[$field]);

							break;
						default:
							break;
						}
					}
					$product_info['product_description'] = $product_description;
					$product_info['product_store'] = $this->model_catalog_product->getProductStores($product_info['product_id']);
					//$product_info['product_related'] = $this->model_catalog_product->getProductRelated($product_info['product_id']);
					if (isset($product['option']) && !empty($product['option'])) {
						$product_info['product_option'] = $this->model_catalog_product->getProductOptions($product_info['product_id']);
					}

					switch ($this->config->get('if_exists')) {
					case 0:
						$this->model_module_csv_import->loadTrigger('onBeforeProductUpdate', $product_info);

						$product['product_description'][$language] = array_merge($product_info['product_description'][$language], $product['product_description'][$language]);
						$product = array_merge($product_info, $product);
						// Option
						if (isset($product['option']) && !empty($product['option'])) {
							$this->add_option($options, $product);
						}

						// Seo url
						$product['keyword'] = $this->model_module_csv_import->getKeyword($product_info['product_id']);
						if (empty($product['keyword'])) {
							$product['keyword'] = $this->model_module_csv_import->getSeoUrl($product['product_description'][$language]['name']); //TODO redo
						}

						// Additional categories
						if (isset($product['category_id']) && $category !== false) {	
							//$product['product_category'] = array();
							$product['product_category'] = $this->model_catalog_product->getProductCategories($product_info['product_id']);
								
							if (is_array($category)) {
								$product['product_category'] = array_keys(array_flip(array_merge($category, $product['product_category'])));
							} elseif (is_int($category)) {
								if (($key = array_search($category, $product['product_category'])) === false) {
									$product['product_category'][] = $category;
								}
							}							
						}
						
						// Manufacturer						
						if (isset($product['manufacturer_id'])) {
							$this->addManufacturer($product, $manufacturers);
						}
								
						// Status
						if (!isset($product['status'])) {
							$product['status'] = '1';
						}
						
						// TODO some array fields rewrites
						$product['product_image'] = $this->model_catalog_product->getProductImages($product_info['product_id']); // D
						$product['product_special'] = $this->model_catalog_product->getProductSpecials($product_info['product_id']); // D
						
						
						$this->model_catalog_product->editProduct($product_info['product_id'], $product);
						$this->products_changed[$product_info['product_id']] = array(
								'key' => isset($product[$import_key]) ?  $product[$import_key] : $product['product_description'][$language][$import_key] ,
								'status' => $text_update,
								'name' => isset($product['product_description'][$language]['name']) ? $product['product_description'][$language]['name'] : ''
							);
						break;
					case 1:
						$this->products_excluded[$product_info['product_id']] = array(
							'key' => isset($product_info[$import_key]) ?  $product_info[$import_key] : $product_info['product_description'][$language][$import_key] ,
							'status' => $text_exclude,
							'name' => isset($product_info['product_description'][$language]['name']) ? $product_info['product_description'][$language]['name'] : ''
						);
						break;
					default:
						break;
					}
				}
			}
			
			$product = null;
		}
		if (!flock($handle, LOCK_UN)) {
			$this->log->write('Import warring: Can\'t unlock file '.DIR_DOWNLOAD.'import/'.$file );
		}
		fclose($handle);
				
		if ($import_options) {
			// Add options
			foreach ($options as &$option) {
				// TODO check if option not exists and ADD
				$option_id = $option['option_id'];
				unset($option['option_id']);
				$this->model_catalog_option->editOption($option_id, $option);
			}
			unset($option);
			unset($options);
		}

		switch ($this->config->get('if_not_exists')) {
		case 0:
			$products_ids = array_keys($this->products_changed);
			$not_in_import = $this->model_module_csv_import->getStoreProductDiff($products_ids);
			$this->model_module_csv_import->disableProducts($not_in_import );
			break;
		case 1:
			$products_ids = array_keys($this->products_changed);
			$not_in_import = $this->model_module_csv_import->getStoreProductDiff($products_ids);

			foreach ($not_in_import as $product_id) {
				$this->model_catalog_product->deleteProduct($product_id);
			}
			break;
		default:
			break;
		}

		foreach ($not_in_import as $product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			$this->products_excluded[$product_info['product_id']] = array(
							'key' => isset($product_info[$import_key]) ?  $product_info[$import_key] : $product_info[$import_key] ,
							'name' => isset($product_info['name']) ? $product_info['name'] : ''
						);
			switch ($this->config->get('if_not_exists')) {
			case 0:
				$this->products_excluded[$product_info['product_id']]['status'] = $text_hide;
				break;
			case 1:
				$this->products_excluded[$product_info['product_id']]['status'] = $text_delete; // TODO not work!
				break;
			default:
				break;
			}
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
