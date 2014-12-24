<?php
// Heading Goes here:
$_['heading_title']    = 'Csv Import';

// Text
$_['text_module']      			= 'Modules';
$_['text_success']     			= 'Сохранено: Вы успешно изменили настройки модуля '.$_['heading_title'] ;
$_['text_content_top']   		= 'Content Top';
$_['text_content_bottom'] 		= 'Content Bottom';
$_['text_column_left']    		= 'Column Left';
$_['text_import_file']    		= 'Путь к файлу выгрузки относительно папки downdloads/import/: <span>import/file.csv</span>';
$_['text_update_if_exists']     = 'Обновить';
$_['text_missing_if_exists']    = 'Пропустить';
$_['text_donothing']  			= 'Ни чего не делать';
$_['text_product_error']  		= 'Ошибка';
$_['text_product_name']  		= 'Название';
$_['text_product_status']  		= 'Статус';
$_['text_hide_if_not_exists']   = 'Скрыть';
$_['text_add']   				= 'Добавлено';
$_['text_update']   			= 'Обновленно';
$_['text_missing']   			= 'Пропущено';
$_['text_exclude']   			= 'Исключено';
$_['text_hide']   				= 'Скрыт';
$_['text_delete']   			= 'Удален';
$_['text_warring']   			= 'Предупреждение';
$_['text_delete_if_not_exists'] = 'Удалить';
$_['text_dropped'] 				= 'Для изменения порядка просто перетащите поле в нужную позицию.';
$_['text_import_results'] 		= 'Результаты импорта';
$_['text_import_time'] 			= 'Time:';
$_['text_import_memory'] 		= 'Memory total/peak ,KB:';
$_['text_import_success'] 		= 'Импорт успешно выполнен!';
$_['text_import_error'] 		= 'Ошибка импорта!';
$_['text_exit_on_import'] 		= 'В данный момент выполняется импорт, прерывание импорта может привести к непредвиденным послествиям!';
$_['text_import_processing'] 	= '<p>Импорт...</p><p>не закрываете окно браузера пока импорт не завершится.</p>';
$_['text_import_confirm'] 		= 'Начать импорт прямо сейчас?';

// Email
$_['email_sender'] 				= $_['heading_title'];
$_['email_subject'] 			= 'Результаты импорта товаров';
$_['email_text'] 				= '<html><head><title>Результаты импорта товаров</title></head><body>
								<table>
									<tr>
										<td>Продуктов в выгрузке:</td><td>%s</td>
									</tr>
									<tr>
										<td>Импортировано:</td><td>%s</td>
									</tr>
									<tr>
										<td>Исключено:</td><td>%s</td>
									</tr>
									<tr>
										<td>С ошибками:</td><td>%s</td>
									</tr>
								</table>
								</body></html>';

// Entry
$_['entry_import_file']       	= 'Путь к файлу выгрузки:<span class="help">относительно папки downdloads/import/:</span>'; // this will be pulled through to the controller, then made available to be displayed in the view.
$_['entry_email_report']        = 'Отправлять уведомление о результатах импорта на email:';
$_['entry_category_id']        	= 'Категория';
$_['entry_clear_p2c']        	= 'Удалить все связи <i>продукт&nbsp;-&nbsp;категория</i>:<span class="help">Рекомендуется если выгружается полный список товаров.</span>';
$_['entry_add_to_parent']  		= 'Добавлять продукт в родительские категории:';
$_['entry_if_exists']        	= 'Действие если продукт существует в базе:';
$_['entry_if_not_exists']       = 'Действие если продукт отсутствует в выборке:';
$_['entry_fields']        		= 'Укажите какие поля будут импортированы<span class="help">В том порядке в котором они идут в файле импорта</span>';
$_['entry_sort_order']   		= 'Sort Order:';
$_['entry_import_key']   		= 'Поле для индетификации продукта: <span class="help">данное поле должно быть уникальным для каждого продукта, оно будет использоваться для обновления продукта.</span>';
$_['entry_category_key']   		= 'Название категории из: <span class="help">Поле по которому строиться проверяется путь к категории.</span>';

// Tabs
$_['tab_products']   			= 'Продукты';
$_['tab_categories']   			= 'Категории';

// Button
$_['button_add_field']   		= 'Добавить поля';
$_['button_check_import']       = 'Проверить импорт';
$_['button_import']       		= 'Импорт';
$_['button_print']       		= 'Печать';

// Error
$_['error_permission'] 			= 'Warning: You do not have permission to modify module My Module!';
$_['error_file']	 			= 'Файл выгрузки /downloads/import/%s не существует или не доступен для чтения!';
$_['error_fields_count'] 		= 'Количество полей в выгрузке %s вы указали %s';
$_['error_fields_empty'] 		= 'Укажите поля которые будут выгружаться!';
$_['error_import_key'] 			= 'Вы не указали уникальное поле!';
$_['error_import_key_not_exists'] = 'Уникальное поле отсутствует в списки полей!';
$_['error_import_fields'] 		= 'Вы должны выбрать поля для импорта!';
$_['error_import_email'] 		= 'Email адрес для уведомления не корректен!';
$_['error_key_not_unique'] 		= '`%s` не может быть уникальным ключем импорта!';

// Warring
$_['warring_category_not_found'] = 'Категория не найдена!';
?>