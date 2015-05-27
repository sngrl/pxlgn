<?
/**
 * TEMPLATE_IS_NOT_SETTABLE
 */
?>

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
                    <form id="log-in-form">
                        <div class="main-fields">
                            <p class="info">
                                {{ trans("interface.tpl.email") }}
                            </p>
                            <div class="e-mail">
                                <input type="text" required name="email" form="log-in-form">
                                <p class="warning">E-mail уже занят!</p>
                            </div>
                            <p class="info">
                                {{ trans("interface.tpl.password") }}
                            </p>
                            <div class="pass">
                                <input type="password" required name="password" form="log-in-form">
                                <button class="spice"></button>
                                <p class="warning">Слишком простой пароль</p>
                            </div>
                        </div>
                        <p class="info">
                            {{ trans("interface.tpl.enter_the_code") }}
                        </p>
                        <div class="capcha">
                            <input type="text" required name="capcha" form="log-in-form" class="capcha-field">
                            <div class="capcha-image">
                                <div class="refresh"><a></a></div><img src="{{ Config::get('site.theme_path') }}/images/capcha.png">
                            </div>
                            <p class="warning">Неверный код!</p>
                        </div>
                        <div class="agreement">
                            <input type="checkbox" required name="agreement" form="log-in-form" checked>
                            <p class="listense">Я прочитал и принимаю <a href="#">пользовательское соглашение</a> и <a href="#">правила конфиденциальности</a></p>
                            <p class="warning">Вы забыли принять пользовательское соглашение</p>
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
                        <h3>Аккаунт <span>успешно создан</span></h3>
                        <p class="congratulation">
                            {{ strtr(trans("interface.tpl.registration_success"), [
                                '%link%' => isset($link) ? $link : '#',
                                '%nickname%' => isset($nickname) ? $nickname : '&nbsp;',
                            ]) }}
                        </p>
                        <p class="verification"> На ваш e-mail <a href="#">werewombat15@gmail.com</a> <nobr>отправлено письмо со ссылкой. Перейдите</nobr> <nobr>по ссылке, чтобы зарегистрировать аккаунт</nobr> и получить приятный бонус в игре.</p>
                    </div>
                </div>
                <div class="form-block">
                    <p class="download">Если вы еще не скачали пакет установки  игры, самое время сделать это сейчас.</p>
                    <div class="download-button"> <a>Скачать игру <span>(540MB)</span></a></div>
                </div>
            </div>
            <div class="js-form-error">Ошибка!</div>
        </div>
    </div>
</div>