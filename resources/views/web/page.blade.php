@extends('layouts.app')

@section('title', $page->title.' | Jasapedia')
@if(isset($page->seo['description']))@section('meta_description', $page->seo['description'])@endif

@section('content')
<x-ui.breadcrumb :items="[['label' => 'Beranda', 'url' => route('web.home')], ['label' => $page->title]]"/>
<article class="mx-auto mt-4 max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
    <h1 class="text-2xl font-extrabold text-slate-900">{{ $page->title }}</h1>
    <div class="prose prose-slate mt-5 max-w-none text-[15px] leading-relaxed text-slate-600">{!! $page->content !!}</div>
</article>
@endsection
