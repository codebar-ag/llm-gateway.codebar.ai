@php
    /** @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception */
    $statusCode = $exception->getStatusCode();
    $raw = trim($exception->getMessage());
    $generic = [
        'Not Found',
        'Forbidden',
        'Unauthorized',
        'Bad Request',
        'Method Not Allowed',
        'Gone',
        'Request Timeout',
        'Conflict',
        'Too Many Requests',
        'Page Expired',
        'Payment Required',
    ];
    $message = ($raw !== '' && ! in_array($raw, $generic, true))
        ? $raw
        : __('errors.default_client');
@endphp

@include('errors.partials._auth-error', [
    'statusCode' => $statusCode,
    'title' => __('errors.title_client'),
    'message' => $message,
])
