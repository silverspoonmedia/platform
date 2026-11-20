<?php

return [
    'pong' => 'Pong',
    'data_not_found' => 'Data not found.',
    'resource_not_found' => 'Resource not found.',
    'unauthorized' => 'You are not authorized to perform this action.',
    'updated' => 'Updated successfully.',
    'batch_invalid' => 'Batch payload must be a non-empty list of requests.',
    'batch_too_many' => 'Batch payload exceeds the maximum of 10 requests.',
    'batch_entry_invalid' => 'Batch entry is malformed.',
    'batch_method_not_allowed' => 'Batch entries only allow GET or HEAD requests.',
    'batch_path_not_allowed' => 'Batch entries may only target this API.',
    'batch_sub_failed' => 'A batched request failed to complete.',
    'batch_timeout' => 'The batch exceeded its time budget; remaining entries were skipped.',
    'batch_ok' => 'Batch completed.',
    'spa_origin_required' => 'This API is only reachable through the application.',
    'spa_csrf_issued' => 'CSRF token issued.',
    'spa_csrf_mismatch' => 'CSRF token mismatch.',
    'too_many_requests' => 'Too many requests. Please slow down and try again.',
];
