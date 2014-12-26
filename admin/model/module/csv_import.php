<?php







class ModelModuleCsvImport extends Model {
	
	const DEFAULT_STOCK_STATUS_ID = 5;
	
	protected $itemsChanged = null;
	protected $itemsWarning = null;
	protected $itemsExclude = null;
	
	protected $importFields = array();
	
	private $_baseFields = array(
		'model' => '',
		'sku' => '',
		'upc' => '',
		'ean' => '',
		'jan' => '',
		'isbn' => '',
		'mpn' => '',
		'location' => '',
		'price' => 0,
		'tax_class_id' => 0,
		'quantity' => 1,
		'minimum' => 1,
		'subtract' => 0,
		'stock_status_id' => self::DEFAULT_STOCK_STATUS_ID,
		'shipping' => 1,
		'date_available' => date("Y-m-d"),
		'length' => 0,
		'width' => 0,
		'height' => 0,
		'length_class_id' => 1,
		'weight' => 0,
		'weight_class_id' => 1,
		'status' => 1,
		'sort_order' => 1,
		'manufacturer_id' => 0,
		'points' => 0,	
	);	
	
	private $_handle = null;
	private $_cron = false;
	private $_csvOptions = array(
		'delimiter' => ',',
		'enclosure' => '"',
	);
	
	private $error = array();
	// TODO get LAST ERROR
	
	
	/*
	 * Execute import via cron tab task.
	 *
	 * @param boolean $cron whether execute import with cron
	 */
	public function setCron($cron)
	{				
		$this->_cron = (bool)$cron;
	}
	
	/*
	 * Set CSV file format options
	 *
	 * @param array $options
	 */
	public function setCsvOptions($options)
	{				
		if (empty($options)) {
			return;
		}
		foreach ($this->_csvOptions as $option => $value) {
			$this->_csvOptions[$option] = (isset($options[$option]) && !empty($options[$option])) ? $options[$option] : $value;
		}
	}
	
	/*
	 * Set CSV file handle to read/wraite operation.
	 *
	 * @param object $handle CSV file handler
	 * @return boolean.
	 */
	public function setHandle($handle)
	{				
		if ($handle) {
			$this->_handle = $handle;
			return true;
		}
		return false;
	}
	
	/*
	 * This function get product with base values.
	 *
	 * @param array  $values the castom fill values of product
	 * @return product array.
	 */
	public function getBaseProduct($values = array()) 
	{	
		$baseProduct = array();
		
		foreach ($this->_baseFields as $field => $default) {
			$baseProduct[$field] = isset($values[$field]) ? $values[$field] : $default;
		}
		return $baseProduct;	
	}
	
	/*
	 * Return product fields for creation
	 * 
	 * @return array 
	 */
	public function getBaseFields() 
	{
		return $this->_baseFields;
	}
	
	/*
	 * Set new base fields for product
	 * 
	 * @param array the new field=>defVal fields array 
	 */
	public function setBaseFields($baseFields) 
	{
		$this->_baseFields = $baseFields;
	}
	
