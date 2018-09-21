@if(config('larecipe.fourm.enabled'))
    @if(config('larecipe.fourm.default') == 'disqus' && config('larecipe.fourm.services.disqus.site_name'))
    <hr>
    <div id="disqus_thread"></div>
    <script async>
        var disqus_config = function () {
            this.page.url = '{{ url($canonical) }}';
            this.page.identifier = '{{ $currentSection }}';
        };

        (function() {
        var d = document, s = d.createElement('script');
        s.src = "https://{{ config('larecipe.fourm.services.disqus.site_name') }}.disqus.com/embed.js";
        s.setAttribute('data-timestamp', +new Date());
        (d.head || d.body).appendChild(s);
        })();
    </script>
    <noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>
    @endif
@endif
                            