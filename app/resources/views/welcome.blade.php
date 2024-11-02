<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Site Under Maintenance') }}</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            text-align: center;
        }
        h1 {
            font-size: 2.5em;
            margin-bottom: 0.5em;
        }
        p {
            font-size: 1.2em;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('We\'re Under Maintenance') }}</h1>
        <p>{{ __('Our site is currently undergoing scheduled maintenance.') }} <br> {{ __('We should be back shortly. Thank you for your patience!') }}</p>
    </div>
</body>
</html>
