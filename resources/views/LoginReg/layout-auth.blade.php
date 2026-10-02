<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>


    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Dancing+Script:wght@600;700&display=swap" rel="stylesheet">

  
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
</head>
<body>
    @yield('content')
</body>
</html>