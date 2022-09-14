@extends('larecipe::default')

@section('content')
<div>
	@include('larecipe::partials.sidebar')
	
	<div class="documentation" :class="{'expanded': sidebar}">
		{!! $document['content'] !!}
	</div>
</div>
@endsection
