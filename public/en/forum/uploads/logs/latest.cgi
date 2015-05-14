Wed, 06 May 2015 16:22:08 +0000 (Severity: 2)
178.76.195.226 - http://pixel.dev.grapheme.ru/en/forum/index.php?/profile/1-admin/&tab=activity
ErrorException
2: Invalid argument supplied for foreach()
#0 /var/www/dev.grapheme.ru/pixel/public/en/forum/system/Theme/Theme.php(624) : eval()'d code(1796): IPS\IPS::errorHandler(2, 'Invalid argumen...', '/var/www/dev.gr...', 1796, Array)
#1 /var/www/dev.grapheme.ru/pixel/public/en/forum/applications/core/modules/front/members/profile.php(226): IPS\Theme\class_core_front_profile->profile(Object(IPS\Member), Array, Array, Array, Object(IPS\Patterns\ActiveRecordIterator), 0, '<form accept-ch...', Array, Object(IPS\Http\Url), Object(IPS\Db\Select), Object(IPS\Content\Search\Mysql\Results))
#2 /var/www/dev.grapheme.ru/pixel/public/en/forum/system/Dispatcher/Controller.php(94): IPS\core\modules\front\members\_profile->manage()
#3 /var/www/dev.grapheme.ru/pixel/public/en/forum/applications/core/modules/front/members/profile.php(65): IPS\Dispatcher\_Controller->execute()
#4 /var/www/dev.grapheme.ru/pixel/public/en/forum/system/Dispatcher/Dispatcher.php(129): IPS\core\modules\front\members\_profile->execute()
#5 /var/www/dev.grapheme.ru/pixel/public/en/forum/index.php(13): IPS\_Dispatcher->run()
#6 {main}
------------------------------------------------------------------------
