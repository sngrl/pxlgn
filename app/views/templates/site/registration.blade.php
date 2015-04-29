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

    <div class="inner-content">
        <div class="registration-page-block">
            <h3 class="play-free">Играйте бесплатно!</h3>
            <div class="congratulation-field">
                <div class="button-download-big"><a><span>(590 MB)</span></a></div>
            </div>
        </div>
        <div class="registration-page-block">
            <h3>Войдите с помощью соцсетей:</h3>
            <div class="socials-big-holder"><a class="fb"></a><a class="vk"></a><a class="tw"></a></div>
        </div>
        <div class="registration-page-block">
            <h3>или создайте новый аккаунт:</h3>
            <form id="log-in-form" class="page">
                <div class="main-fields">
                    <div class="e-mail">
                        <input id="e-mail" type="text" required name="email" form="log-in-form">
                        <label for="e-mail">E-mail</label>
                        <p class="warning">E-mail уже занят!</p>
                        <p class="description">Ваш действующий e-mail, будет использоваться для входа в игру</p>
                    </div>
                    <div class="pass">
                        <input type="password" required name="password" form="log-in-form">
                        <label for="password">Пароль</label>
                        <button class="spice"></button>
                        <p class="warning">Слишком простой пароль</p>
                        <p class="description">От 6 до 20 символов</p>
                    </div>
                    <div class="nickname">
                        <input id="nickname" type="text" required name="password" form="log-in-form">
                        <label for="nickname">Имя в игре</label>
                        <p class="warning">Неподходящее имя</p>
                    </div>
                </div>
                <div class="capcha">
                    <input id="capcha" type="text" required name="capcha" form="log-in-form" class="capcha-field">
                    <label for="capcha">Введите код</label>
                    <div class="capcha-image">
                        <div class="refresh"><a></a></div><img src="./images/capcha.png">
                    </div>
                    <p class="warning">Неверный код!</p>
                </div>
                <div class="confirmation">
                    <div class="stylish">
                        <input type="checkbox" required name="submit" form="log-in-form">
                    </div>
                    <p>Я прочитал и принимаю <a href="#">пользовательское соглашение</a> и <a href="#">правила конфиденциальности</a></p>
                </div>
                <div class="log-in-button-3">
                    <button type="submit"></button>
                </div>
            </form>
        </div>
    </div>


@stop


@section('scripts')
@stop
