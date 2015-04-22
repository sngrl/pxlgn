<?php
/**
 * С помощью конфигурационных файлов в папке dic можно производить тонкую настройку каждого конкретного словаря в отдельности:
 * - добавлять собственные поля к объектам DicVal
 * - добавлять пункты меню, фильтры, кнопки, поля, ссылки...
 * - добавлять кнопки в область "Действия" для каждой записи DicVal
 * - модифицировать выводимый текст в списках
 * - ...
 *
 * Для каждого словаря (Dic) создается отдельный файл, в котором описана его конфигурация.
 * Название файла должно соответствовать системному имени словаря.
 *
 * Все элементы массива должны являться функциями-замыканиями, внутри которых происходит возврат нужных данных.
 *
 * Для каждого словаря можно определить набор "постоянных" полей (fields) и полей для мультиязычных версий записи (fields_i18n).
 * Первые будут доступны для редактирования всегда, вторые - только если сайт имеет больше чем 1 язык.
 *
 * Каждое поле представлено в наборе именем на форме (ключ массива) и набором свойств (поля массива по ключу).
 * Обязательно должен быть определен тип поля (type) и заголовок (title).
 * Также можно задать следующие свойства:
 * - default - значение поля по-умолчанию
 * - others - набор дополнительных произвольных свойств элемента, таких как class, style, placeholder и т.д.
 * - handler - функция-замыкание, вызывается для обработки значения поля после получения ИЗ формы, перед записью в БД. Первым параметром передается значение поля, вторым - существующий объект DicVal, к которому относится данное поле
 * - value_modifier - функция-замыкание, вызывается для обработки значения поля после получения значения из БД, перед выводом В форму
 * - after_save_js - JS-код, который будет выполнен после сохранения страницы
 * - content - содержимое, которое будет выведено на экран, вместо генерации кода элемента формы
 * - label_class - css-класс родительского элемента
 *
 * Некоторые типы полей могут иметь свои собственные уникальные свойства, например: значения для выбора у поля select; accept для указания разрешенных форматов у поля типа file и т.д.
 *
 * [!] Вывод полей на форму происходит с помощью /app/lib/Helper.php -> Helper::formField();
 *
 * На данный момент доступны следующие поля:
 * - text
 * - textarea
 * - textarea_redactor (доп. JS)
 * - date (не требует доп. JS, работает для SmartAdmin из коробки, нужны handler и value_modifier для обработки)
 * - image (использует ExtForm::image() + доп. JS)
 * - gallery (использует ExtForm::gallery() + доп. JS, нужен handler для обработки)
 * - upload
 * - video
 * - select
 * - select-multiple
 * - checkbox
 * - checkboxes (замена select-multiple)
 * - hidden
 * - textline (если нет значения - текстовая строка для ввода (обычно ID), если есть - значение из view_text)
 * - custom
 * - [map] - с помощью custom помогает сделать карту для геокодинга (связывает адреса и координаты)
 *
 * Типы полей, запланированных к разработке:
 * - radio
 * - upload-group
 * - video-group
 *
 * [!] Для визуального разделения можно использовать следующий элемент массива: array('content' => '<hr/>'),
 *
 * @author Zelensky Alexander
 *
 */
