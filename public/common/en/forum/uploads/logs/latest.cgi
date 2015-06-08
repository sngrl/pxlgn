Sat, 30 May 2015 11:28:16 +0000 (Severity: 2)
178.76.195.226 - http://pixel.dev.grapheme.ru/en/forum/index.php?/topic/4-%D0%BE%D0%B1%D1%81%D1%83%D0%B6%D0%B4%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B0%D1%80%D0%B5%D0%BD%D1%8B-%D1%82%D0%B2%D0%B5%D1%80%D0%B4%D1%8B%D0%BD%D1%8F-%D0%BA%D0%BE%D1%80%D1%81%D0%B0%D1%80%D0%BE%D0%B2/
ErrorException
2: Invalid argument supplied for foreach()
#0 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Theme/Theme.php(624) : eval()'d code(3149): IPS\IPS::errorHandler(2, 'Invalid argumen...', '/var/www/dev.gr...', 3149, Array)
#1 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/applications/forums/modules/front/forums/topic.php(254): IPS\Theme\class_forums_front_topics->topic(Object(IPS\forums\Topic), Array, NULL, Array, NULL, NULL, Array)
#2 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Dispatcher/Controller.php(94): IPS\forums\modules\front\forums\_topic->manage()
#3 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Content/Controller.php(45): IPS\Dispatcher\_Controller->execute()
#4 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/applications/forums/modules/front/forums/topic.php(40): IPS\Content\_Controller->execute()
#5 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/system/Dispatcher/Dispatcher.php(129): IPS\forums\modules\front\forums\_topic->execute()
#6 /var/www/dev.grapheme.ru/pixel/public/common/en/forum/index.php(13): IPS\_Dispatcher->run()
#7 {main}
------------------------------------------------------------------------
