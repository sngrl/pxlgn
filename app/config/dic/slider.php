<?php

return array(

    'fields' => function() {

        return array(
            'image' => array(
                'title' => 'Изображение',
                'type' => 'image',
            ),
            'link' => array(
                'title' => 'Ссылка',
                'type' => 'text',
            ),

            'embed' => array(
                'title' => 'ИЛИ embed-код видео',
                'type' => 'textarea',
            ),
        );
    },

);