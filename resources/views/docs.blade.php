@extends('larecipe::default')

@section('content')
    <div class="box">
        {{ $title }}
    </div>
    
    <div class="box">
        {!! $index !!}
    </div>
    
    <div class="box">
        {!! $content !!}
    </div>
    
    <div class="box">
        {{ $currentVersion }}
    </div>
    
    <div class="box">
        @foreach($versions as $version)
            {{ $version }}
        @endforeach
    </div>
    
    <div class="box">
        {{ $currentSection }}
    </div>
    
    <div class="box">
        {{ $canonical }}
    </div>
@endsection