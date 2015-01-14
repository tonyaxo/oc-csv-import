<?php







class ModelModuleCsvImport extends Model {
	
	const DEFAULT_STOCK_STATUS_ID = 5;
	
	protected $itemsChanged = null;
	protected $itemsWarning = null;
	protected $itemsExclude = null;
	
	public $importFields = array();
	
	private $_handle = null;
	private $_cron = false;
	private $_csvOptions = array(
		'delimiter' => ',',
		'enclosure' => '"',
	);
	
	private $error = array();
	// TODO get LAST ERROR
	
	public $manufacturers =  array();
	
	protected $imageFileTpl;
	
	protected $imageTplPlaceholders = array();
	
	protected $categoryDelimiter;
	
	protected $categoryKey;
	
	protected $productStatus;
	
	protected $categoryStatus;
	
	private $_baseProduct = array();
	
	/*
	 * Execute import via cron tab task.
	 *
	 * @param boolean $cron whether execute import with cron
	 */
	public function init($config = array())
	{				
		if (isset($config['categoryKey'])) {
			$this->categoryKey = $config['categoryKey'];
		} 
		if (isset($config['cronTask'])) {
			$this->setCron($config['cronTask']);
		} 
		if (isset($config['csvOptions'])) {
			$this->setCsvOptions($config['csvOptions']);
		} 
		if (isset($config['importFields'])) {
			$this->importFields = $config['importFields']);
		} 
		if (isset($config['productStatus'])) {
			$this->productStatus = $config['productStatus']);
		} 
		if (isset($config['categoryStatus'])) {
			$this->categoryStatus = $config['categoryStatus']);
		} 
		if (isset($config['imageFileTpl'])) {
			$this->prepareImages($config['imageFileTpl']);
		} 
		$this->_baseProduct = $this->getBaseProduct();
	}
	
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
	 * Prepare image name template and placeholders
	 *
	 * @param object $handle CSV file handler
	 * @return boolean.
	 */
	public function prepareImages($imageNameTpl)
	{				
		$this->imageFileTpl = $imageNameTpl;
		$placeholders = $this->getTableFields('product');
		
		foreach ($placeholders as $key => $value) {
			$this->imageTplPlaceholders[] = '%' . $key . '%';
		}
	}
	
	/*
	 * This function get product with base values.
	 *
	 * @param array  $values the castom fill values of product
	 * @return product array.
	 */
	public function initProduct($values = array(), $includeDescription = false, $languageId = null) 
	{	
		$product = $this->_baseProduct;
		foreach ($values as $key => $value) {
			if (isset($product[$key])) {
				$product[$key] = $value;
			}
		}	
		if ($includeDescription) {
			if ($languageId && isset($product['product_description']) && isset($product['product_description'][$languageId])) {
				foreach ($values as $key => $value) {
					if (isset($product['product_description'][$languageId][$key])) {
						$product['product_description'][$languageId][$key] = $value;
					}
				}
			} elseif (isset($product['product_description']) && !empty($product['product_description'])) {
				foreach ($product['product_description'] as $languageId => $description) {
					foreach ($values as $key => $value) {
						if (isset($product['product_description'][$languageId][$key])) {
							$product['product_description'][$languageId][$key] = $value;
						}
					}
				}				
			}
		}	
		return $product;
	}
	
	/*
	 * This function get product with base values.
	 *
	 * @param array  $values the castom fill values of product
	 * @return product array.
	 */
	public function getBaseProduct($languageId = null) 
	{	
		$baseProduct = $this->getTableFields('product');
		unset($baseProduct['product_id']);		

		$baseProductDescription = $this->getTableFields('product_description');
		unset($baseProductDescription['product_id']);
		
		if ($languageId == null) {
			$languageId = $this->config->get('config_language_id');
		}
		$baseProduct['product_description'] = array();
		$baseProduct['product_description'][$languageId] = $baseProductDescription;
		
		return $baseProduct;	
	}
	
	/*
	 * Wrapper for fgetcsv
	 * 
	 * @return array or false 
	 */
	public function getItem() 
	{			
		$fields = fgetcsv($this->_handle, 0, $this->_csvOptions['delimiter'], $this->_csvOptions['enclosure']);
		if ($fields === false ) {			
			return false;
		}
		if ($fields[0] === null) {
			return array();
		}
		return array_diff($fields, array(''));
	}
	
	/*
	 * This function make import from file to store
	 * 
	 * @param array $product the array returns $catalog_model_product->getProduct($product_id)
	 * @param array $extender fields returned from CSV file
	 */
	public function extendProduct($product, $extender) 
	{
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		
		$languageId = $this->config->get('config_language_id');		
		$productId = $product['product_id'];
		
		/* Update description fields without condition */
		
		// Move description fields in sub array	
		$descriptionFields = $this->getTableFields('product_description');
		unset($descriptionFields['product_id']);		
		
		$product['product_description'] = array(
			$languageId => array(),
		);
		foreach ($descriptionFields as $key => $value) {
			if (isset($product[$key])) {
				$product['product_description'][$languageId][$key] = $product[$key];
				unset($product[$key]);
			}
		}
		
		// Update manufacturer
		if (isset($extender['manufacturer_id'])) {
			$product['manufacturer_id'] = $this->addManufacturer($extender['manufacturer_id']);
		}
		
		// Update SEO url
		if (isset($extender['keyword'])) {
			$product['keyword'] = $extender['keyword'];
		}
		// TODO autogenerated keyword
		
		// Update image
		if (isset($extender['image'])) {
			$product['image'] = $this->addImage($extender['image']);
		} elseif (!empty($this->imageFileTpl)) {
			$values = array_values($product);
			$image = str_replace($placeholders, $values, $this->imageFileTpl);
			
			$product['image'] = $this->addImage($image);
		}
		
		// Category processing
		// if (isset($this->importFields['category_id']) && !empty($this->importFields['category_id'])) {
			// $this->processCategory($this->importFields['category_id']);
		// }
		
		//$product = array_merge($product, array('product_category' => $this->model_catalog_product->getProductCategories($productId)));		
		//$product = array_merge($product, array('product_image' => $this->model_catalog_product->getProductImages($productId)));
		
		//$product = array_merge($product, array('product_attribute' => $this->model_catalog_product->getProductAttributes($productId)));
		//$product = array_merge($product, array('product_discount' => $this->model_catalog_product->getProductDiscounts($productId)));
		//$product = array_merge($product, array('product_filter' => $this->model_catalog_product->getProductFilters($productId)));
		//$product = array_merge($product, array('product_option' => $this->model_catalog_product->getProductOptions($productId)));
		//$product = array_merge($product, array('product_related' => $this->model_catalog_product->getProductRelated($productId)));
		//$product = array_merge($product, array('product_reward' => $this->model_catalog_product->getProductRewards($productId)));
		//$product = array_merge($product, array('product_special' => $this->model_catalog_product->getProductSpecials($productId)));
		//$product = array_merge($product, array('product_download' => $this->model_catalog_product->getProductDownloads($productId)));
		//$product = array_merge($product, array('product_layout' => $this->model_catalog_product->getProductLayouts($productId)));
		//$product = array_merge($product, array('product_store' => $this->model_catalog_product->getProductStores($productId)));
	
		return $product;
	}	
	
	/*
	 * Function add new manufacturer if not exists 
	 * 
	 * @param integer|string $manufacturerId new manufacturer Name or ID
	 * @return integer manufacturer ID
	 */
	protected function addManufacturer($manufacturerId)
	{
		$this->load->model('catalog/manufacturer');
		
		if (!is_int($manufacturerId)) {
			$result = array_search($manufacturerId, $this->manufacturers);
			
			if ($result !== false) {
				return $result;
			}
			
			$this->model_catalog_manufacturer->addManufacturer(array(
				'name' => $manufacturerId,
				'sort_order' => 0
			));
			$result = $this->db->getLastId();	
			$this->manufacturers[$result] = $manufacturerId;
			
			return $result;
		}
		if (!isset($manufacturers[$manufacturerId])) {
			return false;
		} 
		return $manufacturerId;
	}
	
	/*
	 * Function check image and return path to image 
	 * 
	 * @param string $image image file name
	 * @return string Image file path
	 */
	protected function addImage($image)
	{
		$path = $this->config->get('import_image_dir', '');		
		$imagePath = 'data/' . $path . $image;
		
		if (is_file(DIR_IMAGE . $imagePath)) {
			return $imagePath;
		}
		return '';
	}
	
	/*
	 * Process category from CSV
	 * 
	 * @return integer category_id or false
	 */
	protected function processCategory($categoryPath)
	{
		$this->load->model('catalog/category');
		
		$languageId = $this->config->get('config_language_id');		
				
		$categories = explode($this->categoryDelimiter, $categoryPath);
		
		$path = array();
		$previousId = 0;
		
		foreach ($categories as $category) {
			$path[] = $category;
			$categoryId = $this->getCategoryIdByPath(
				implode($this->categoryDelimiter, $path), 
				$this->categoryKey
			);
			
			if ($categoryId !== false) {
				$previousId = $categoryId;
			} else {
				$this->model_catalog_category->addCategory(array(
					'parent_id' => $previousId,
					'top' => ($previousId ? 0 : 1),
					'column' => 1,
					'sort_order' => 0,
					'status' => 1,
					'category_description' => array(
						$languageId => array(
							'name' => $category,
							'meta_keyword' => $category,
							'meta_description' => $category,
							'description' => $category,
							'seo_title' => $category,
							'seo_h1' => $category,
							'href' => '',
						),
					),
					'category_store' => array(0),
				));
				$previousId = $this->model_module_csv_import->getCategoryIdByPath(
					implode($this->categoryDelimiter, $path), 
					$this->categoryKey
				);
			}
		}
	}
	
	/**
	 * Return category_id from path string
	 *	
	 * @return integer category_id or false
	 */
	public function getCategoryIdByPath($categoryPath, $nameField = 'name') {
		$query = $this->db->query(
			'SELECT DISTINCT c.category_id ' .
			'FROM ' . DB_PREFIX . 'category c ' .
				'LEFT JOIN ' . DB_PREFIX . 'category_description cd2 ON (c.category_id = cd2.category_id) ' . 
			'WHERE TRIM(LOWER(CONCAT_WS(\'' . $this->categoryDelimiter . '\',(SELECT LOWER(GROUP_CONCAT(cd1.' . $nameField . ' ORDER BY level SEPARATOR \'' . $this->categoryDelimiter . '\'))' . 
				'FROM ' . DB_PREFIX . 'category_path cp ' . 
				'LEFT JOIN ' . DB_PREFIX . 'category_description cd1 ON (cp.path_id = cd1.category_id AND cp.category_id != cp.path_id) ' .
				'WHERE cp.category_id = c.category_id ' .
				'GROUP BY cp.category_id ' .
			'), cd2.' . $nameField . '))) LIKE \'' . $categoryPath . '\''
		);
		return !empty($query->rows) ? $query->rows[0]['category_id'] : false;
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
	public function getTableFields($tableName) {
		
		$result = array();  
		$fields = array();
		
		$query = $this->db->query('DESCRIBE '. DB_PREFIX . $tableName);
		$result = $query->rows;
		
		foreach ($result as $row) {
			$fields[$row['Field']] = $row['Default'];
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
	 * Govnocode
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
	
	/*
	 * Search product by unique field
	 * 
	 * @param string $key
	 * @param string $value
	 */
	public function findProductBy($key, $value) 
	{
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "product p WHERE p." . $key . " = '" . $value . "'");
				
		return $query->row;
	}

}
?>
