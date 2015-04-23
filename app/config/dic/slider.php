<?php

return array(

    'fields' => function() {

        return array(
            'image' => array(
                'title' => 'Изображение',
                'type' => 'image',
            ),

            'embed' => array(
                'title' => 'ИЛИ embed-код видео',
                'type' => 'textarea',
            ),

            'link' => array(
                'title' => 'Ссылка',
                'type' => 'text',
            ),
        );
    },

);