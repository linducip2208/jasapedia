@extends('layouts.app')

@section('title', $post->title.' | Jasapedia')

@section('content')
<article class="mx-auto mt-4 max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
    <p class="text-xs text-slate-400">{{ $post->published_at?->translatedFormat('d F Y') }}</p>
    <h1 class="mt-1.5 text-2xl font-extrabold text-slate-900">{{ $post->title }}</h1>
    <div class="prose prose-slate mt-5 max-w-none text-[15px] leading-relaxed text-slate-600">{!! $post->content !!}</div>
</article>
@endsection
