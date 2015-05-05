<?
/**
 * TITLE: Главная страница
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())
<?
$slider = Dic::valuesBySlug('slider', function($query) {
    $query->orderBy('lft', 'ASC');
});
$slider = DicLib::loadImages($slider, ['image']);
#Helper::tad($slider);

$news = Dic::valuesBySlug('news', function($query) {
    $query->order_by_field('published_at', 'DESC');
    $query->take(3);
});
$news = DicLib::loadImages($news, ['image']);
#Helper::tad($news);


$sidebar = Dic::valuesBySlug('sidebar', function($query) {
    $query->orderBy('lft', 'ASC');
}, []);
#Helper::tad($sidebar);

$socials = Dic::valuesBySlug('socials', function($query) {
    $query->orderBy('lft', 'ASC');
});
$socials = DicLib::loadImages($socials, ['image']);
#Helper::tad($socials);

$shops = Dic::valuesBySlug('shops', function($query) {
    $query->orderBy('lft', 'ASC');
});
$shops = DicLib::loadImages($shops, ['image']);
#Helper::tad($shops);

$video = Dic::valuesBySlug('video', function($query) {
    $query->orderBy('lft', 'ASC');
    $query->take(10);
});
$video = DicLib::loadImages($video, ['image']);
#Helper::tad($video);

$screenshots = Dic::valuesBySlug('screenshots', function($query) {
    $query->orderBy('lft', 'ASC');
    $query->take(10);
});
$screenshots = DicLib::loadImages($screenshots, ['image']);
#Helper::tad($screenshots);

#$options = Config::get('temp.options');
#Helper::tad(' [ ' . $options . ' ] ');
?>


@section('style')
@stop


@section('content')

    <div class="main-content">
        <div class="column-left">

            @if (is_collection($slider))
                <div class="main-slider-wrapper">
                    @if ($slider->count() > 1)
                        <div class="arrow-left js-main-left"></div>
                        <div class="arrow-right js-main-right"></div>
                    @endif
                    <div class="slider-frame"></div>
                    <div class="slider">
                        <div class="main-fotorama fotorama">
                            @foreach ($slider as $slide)
                                <div class="main-fotorana__item">
                                    @if ($slide->embed)
                                        {{ $slide->embed }}
                                    @elseif ($slide->is_img('image'))
                                        <a href="{{ $slide->link ?: '#' }}"><img src="{{ $slide->image->full() }}" /></a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if (is_collection($news))
                <div class="news-head-panel">
                    <h3>{{ trans("interface.menu.news") }}</h3>
                    <a href="{{ URL::route('page', ['slug' => pageslug('news')]) }}" class="all-news">{{ trans("interface.tpl.all_news") }}</a>
                </div>
                <div class="news-feed">
                    @foreach ($news as $new)
                        <?
                        $carbon = Carbon::createFromFormat('Y-m-d', $new->published_at);
                        #$link = URL::route('app.news_one', $new->slug);
                        $link = '#';
                        ?>
                        <div class="news-block">
                            <a href="{{ $link }}">
                                <h3>{{ $new->title }}</h3>
                            </a>
                            <p class="info">
                                <span class="date">{{ $carbon->format('d.m.Y') }}</span>
                                <span class="time">{{ $carbon->format('H:i') }}</span>
                            </p>
                            @if ($new->is_img('image'))
                                <a href="{{ $new->image->full() }}" class="fancybox image-prev" style="background-image:url({{ $new->image->thumb() }})"></a>
                            @endif
                            <p class="post">{{ $new->preview }}</p>
                            @if ($new->preview)
                                <a href="{{ $link }}" class="readmore">{{ trans("interface.tpl.read_more") }}</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>


        <div class="column-right">

            <a href="#" class="button-registration"><span>Регистрация</span></a>

            @if (is_collection($sidebar))
                <?
                $sdbr = new PixelGunSidebar(compact('socials', 'shops', 'video', 'screenshots'));
                ?>
                @foreach ($sidebar as $block)
                    <?
                    $method = $block->slug;
                    ?>
                    {{ $sdbr->$method() }}
                @endforeach
            @endif
        </div>
    </div>

@stop


@section('scripts')
@stop


<?
class PixelGunSidebar {

    private $data;

    public function __construct($data) {
        #Helper::tad($data);
        $this->data = $data;
    }

    public function socials() {
        if (is_collection($this->data['socials'])) {
?>
            <div class="social">
                <h3>{{ trans("interface.title.socials") }}</h3>
                <div class="social-buttons-panel">
                    @foreach ($this->data['socials'] as $tmp)
                        <?
                        if (!$tmp->slug)
                            continue;
                        ?>
                        <div class="social-button-holder" data-img="{{ $tmp->is_img('image') ? $tmp->image->thumb() : '' }}"><a href="#" class="soc-{{ $tmp->slug }}"></a></div>
                    @endforeach
                </div>
            </div>
<?
        }
    }

    public function shops() {
        if (is_collection($this->data['shops'])) {
?>
            <div class="store">
                <h3>{{ trans("interface.title.play_on_mobile") }}</h3>
                <p>{{ trans("interface.title.play_on_mobile_intro") }}</p>
                @foreach ($this->data['shops'] as $tmp)
                    <?
                    if (!$tmp->slug)
                        continue;
                    ?>
                    <div class="{{ $tmp->slug }}-holder" data-img="{{ $tmp->is_img('image') ? $tmp->image->thumb() : '' }}"><a href="#" class="{{ $tmp->slug }}"><span>{{ StringView::force($tmp->intro) }}</span></a></div>
                @endforeach
            </div>
<?
        }
    }

    public function video() {
        if (is_collection($this->data['video'])) {
?>
            <div class="video">
                <a href="{{ URL::route('page', 'video') }}">
                    <h3>{{ trans("interface.menu.video") }}</h3>
                </a>
                <div class="video-box">
                    @if (count($this->data['video']) > 1)
                        <div class="arrow-left js-video-left"></div>
                        <div class="arrow-right js-video-right"></div>
                    @endif
                    <div class="video-fotorama fotorama">
                        @foreach ($this->data['video'] as $tmp)
                            <?
                            if (!$tmp->embed)
                                continue;
                            ?>
                            <div class="video-fotorama__item">
                                <a href="#embed" rel="video" class="fancybox">
                                    <img src="{{ $tmp->is_img('image') ? $tmp->image->thumb() : '' }}">
                                </a>
                                <div id="embed">
                                    {{ $tmp->embed }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
<?
        }
    }

    public function screenshots() {
        if (is_collection($this->data['screenshots'])) {
?>
            <div class="screenshoots">
                <a href="{{ URL::route('page', 'screenshoots') }}">
                    <h3>{{ trans("interface.menu.screenshots") }}</h3>
                </a>
                <div class="screenshoot-box">
                    @if (count($this->data['screenshots']) > 1)
                        <div class="arrow-left js-screen-left"></div>
                        <div class="arrow-right js-screen-right"></div>
                    @endif
                    <div class="screen-fotorama fotorama">
                        @foreach ($this->data['screenshots'] as $tmp)
                            <?
                            if (!$tmp->is_img('image'))
                                continue;
                            ?>
                            <div><a href="{{ $tmp->is_img('image') ? $tmp->image->full() : '' }}" rel="screen-shoots" class="fancybox"><img src="{{ $tmp->is_img('image') ? $tmp->image->thumb() : '' }}"></a></div>
                        @endforeach
                    </div>
                </div>
            </div>
<?
        }
    }

    public function __call($method, $arguments) {
        #
    }
}
?>