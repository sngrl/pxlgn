<?
/**
 * TITLE: Главная страница
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())
<?
$temp = Dic::valueBySlugAndId('equipments', 1);
Helper::ta($temp);
?>


@section('style')
@stop


@section('content')

    {{ $page->block('slider') }}

    <div class="jumbotron">

        <h1 class="noview">111</h1>

        <center>
            <p>111</p>
        </center>

        <div class="text-center clearfix" style="margin: 30px auto 0px auto;">

            <!-- Put this div tag to the place, where the Like block will be -->
            <div class="" style="display:inline-block; margin-right:0; margin-bottom:5px;">
                <div id="vk_like"></div>
            </div>

            <!-- Go to www.addthis.com/dashboard to customize your tools -->
            <div class="addthis_native_toolbox pull-left" style="display:inline-block"></div>

            <div style="float:none; clear:both"></div>

        </div>

    </div>

    <!-- Put this script tag to the <head> of your page -->
    <script type="text/javascript" src="//vk.com/js/api/openapi.js?116"></script>
    <script type="text/javascript">
        VK.init({apiId: 4858249, onlyWidgets: true});
        VK.Widgets.Like("vk_like", {type: "button", height: 20});
    </script>

    <!-- Go to www.addthis.com/dashboard to customize your tools -->
    <script type="text/javascript" src="//s7.addthis.com/js/300/addthis_widget.js#pubid=ra-551d3e6a4848eea9" async="async"></script>


@stop


@section('scripts')
@stop