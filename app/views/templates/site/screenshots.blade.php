<?
/**
 * TITLE: Скриншоты
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())
<?
$p = (int)Input::get('p') ?: 1;

$screenshots = Dic::valuesBySlug('screenshots', function($query) {
    $query->orderBy('lft', 'ASC');
}, ['fields', 'textfields'], true, true, true, 20);
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

        @if (is_collection($screenshots))
            <div class="screenshoot-gallery">
                <div class="gallery-head">
                    <h3>{{ trans("interface.menu.screenshots") }}</h3>
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
                <div class="pagination"></div>

                {{ $screenshots->links() }}

            </div>
        @endif

    </div>

@stop


@section('scripts')
@stop
