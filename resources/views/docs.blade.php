@extends('larecipe::default')

@section('content')
<div class="container-fluid pl-0 documentation">
	<nav class="row">
		<div class="col-12 col-md-3 sidebar">
			{!! $index !!}
		</div>
		
		<div class="col-12 col-md-9 article">
			{!! $content !!}
		</div>
	</div>
@endsection
