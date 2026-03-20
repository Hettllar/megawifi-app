<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body style=padding:20px;font-family:Arial;>
<h1>Test Page</h1>
<p>Subscribers count: {{ $subscribers->count() }}</p>
<p>Routers count: {{ $routers->count() }}</p>
<hr>
@foreach($subscribers->take(5) as $sub)
<p>{{ $sub->username }} - {{ $sub->router->name ?? 'N/A' }}</p>
@endforeach
</body>
</html>
