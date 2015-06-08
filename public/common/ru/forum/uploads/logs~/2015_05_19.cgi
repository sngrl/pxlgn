Tue, 19 May 2015 14:38:23 +0000 (Severity: 2)
188.162.185.3 - http://pixel.dev.grapheme.ru/en/forum/index.php?/profile/1-admin/&do=hovercard&csrfKey=3ebcd5e6e9998f703548f835db1e0f88
ErrorException
0: templates_already_rebuilding
#0 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Theme/Theme.php(624) : eval()'d code(446): IPS\_Theme->getTemplate('global', 'core')
#1 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/applications/core/modules/front/members/profile.php(241): IPS\Theme\class_core_front_profile->hovercard(Object(IPS\Member), Object(IPS\Http\Url))
#2 [internal function]: IPS\core\modules\front\members\_profile->hovercard()
#3 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Dispatcher/Controller.php(85): call_user_func(Array)
#4 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/applications/core/modules/front/members/profile.php(65): IPS\Dispatcher\_Controller->execute()
#5 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Dispatcher/Dispatcher.php(129): IPS\core\modules\front\members\_profile->execute()
#6 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/index.php(13): IPS\_Dispatcher->run()
#7 {main}
------------------------------------------------------------------------
