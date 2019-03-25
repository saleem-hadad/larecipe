@extends('larecipe::default')

@section('content')
<div>
	@include('larecipe::partials.sidebar')
	
	<div class="documentation is-{{ config('larecipe.ui.code') }}" :class="{'expanded': ! sidebar}">
		{!! $content !!}
		@include('larecipe::partials.plugins.forum')
	</div>
</div>
@endsection
