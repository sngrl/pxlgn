<?
/**
 * TITLE: Регистрация OK
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())
<?
$page_title = trans("interface.menu.play_for_free");
?>


@section('style')
@stop


@section('content')

    <div class="registration-head">
        <h3>{{ trans("interface.menu.registration") }}</h3>
    </div>
    <div class="inner-content gradient-box">
        <div class="success-download">
            <h3 class="play-free">
                {{ trans("interface.menu.play_for_free") }}
            </h3>
            <div class="congratulation-field">
                <p>
                    {{ strtr(trans("interface.tpl.registration_success"), [
                        '%link%' => isset($link) ? $link : '#',
                        #'%nickname%' => isset($nickname) ? $nickname : '&nbsp;',
                    ]) }}
                </p>
                <p>
                    {{ trans("interface.tpl.download_installer_now") }}
                </p>
            </div>
            <div class="button-download-big">
                <a>
                    <span class="download-text">
                        {{ trans("interface.menu.load_game") }}
                    </span>
                    <span class="download-size">
                        {{ trans("interface.tpl.installer_size") }}
                    </span>
                </a>
            </div>
        </div>
    </div>

@stop


@section('scripts')
@stop
