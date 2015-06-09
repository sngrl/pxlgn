Tue, 09 Jun 2015 09:53:47 +0000 (Severity: 2)
178.76.195.226 - http://pixel.dev.grapheme.ru/en/forum/admin/?adsess=qsqj3aesjpi0s0t36osktkahc5&app=core&module=customization&controller=themes&id=1&do=saveTemplate
UnderflowException
0: 
#0 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/applications/core/modules/admin/customization/themes.php(2210): IPS\_Theme->saveCss(Array)
#1 [internal function]: IPS\core\modules\admin\customization\_themes->saveTemplate()
#2 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Dispatcher/Controller.php(85): call_user_func(Array)
#3 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Node/Controller.php(63): IPS\Dispatcher\_Controller->execute()
#4 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Dispatcher/Dispatcher.php(129): IPS\Node\_Controller->execute()
#5 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/admin/index.php(13): IPS\_Dispatcher->run()
#6 {main}
------------------------------------------------------------------------
