@extends('larecipe::default')

@section('content')
<div class="docs-wrapper container">

	<section class="sidebar">
		{!! $index !!}
	</section>

	<article>
		{!! $content !!}
	</article>
</div>
@endsection
