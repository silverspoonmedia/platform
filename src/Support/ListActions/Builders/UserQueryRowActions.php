<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class UserQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof User || ! $actor instanceof User) {
            return [];
        }

        $actions = [
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'secondary',
                route: 'users.edit',
                params: ['id' => (string) $model->id],
            ),
        ];

        if ($actor->can('delete user') && (string) $actor->id !== (string) $model->id) {
            $actions[] = QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'users/'.$model->id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('ui.datatable.confirm.delete_user'),
                ],
            );
        }

        return $actions;
    }
}