	/*
	 * This function get product fields from import file
	 * @param file - handle of file
	 * @param options - parameters of import file:
	 *					delimiter, enclosure, fields(fields in import file)
	 * @return array/false 
	 */
	public function getItem() 
	{			
		$product = array();
		
		if (($fields = fgetcsv($this->_handle, 0, $this->_csvOptions['delimiter'], $this->_csvOptions['enclosure'])) === false ) {			
			return false;
		}
		if ($fields[0] === null) {
			return array();
		}
		
		$this->loadTrigger('onAfterGetCsvFields', $fields);
		
		if (count($fields) != count($this->importFields) {
			return false;
		}
		$fields = array_combine($this->importFields, $fields); // TODO if false?
	
		$this->load->model('localisation/language');
		
		$languages = $this->model_localisation_language->getLanguages();
		
		foreach ($fields as $key => $value) {
			switch ($key) {
			case 'name':case 'meta_description':case 'meta_keyword':
			case 'description':case 'tag':
			
				foreach($languages as $lang){               
					$product['product_description'][$lang['language_id']][$key] = $value;
				}
				break;	
			default:
				$product[$key] = $value;
				break;
			}			
		}
		
		return $product;
	}	
	
	/*
	 * This function realize plugin system.
	 * All plugins classes must place in "admin/model/module/csv_import_triggers/" folder.
	 *
	 * @param trigger - name of trigger function.
	 * @param data - parameters of trigger function referred by link.
	 * @return array/false 
	 */
	public function loadTrigger($trigger, &$data) {
		$dir  = DIR_APPLICATION . 'model/module/csv_import_triggers/';
		
		$plugins = $this->cache->get('csv.import.triggers.plugins');
		if (!$plugins) { 
			$plugins = array_diff(scandir($dir), array('..', '.'));
			$this->cache->set('csv.import.triggers.plugins', $plugins);
		}
		
		foreach ($plugins as $plugin) {
			if (is_file($dir.$plugin) && preg_match("/(\w+)\.php$/i", $plugin, $class)) {
			
				include_once($dir.$plugin);
				if (!class_exists( $class[1],false) || (class_exists( $class[1],false) && !method_exists($class[1], $trigger)) ) { continue; }
				
				$pluginClass = $class[1];
				$callback = new $pluginClass($this->registry);
				call_user_func_array(array($callback ,$trigger),array(&$data)); 			
			}
		}
		return true;
	}
	
	/*
	 * This function get fields of product table from db.
	 *
	 */
	public function getProductFieds() {
		$result = $fields = array();
	
		$query = $this->db->query('DESCRIBE '. DB_PREFIX . 'product');
		$result = $query->rows;
		
		$query = $this->db->query('DESCRIBE '. DB_PREFIX . 'product_description');
		$result = array_merge($result, $query->rows);
		
		foreach ($result as $row) {
			if ($row['Field'] == 'product_id') { continue; }
			$field = $row['Field'];
			$fields[$field] = $field;
		}
		return $fields;
	}
	
	/*
	 * This function get fields of category description table from db.
	 *
	 */
	public function getCategoryFieds() {
		$result = $fields = array();
	
		$query = $this->db->query('DESCRIBE '. DB_PREFIX . 'category_description');
		$result = $query->rows;
		
		foreach ($result as $row) {
			if ($row['Field'] == 'product_id' || $row['Field'] == 'language_id') { continue; }
			$field = $row['Field'];
			$fields[$field] = $field;
		}
		return $fields;
	}
	
	/*
	 * This function disable products - set `status` to 0.
	 *
	 * @param data - array of products id.
	 * @return false if error.
	 */
	public function disableProducts(&$data) {
		// TODO maybe add limit?
	
		if (!empty($data) && is_array($data)) {
			$this->db->query("UPDATE " . DB_PREFIX . "product SET status = '0', date_modified = NOW() WHERE status = '1' && product_id IN(".implode(',', $data).") ");
			return true;
		} else {
			return false;
		}
	}
	
	/*
	 * This function detect difference between products in import file and products in store.
	 *
	 * @param import_products - array of products id in import file.
	 * @return array of product id that no in import.
	 */
	public function getStoreProductDiff($import_products) {
		$sql = "SELECT `product_id` FROM " . DB_PREFIX . "product";
		$result = $this->db->query($sql);
		
		$diff  = array();
		foreach($result->rows as $val) {
			$val = $val['product_id'];
			$diff[$val] = 1;
		}
		foreach($import_products as $val) unset($diff[$val]);
		return array_keys($diff);		
	}
	
	/*
	 * This function search category by rulse.
	 *
	 * @param where - array of ruls.
	 * @param limit.
	 * @return array of category info.
	 */
	public function getCategory($where, $limit = 0) {
		$sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR ' &gt; ') AS name, c.parent_id, c.sort_order FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c ON (cp.path_id = c.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (c.category_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE ";
		
		if (is_array($where) && !empty($where)) {
			foreach ($where as $criteria) {
				//print_r($criteria);
				reset($criteria);
				$sql .= (key($criteria) .' \''. current($criteria).'\' ');
			}
			$sql .= " AND cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		} else {
			$sql .= " cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		}
		$sql .= ' GROUP BY cp.category_id ORDER BY name';
		
		if ($limit > 0) {		 
			$sql .= ' LIMIT ' . (int)$limit;
		} else {		 
			$sql .= ' LIMIT 1';
		}						
		$query = $this->db->query($sql);
		
		return !empty($query->rows[0]) ? $query->rows[0] : false;
	}
	
	public function getCategoryIdByPath($category_path, $category_field, $delimeter = '>') {
		$sql = 'SELECT DISTINCT c.category_id
				FROM ' . DB_PREFIX . 'category c
				LEFT JOIN ' . DB_PREFIX . 'category_description cd2 ON (c.category_id = cd2.category_id) 
				WHERE TRIM(LOWER(CONCAT_WS(\''.$delimeter.'\',(SELECT LOWER(GROUP_CONCAT(cd1.'.$category_field.' ORDER BY level SEPARATOR \''.$delimeter.'\')) 
					 FROM ' . DB_PREFIX . 'category_path cp 
					 LEFT JOIN ' . DB_PREFIX . 'category_description cd1 ON (cp.path_id = cd1.category_id AND cp.category_id != cp.path_id) 
					 WHERE cp.category_id = c.category_id  
					 GROUP BY cp.category_id
					), cd2.'.$category_field.'))) LIKE \''. $category_path. '\'';
		$query = $this->db->query($sql);
		return !empty($query->rows) ? $query->rows[0]['category_id'] : false;
	}
	
	/*
	 * This function search alias of url fo product.
	 *
	 * @param product_id.
	 * @return string.
	 */
	public function getKeyword($product_id) {
		$sql = "SELECT `keyword` FROM " . DB_PREFIX . "url_alias WHERE query = 'product_id=" . (int)$product_id . "'";
	
		$query = $this->db->query($sql);
		return !empty($query->rows) ? $query->rows[0]['keyword'] : '';
	}
	
	/*
	 * This function search last inserted product_id by date.
	 * @return product_id.
	 */
	public function getLastProductId() {
		// $sql = "SELECT `product_id` FROM " . DB_PREFIX . "product WHERE date_added <= NOW()";
	
		// $query = $this->db->query($sql);
		// return !empty($query->rows) ? $query->rows[0]['keyword'] : '';
	}
	
	/*
	 * This function search alias of url fo product.
	 *
	 * @param product_id.
	 * @return string.
	 */
	public function unlinkP2C($product_id = false) {
		if ($product_id === false) {
			$query = $this->db->query('TRUNCATE TABLE ' . DB_PREFIX . 'product_to_category');
		} else {
			$query = $this->db->query('DELETE FROM ' . DB_PREFIX . 'product_to_category WHERE product_id ='.$product_id);
		}
	}
	
	/*
	 * This function generate friendly url.
	 *
	 * @param name - string for translate.
	 * @return string.
	 */
	public function getSeoUrl($name) {
		// TODO place keyword trigger
		
		$seo_url = $this->db->escape($name);
		$tr = array ("А"=>"a","Б"=>"b","В"=>"v","Г"=>"g","Д"=>"d","Е"=>"e","Ж"=>"j","З"=>"z","И"=>"i","Й"=>"y","К"=>"k","Л"=>"l",
					 "М"=>"m","Н"=>"n","О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t","У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"ts","Ч"=>"ch",
					 "Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"yi","Ь"=>"","Э"=>"e","Ю"=>"yu","Я"=>"ya","а"=>"a","б"=>"b","в"=>"v","г"=>"g",
					 "д"=>"d","е"=>"e","ж"=>"j","з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l","м"=>"m","н"=>"n","о"=>"o","п"=>"p",
					 "р"=>"r","с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h","ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y","ы"=>"yi",
					 "ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya","."=>"-"," "=>"-","?"=>"-","/"=>"-","\\"=>"-","*"=>"-",":"=>"-","*"=>"-",
					 ">"=>"-","|"=>"-","'"=>""); 
		$seo_url  = strtr($seo_url ,$tr);
		return  preg_replace("/[^a-zA-Z0-9\/_|+-]/", '', $seo_url);
	}
	
	
	public function findProductBy($key, $value) 
	{
		$query = $this->db->query("SELECT DISTINCT *, (SELECT keyword FROM " . DB_PREFIX . "url_alias WHERE query = 'product_id=" . (int)$product_id . "') AS keyword FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p." . $key. " = '" . $value . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
				
		return $query->row;
	}
	
	/*
	 * This function make import from file to store
	 * @param cron - true/false indicate that module run on cron
	 */
	protected function execute() {
	
		// Load config
		$file = ($cron) ? self::CRON_IMPORT_FILENAME : $this->config->get('csv_import_file');
		$isZip = (strtolower(pathinfo($file, PATHINFO_EXTENSION)) == 'zip');
		$fileName = pathinfo($file, PATHINFO_FILENAME);
		$language = $this->config->get('config_language_id');
		$importKey = $this->config->get('import_key');
		$category_key = $this->config->get('category_key');
		$import_report = $this->config->get('csv_import_report');
		$import_email = $this->config->get('csv_import_email');
		$this->importFields = $this->config->get('csv_import_fields');
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

		$this->itemsChanged = array();
		$products_missing = array(); // TODO fix 
		$this->itemsExclude = array();
		
		// flags
		$import_options = in_array('option', $this->importFields);
		$import_categories = in_array('category_id', $this->importFields);
		$import_manufacturers = in_array('manufacturer_id', $this->importFields);
		
		// Lock file before read
		if (!flock($handle, LOCK_SH)) {
			$this->error['warning'] = "lock file error";
			$this->log->write('Import failed: Can\'t lock file '.DIR_DOWNLOAD.'import/'.$file );

			return false;
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');

		$this->cache->delete('product');
		
		// TODO check import key		
		
		while (($item = $this->getItem() !== false) {
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
			$result = $this->findProductBy($importKey, $item[$importKey]);
			
			if (empty($result)) {
				// Initialize new new base product by CSV values and add them to db
				$baseProduct = $this->getBaseProduct($item);
				
				// TODO trigger beforeAdd
				
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
			
			/* Update additional product parameters if it exist in importFields */
			
			// Category processing
			if (isset($this->importFields['category_id']) && !empty($this->importFields['category_id'])) {
				$product = array_merge($product, array('product_category' => $this->model_catalog_product->getProductCategories($productId)));
			}
			$product = array_merge($product, array('product_attribute' => $this->model_catalog_product->getProductAttributes($productId)));
			$product = array_merge($product, array('product_description' => $this->model_catalog_product->getProductDescriptions($productId)));			
			$product = array_merge($product, array('product_discount' => $this->model_catalog_product->getProductDiscounts($productId)));
			$product = array_merge($product, array('product_filter' => $this->model_catalog_product->getProductFilters($productId)));
			$product = array_merge($product, array('product_image' => $this->model_catalog_product->getProductImages($productId)));		
			$product = array_merge($product, array('product_option' => $this->model_catalog_product->getProductOptions($productId)));
			$product = array_merge($product, array('product_related' => $this->model_catalog_product->getProductRelated($productId)));
			$product = array_merge($product, array('product_reward' => $this->model_catalog_product->getProductRewards($productId)));
			$product = array_merge($product, array('product_special' => $this->model_catalog_product->getProductSpecials($productId)));
			$product = array_merge($product, array('product_download' => $this->model_catalog_product->getProductDownloads($productId)));
			$product = array_merge($product, array('product_layout' => $this->model_catalog_product->getProductLayouts($productId)));
			$product = array_merge($product, array('product_store' => $this->model_catalog_product->getProductStores($productId)));
			
			// TODO trigger beforeUpdate
			
			// Update product data from CSV
			$this->model_catalog_product->editProduct($productId, $product);
			
			$product = null;
		}
		
		if (!flock($handle, LOCK_UN)) {
			$this->log->write('Import warring: Can\'t unlock file '.DIR_DOWNLOAD.'import/'.$file );
		}

		switch ($this->config->get('if_not_exists')) {
		case 0:
			$products_ids = array_keys($this->itemsChanged);
			$not_in_import = $this->getStoreProductDiff($products_ids);
			$this->disableProducts($not_in_import );
			break;
		case 1:
			$products_ids = array_keys($this->itemsChanged);
			$not_in_import = $this->getStoreProductDiff($products_ids);

			foreach ($not_in_import as $product_id) {
				$this->model_catalog_product->deleteProduct($product_id);
			}
			break;
		default:
			break;
		}

		foreach ($not_in_import as $product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			$this->itemsExclude[$product_info['product_id']] = array(
							'key' => isset($product_info[$importKey]) ?  $product_info[$importKey] : $product_info[$importKey] ,
							'name' => isset($product_info['name']) ? $product_info['name'] : ''
						);
			switch ($this->config->get('if_not_exists')) {
			case 0:
				$this->itemsExclude[$product_info['product_id']]['status'] = $text_hide;
				break;
			case 1:
				$this->itemsExclude[$product_info['product_id']]['status'] = $text_delete; // TODO not work!
				break;
			default:
				break;
			}
		}
		
		$this->log->write('Import complete: '."\n\t\t".'-> success: '.count($this->itemsChanged)."\n\t\t".'-> exclude: '.count($this->itemsExclude));
		
		if (!$this->_cron) {
			return json_encode(array (
				'success' => $this->itemsChanged,
				'warnings' => $this->itemsWarning,
				'exclude' => $this->itemsExclude,
			));
		}
		return true;
	}
}
?>
