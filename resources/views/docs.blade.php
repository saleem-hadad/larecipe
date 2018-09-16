@extends('larecipe::default')

@section('content')
<div class="container-fluid pl-0 documentation">
	<div class="row">
		<div class="col-12 col-md-3 sidebar {{ config('larecipe.ui.theme') == 'dark' ? 'is-dark' : '' }}" 
			:class="[{'is-hidden': ! sidebar}, {'is-dark': forceDarkSidebar}, {'is-light': forceLightSidebar}]">
			{!! $index !!}
		</div>
		
		<div class="col-12 col-md-9 article" :class="{'expanded': ! sidebar}">
			{!! $content !!}
		</div>
	</div>
</div>
@endsection
