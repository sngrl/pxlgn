<?
/**
 * TITLE: Об игре
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
        <div class="features-block">

            {{ $page->block('content') }}

        </div>
        <div class="features-block">
            <h3>{{ trans("interface.title.features") }}</h3>

            {{ $page->block('features') }}

        </div>
    </div>

@stop


@section('scripts')
@stop
