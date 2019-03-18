@extends('larecipe::default')

@section('content')
<div>
	<div class="sidebar" 
		:class="[{'is-hidden': ! sidebar}, theme]">
		{!! $index !!}
	</div>
	
	<div class="documentation is-{{ config('larecipe.ui.code') }}" :class="{'expanded': ! sidebar}">
		{!! $content !!}
		@include('larecipe::partials.plugins.forum')
	</div>
</div>
@endsection
