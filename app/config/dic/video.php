<?php

return array(

    'fields' => function() {

        return array(
            'image' => array(
                'title' => 'Изображение для предпросмотра',
                'type' => 'image',
            ),
            'emdeb' => array(
                'title' => 'Embed-код',
                'type' => 'textarea',
            ),
        );
    },

    'seo' => ['title', 'description', 'keywords'],
);