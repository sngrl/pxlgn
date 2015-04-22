<?php

class ApplicationController extends BaseController {

    public static $name = 'application';
    public static $group = 'application';

    /****************************************************************************/

    ## Routing rules of module
    public static function returnRoutes($prefix = null) {

        Route::group(array(), function() {

            Route::any('/ajax/request-call', array('as' => 'ajax.request-call', 'uses' => __CLASS__.'@postRequestCall'));
            Route::any('/ajax/send-message', array('as' => 'ajax.send-message', 'uses' => __CLASS__.'@postSendMessage'));
            Route::any('/ajax/architects-competition', array('as' => 'ajax.architects-competition', 'uses' => __CLASS__.'@postArchitectsCompetition'));
        });
    }


    /****************************************************************************/


	public function __construct(){
        #
	}


    public function postRequestCall() {

        #
    }


    public function postSendMessage() {

        #
    }


    public function postArchitectsCompetition() {

        #
    }

}