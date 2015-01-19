<?php
interface CsvImportPluginInterface {
	public function getClassName();
	public function getName();
	public function getDescription();
	public function afterGetCsv($data);
}
?>
