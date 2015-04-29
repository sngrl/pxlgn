<?
/**
 * TITLE: Wiki
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

        <div class="wiki-about">
            {{ $page->block('about') }}
        </div>

        <div class="wiki-content">
            {{ $page->block('content') }}
        </div>

    </div>

@stop


@section('scripts')
@stop
