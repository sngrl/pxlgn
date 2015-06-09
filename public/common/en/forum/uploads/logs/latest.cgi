Tue, 09 Jun 2015 09:51:22 +0000 (Severity: 1)
127.0.0.1 - http://pixel.dev/en/forum/
IPS\Db\Exception
1049: Unknown database 'pixelgun3d'
#0 D:\home\dev\pixel\public\common\en\forum\system\Session\Front.php(115): IPS\_Db::i()
#1 [internal function]: IPS\Session\_Front->read('vrgb7086iid5neq...')
#2 D:\home\dev\pixel\public\common\en\forum\system\Session\Session.php(91): session_start()
#3 D:\home\dev\pixel\public\common\en\forum\system\Member\Member.php(124): IPS\_Session::i()
#4 D:\home\dev\pixel\public\common\en\forum\system\Theme\Theme.php(231): IPS\_Member::loggedIn()
#5 D:\home\dev\pixel\public\common\en\forum\system\Dispatcher\Standard.php(50): IPS\_Theme::i()
#6 D:\home\dev\pixel\public\common\en\forum\system\Dispatcher\Front.php(442): IPS\Dispatcher\_Standard::baseCss()
#7 D:\home\dev\pixel\public\common\en\forum\system\Dispatcher\Front.php(51): IPS\Dispatcher\_Front::baseCss()
#8 D:\home\dev\pixel\public\common\en\forum\system\Dispatcher\Dispatcher.php(86): IPS\Dispatcher\_Front->init()
#9 D:\home\dev\pixel\public\common\en\forum\index.php(13): IPS\_Dispatcher::i()
#10 {main}
------------------------------------------------------------------------
