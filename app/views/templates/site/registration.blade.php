<?
/**
 * TITLE: Регистрация
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())


@section('style')
@stop


@section('content')

    <div class="registration-head">
        <h3>{{ trans("interface.menu.registration") }}</h3>
    </div>
    <div class="inner-content">
        <div class="registration-page-block">
            <h3 class="play-free">
                {{ trans("interface.menu.play_for_free") }}
            </h3>
            <div class="congratulation-field">
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
        @if (Config::get('app.settings.main.show_social_on_registration'))
            <div class="registration-page-block">
                <h3>
                    {{ trans("interface.tpl.enter_via_social") }}
                </h3>
                <div class="socials-big-holder"><a class="fb"></a><a class="vk"></a><a class="tw"></a></div>
            </div>
        @endif
        <div class="registration-page-block">
            <h3>
                {{ trans("interface.tpl.or_create_new_account") }}
            </h3>
            <form id="log-in-form" class="page">
                <div class="main-fields">
                    <div class="e-mail">
                        <input id="e-mail" type="text" required name="email" form="log-in-form">
                        <label for="e-mail">
                            {{ trans("interface.tpl.email") }}
                        </label>
                        <p class="warning">
                        </p>
                        <p class="description">
                            {{ trans("interface.tpl.email_desc") }}
                        </p>
                    </div>
                    <div class="pass">
                        <input type="password" required name="password" form="log-in-form">
                        <label for="password">
                            {{ trans("interface.tpl.password") }}
                        </label>
                        <button class="spice"></button>
                        <p class="warning">
                        </p>
                        <p class="description">
                            {{ trans("interface.tpl.password_rules") }}
                        </p>
                    </div>
                    @if (FALSE)
                        <div class="nickname">
                            <input id="nickname" type="text" required name="password" form="log-in-form">
                            <label for="nickname">
                                {{ trans("interface.tpl.name_in_the_game") }}
                            </label>
                            <p class="warning">
                            </p>
                        </div>
                    @endif
                </div>
                <div class="capcha">
                    <input id="capcha" type="text" required name="keystring" form="log-in-form" class="capcha-field">
                    <label for="capcha">
                        {{ trans("interface.tpl.enter_the_code") }}
                    </label>
                    <div class="capcha-image">
                        <div class="refresh"><a></a></div>
                        <img src="{{ URL::route('captcha_image', [session_name() => session_id(), 'w' => '99', 'h' => '39', 'hash' => time()]) }}">
                    </div>
                    <p class="warning">
                    </p>
                </div>
                <div class="confirmation">
                    <div class="stylish">
                        <input type="checkbox" required name="submit" form="log-in-form">
                    </div>
                    <p>
                        {{ trans("interface.tpl.i_read_the_rules") }}
                    </p>
                </div>
                <div class="log-in-button-3">
                    <button type="submit">
                        {{ trans("interface.tpl.create_account") }}
                    </button>
                </div>
            </form>
        </div>
    </div>


@stop


@section('scripts')
@stop
