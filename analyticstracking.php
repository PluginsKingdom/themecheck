<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-47860956-1', 'themecheck.org');
  ga('send', 'pageview');


		var trackDL = function(name) {
		   ga('send', 'event', 'download', 'click', name);
		}

		var linkout = function(linktype, uriNameSeo) {
		console.log('linktype:'+linktype+',uriNameSeo:'+uriNameSeo);
		   ga('send', 'event', 'linkout', linktype, uriNameSeo);
		}
</script>