@php
    /** @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception */
    $statusCode = $exception->getStatusCode();
    $raw = trim($exception->getMessage());
    $generic = [
        'Server Error',
        'Service Unavailable',
        'Bad Gateway',
        'Gateway Timeout',
    ];
    $message = ($raw !== '' && ! in_array($raw, $generic, true))
        ? $raw
        : __('errors.default_server');
@endphp

@include('errors.partials._auth-error', [
    'statusCode' => $statusCode,
    'title' => __('errors.title_server'),
    'message' => $message,
])
