Mon, 08 Jun 2015 09:16:55 +0000 (Severity: 2)
178.76.195.226 - http://pixel.dev.grapheme.ru/ru/forum/
OutOfRangeException
0: 
#0 /var/www/dev.grapheme.ru/pixel/public/common/ru/forum/system/Theme/Theme.php(292): IPS\Patterns\_ActiveRecord::load(0)
#1 /var/www/dev.grapheme.ru/pixel/public/common/ru/forum/system/Dispatcher/Standard.php(50): IPS\_Theme::i()
#2 /var/www/dev.grapheme.ru/pixel/public/common/ru/forum/system/Dispatcher/Front.php(442): IPS\Dispatcher\_Standard::baseCss()
#3 /var/www/dev.grapheme.ru/pixel/public/common/ru/forum/system/Dispatcher/Front.php(51): IPS\Dispatcher\_Front::baseCss()
#4 /var/www/dev.grapheme.ru/pixel/public/common/ru/forum/system/Dispatcher/Dispatcher.php(86): IPS\Dispatcher\_Front->init()
#5 /var/www/dev.grapheme.ru/pixel/public/common/ru/forum/index.php(13): IPS\_Dispatcher::i()
#6 {main}
------------------------------------------------------------------------
