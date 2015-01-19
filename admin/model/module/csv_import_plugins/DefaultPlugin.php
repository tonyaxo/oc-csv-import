<?php
class DefaultPlugin implements CsvImportPluginInterface {
	public function getClassName()
	{
		return get_class($this);
	}
	public function getName()
	{
		return 'Default Plugin';
	}
	public function getDescription()
	{
		return 'Default Plugin Description';
	}
	public function afterGetCsv($data) 
	{
		return $data;
	}
}
?>
