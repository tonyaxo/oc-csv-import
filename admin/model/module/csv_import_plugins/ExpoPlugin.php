<?php
class ExpoPlugin implements CsvImportPluginInterface {

	const CATEGORY_ID_INDEX = 6;
	const DESCRIPTION_INDEX = 2;
	const PRICE_INDEX = 10;

	public function getClassName()
	{
		return get_class($this);
	}
	public function getName()
	{
		return 'Expo Plugin';
	}
	public function getDescription()
	{
		return 'Expo Plugin Description';
	}
	public function afterGetCsv($data) 
	{
		$data[self::CATEGORY_ID_INDEX] = implode('>', array_slice($data, self::CATEGORY_ID_INDEX, 4));
		$data[self::DESCRIPTION_INDEX] = implode('<br>', array_slice($data, self::DESCRIPTION_INDEX, 3));
		$data[self::PRICE_INDEX] = $data[self::PRICE_INDEX+1];
		
		unset(
			$data[self::CATEGORY_ID_INDEX+1],
			$data[self::CATEGORY_ID_INDEX+2],
			$data[self::CATEGORY_ID_INDEX+3],
			
			$data[self::DESCRIPTION_INDEX+1],
			$data[self::DESCRIPTION_INDEX+2],
			
			$data[self::PRICE_INDEX+1]
		);
		
		return $data;
	}
}
?>
