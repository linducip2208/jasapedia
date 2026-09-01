@extends('layouts.app')

@section('title', 'Blog | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Blog Jasapedia</h1>
<div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse($posts as $post)
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <a href="{{ route('web.blog.show', $post->slug) }}" class="block p-5">
                <p class="text-xs text-slate-400">{{ $post->published_at?->translatedFormat('d F Y') }}</p>
                <h2 class="mt-1.5 font-bold text-slate-900 hover:text-teal-700">{{ $post->title }}</h2>
                <p class="mt-1.5 line-clamp-2 text-sm text-slate-500">{{ $post->excerpt }}</p>
            </a>
        </article>
    @empty
        <x-ui.empty-state title="Belum ada artikel" description="Artikel akan hadir segera."/>
    @endforelse
</div>
<x-ui.pagination :paginator="$posts"/>
@endsection
