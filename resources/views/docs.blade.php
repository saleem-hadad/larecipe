@extends('larecipe::default')

@section('content')
<div>
	@include('larecipe::partials.sidebar')

	<div id="documentation" class="documentation" :class="{'shrink': sidebar}">
		{!! $document['content'] !!}

	    @include('larecipe::partials.footer')
	</div>
</div>
@endsection
