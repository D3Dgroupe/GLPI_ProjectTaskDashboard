<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard;

final class Config
{
    public const STATE_TODO = 1;
    public const STATE_IN_PROGRESS = 2;
    public const STATE_CHECK = 8;

    public const FIELDS_MODULE_HINT_ID = 5;
    public const FIELDS_PRIORITY_HINT_ID = 4;

    public const FIELD_PROJECT = 2;
    public const FIELD_STATE = 12;
    public const FIELD_TYPE = 14;
    public const FIELD_TEAM_USER = 87;
    public const FIELD_TEAM_GROUP = 88;
    public const FIELD_MINE_MARKER = 99001;
    public const FIELD_TASK_ID_INTERNAL = 99002;
}
