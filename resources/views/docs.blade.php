@extends('larecipe::default')

@section('content')
<div>
	@include('larecipe::partials.sidebar')

	<div id="documentation" class="documentation" :class="{'expanded': sidebar}">
		{!! $document['content'] !!}

	    @include('larecipe::partials.footer')
	</div>
</div>
@endsection
