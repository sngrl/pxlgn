<?php

class ApplicationController extends BaseController {

    public static $name = 'application';
    public static $group = 'application';

    /****************************************************************************/

    ## Routing rules of module
    public static function returnRoutes($prefix = null) {

        Route::group(array('prefix' => '{lang}'), function() {

            Route::get('/registration-ok', array('as' => 'app.registration-ok', 'uses' => __CLASS__.'@getRegistrationOk'));

            Route::any('/api', array('as' => 'app.api', 'uses' => __CLASS__.'@getApi'));

            /*
            Route::any('/ajax/request-call', array('as' => 'ajax.request-call', 'uses' => __CLASS__.'@postRequestCall'));
            Route::any('/ajax/send-message', array('as' => 'ajax.send-message', 'uses' => __CLASS__.'@postSendMessage'));
            Route::any('/ajax/architects-competition', array('as' => 'ajax.architects-competition', 'uses' => __CLASS__.'@postArchitectsCompetition'));
            */
        });
    }


    /****************************************************************************/


	public function __construct(){
        #
	}


    public function getRegistrationOk() {

        $link = "#";
        $nickname = "Werewombat";
        return View::make(Helper::layout('registration-ok'), compact('asd'));
    }


    public function getApi() {

        session_start();
        $json_response = ['status' => FALSE];

        ## Входные данные
        $captcha_key = Input::get('keycode');
        $input = Input::only(['api', 'email', 'pass']);
        #Helper::tad($input);

        if (!@$input['api'])
            $input['api'] = 'register';

        ## Проверка капчи
        $valid = CaptchaController::checkCaptcha($captcha_key, FALSE);
        if (!$captcha_key || !$valid) {
            $json_response['text'] = trans("interface.api.bad_captcha");
            $json_response['reason'] = 'bad_captcha';
            $json_response['place'] = 'captcha';

            if (Config::get('app.debug'))
                $json_response['session'] = @print_r($_SESSION, 1);

            return Response::json($json_response);
        }

        ## Берем из настроек для апи: урл, ключ
        $api_url = Config::get('app.settings.main.api_url');
        $input['api_key'] = Config::get('app.settings.main.api_key');

        #Helper::d($api_url);
        #Helper::ta($input);

        if (!$api_url || !$input['api_key']) {
            $json_response['text'] = 'Bad request parameters';
            $json_response['reason'] = 'result_2';
            $json_response['place'] = 'global';

            if (Config::get('app.debug'))
                $json_response['debug'] = 'api_url = ' . $api_url . ' | api_key = ' . $input['api_key'];

            return Response::json($json_response);
        }

        ## Отправка запроса на сервер Pixel Gun 3D
        $result = curl_get_content($api_url, $input);
        #Helper::ta($result);
        $result = @json_decode($result, 1)['result'] ?: NULL;
        #Helper::dd($result);

        /*
        1 => OK,
        2 => Не валидный запрос,
        3 => Не валидный email,
        4 => email занят,
        7 => Пара логин/пароль не найдена,
        */

        $json_response['code'] = $result;
        if ($result == '1') {
            $json_response['status'] = TRUE;
            CaptchaController::clearCaptcha();
        }
        $json_response['text'] = trans("interface.api.result." . $result);
        $json_response['reason'] = 'result_' . $result;
        return Response::json($json_response);
    }

}

function curl_get_content($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}