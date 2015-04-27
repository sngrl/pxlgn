<?
/**
 * TEMPLATE_IS_NOT_SETTABLE
 */
?>

<div class="footer">
    <div class="footer-block">
        <h3 class="orange">Pixel Gun 3D</h3>
        <ul>
            <li><a href="#">Скачать игру</a></li>
            <li><a href="#">Регистрация</a></li>
            <li><a href="#">Об игре</a></li>
            <li><a href="/faq.html">F.A.Q.</a></li>
            <li><a href="#">Wiki</a></li>
        </ul>
    </div>
    <div class="footer-block">
        <h3 class="yellow">Сообщество</h3>
        <ul>
            <li><a href="#" class="forum">Форум</a></li>
            <li><a href="#" class="vk">Вконтакте</a></li>
            <li><a href="#" class="fb">Facebook</a></li>
            <li><a href="#" class="tw">Twitter</a></li>
        </ul>
    </div>
    <div class="footer-block">
        <h3 class="green">О нас</h3>
        <ul>
            <li><a href="#">Сайт компании</a></li>
            <li><a href="#">Вакансии</a></li>
        </ul>
    </div>
    <div class="footer-block help">
        <h3 class="blue">Поддержка</h3>
        <ul>
            <li><a href="#">Центр поддержки</a></li>
            <li><a href="#">Пользовательское соглашение</a></li>
            <li><a href="#">Политика конфиденциальности</a></li>
        </ul>
    </div>
    <div class="footer-copyright"><a href="#"><img src="{{ Config::get('site.theme_path') }}/images/logo-copyright.png"></a>
        <p>Copyright 2012-2015 LLC Rilisoft ©</p>
        <p>Все права защищены.</p>
    </div>
</div>


<div class="login-form-background">
    <div class="login-hack"></div>
    <div class="login-form">
        <div class="form-head">
            <h3>Регистрация</h3>
            <div class="button-wrapper">
                <button class="close-button"></button>
            </div>
        </div>
        <div class="form-body">
            <div class="form-fade">
                <div class="form-block no-gradient">
                    <form id="log-in-form">
                        <div class="main-fields">
                            <p class="info">E-mail</p>
                            <div class="e-mail">
                                <input type="text" required name="email" form="log-in-form">
                                <p class="warning">E-mail уже занят!</p>
                            </div>
                            <p class="info">Пароль</p>
                            <div class="pass">
                                <input type="password" required name="password" form="log-in-form">
                                <button class="spice"></button>
                                <p class="warning">Слишком простой пароль</p>
                            </div>
                        </div>
                        <p class="info">Введите код</p>
                        <div class="capcha">
                            <input type="text" required name="capcha" form="log-in-form" class="capcha-field">
                            <div class="capcha-image">
                                <div class="refresh"><a></a></div><img src="{{ Config::get('site.theme_path') }}/images/capcha.png">
                            </div>
                            <p class="warning">Неверный код!</p>
                        </div>
                        <div class="log-in-button-2">
                            <button type="submit"></button>
                        </div>
                    </form>
                </div>
                <div class="form-block">
                    <h3 class="form-block-head">Войти с помощью соцсетей:</h3>
                    <div class="social-panel"><a href="#" class="vk"></a><a href="#" class="fb"></a><a href="#" class="tw"></a></div>
                </div>
            </div>
            <div class="success-fade">
                <div class="form-block">
                    <div class="success">
                        <h3>Аккаунт <span>успешно создан</span></h3>
                        <p class="congratulation">Поздравляем! Вы зарегистрированы в игре  под именем <a href="#">Werewombat</a>.</p>
                        <p class="verification"> На ваш e-mail <a href="#">werewombat15@gmail.com</a> <nobr>отправлено письмо со ссылкой. Перейдите</nobr> <nobr>по ссылке, чтобы зарегистрировать аккаунт</nobr> и получить приятный бонус в игре.</p>
                    </div>
                </div>
                <div class="form-block">
                    <p class="download">Если вы еще не скачали пакет установки  игры, самое время сделать это сейчас.</p>
                    <div class="download-button"> <a>Скачать игру <span>(540MB)</span></a></div>
                </div>
            </div>
        </div>
    </div>
</div>