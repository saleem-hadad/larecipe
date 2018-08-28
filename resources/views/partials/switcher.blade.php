<div class="switcher">
	<div class="dropdown">
		<button class="btn dropdown-toggle" type="button" id="dropdownMenu1" data-toggle="dropdown" aria-expanded="true">
			{{ ucfirst($currentVersion) }}
		</button>
		<ul class="dropdown-menu" role="menu" aria-labelledby="dropdownMenu1">
			@foreach ($versions as $key => $display)
				<li role="presentation">
					<a role="menuitem" tabindex="-1" href="{{ url('docs/'.$key.$currentSection) }}">{{ $display }}</a>
				</li>
			@endforeach
		</ul>
	</div>
</div>
