@extends('layouts.app')

@section('title', $page->title)

@section('content')
<article class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-8">
    <h1 class="text-2xl font-bold">{{ $page->title }}</h1>
    <div class="prose prose-slate mt-4 max-w-none">{!! $page->content !!}</div>
</article>
@endsection
