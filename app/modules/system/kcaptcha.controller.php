<?php

class KcaptchaController extends BaseController {

    public static $name = 'kcaptcha';
    public static $group = 'system';
    private $dir = 'lib/kcaptcha';

    /****************************************************************************/

    ## Routing rules of module
    public static function returnRoutes($prefix = null) {

        Route::any('captcha.html', array('as' => 'captcha_form', 'uses' => __CLASS__.'@getKcaptchaForm'));
        Route::any('captcha.png', array('as' => 'captcha_image', 'uses' => __CLASS__.'@getKcaptchaImage'));
        Route::post('check_captcha.json', array('as' => 'captcha_check', 'uses' => __CLASS__.'@checkCaptchaImage'));
    }

    ## Actions of module (for distribution rights of users)
    public static function returnActions() {
    }

    ## Info about module (now only for admin dashboard & menu)
    public static function returnInfo() {
    }


    /****************************************************************************/


	public function __construct(){

        /*
        $this->module = array(
            'name' => self::$name,
            'group' => self::$group,
            'tpl' => static::returnTpl('admin/kcaptcha'),
            'gtpl' => static::returnTpl(),

            'class' => __CLASS__,
        );
        View::share('module', $this->module);
        */
	}


    public function getKcaptchaForm() {

        session_start();
        ?>
        <form action="" method="post">
            <p>Enter text shown below:</p>
            <p><img src="<?php echo URL::route('captcha_image', [session_name() => session_id()]) ?>"></p>
            <p><input type="text" name="keystring"></p>
            <p><input type="submit" value="Check"></p>
        </form>
        <?php
        if (count($_POST) > 0) {
            if (isset($_SESSION['captcha_keystring']) && $_SESSION['captcha_keystring'] === $_POST['keystring']) {
                echo "Correct";
            } else {
                echo "Wrong";
            }
        }
        unset($_SESSION['captcha_keystring']);
    }


    public function getKcaptchaImage() {

        error_reporting (E_ALL);
        session_start();
        $captcha = new KCAPTCHA();
        if($_REQUEST[session_name()]){
            $_SESSION['captcha_keystring'] = $captcha->getKeyString();
        }
    }


    public function checkCaptchaImage() {

        session_start();
        $json_response = array('status' => FALSE, 'responseText' => '');

        if (isset($_SESSION['captcha_keystring']) && $_SESSION['captcha_keystring'] === $_POST['keystring']) {
            $json_response['status'] = TRUE;
        }

        $clear = Input::get('clear') !== NULL ? Input::get('clear') : TRUE;
        if ($clear) {
            unset($_SESSION['captcha_keystring']);
        }

        return Response::json($json_response, 200);
    }


    public static function check($keystring, $clear = TRUE) {

        $return = FALSE;
        if (isset($_SESSION['captcha_keystring']) && $_SESSION['captcha_keystring'] === $keystring) {
            $return = TRUE;
        }
        if ($clear) {
            unset($_SESSION['captcha_keystring']);
        }
        return $return;
    }
}


class Captcha extends KcaptchaController {
    ##
}