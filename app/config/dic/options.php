<?php

return array(

    'fields' => function () {

        return array(
            'desc' => array(
                'title' => 'Описание параметра',
                'type' => 'textarea',
            ),
        );

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
            $dicvals->load('textfields');
            #Helper::tad($dicvals);
            $dicvals = DicLib::extracts($dicvals, null, true, true);
        },
    ),

    'second_line_modifier' => function($line, $dic, $dicval) {
        #$dicval->extract(true);
        return $dicval->slug . (isset($dicval->desc) && $dicval->desc ? ' &mdash; <i>' . $dicval->desc . '</i>' : '');
    },

    'slug_label' => 'Системное имя параметра',

);
