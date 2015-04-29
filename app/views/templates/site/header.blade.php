<?
/**
 * TEMPLATE_IS_NOT_SETTABLE
 */
?>

<div class="header">
    {{ Menu::placement('main_menu') }}<a href="{{ URL::route('mainpage', ['lang' => Config::get('app.locale')]) }}" class="logo"></a><a href="{{ URL::route('page', pageslug('registration')) }}" class="play-button"></a>
    <div class="language-panel">
        <select class="styled-select">
            <option data-url="#">rus</option>
            <option data-url="#" selected="selected">eng</option>
        </select>
    </div>
</div>
