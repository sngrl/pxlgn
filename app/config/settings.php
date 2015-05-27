<?php

return array(

    'sections' => function() {

        $settings = [];

        if (TRUE)
            $settings['main'] = [
                'title' => 'Основные',
                #'description' => 'Здесь собраны основные настройки сайта',
                'options' => array(
                    /*
                    'feedback_address' => array(
                        'title' => 'Адрес почты для сообщений обратной связи',
                        'type' => 'text',
                    ),
                    'feedback_from_email' => array(
                        'title' => 'Адрес почты, от имени которого будут отправляться сообщения',
                        'type' => 'text',
                    ),
                    'feedback_from_name' => array(
                        'title' => 'Имя пользователя, от которого будут отправляться сообщения',
                        'type' => 'text',
                    ),
                    */
                    [
                        'group_title' => 'Ссылки',
                        'style' => 'margin: 0 0 5px 0',
                    ],

                    'all_news_link' => array(
                        'title' => 'Ссылка на полный список новостей на форуме',
                        'type' => 'text',
                        'second_note' => '%locale% - заменится на языковую метку (ru/en) Например: /%locale%/forum/',
                    ),
                    'download_game_link' => array(
                        'title' => 'Ссылка на скачивание игры',
                        'type' => 'text',
                    ),
                    'user_agreement_link' => array(
                        'title' => 'Ссылка на пользовательское соглашение',
                        'type' => 'text',
                    ),
                    'mobile_link' => array(
                        'title' => 'Ссылка на мобильную версию сайта',
                        'type' => 'text',
                    ),

                    ['group_title' => 'Отображение'],

                    'show_play_button' => array(
                        'no_label' => true,
                        'title' => 'Показывать кнопку "Играть бесплатно"',
                        'type' => 'checkbox',
                        'label_class' => 'normal_checkbox',
                    ),
                    'show_registration_button' => array(
                        'no_label' => true,
                        'title' => 'Показывать в сайдбаре кнопку "Регистрация"',
                        'type' => 'checkbox',
                        'label_class' => 'normal_checkbox',
                    ),
                    'show_social_on_registration' => array(
                        'no_label' => true,
                        'title' => 'Показывать блок соц. сетей на форме регистрации',
                        'type' => 'checkbox',
                        'label_class' => 'normal_checkbox',
                    ),

                    ['group_title' => 'Тайминги'],

                    'mainpage_slider_timeout' => array(
                        'title' => 'Кол-во секунд для смены слайдера на главной странице',
                        'type' => 'text',
                    ),
                    'sidebar_screenshot_timeout' => array(
                        'title' => 'Кол-во секунд для смены скриншотов в сайдбаре',
                        'type' => 'text',
                    ),
                    'sidebar_video_timeout' => array(
                        'title' => 'Кол-во секунд для смены видео в сайдбаре',
                        'type' => 'text',
                    ),
                    'db_remember_timeout' => array(
                        'title' => 'Кол-во минут, на которое кешировать запросы к БД',
                        'type' => 'text',
                    ),

                    ['group_title' => 'API'],

                    'api_url' => array(
                        'title' => 'API URL',
                        'type' => 'text',
                    ),
                    'api_key' => array(
                        'title' => 'API Key',
                        'type' => 'text',
                    ),

                ),
            ];

        if (Allow::action('catalog', 'catalog_allow', true, false))
            $settings['catalog'] = [
                'title' => 'Магазин',
                'options' => array(
                    'allow_products_order' => array(
                        'no_label' => true,
                        'title' => 'Разрешить сортировку всех товаров (не рекомендуется)',
                        'type' => 'checkbox',
                        'label_class' => 'normal_checkbox',
                    ),
                    'disable_attributes_for_products' => array(
                        'no_label' => true,
                        'title' => 'Отключить функционал работы с атрибутами для товаров',
                        'type' => 'checkbox',
                        'label_class' => 'normal_checkbox',
                    ),
                    'disable_attributes_for_categories' => array(
                        'no_label' => true,
                        'title' => 'Отключить функционал работы с атрибутами для категорий',
                        'type' => 'checkbox',
                        'label_class' => 'normal_checkbox',
                    ),
                ),
            ];

        return $settings;
    },

);

##
## ПРОТЕСТИРОВАННЫЕ ОПЦИИ
##
/*
                    'sitename' => array(
                        'title' => 'Название сайта',
                        'type' => 'text',
                    ),
                    'disabled' => array(
                        'no_label' => true,
                        'title' => 'Сайт отключен',
                        'type' => 'checkbox',
                        'label_class' => 'normal_checkbox',
                    ),
                    'description' => array(
                        'title' => 'Описание сайта',
                        'type' => 'textarea',
                    ),
                    'content' => array(
                        'title' => 'Визуальный текстовый редактор',
                        'type' => 'textarea_redactor',
                    ),
                    'photo' => array(
                        'title' => 'Поле для загрузки изображения',
                        'type' => 'image',
                        'params' => array(
                            'maxFilesize' => 1, // MB
                            #'acceptedFiles' => 'image/*',
                            #'maxFiles' => 2,
                        ),
                    ),
                    'gallery' => array(
                        'title' => 'Галерея изображений',
                        'type' => 'gallery',
                        'params' => array(
                            'maxfilesize' => 1, // MB
                            #'acceptedfiles' => 'image/*',
                        ),
                        'handler' => function($array, $element = false) {
                            return ExtForm::process('gallery', array(
                                #'module'  => 'dicval_meta',
                                #'unit_id' => $element->id,
                                'gallery' => $array,
                                'single'  => true,
                            ));
                        }
                    ),
                    'link_to_file' => array(
                        'title' => 'Поле для загрузки файла',
                        'type' => 'upload',
                        'accept' => '*', # .exe,image/*,video/*,audio/*
                        'label_class' => 'input-file',
                        'handler' => function($value, $element = false) {
                            if (@is_object($element) && @is_array($value)) {
                                $value['module'] = 'dicval';
                                $value['unit_id'] = $element->id;
                            }
                            return ExtForm::process('upload', $value);
                        },
                    ),
                    'theme' => array(
                        'title' => 'Тема оформления',
                        'type' => 'select',
                        'values' => ['Выберите..'] + ['Темная' => 'Темная', 'Светлая' => 'Светлая', 'Красная' => 'Красная'], ## Используется предзагруженный словарь
                    ),
*/