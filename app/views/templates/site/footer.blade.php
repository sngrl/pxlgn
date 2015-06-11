<?
/**
 * TEMPLATE_IS_NOT_SETTABLE
 */
?>
<script>
    var api_errors = {
        'required_field': '{{ trans("interface.api.required_field") }}',
        'accept_rules': '{{ trans("interface.api.accept_rules") }}',
        'bad_captcha': '{{ trans("interface.api.bad_captcha") }}',
        'bad_password': '{{ trans("interface.api.bad_password") }}',
        'result_2': '{{ trans("interface.api.result.2") }}',
        'result_3': '{{ trans("interface.api.result.3") }}',
        'result_4': '{{ trans("interface.api.result.4") }}',
        'result_7': '{{ trans("interface.api.result.7") }}'
    };
</script>


<div class="footer">
    <div class="footer-block">
        {{ Menu::placement('footer_1') }}
    </div>
    <div class="footer-block">
        {{ Menu::placement('footer_2') }}
    </div>
    <div class="footer-block">
        {{ Menu::placement('footer_3') }}
    </div>
    <div class="footer-block help">
        {{ Menu::placement('footer_4') }}
    </div>
    <div class="footer-copyright"><a href="#"><img src="{{ Config::get('site.theme_path') }}/images/logo-copyright.png"></a>
        <p>Copyright 2012-{{ date('Y') }} {{ trans("interface.tpl.company_name") }} ©</p>
        <p>{{ trans("interface.tpl.all_rights_reserved") }}</p>
    </div>
</div>


<div class="login-form-background">
    <div class="login-hack"></div>
    <div class="login-form">
        <div class="form-head">
            <h3>{{ trans("interface.menu.registration") }}</h3>
            <div class="button-wrapper">
                <button class="close-button"></button>
            </div>
        </div>
        <div class="form-body">
            <div class="form-fade">
                <div class="form-block no-gradient">
                    <form action="{{ URL::route('app.api') }}" method="POST" id="log-in-form2">
                        <div class="main-fields">
                            <p class="info">
                                {{ trans("interface.tpl.email") }}
                            </p>
                            <div class="e-mail">
                                <input type="text" required name="email" form="log-in-form2">
                                <p class="warning"></p>
                                <label id="email-error2" class="error" for="email"></label>
                            </div>
                            <p class="info">
                                {{ trans("interface.tpl.password") }}
                            </p>
                            <div class="pass">
                                <input type="password" required name="pass" form="log-in-form2">
                                <button class="spice"></button>
                                <p class="warning"></p>
                            </div>
                        </div>
                        <p class="info">
                            {{ trans("interface.tpl.enter_the_code") }}
                        </p>
                        <div class="capcha">
                            <input type="text" required name="keycode" form="log-in-form2" class="capcha-field">
                            <div class="capcha-image">
                                <div class="refresh"><a></a></div><img src="{{ URL::route('captcha_image', [session_name() => session_id(), 'w' => '99', 'h' => '39', 'hash' => time()]) }}">
                            </div>
                            <label id="capcha-error" class="error forsed-popup" for="keycode"></label>
                            <p class="warning"></p>
                        </div>
                        <div class="agreement">
                            <input type="checkbox" required name="agreement" form="log-in-form2" checked>
                            <p class="listense">
                                {{ trans("interface.tpl.i_read_the_rules") }}
                            </p>
                            <p class="warning"></p>
                        </div>
                        <div class="log-in-button-2">
                            <button type="submit">
                                {{ trans("interface.tpl.create_account") }}
                            </button>
                        </div>
                    </form>
                </div>
                @if (Config::get('app.settings.main.show_social_on_registration'))
                    <div class="form-block">
                        <h3 class="form-block-head">
                            {{ trans("interface.tpl.enter_via_social") }}
                        </h3>
                        <div class="social-panel"><a href="#" class="vk"></a><a href="#" class="fb"></a><a href="#" class="tw"></a></div>
                    </div>
                @endif
            </div>
            <div class="success-fade">
                <div class="form-block">
                    <div class="success">
                        <h3>
                            {{ trans("interface.tpl.account_created") }}
                        </h3>
                        {{ strtr(trans("interface.tpl.registration_success_popup"), [
                            #'%link%' => isset($link) ? $link : '#',
                            #'%nickname%' => isset($nickname) ? $nickname : '&nbsp;',
                        ]) }}
                    </div>
                </div>
                @if (Config::get('app.settings.main.show_download_block_after_registration'))
                    <div class="form-block">
                        <p class="download">
                            {{ trans("interface.tpl.download_installer_now") }}
                        </p>
                        <div class="download-button">
                            <a href="{{ Config::get('app.settings.main.download_game_link') }}">
                                <span class="download-text">
                                    {{ trans("interface.menu.load_game") }}
                                </span>
                                <span class="download-size">
                                    {{ trans("interface.tpl.installer_size") }}
                                </span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
            <div class="js-form-error">Ошибка!</div>
        </div>
    </div>
</div>