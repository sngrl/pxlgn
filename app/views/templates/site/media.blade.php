<?
/**
 * TITLE: Медиа
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())
<?
$p = (int)Input::get('p') ?: 1;

$video = Dic::valuesBySlug('video', function($query) {
    $query->orderBy('lft', 'ASC');
    #$query->take(10);
}, ['fields', 'textfields', 'seo'], true, true, true, 8);
$video = DicLib::loadImages($video, ['image']);
#Helper::tad($video);

$screenshots = Dic::valuesBySlug('screenshots', function($query) {
    $query->orderBy('lft', 'ASC');
    #$query->take(10);
}, ['fields', 'textfields'], true, true, true, 8);
$screenshots = DicLib::loadImages($screenshots, ['image']);
#Helper::tad($screenshots);
?>


@section('style')
@stop


@section('content')

    <div title="nope" class="inner-page-menu">
        {{ Menu::placement('media_menu') }}
    </div>

    <div class="inner-content">

        @if (is_collection($video))
            <div class="video-gallery">
                <div class="gallery-head">
                    <h3>{{ trans("interface.menu.video") }}</h3>
                    <a href="{{ URL::route('page', pageslug('video')) }}" class="watch-all">{{ trans("interface.tpl.all_videos") }}</a>
                </div>
                <ul class="gallery-row">
                    @foreach ($video as $vid)
                        <?
                        if (!$vid->is_img('image') || !$vid->embed)
                            continue;
                        ?>
                        <li class="gallery-row-holder">
                            <a href="#embed" rel="video" class="gallery-block fancybox fancybox.iframe">
                                <div class="img-wrapper">
                                    <img src="{{ $vid->image->thumb() }}">
                                </div>
                                <div id="embed">
                                    {{ $vid->embed }}
                                </div>
                                <p>{{ isset($vid->seo) && is_object($vid->seo) && $vid->seo->h1 ? $vid->seo->h1 : $vid->name }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (is_collection($screenshots))
            <div class="screenshoot-gallery">
                <div class="gallery-head">
                    <h3>{{ trans("interface.menu.screenshots") }}</h3>
                    <a href="{{ URL::route('page', pageslug('screenshots')) }}" class="watch-all">{{ trans("interface.tpl.all_screenshots") }}</a>
                </div>
                <ul class="gallery-row">
                    @foreach ($screenshots as $screen)
                        <?
                        if (!$screen->is_img('image'))
                            continue;
                        ?>
                        <li class="gallery-row-holder">
                            <a rel="screen-gall" href="{{ $screen->image->full() }}" class="gallery-block screenshoot fancybox">
                                <div class="img-wrapper">
                                    <img src="{{ $screen->image->thumb() }}">
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>

@stop


@section('scripts')
@stop
