<?php

return [
    // Error page headings (4xx vs 5xx)
    'title_client' => 'Request could not be completed', // Short heading for client errors
    'title_server' => 'Something went wrong', // Short heading for server errors

    // Shown when the framework message is empty or generic
    'default_client' => 'The page or action you requested is not valid or is no longer available.', // Friendly 4xx body
    'default_server' => 'We hit a problem on our side. Please try again in a moment.', // Friendly 5xx body

    // Primary CTA on error pages
    'back_home' => 'Back to home', // Button label linking to site home
];
