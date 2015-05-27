<?
/**
 * TEMPLATE_IS_NOT_SETTABLE
 */
?>
<?
#$options = Config::get('temp.options');
$options = Config::get('app.settings.main');
?>

<div class="header">

    {{ Menu::placement('main_menu') }}

    <a href="{{ URL::route('mainpage', ['lang' => Config::get('app.locale')]) }}" class="logo"></a>

    {{--{{ Helper::d(Config::get('temp')) }}--}}
    @if (isset($options['show_play_button']) && $options['show_play_button'])
        <a href="{{ URL::route('page', pageslug('registration')) }}" class="play-button">
            <p>
                {{ trans("interface.menu.play_for_free_header") }}
            </p>
        </a>
    @endif

{{--    <div class="language-panel">
        <select class="styled-select">
            <option data-url="/">rus</option>
            <option data-url="/en" selected="selected">eng</option>
        </select>
    </div>--}}


    @if (NULL !== ($route = Route::getCurrentRoute()) && is_object($route))
        {{--{{ $route->getName() }}--}}
        <?
        if ($route->getName() == 'mainpage') {
            #var_dump($route); die;
            #dd($route->getParameter('lang'));
        }
        $route_name = $route->getName();
        $route_lang = $route->getParameter('lang');
        $langs = [
            'ru' => 'rus',
            'en' => 'eng',
        ];
        ?>
        <div class="language-panel">
            <select class="styled-select">

                {{-- LOCALE SWITCHER --}}

                @foreach ($langs as $lang_sign => $lang_text)

                    <?
                    if (in_array($route_name, ['mainpage'])) {

                        ## Если мы на главной странице (основной или языковой)
                        $route_name = 'mainpage';
                        $route_params = ['lang' => $lang_sign] + $route->parameters();
                        $class = ($route_lang == $lang_sign
                                  || (is_null($route_lang)
                                      && Config::get('app.locale') == Config::get('app.default_locale')
                                      && $lang_sign == Config::get('app.locale'))) ? ' selected="selected"' : '';

                    } else {

                        ## Для всех остальных роутов, кроме главной страницы (основной или языковой)
                        $route_params = ['lang' => $lang_sign] + $route->parameters();
                        $class = (NULL !== ($route_lang = $route->getParameter('lang')) && $route_lang == $lang_sign) ? ' selected="selected"' : '';
                    }
                    ?>

                    <option data-url="{{ URL::route($route_name, $route_params) }}"{{ $class }}>{{ $lang_text }}</option>

                @endforeach

            </select>
        </div>
    @endif


</div>
