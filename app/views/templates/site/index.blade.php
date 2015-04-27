<?
/**
 * TITLE: Главная страница
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())
<?
#$temp = Dic::valueBySlugAndId('equipments', 1);
#Helper::ta($temp);
?>


@section('style')
@stop


@section('content')

    <div class="main-content">
        <div class="column-left">

            <div class="main-slider-wrapper">
                <div class="arrow-left js-main-left"></div>
                <div class="arrow-right js-main-right"></div>
                <div class="slider-frame"></div>
                <div class="slider">
                    <div class="main-fotorama fotorama">
                        <div class="main-fotorana__item">
                            <iframe frameborder="0" src="https://www.youtube.com/embed/SCYsskCPM5g"></iframe>
                        </div>
                        <div class="main-fotorana__item">
                            <iframe frameborder="0" src="https://www.youtube.com/embed/GZvW4v3-T1Y"></iframe>
                        </div>
                        <div class="main-fotorana__item">
                            <iframe frameborder="0" src="https://www.youtube.com/embed/LtLeJE5CijI"></iframe>
                        </div>
                        <div class="main-fotorana__item">
                            <iframe frameborder="0" src="https://www.youtube.com/embed/wucPJHgus2c"></iframe>
                        </div>
                    </div>
                </div>
            </div>
            <div class="news-head-panel">
                <h3>Новости</h3><a href="#" class="all-news">Все новости</a>
            </div>
            <div class="news-feed">
                <div class="news-block"><a href="#">
                        <h3>Важно: о грядущем большом обновлении</h3></a>
                    <p class="info"><span class="date">13.05.2005</span><span class="time">10:34</span></p>
                    <p class="post">Большое обновление неумолимо приближается с каждым днем. Вы еще не слышали о нем?.. Что ж, возможно, последние месяцы вы провели в бомбоубежище безо всякой связи с внешним миром: только это может послужить вам оправданием. Как бы то ни было, до обновления осталось совсем немного; самое время провести короткий инструктаж и рассказать, что ждет всех нас в ближайшем будущем.</p><a class="readmore">Подробнее</a>
                </div>
                <div class="news-block"><a href="#">
                        <h3>Основательно обновленные локации</h3></a>
                    <p class="info"><span class="date">13.05.2005</span><span class="time">10:34</span></p><a href="{{ Config::get('site.theme_path') }}/images/post-image-big.jpg" class="fancybox"><img src="{{ Config::get('site.theme_path') }}/images/post-image.jpg"></a>
                    <p class="post">Большое обновление неумолимо приближается с каждым днем. Вы еще не слышали о нем?.. Что ж, возможно, последние месяцы вы провели в бомбоубежище безо всякой связи с внешним миром: только это может послужить вам оправданием. Как бы то ни было, до обновления осталось совсем немного; самое время провести короткий инструктаж и рассказать, что ждет всех нас в ближайшем будущем.</p><a class="readmore">Подробнее</a>
                </div>
                <div class="news-block"><a href="#">
                        <h3>Важно: о грядущем большом обновлении</h3></a>
                    <p class="info"><span class="date">13.05.2005</span><span class="time">10:34</span></p>
                    <p class="post">Большое обновление неумолимо приближается с каждым днем. Вы еще не слышали о нем?.. Что ж, возможно, последние месяцы вы провели в бомбоубежище безо всякой связи с внешним миром: только это может послужить вам оправданием. Как бы то ни было, до обновления осталось совсем немного; самое время провести короткий инструктаж и рассказать, что ждет всех нас в ближайшем будущем.</p><a class="readmore">Подробнее</a>
                </div>
            </div>
        </div>
        <div class="column-right"><a href="#" class="button-registration"></a>
            <div class="social">
                <h3>Наша игра в соцсетях:</h3>
                <div class="social-buttons-panel">
                    <div class="social-button-holder"><a href="#" class="soc-vk"></a></div>
                    <div class="social-button-holder"><a href="#" class="soc-fb"></a></div>
                    <div class="social-button-holder"><a href="#" class="soc-tw"></a></div>
                </div>
            </div>
            <div class="store">
                <h3>Играйте на мобильных</h3>
                <p>У нас есть ещё и шутеры для мобильных устройств!</p>
                <div class="google-play-holder"><a href="#" class="google-play"><span>Загрузить на</span></a></div>
                <div class="app-store-holder"><a href="#" class="app-store"><span>Доступно в</span></a></div>
            </div>
            <div class="video"><a href="#">
                    <h3>Видео</h3></a>
                <div class="video-box">
                    <div class="arrow-left js-video-left"></div>
                    <div class="arrow-right js-video-right"></div>
                    <div class="video-fotorama fotorama">
                        <div class="video-fotorama__item"><a href="http://www.youtube.com/embed/SCYsskCPM5g" rel="video" class="fancybox fancybox.iframe"><img src="http://img.youtube.com/vi/SCYsskCPM5g/mqdefault.jpg"></a></div>
                        <div class="video-fotorama__item"><a href="http://www.youtube.com/embed/GZvW4v3-T1Y" rel="video" class="fancybox fancybox.iframe"><img src="http://img.youtube.com/vi/GZvW4v3-T1Y/mqdefault.jpg"></a></div>
                        <div class="video-fotorama__item"><a href="http://www.youtube.com/embed/LtLeJE5CijI" rel="video" class="fancybox fancybox.iframe"><img src="http://img.youtube.com/vi/LtLeJE5CijI/mqdefault.jpg"></a></div>
                        <div class="video-fotorama__item"><a href="http://www.youtube.com/embed/wucPJHgus2c" rel="video" class="fancybox fancybox.iframe"><img src="http://img.youtube.com/vi/wucPJHgus2c/mqdefault.jpg"></a></div>
                    </div>
                </div>
            </div>
            <div class="screenshoots"><a href="#">
                    <h3>Скриншоты</h3></a>
                <div class="screenshoot-box">
                    <div class="arrow-left js-screen-left"></div>
                    <div class="arrow-right js-screen-right"></div>
                    <div class="screen-fotorama fotorama">
                        <div><a href="{{ Config::get('site.theme_path') }}/images/post-image-2.jpg" rel="screen-shoots" class="fancybox"><img src="{{ Config::get('site.theme_path') }}/images/post-image-2.jpg"></a></div>
                        <div><a href="{{ Config::get('site.theme_path') }}/images/post-image-3.jpg" rel="screen-shoots" class="fancybox"><img src="{{ Config::get('site.theme_path') }}/images/post-image-3.jpg"></a></div>
                        <div><a href="{{ Config::get('site.theme_path') }}/images/post-image-4.jpg" rel="screen-shoots" class="fancybox"><img src="{{ Config::get('site.theme_path') }}/images/post-image-4.jpg"></a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop


@section('scripts')
@stop