return array(

    /**
     * FIELDS - задает для всех сущностей словаря набор дополнительных полей для редактирования.
     */
    'fields' => function ($dicval = NULL) {

        /**
         * Предзагружаем нужные словари с данными, по системному имени словаря, для дальнейшего использования.
         * Делается это одним SQL-запросом, для снижения нагрузки на сервер БД.
         */
        $dics_slugs = array(
            'product_type',
            'countries',
            'factory',
            'format',
            'surface',
            'scope',
        );
        $dics = Dic::whereIn('slug', $dics_slugs)->with('values')->get();
        $dics = Dic::modifyKeys($dics, 'slug');
        #Helper::tad($dics);
        $lists = Dic::makeLists($dics, 'values', 'name', 'id');
        $lists_ids = Dic::makeLists($dics, null, 'id', 'slug');
        #Helper::dd($lists);

        /**
         * Возвращаем набор полей
         */
        return array(

            'input_text' => array(
                'title' => 'Обычное однострочное поле ввода текста',
                'type' => 'text',
            ),

            'description' => array(
                'title' => 'Поле textarea',
                'type' => 'textarea',
                'others' => array(
                    'onkeyup' => trim(str_replace("\n", ' ', "
var c = 1;
var len = 0;
for (i=0; i<this.value.length; i++) {
    if ( this.value[i] == '\\n' ) {
        ++c;
        if ( c == 4 ) {
            len = i;
            break;
        }
    }
}
if (len > 0) {
    this.value = this.value.slice(0, len + 1);
}
")
                    ), ## ONLY 3 LINES AT TEXTAREA
            )),

            'content' => array(
                'title' => 'Визуальный текстовый редактор',
                'type' => 'textarea_redactor',
            ),

            'date_start' => array(
                'title' => 'Поле выбора даты',
                'type' => 'date',
                'others' => array(
                    'class' => 'text-center',
                    'style' => 'width: 221px',
                    'placeholder' => 'Нажмите для выбора'
                ),
                'handler' => function($value) {
                    return $value ? @date('Y-m-d', strtotime($value)) : $value;
                },
                'value_modifier' => function($value) {
                    return $value ? date('d.m.Y', strtotime($value)) : date('d.m.Y');
                },
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
                'handler' => function($array, $element) {
                    return ExtForm::process('gallery', array(
                        'module'  => 'DicValMeta',
                        'unit_id' => $element->id,
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
                        $value['module'] = 'DicVal';
                        $value['unit_id'] = $element->id;
                    }
                    return ExtForm::process('upload', $value);
                },
            ),

            'video' => array(
                'title' => 'Поле для вставки EMBED-кода видео + картинка для предпросмотра',
                'type' => 'video',
                'handler' => function($value, $element = false) {
                    if (@is_object($element) && @is_array($value)) {
                        $value['module'] = 'DicVal';
                        $value['unit_id'] = $element->id;
                    }
                    return ExtForm::process('video', $value);
                },
            ),

            'product_type_id' => array(
                'title' => 'Выпадающий список для выбора одного значения',
                'type' => 'select',
                'values' => array('Выберите..') + $lists['product_type'], ## Используется предзагруженный словарь
                'default' => Input::get('filter.fields.product_type') ?: null,
            ),

            'scope_id' => array(
                'title' => 'Выпадающий список со множественным выбором',
                'type' => 'select-multiple',
                'values' => $lists['scope'],
                'handler' => function($value, $element) {
                    /**
                     * Тут нужно проверить работу с новым форматом данных в DicValRel,
                     * привести в соответствие с методом из scope_id.
                     * Сделать это нужно при первой же необходимости использования select-multiple.
                     */
                    $value = (array)$value;
                    $value = array_flip($value);
                    foreach ($value as $v => $null)
                        $value[$v] = array('dicval_child_dic' => 'scope');
                    $element->related_dicvals()->sync($value);
                    return @count($value);
                },
                'value_modifier' => function($value, $element) {
                    $return = (is_object($element) && $element->id)
                        ? $element->related_dicvals()->get()->lists('id')
                        : $return = array()
                    ;
                    return $return;
                },
            ),

            'basic' => array(
                'no_label' => true,
                'title' => 'Чекбокс обычный',
                'type' => 'checkbox',
                'label_class' => 'normal_checkbox',
            ),

            'scope_id' => array(
                'title' => 'Группа чекбоксов',
                'type' => 'checkboxes',
                'columns' => 2, ## Количество колонок
                'values' => $lists['scope'],
                'handler' => function ($value, $element) {
                    $value = DicLib::formatDicValRel($value, 'scope_id', $element->dic_id, $lists_ids['scope']);
                    $element->related_dicvals()->sync($value);
                    return @count($value);
                },
                'value_modifier' => function ($value, $element) {
                    $return = (is_object($element) && $element->id)
                        ? $element->related_dicvals()->get()->lists('name', 'id')
                        : $return = array();
                    return $return;
                },
            ),
            'promise_id' => array(
                'title' => 'Обещание',
                'type' => 'textline',
                'view_text' => @$lists['promises'][$dicval->promise_id], ## Используется предзагруженный словарь
            ),

            ## КАРТА ДЛЯ ГЕОКОДИНГА
            'map' => array(
                'type' => 'custom',
                'content' => View::make('system.views.map_google_block', [
                    'element' => $dicval,

                    #'map_id' => 'map',
                    #'map_style' => 'height:300px;',
                ])->render(),
                'scripts' => View::make('system.views.map_google_script', [
                    'element' => $dicval,

                    #'map_id' => 'map',
                    #'map_type' => 'google.maps.MapTypeId.ROADMAP',
                    #'field_address' => 'address',
                    #'field_lat' => 'lat',
                    #'field_lng' => 'lng',
                    #'keyup_timer' => 1200,

                    'geo_prefix' => 'Россия, Ростов-на-Дону, ',
                    'default_lat' => '47.25221300',
                    'default_lng' => '39.69359700',
                    'default_zoom' => '11',
                ])->render(),
            ),

        );

    },

    /**
     * MENUS - дополнительные пункты верхнего меню, под названием словаря.
     */
    'menus' => function($dic, $dicval = NULL) {
        $menus = array();
        $menus[] = array('raw' => '<br/>');

        /**
         * Предзагружаем словари для дальнейшего использования, одним SQL-запросом
         */
        $dics_slugs = array(
            'product_type',
            'countries',
            'factory',
        );
        $dics = Dic::whereIn('slug', $dics_slugs)->with('values')->get();
        $dics = Dic::modifyKeys($dics, 'slug');
        $lists = Dic::makeLists($dics, 'values', 'name', 'id');
        #Helper::tad($lists);

        /**
         * Добавляем доп. элементы в меню, в данном случае: выпадающие поля для организации фильтрации записей по их свойствам
         */
        $menus[] = Helper::getDicValMenuDropdown('product_type_id', 'Все виды продукции', $lists['product_type'], $dic);
        $menus[] = Helper::getDicValMenuDropdown('country_id', 'Все страны', $lists['countries'], $dic);
        $menus[] = Helper::getDicValMenuDropdown('factory_id', 'Все фабрики', $lists['factory'], $dic);
        #$menus[] = Helper::getDicValMenuDropdown('format_id', 'Все форматы', $lists['format'], $dic);
        return $menus;
    },


    /**
     * ACTIONS - дополнительные элементы в столбце "Действия", на странице списка записей словаря.
     * Внутри данной функции не должно производиться запросов к БД!
     * Все запросы следует выносить в хуки (описание хуков ниже).
     */
    'actions' => function($dic, $dicval) {

        /**
         * Получаем данные, которые были созданы с помощью хука before_index_view (описание ниже).
         */
        $dics = Config::get('temp.index_dics');
        $dic_products = $dics['products'];
        $dic_interiors = $dics['interiors'];
        $counts = Config::get('temp.index_counts');

        /**
         * Возвращаем доп. элементы в столбец "Действия": кнопки со ссылками и счетчиками, индивидуальны для каждой записи
         */
        return '
            <span class="block_ margin-bottom-5_">
                <a href="' . URL::route('entity.index', array('products', 'filter[fields][collection_id]' => $dicval->id)) . '" class="btn btn-default">
                    Продукция (' . @(int)$counts[$dicval->id][$dic_products->id]. ')
                </a>
                <a href="' . URL::route('entity.index', array('interiors', 'filter[fields][collection_id]' => $dicval->id)) . '" class="btn btn-default">
                    Интерьеры (' . @(int)$counts[$dicval->id][$dic_interiors->id] . ')
                </a>
            </span>
        ';
    },


    /**
     * HOOKS - набор функций-замыканий, которые вызываются в некоторых местах кода модуля словарей, для выполнения нужных действий.
     */
    'hooks' => array(

        /**
         * Вызывается первым из всех хуков в каждом действенном методе модуля
         */
        'before_all' => function ($dic) {
        },

        /**
         * Вызывается в самом начале метода index, после хука before_all
         */
        'before_index' => function ($dic) {
        },

        /**
         * Вызывается в методе index, перед выводом данных в представление (вьюшку).
         * На этом этапе уже известны все элементы, которые будут отображены на странице.
         */
        'before_index_view' => function ($dic, $dicvals) {
            /**
             * Предзагружаем нужные словари
             */
            $dics_slugs = array(
                'products',
                'interiors',
            );
            $dics = Dic::whereIn('slug', $dics_slugs)->get();
            $dics = Dic::modifyKeys($dics, 'slug');
            #Helper::tad($dics);
            Config::set('temp.index_dics', $dics);

            /**
             * Создаем списки из полученных данных
             */
            $dic_ids = Dic::makeLists($dics, false, 'id');
            #Helper::d($dic_ids);
            $dicval_ids = Dic::makeLists($dicvals, false, 'id');
            #Helper::d($dicval_ids);

            /**
             * Получаем количество необходимых нам данных, одним SQL-запросом.
             * Сохраняем данные в конфиг - для дальнейшего использования в функции-замыкании actions (см. выше).
             */
            $counts = array();
            if (count($dic_ids) && count($dicval_ids))
                $counts = DicVal::counts_by_fields($dic_ids, array('collection_id' => $dicval_ids));
            #Helper::dd($counts);
            Config::set('temp.index_counts', $counts);
        },

        /**
         * Вызывается в самом начале методов create и edit
         */
        'before_create_edit' => function ($dic) {
        },

        /**
         * Вызывается в начале метода create, сразу после хука before_create_edit
         */
        'before_create' => function ($dic) {
        },

        /**
         * Вызывается в начале метода edit, сразу после хука before_create_edit
         */
        'before_edit' => function ($dic, $dicval) {
        },

        /**
         * Вызывается в самом начале методов store и update
         */
        'before_store_update' => function ($dic) {
        },

        /**
         * Вызывается в начале метода postStore, сразу после хука before_store_update
         */
        'before_store' => function ($dic) {
        },

        /**
         * Вызывается в метода postStore, после создания записи
         */
        'after_store' => function ($dic, $dicval) {
        },

        /**
         * Вызывается в начале метода postStore, сразу после хука before_store_update
         */
        'before_update' => function ($dic, $dicval) {
        },

        /**
         * Вызывается в метода postStore, после обновления записи
         */
        'after_update' => function ($dic, $dicval) {
        },

        /**
         * Вызывается в методе postStore, после создания или обновления записи
         */
        'after_store_update' => function ($dic, $dicval) {
        },

        /**
         * Вызывается в начале метода destroy
         */
        'before_destroy' => function ($dic, $dicval) {
        },

        /**
         * Вызывается в конце метода destroy, после удаления записи словаря
         */
        'after_destroy' => function ($dic, $dicval) {
        },

        /**
         * Вызывается после изменения порядка сортировки
         */
        'after_order' => function ($dic) {
        },

        /**
         * Вызывается после создания, обновления или удаления записи
         */
        'after_store_update_destroy' => function ($dic, $dicval) {
        },

        /**
         * Вызывается после создания, обновления, удаления записи, изменения порядка сортировки
         */
        'after_store_update_destroy_order' => function ($dic = NULL, $dicval = NULL) {
        },
    ),

    /*
    'first_line_modifier' => function($line, $dic, $dicval) {
        $dicval->extract(true);
        return '<a href="' . URL::route('feedback.view', $dicval->id) . '" target="_blank">' . $line . '</a> <i class="fa fa-arrow-right"></i>';
    },
    #*/

    /*
    'second_line_modifier' => function($line, $dic, $dicval) {
        return 'От: ' . $dicval->message_name . ($dicval->message_email ? ' &lt;<a href="mailto:' . $dicval->message_email . '">' . $dicval->message_email . '</a>>' : '') . ', ' . $dicval->created_at->format('d.m.Y в H:i');
    },
    #*/

    /**
     * Вкл./выкл. модуль SEO для данного словаря
     */
    'seo' => false,
    #'seo' => ['title', 'description', 'keywords', 'h1'],

    /**
     * Поддержка версионности. В данном случае система будет хранить 3 последние резервные копии
     */
    'versions' => 3,

    /**
     * Перезаписываем права групп для работы с данным словарем.
     * В данном случае пользователям из группы "Модераторы" запрещено добавлять новые записи.
     */
    'group_actions' => array(
        'moderator' => function() {
            return array(
                'dicval_create' => 0,
            );
        },

    ),

    /**
     * Максимальное количество элементов в списке.
     * Если достигнуто - кнопка "Добавить" будет скрыта.
     */
    'max_elements' => 1,

    /**
     * Минимально допустимое количество элементов в списке.
     * Если кол-во элементов в списке <= этого количества - все кнопки "Удалить" для всех элементов будут скрыты.
     */
    'min_elements' => 1,

    /**
     * JavaScript код, который будет внедрен на все страницы DicVal
     */
    'javascript' => <<<JS
        /* some JS code */
JS
,

    /**
     * Собственные правила валидации данной формы.
     * Не забыть про поле name, которое по умолчанию должно быть обязательным!
     */
    'custom_validation' => <<<JS
    var validation_rules = {
		'name': { required: true },
		'fields[pdf_document][file]': { accept: "pdf", filesize: 10485760 },
	};
	var validation_messages = {
		'name': { required: "Укажите название" },
		'fields[pdf_document][file]': { accept: "Только файлы PDF", filesize: "Максимальный размер файла - 10 Mb" },
	};
JS
,

    /**
     * Название поля Системное имя
     * По умолчанию: Системное имя (необязательно)
     */
    'slug_label' => 'Системное имя (необязательно)',

    /**
     * Подсказка для поля Системное имя
     * По умолчанию: Только символы англ. алфавита, знаки _ и -, цифры
     */
    'slug_note' => <<<HTML
        Только символы англ. алфавита, знаки _ и -, цифры
HTML
,

    /**
     * Подсказка для поля Название
     * По умолчанию пусто
     */
    'name_note' => '',

    /**
     * Скрыть Название с формы
     * По умолчанию название отображается
     */
    'hide_name' => 1,

    /**
     * Если установлено в TRUE - перед добавлением/сохранением записи поле "Системное имя"
     * будет проверяться на уникальность в пределах своего словаря.
     */
    'unique_slug' => 1,

);