@extends('larecipe::default')

@section('content')
<div class="container-fluid pl-0">
	<nav class="row flex-xl-nowrap">
		<div class="col-12 col-md-3 col-xl-2 sidebar">
			{!! $index !!}
		</div>
		
		<main class="col-12 col-md-9 col-xl-8 py-md-3 pl-md-5 content" role="main">
			{!! $content !!}
		</main>
	</div>
@endsection
