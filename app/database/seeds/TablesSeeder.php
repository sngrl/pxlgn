<?php

class TablesSeeder extends Seeder{

	public function run(){

		#DB::table('settings')->truncate();

		Setting::create(array(
			'id' => 1,
			'name' => 'language',
			'value' => 'ru',
		));


        Dic::create(array(
            'id' => 2,
            'slug' => 'room_type',
            'name' => 'Номера',
            'entity' => '1',
            'icon_class' => 'fa-circle-o',
        ));

        DicVal::inject('room_type', array(
            'slug' => 'first',
            'name' => 'Some name',
            'fields_i18n' => array(
                'ru' => array(
                    'price' => 111,
                    'price2' => 222,
                ),
                'en' => array(
                    'price' => 333,
                    'price2' => 444,
                ),
            ),
        ));

	}
}