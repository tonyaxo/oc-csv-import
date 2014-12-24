<?php
class ModelModuleCsvImport extends Model {
	private $error = array();
	// TODO get LAST ERROR
	
	/*
	 * This function get product fields from import file
	 * @param file - handle of file
	 * @param options - parameters of import file:
	 *					delimiter, enclosure, fields(fields in import file)
	 * @return array/false 
	 */
	public function getProduct($file, $options = array()) {	
		$delimiter = (isset($options['delimiter'])) ? $options['delimiter'] : ',';
		$enclosure = (isset($options['enclosure'])) ? $options['enclosure'] : '"';
		
		$product = array();
		
		if (($fields = fgetcsv($file, 0, $delimiter, $enclosure)) === FALSE ) {			
			return FALSE;
		}
		if ($fields[0] === null) {
			return array();
		}
		
		$this->loadTrigger('onAfterGetCsvFields', $fields);
		
		$fields_count = count($fields);
		$options_fields_count = count($options['fields']);
		if ($fields_count != $options_fields_count) {
			return false;
		}
		$fields = array_combine($options['fields'], $fields); // TODO if false?
	
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
	
	/*
	 * This function find the products by criteria.
	 *
	 * @param data - array of filters.
	 * @return products array.
	 */
	public function getProducts($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)";
		
		if (!empty($data['filter_category_id'])) {
			$sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)";			
		}
				
		$sql .= " WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'"; 
		
		if (!empty($data['filter_name'])) {
			$sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_name']) . "'";
		}

		if (!empty($data['filter_model'])) {
			$sql .= " AND p.model LIKE '" . $this->db->escape($data['filter_model']) . "'";
		}
		
		if (!empty($data['filter_price'])) {
			$sql .= " AND p.price LIKE '" . $this->db->escape($data['filter_price']) . "%'";
		}
		
		if (isset($data['filter_quantity']) && !is_null($data['filter_quantity'])) {
			$sql .= " AND p.quantity = '" . $this->db->escape($data['filter_quantity']) . "'";
		}
		
		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$sql .= " AND p.status = '" . (int)$data['filter_status'] . "'";
		}
		
		if (isset($data['filter_sku']) && !is_null($data['filter_sku'])) {
			$sql .= " AND p.sku = '" . $data['filter_sku'] . "'";
		}
		
		$sql .= " GROUP BY p.product_id";
					
		$sort_data = array(
			'pd.name',
			'p.model',
			'p.price',
			'p.quantity',
			'p.status',
			'p.sort_order'
		);	
		
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];	
		} else {
			$sql .= " ORDER BY pd.name";	
		}
		
		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}
	
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}				

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}	
		
			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}	
		$query = $this->db->query($sql);
	
		return $query->rows;
	}
	
	/*
	 * This function get product with default values.
	 *
	 * @return product array.
	 */
	public function getDefaultProduct() {
		
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		
		$product_description = array();
		foreach($languages as $lang){  
			$lang_id = $lang['language_id'];
			$product_description[$lang_id] = array(
				'name' =>  '',
				'meta_description' => '',
				'meta_keyword' => '',
				'description' => '',
				'tag' => 	''	
			);
		}
		$product = array(
			'product_description' => $product_description,
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
			'stock_status_id' => 5,
			'shipping' => 1,
			'keyword' => '',
			'image' => '',
			'date_available' => date("Y-m-d"),
			'length' => 0,
			'width' => 0,
			'height' => 0,
			'length_class_id' => 1,
			'weight' => 0,
			'weight_class_id' => 1,
			'status' => 1,
			'sort_order' => 1,
			'manufacturer' => '',
			'manufacturer_id' => 0,
			//'product_category' => array(),
			'filter' => array(),
			'product_store' => array(0),
			'download' => array(),
			'related' => array(),
			'product_option' => array(),
			'points' => array(),
			'product_reward' => array(
					1 => array(
							'points' => 0
						)
				),
			'product_layout' => array(
					array(
							'layout_id' => '',
						)
				)
		);	
		return $product;
	}
}
?>