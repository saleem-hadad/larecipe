@extends('larecipe::default')

@section('content')
<div>
	@include('larecipe::partials.sidebar')
	
	<div class="documentation is-{{ config('larecipe.ui.code_theme') }}" :class="{'expanded': ! sidebar}">
		@if($authors)
			<div class="text-xs text-grey-darker mb-6">
				@if(($authorsCount = count($authors)) > 1)
					{{$authorsCount}} authors ($authors[0]['name'] and others)
				@else
					By {{$authors[0]['name']}}
				@endif
			</div>
		@endif
		{!! $content !!}
		@include('larecipe::plugins.forum')
	</div>
</div>
@endsection
