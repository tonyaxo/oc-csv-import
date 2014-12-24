<?php
class AfterGetCsvFields extends Model {
	const CONSTANT_FILEDS_COUNT = 5;
	const REVERCE_PRICE_FIELD_NUM = 2;

	public function onAfterGetCsvFields(&$data) {
	
		$categories = array();
		$start_field = count($data) - self::CONSTANT_FILEDS_COUNT-1;
		for ($i = $start_field ; $i >= 0; $i--) {
			if ($data[$i] != '0') {
				$categories[] = $data[$i];
			}
			unset($data[$i]);
		}
		$category_path = implode('>', $categories);

		$data = array_merge((array)$category_path, $data);
		$data = array_values($data);
		$data[4] = str_replace(',', '.',$data[4]);
		
		//option
		if (!empty($data[3])) { $data[3] = 'select:Варианты:'.$data[3]; }
	}
	
	public function onBeforeProductInsert(&$product) {
		
		$lang = $this->config->get('config_language_id');
		
		$product['force_latest'] = 0;
		$product['force_bestseller'] = 0;
		$product['product_description'][$lang]['short_description'] = '';
	}
	public function onBeforeProductUpdate(&$product) {

		$lang = $this->config->get('config_language_id');
		
		$product['product_description'][$lang]['short_description'] = $product['short_description'];
		unset($product['short_description']);
	}
	public function onBeforeCategoryCriteria(&$criteria) {
		if (is_array($criteria) && !empty($criteria) && is_array($criteria[0]) && !empty($criteria[0])) {
			$category = current($criteria[0]);
			$criteria[] = array(' OR cd2.naz LIKE' => $category.'%');
		}
	}
}

?>