<?php

class ApplicationController extends BaseController {

    public static $name = 'application';
    public static $group = 'application';

    /****************************************************************************/

    ## Routing rules of module
    public static function returnRoutes($prefix = null) {

        Route::group(array('prefix' => '{lang}'), function() {

            Route::get('/registration-ok', array('as' => 'app.registration-ok', 'uses' => __CLASS__.'@getRegistrationOk'));

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


    public function postSendMessage() {

        #
    }


    public function postArchitectsCompetition() {

        #
    }

}