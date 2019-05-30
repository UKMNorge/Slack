<?php

$actions = [];
$attachments = new stdClass();
$attachments->callback_id = 'kjop_add';
$attachments->fields = [];

	$liste = trello::getListByName('Ukjent butikk');

	
	$menu_fra = new stdClass();
	$menu_fra->name = 'fra_test';
	$menu_fra->text = 'Kjøpes fra';
	$menu_fra->type = 'select';
	$menu_fra->data_source = 'static';
	$menu_fra->option_groups = $option_groups;
	
	$actions[] = $menu_fra;
	$attachments->actions = $actions;

}




$attachments->fields[] = $antall;
$attachments->fields[] = $til;
if( $_FRA_FOUND ) {
	$attachments->fields[] = $fra;
}
$attachments->fields[] = $url;


$response->attachments = [ $attachments ];
