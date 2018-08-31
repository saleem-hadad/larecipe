@extends('larecipe::default')

@section('content')
<div class="container-fluid pl-0 documentation">
	<nav class="row">
		<div class="col-12 col-md-3 sidebar" :class="{'hidden': sidebar}">
			{!! $index !!}
		</div>
		
		<div class="col-12 col-md-9 article" :class="{'expanded': sidebar}">
			{!! $content !!}
		</div>
	</div>
@endsection
