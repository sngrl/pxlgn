<?
/**
 * MENU_PLACEMENTS: main_menu=Основное меню|about_menu=Меню "Об игре"|media_menu=Медиа меню|footer_1=Подвал 1|footer_2=Подвал 2|footer_3=Подвал 3|footer_4=Подвал 4
 */
?>
<!DOCTYPE html>
<!--[if lt IE 7]>
<html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>
<html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>
<html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!-->
<html class="no-js"> <!--<![endif]-->
<head>
    @include(Helper::layout('head'))
    @yield('style')
</head>
<body>
<!--[if lt IE 7]>
<p class="browsehappy">You are using an <strong>outdated</strong> browser. Please
    <a href="http://browsehappy.com/">upgrade your browser</a>
    to improve your experience.
</p><![endif]-->

<div class="header-img-holder">
    <div class="main-wrapper">

        @include(Helper::layout('header'))

        @section('content')
            {{ @$content }}
        @show

        @section('footer')
            {{-- @file_get_contents(Helper::inclayout('footer')) --}}
            @include(Helper::layout('footer'))
        @show

    </div>
</div>

@include(Helper::layout('scripts'))

@section('overlays')
@show

@section('scripts')
@show

{{ Config::get('app.settings.main.tpl_footer_counters') }}

</body>
</html>