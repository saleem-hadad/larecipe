@extends('larecipe::default')

@section('content')
<div>
	@include('larecipe::partials.sidebar')
	
	<div class="documentation is-{{ config('larecipe.ui.code_theme') }}" :class="{'expanded': ! sidebar}">
        {!! $content !!}

		<div>
		<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2975960653015025"
			crossorigin="anonymous"></script>
		<!-- docs-footer -->
		<ins class="adsbygoogle"
			style="display:block"
			data-ad-client="ca-pub-2975960653015025"
			data-ad-slot="3218458963"
			data-ad-format="auto"
			data-full-width-responsive="true"></ins>
		<script>
			(adsbygoogle = window.adsbygoogle || []).push({});
		</script>
		</div>
	</div>
</div>
@endsection
