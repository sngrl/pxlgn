<?
/**
 * TEMPLATE_IS_NOT_SETTABLE
 */
?>
<?
$options = Config::get('temp.options');
?>

<div class="header">

    {{ Menu::placement('main_menu') }}

    <a href="{{ URL::route('mainpage', ['lang' => Config::get('app.locale')]) }}" class="logo"></a>

    {{--{{ Helper::d(Config::get('temp')) }}--}}
    @if (isset($options['show_play_button']) && $options['show_play_button'])
        <a href="{{ URL::route('page', pageslug('registration')) }}" class="play-button"></a>
    @endif

    <div class="language-panel">
        <select class="styled-select">
            <option data-url="/">rus</option>
            <option data-url="/en" selected="selected">eng</option>
        </select>
    </div>
</div>
