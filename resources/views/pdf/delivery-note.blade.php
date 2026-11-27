<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Surat Jalan {{ $delivery->code }}</title>
        <style>
            @page {
                size: A4;
                margin: 0;
            }

            body {
                margin: 0;
            }
        </style>
    </head>
    <body>
        @include('deliveries.document', ['delivery' => $delivery])
    </body>
</html>
