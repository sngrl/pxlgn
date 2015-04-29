<?
/**
 * TITLE: FAQ
 * AVAILABLE_ONLY_IN_ADVANCED_MODE
 */
?>
@extends(Helper::layout())


@section('style')
@stop


@section('content')

    <div class="inner-page-menu">
        {{ Menu::placement('about_menu') }}
    </div>

    <div class="inner-content">
        <div class="faq-about">

            {{ $page->block('about') }}

        </div>

        {{ $page->block('questions') }}

    </div>

@stop


@section('scripts')
@stop
