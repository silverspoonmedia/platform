<?php

return [
    'list_success' => 'Users retrieved successfully.',
    'show_success' => 'User retrieved successfully.',
    'created' => 'User created successfully.',
    'updated' => 'User updated successfully.',
    'deleted' => 'User deleted successfully.',
    'not_found' => 'User not found.',
    'assignable_roles_success' => 'Assignable roles retrieved successfully.',
    'cannot_delete_self' => 'You cannot delete your own account.',
    'policy' => [
        'view' => 'You are not authorized to view users.',
        'create' => 'You are not authorized to create users.',
        'edit' => 'You are not authorized to edit users.',
        'delete' => 'You are not authorized to delete users.',
        'default' => 'You are not authorized to perform this action.',
        'hierarchy_view' => 'You cannot access a user with a higher role level than yours.',
        'hierarchy_modify' => 'You cannot modify a user with the same or higher role level than yours.',
        'assignable_roles' => 'You are not authorized to view assignable roles.',
    ],
];
