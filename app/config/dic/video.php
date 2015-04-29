<?php

return array(

    'fields' => function() {

        return array(
            'image' => array(
                'title' => 'Изображение для предпросмотра',
                'type' => 'image',
            ),
            'embed' => array(
                'title' => 'Embed-код',
                'type' => 'textarea',
            ),
        );
    },

    'seo' => ['title', 'description', 'keywords'],
);