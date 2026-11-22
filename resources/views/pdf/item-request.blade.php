<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Permintaan {{ $itemRequest->code }}</title>
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
        @include('item-requests.document', ['itemRequest' => $itemRequest, 'mode' => 'pdf'])
    </body>
</html>
