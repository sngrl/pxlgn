<?php

class GroupsTableSeeder extends Seeder{

	public function run(){
		
		#DB::table('groups')->truncate();

        Group::create(array(
			'name' => 'developer',
			'desc' => 'Разработчики',
			'dashboard' => 'admin'
		));

		Group::create(array(
			'name' => 'admin',
			'desc' => 'Администраторы',
			'dashboard' => 'admin'
		));

		Group::create(array(
			'name' => 'moderator',
			'desc' => 'Модераторы',
			'dashboard' => 'admin'
		));
	}
}