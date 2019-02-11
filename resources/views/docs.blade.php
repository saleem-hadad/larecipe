@extends('larecipe::default')

@section('content')
<div class="container-fluid pl-0 documentation is-{{ config('larecipe.ui.code') }}">
	<div class="row">
		<div class="col-12 col-md-3 sidebar {{ config('larecipe.ui.theme') == 'dark' ? 'is-dark' : '' }}" 
			:class="[{'is-hidden': ! sidebar}, theme]">
			{!! $index !!}
		</div>
		
		<div class="col-12 col-md-9 article" :class="{'expanded': ! sidebar}">
			{!! $content !!}
			@include('larecipe::partials.plugins.forum')
		</div>
	</div>
</div>
@endsection
