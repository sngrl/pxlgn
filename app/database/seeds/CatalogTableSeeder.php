<?php

class CatalogTableSeeder extends Seeder{

	public function run(){

        /**
         * КАТЕГОРИИ
         */
        CatalogCategory::create(array(
            'id' => 1,
            'active' => 1,
            'slug' => 'bicycles',
            'lft' => 1,
            'rgt' => 8,
        ));
        CatalogCategoryMeta::create(array(
            'category_id' => 1,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Велосипеды',
        ));
        CatalogCategoryMeta::create(array(
            'category_id' => 1,
            'language' => 'en',
            'active' => 1,
            'name' => 'Bicycles',
        ));

        CatalogCategory::create(array(
            'id' => 2,
            'active' => 1,
            'slug' => 'mountain',
            'lft' => 2,
            'rgt' => 3,
        ));
        CatalogCategoryMeta::create(array(
            'category_id' => 2,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Горные',
        ));

        CatalogCategory::create(array(
            'id' => 3,
            'active' => 1,
            'slug' => 'road',
            'lft' => 4,
            'rgt' => 5,
        ));
        CatalogCategoryMeta::create(array(
            'category_id' => 3,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Шоссейные',
        ));

        CatalogCategory::create(array(
            'id' => 4,
            'active' => 1,
            'slug' => 'city',
            'lft' => 6,
            'rgt' => 7,
        ));
        CatalogCategoryMeta::create(array(
            'category_id' => 4,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Городские',
        ));

        CatalogCategory::create(array(
            'id' => 5,
            'active' => 1,
            'slug' => 'pc',
            'lft' => 9,
            'rgt' => 10,
        ));
        CatalogCategoryMeta::create(array(
            'category_id' => 5,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Компьютеры',
        ));


        /**
         * ТОВАРЫ
         */
        CatalogProduct::create(array(
            'id' => 1,
            'active' => 1,
            'category_id' => 1,
            'slug' => 'normal_bike',
            'article' => 'sku0001',
            'amount' => '5',
            'lft' => 1,
            'rgt' => 2,
        ));
        CatalogProductMeta::create(array(
            'product_id' => 1,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Обычный велосипед',
            'description' => 'Какое-то описание продукта...',
            'price' => '25000',
        ));

        CatalogProduct::create(array(
            'id' => 2,
            'active' => 1,
            'category_id' => 1,
            'slug' => 'normal_bike_2',
            'article' => 'sku0002',
            'amount' => '5',
            'lft' => 3,
            'rgt' => 4,
        ));
        CatalogProductMeta::create(array(
            'product_id' => 2,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Обычный велосипед 2',
            'description' => 'Какое-то описание продукта...',
            'price' => '25000',
        ));

        CatalogProduct::create(array(
            'id' => 3,
            'active' => 1,
            'category_id' => 1,
            'slug' => 'normal_bike_3',
            'article' => 'sku0003',
            'amount' => '5',
            'lft' => 5,
            'rgt' => 6,
        ));
        CatalogProductMeta::create(array(
            'product_id' => 3,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Обычный велосипед 3',
            'description' => 'Какое-то описание продукта...',
            'price' => '25000',
        ));


        /**
         * АТРИБУТЫ
         */
        CatalogAttributeGroup::create(array(
            'id' => 1,
            'category_id' => 1,
            'active' => 1,
            'slug' => 'default',
            'lft' => 1,
            'rgt' => 2,
        ));
        CatalogAttributeGroupMeta::create(array(
            'attributes_group_id' => 1,
            'language' => 'ru',
            'active' => 1,
            'name' => 'По умолчанию',
        ));

        CatalogAttribute::create(array(
            'id' => 1,
            'active' => 1,
            'slug' => 'wheel_radius',
            'attributes_group_id' => 1,
            'type' => 'text',
            'lft' => 1,
            'rgt' => 2,
        ));
        CatalogAttributeMeta::create(array(
            'attribute_id' => 1,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Радиус колеса',
        ));

        CatalogAttribute::create(array(
            'id' => 2,
            'active' => 1,
            'slug' => 'material',
            'attributes_group_id' => 1,
            'type' => 'textarea',
            'lft' => 3,
            'rgt' => 4,
        ));
        CatalogAttributeMeta::create(array(
            'attribute_id' => 2,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Материал рамы',
        ));


        CatalogAttributeGroup::create(array(
            'id' => 2,
            'category_id' => 1,
            'active' => 1,
            'slug' => 'additional',
            'lft' => 3,
            'rgt' => 4,
        ));
        CatalogAttributeGroupMeta::create(array(
            'attributes_group_id' => 2,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Дополнительно',
        ));

        CatalogAttribute::create(array(
            'id' => 3,
            'active' => 1,
            'slug' => 'flashlight',
            'attributes_group_id' => 2,
            #'type' => 'wysiwyg',
            'type' => 'checkbox',
            'lft' => 5,
            'rgt' => 6,
        ));
        CatalogAttributeMeta::create(array(
            'attribute_id' => 3,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Наличие фары освещения',
        ));

        CatalogAttribute::create(array(
            'id' => 4,
            'active' => 1,
            'slug' => 'breaks',
            'attributes_group_id' => 2,
            'type' => 'select',
            'settings' => '{"selectable":1}',
            'lft' => 7,
            'rgt' => 8,
        ));
        CatalogAttributeMeta::create(array(
            'attribute_id' => 4,
            'language' => 'ru',
            'active' => 1,
            'name' => 'Тормоза',
            'settings' => '{"values":"\u0414\u0438\u0441\u043a\u043e\u0432\u044b\u0435\n\u041a\u043e\u043b\u043e\u0434\u043e\u0447\u043d\u044b\u0435"}',
        ));



        /**
         * ЗНАЧЕНИЯ АТРИБУТОВ для ТОВАРА
         */
        CatalogAttributeValue::create(array(
            'product_id' => 1,
            'attribute_id' => 1,
            'language' => 'ru',
            'value' => 'R22',
        ));
        CatalogAttributeValue::create(array(
            'product_id' => 1,
            'attribute_id' => 2,
            'language' => 'ru',
            'value' => '',
            'settings' => '{"value":"\u041a\u0430\u0440\u0431\u043e\u043d\/\u0430\u043b\u044e\u043c\u0438\u043d\u0438\u0439"}',
        ));
        CatalogAttributeValue::create(array(
            'product_id' => 1,
            'attribute_id' => 3,
            'language' => 'ru',
            'value' => '1',
        ));
        CatalogAttributeValue::create(array(
            'product_id' => 1,
            'attribute_id' => 4,
            'language' => 'ru',
            'value' => 'Дисковые',
        ));



        /**
         * СТАТУСЫ ЗАКАЗОВ
         */
        CatalogOrderStatus::create(array(
            'id' => 1,
            'sort_order' => 1,
        ));
        CatalogOrderStatusMeta::create(array(
            'status_id' => 1,
            'language' => 'ru',
            'title' => 'В обработке',
        ));
        CatalogOrderStatus::create(array(
            'id' => 2,
            'sort_order' => 2,
        ));
        CatalogOrderStatusMeta::create(array(
            'status_id' => 2,
            'language' => 'ru',
            'title' => 'Ожидает оплаты',
        ));
        CatalogOrderStatus::create(array(
            'id' => 3,
            'sort_order' => 3,
        ));
        CatalogOrderStatusMeta::create(array(
            'status_id' => 3,
            'language' => 'ru',
            'title' => 'Оплачен',
        ));
        CatalogOrderStatus::create(array(
            'id' => 4,
            'sort_order' => 4,
        ));
        CatalogOrderStatusMeta::create(array(
            'status_id' => 4,
            'language' => 'ru',
            'title' => 'Ожидает отправки',
        ));
        CatalogOrderStatus::create(array(
            'id' => 5,
            'sort_order' => 5,
        ));
        CatalogOrderStatusMeta::create(array(
            'status_id' => 5,
            'language' => 'ru',
            'title' => 'Завершен',
        ));


        /**
         * ЗАКАЗЫ
         */
        CatalogOrder::create(array(
            'id' => 1,
            'status_id' => 4,
            'total_sum' => 120000,
            'client_id' => NULL,
            'client_name' => 'Покупатель',
            'delivery_info' => 'г.Ростов-на-Дону, ул Суворова 52а, оф.301',
            'comment' => 'Комментарий покупателя к заказу',
        ));

        CatalogOrderProduct::create(array(
            'order_id' => 1,
            'product_id' => 1,
            'count' => 2,
            'price' => 25000,
            'product_cache' => '[]',
        ));
        CatalogOrderProductAttribute::create(array(
            'order_id' => 1,
            'product_id' => 1,
            'attribute_id' => 4,
            'attribute_cache' => 'Тормоза',
            'value' => 'Дисковые',
        ));

        CatalogOrderProduct::create(array(
            'order_id' => 1,
            'product_id' => 2,
            'count' => 3,
            'price' => 24000,
            'product_cache' => '[]',
        ));
        CatalogOrderProductAttribute::create(array(
            'order_id' => 1,
            'product_id' => 2,
            'attribute_id' => 4,
            'attribute_cache' => 'Тормоза',
            'value' => 'Колодочные',
        ));

        CatalogOrderProduct::create(array(
            'order_id' => 1,
            'product_id' => 3,
            'count' => 1,
            'price' => 23000,
            'product_cache' => '[]',
        ));
        CatalogOrderProductAttribute::create(array(
            'order_id' => 1,
            'product_id' => 3,
            'attribute_id' => 4,
            'attribute_cache' => 'Тормоза',
            'value' => 'Без тормозов',
        ));


        /**
         * ИСТОРИЯ СТАТУСОВ ЗАКАЗА
         */
        CatalogOrderStatusHistory::create(array(
            'order_id' => 1,
            'status_id' => 1,
            'comment' => 'Заказ сделан, ожидает обработки...',
            'changer_name' => 'Покупатель',
        ));
        CatalogOrderStatusHistory::create(array(
            'order_id' => 1,
            'status_id' => 2,
            'comment' => 'Менеджер обработал заказ, ожидание оплаты',
            'changer_name' => 'Продавец',
        ));
        CatalogOrderStatusHistory::create(array(
            'order_id' => 1,
            'status_id' => 3,
            'comment' => 'Покупатель оплатил товар',
            'changer_name' => 'Покупатель',
        ));
        CatalogOrderStatusHistory::create(array(
            'order_id' => 1,
            'status_id' => 4,
            'comment' => 'Товар был успешно оплачен, ожидание отправки',
            'changer_name' => 'Продавец',
        ));

    }

}