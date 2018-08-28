@extends('larecipe::default')

@section('content')
<div class="container-fluid pl-0 pr-0">
	<nav class="row ">
		<div class="col-12 col-md-3 col-xl-2 ct-sidebar">
			<nav class="collapse ct-links" id="ct-docs-nav">
				<!-- Show links for all groups -->
				<div class="ct-toc-item active">
					{!! $index !!}
				</div>
			</nav>
		</div>
		
		<main class="col-12 col-md-9 col-xl-8 py-md-3 pl-md-5 ct-content" role="main">
			{!! $content !!}
		</main>
	</div>
@endsection
